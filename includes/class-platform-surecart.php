<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SureCart integration: sends Purchase CAPI event via 'surecart/purchase_created' PHP hook.
 *
 * Le hook se déclenche deux fois pour chaque achat :
 *   1. Requête navigateur (checkout direct) → $webhook_data vide → traitement ici
 *   2. Requête serveur SureCart (webhook interne) → $webhook_data = stdClass → ignorée
 *
 * On traite uniquement la requête navigateur car elle dispose du contexte navigateur
 * (cookies fbp/fbc, HTTP_REFERER = page checkout réelle).
 *
 * L'expansion via Purchase::with()->find() récupère montant, email et adresse de facturation.
 */
class FB_Capi_Platform_Surecart {

    public function __construct() {
        add_action( 'surecart/purchase_created', [ $this, 'on_purchase_created' ], 10, 2 );
    }

    public function on_purchase_created( $purchase, $webhook_data = [] ): void {
        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_purchase'] ) || empty( $options['enable_capi'] ) ) return;

        // Si webhook_data est un objet, c'est la requête serveur SureCart (webhook interne).
        // On l'ignore : la requête navigateur (webhook_data vide) est traitée en priorité
        // car elle dispose du contexte navigateur (cookies, referer).
        if ( is_object( $webhook_data ) ) return;

        // ID de l'achat — clé de déduplication.
        $purchase_id = (string) ( $this->prop( $purchase, 'id' ) ?? '' );
        if ( empty( $purchase_id ) ) return;

        // Déduplication en mémoire (même requête PHP).
        static $sent_ids = [];
        if ( in_array( $purchase_id, $sent_ids, true ) ) return;
        $sent_ids[] = $purchase_id;

        // Déduplication cross-requête (audit long terme + sécurité).
        $processed = get_option( 'fb_capi_processed_orders', [] );
        if ( in_array( $purchase_id, $processed, true ) ) return;
        $processed[] = $purchase_id;
        if ( count( $processed ) > 500 ) $processed = array_slice( $processed, -500 );
        update_option( 'fb_capi_processed_orders', $processed );

        // ── Données de base (toujours disponibles sur l'objet minimal) ────────
        $product_id = (string) ( $this->prop( $purchase, 'product' )  ?? '' );
        $quantity   = max( 1, (int) ( $this->prop( $purchase, 'quantity' ) ?? 1 ) );

        // ── Expansion via l'API PHP SureCart ──────────────────────────────────
        // L'objet Purchase initial est minimal (UUIDs uniquement). Purchase::with()->find()
        // charge les données complètes : montant, email, adresse de facturation.
        $email         = '';
        $amount        = 0.0;
        $currency      = 'EUR';
        $raw_user_data = [];

        if ( class_exists( '\\SureCart\\Models\\Purchase' ) ) {
            try {
                // Étape 1 : expand l'Order depuis la Purchase.
                // (SureCart limite à 1 niveau d'expansion par appel en pratique.)
                $expanded = \SureCart\Models\Purchase::with( [ 'initial_order' ] )->find( $purchase_id );
                $order    = $this->prop( $expanded, 'initial_order' );

                // Étape 2 : depuis l'Order, récupérer le UUID du Checkout et le charger séparément.
                $checkout_id_sc = (string) ( $this->prop( $order, 'checkout' ) ?? '' );

                if ( $checkout_id_sc && class_exists( '\\SureCart\\Models\\Checkout' ) ) {
                    $checkout = \SureCart\Models\Checkout::with( [ 'billing_address' ] )->find( $checkout_id_sc );

                    if ( $checkout ) {
                        // Montant : SureCart stocke en centimes.
                        $raw_amount = $this->prop( $checkout, 'amount_due' )
                            ?? $this->prop( $checkout, 'total_amount' )
                            ?? 0;
                        if ( $raw_amount > 0 ) $amount = (float) $raw_amount / 100;
                        $currency = strtoupper( (string) ( $this->prop( $checkout, 'currency' ) ?? 'EUR' ) );

                        // Email — champ direct sur checkout.
                        $email = (string) (
                            $this->prop( $checkout, 'email' )
                            ?? $this->prop( $order, 'email' )
                            ?? ''
                        );

                        // Prénom/nom/téléphone sont des champs directs sur checkout.
                        $billing = $this->prop( $checkout, 'billing_address' );

                        $raw_user_data = array_filter( [
                            'first_name' => (string) ( $this->prop( $checkout, 'first_name' ) ?? $this->prop( $billing, 'first_name' ) ?? '' ),
                            'last_name'  => (string) ( $this->prop( $checkout, 'last_name' )  ?? $this->prop( $billing, 'last_name' )  ?? '' ),
                            'phone'      => (string) ( $this->prop( $checkout, 'phone' )      ?? $this->prop( $billing, 'phone' )      ?? '' ),
                            'city'       => (string) ( $this->prop( $billing, 'city' )    ?? '' ),
                            'state'      => (string) ( $this->prop( $billing, 'state' )   ?? '' ),
                            'zip'        => (string) ( $this->prop( $billing, 'postal_code' ) ?? $this->prop( $billing, 'zip' ) ?? '' ),
                            'country'    => (string) ( $this->prop( $billing, 'country' ) ?? '' ),
                        ] );
                    }
                }

            } catch ( \Throwable $e ) {
                error_log( '[FB CAPI] ⚠️ Purchase expansion failed: ' . $e->getMessage() );
            }
        }

        // ── Payload ───────────────────────────────────────────────────────────
        $custom_data = [ 'value' => $amount, 'currency' => $currency ];
        if ( $product_id ) {
            $custom_data['content_ids']  = [ $product_id ];
            $custom_data['content_type'] = 'product';
            $custom_data['num_items']    = $quantity;
        }

        $event_id   = 'fb_purchase_' . $purchase_id;
        // Requête navigateur → HTTP_REFERER = URL réelle de la page checkout.
        $source_url = esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' );

        ( new FB_Capi_Sender() )->send( 'Purchase', $event_id, $custom_data, $source_url, $email, true, $raw_user_data );
    }

    /**
     * Accès sécurisé à une propriété imbriquée (notation pointée) sur objet ou tableau.
     */
    private function prop( $data, string $path ) {
        foreach ( explode( '.', $path ) as $key ) {
            if ( is_null( $data ) )       return null;
            if ( is_array( $data ) )      { $data = $data[ $key ] ?? null; }
            elseif ( is_object( $data ) ) { $data = $data->$key ?? null; }
            else                          { return null; }
        }
        return $data;
    }
}
