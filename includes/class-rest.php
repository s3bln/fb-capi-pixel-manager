<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers REST API endpoints (event proxy + SureCart webhook) and AJAX handler.
 */
class FB_Capi_Rest {

    public function __construct() {
        add_action( 'rest_api_init',                 [ $this, 'register_routes' ] );
        add_action( 'wp_ajax_fb_capi_refresh_logs',  [ $this, 'ajax_refresh_logs' ] );
    }

    // ── Routes ────────────────────────────────────────────────────────────────

    public function register_routes(): void {
        register_rest_route( 'fb-capi/v1', '/event', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_event' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'fb-capi/v1', '/surecart-webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ── /fb-capi/v1/event ─────────────────────────────────────────────────────

    public function handle_event( WP_REST_Request $request ): WP_REST_Response {
        // Rate limiting: max 30 requests per minute per IP.
        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key = 'fb_capi_rate_' . md5( $ip );
        $count    = (int) get_transient( $rate_key );
        if ( $count > 30 ) {
            return new WP_REST_Response( [ 'status' => 'rate_limited' ], 429 );
        }
        set_transient( $rate_key, $count + 1, 60 );

        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_capi'] ) ) {
            return new WP_REST_Response( [ 'status' => 'disabled' ], 200 );
        }

        $event_name = sanitize_text_field( $request->get_param( 'event_name' ) );
        $event_id   = sanitize_text_field( $request->get_param( 'event_id' ) );
        $source_url = esc_url_raw( $request->get_param( 'source_url' ) );

        // Whitelist: only known events with their toggle key.
        $toggle_map = [
            'ViewContent'      => 'enable_viewcontent',
            'AddToCart'        => 'enable_addtocart',
            'InitiateCheckout' => 'enable_initiatecheckout',
            'Purchase'         => 'enable_purchase',
            'AddPaymentInfo'   => 'enable_addpaymentinfo',
        ];

        if ( ! isset( $toggle_map[ $event_name ] ) ) {
            return new WP_REST_Response( [ 'status' => 'not_allowed' ], 200 );
        }
        if ( empty( $options[ $toggle_map[ $event_name ] ] ) ) {
            return new WP_REST_Response( [ 'status' => 'event_disabled' ], 200 );
        }
        if ( empty( $event_id ) || strlen( $event_id ) < 10 ) {
            return new WP_REST_Response( [ 'status' => 'invalid_event_id' ], 400 );
        }

        $custom_data = $this->sanitize_custom_data( $request->get_param( 'custom_data' ) ?: [] );

        ( new FB_Capi_Sender() )->send( $event_name, $event_id, $custom_data, $source_url );

        return new WP_REST_Response( [ 'status' => 'sent', 'event_id' => $event_id ], 200 );
    }

    // ── /fb-capi/v1/surecart-webhook ─────────────────────────────────────────

    public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
        $options = FB_Capi_Options::get();

        if ( empty( $options['enable_purchase'] ) || empty( $options['enable_capi'] ) ) {
            return new WP_REST_Response( [ 'status' => 'disabled' ], 200 );
        }

        // Quand la plateforme SureCart est active, le hook PHP surecart/purchase_created
        // gère Purchase avec les données complètes. Le webhook retourne 200 (pas de retry)
        // mais n'envoie rien à Meta pour éviter le double comptage.
        if ( ( $options['platform'] ?? '' ) === 'surecart' ) {
            return new WP_REST_Response( [ 'status' => 'skipped', 'reason' => 'handled_by_php_hook' ], 200 );
        }

        // Verify HMAC signature.
        // trim() au cas où le secret aurait été copié avec des espaces.
        $webhook_secret = trim( $options['webhook_secret'] ?? '' );
        if ( empty( $webhook_secret ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Webhook secret not configured' ], 403 );
        }

        // SureCart peut utiliser plusieurs noms d'en-tête selon la version, et préfixer avec "sha256=".
        $raw_sig = $_SERVER['HTTP_X_SC_SIGNATURE']
            ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
            ?? $_SERVER['HTTP_X_SURECART_SIGNATURE']
            ?? $_SERVER['HTTP_X_SURECART_WEBHOOK_SIGNATURE']
            ?? '';

        // strtolower : certaines implémentations envoient le hash en majuscules.
        $received_hash = strtolower( preg_replace( '/^sha256=/', '', sanitize_text_field( $raw_sig ) ) );

        if ( empty( $received_hash ) ) {
            $http_keys = implode( ', ', array_keys( array_filter( $_SERVER, fn( $k ) => str_starts_with( $k, 'HTTP_' ), ARRAY_FILTER_USE_KEY ) ) );
            error_log( '[FB CAPI] ❌ Webhook: signature manquante. En-têtes reçus: ' . $http_keys );
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Missing signature' ], 403 );
        }

        $raw_body  = $request->get_body();
        $timestamp = sanitize_text_field( $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '' );

        // SureCart signe : hash_hmac('sha256', "{timestamp}.{body}", $secret)
        $signed_payload = $timestamp ? $timestamp . '.' . $raw_body : $raw_body;
        $expected_hash  = hash_hmac( 'sha256', $signed_payload, $webhook_secret );

        if ( ! hash_equals( $expected_hash, $received_hash ) ) {
            error_log( '[FB CAPI] ❌ Webhook: signature invalide. timestamp=' . $timestamp );
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Invalid signature' ], 403 );
        }

        // Parse payload.
        $body = json_decode( $raw_body, true );
        $type = $body['type'] ?? '';

        $accepted_types = [ 'order.paid', 'checkout.completed', 'purchase.created', 'order.completed' ];
        if ( ! in_array( $type, $accepted_types, true ) ) {
            return new WP_REST_Response( [ 'status' => 'ignored' ], 200 );
        }

        // SureCart envoie les données de l'objet dans data.object (structure événement).
        $data = $body['data']['object'] ?? ( $body['data'] ?? [] );
        $email    = $data['email'] ?? ( $data['customer']['email'] ?? '' );
        $amount   = $data['total_amount'] ?? ( $data['amount'] ?? 0 );
        $currency = strtoupper( $data['currency'] ?? 'EUR' );

        // Convert cents to major unit if value looks like cents (> 1000).
        if ( $amount > 1000 ) {
            $amount = $amount / 100;
        }

        // Déduplication par checkout ID — identique à la clé utilisée dans FB_Capi_Platform_Surecart.
        // Évite le double envoi si le hook PHP et le webhook se déclenchent tous les deux.
        $order_id = $data['checkout'] ?? ( $data['id'] ?? ( $data['order_id'] ?? '' ) );
        if ( ! empty( $order_id ) ) {
            $processed = get_option( 'fb_capi_processed_orders', [] );
            if ( in_array( $order_id, $processed, true ) ) {
                return new WP_REST_Response( [ 'status' => 'duplicate', 'order_id' => $order_id ], 200 );
            }
            $processed[] = $order_id;
            if ( count( $processed ) > 500 ) {
                $processed = array_slice( $processed, -500 );
            }
            update_option( 'fb_capi_processed_orders', $processed );
        }

        // Extract line items for content_ids / num_items.
        $line_items_raw = $data['line_items']['data'] ?? ( $data['line_items'] ?? [] );
        $content_ids    = [];
        $num_items      = 0;
        foreach ( (array) $line_items_raw as $item ) {
            $pid = $item['product']['id'] ?? ( $item['product_id'] ?? '' );
            if ( $pid ) {
                $content_ids[] = (string) $pid;
            }
            $num_items += (int) ( $item['quantity'] ?? 1 );
        }

        $custom_data = [
            'value'    => (float) $amount,
            'currency' => $currency,
        ];
        if ( $content_ids ) {
            $custom_data['content_ids']  = $content_ids;
            $custom_data['content_type'] = 'product';
            $custom_data['num_items']    = $num_items ?: count( $content_ids );
        }

        // Advanced matching from billing address.
        $billing       = $data['billing_address'] ?? ( $data['address'] ?? [] );
        $customer_name = $data['customer']['name'] ?? ( $data['name'] ?? '' );
        $name_parts    = $customer_name ? explode( ' ', $customer_name, 2 ) : [];
        $raw_user_data = array_filter( [
            'first_name' => $name_parts[0] ?? '',
            'last_name'  => $name_parts[1] ?? '',
            'phone'      => $data['phone'] ?? ( $billing['phone'] ?? '' ),
            'city'       => $billing['city'] ?? '',
            'state'      => $billing['state'] ?? '',
            'zip'        => $billing['postal_code'] ?? ( $billing['zip'] ?? '' ),
            'country'    => $billing['country'] ?? '',
        ] );

        $event_id = 'fb_purchase_' . ( $order_id ?: bin2hex( random_bytes( 8 ) ) );

        // Webhook = requête serveur-à-serveur, pas de referer disponible → home_url() par défaut.
        ( new FB_Capi_Sender() )->send( 'Purchase', $event_id, $custom_data, home_url( '/' ), $email, true, $raw_user_data );

        return new WP_REST_Response( [
            'status'   => 'sent',
            'event_id' => $event_id,
            'value'    => (float) $amount,
            'currency' => $currency,
        ], 200 );
    }

    // ── AJAX : refresh log table ──────────────────────────────────────────────

    public function ajax_refresh_logs(): void {
        check_ajax_referer( 'fb_capi_refresh_logs' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $logs    = ( new FB_Capi_Sender() )->read_logs();
        $total   = count( $logs );
        $success = count( array_filter( $logs, fn( $l ) => ( $l['status'] ?? '' ) === 'success' ) );
        $errors  = $total - $success;

        ob_start();
        include FB_CAPI_DIR . 'admin/views/logs-table.php';
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html, 'total' => $total ] );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function sanitize_custom_data( array $raw ): array {
        $allowed = [
            'value', 'currency', 'content_name',
            'content_ids', 'content_type', 'num_items', 'order_id',
        ];
        $out = [];
        foreach ( $allowed as $key ) {
            if ( ! isset( $raw[ $key ] ) ) continue;
            if ( is_string( $raw[ $key ] ) ) {
                $out[ $key ] = sanitize_text_field( substr( $raw[ $key ], 0, 200 ) );
            } elseif ( is_numeric( $raw[ $key ] ) ) {
                $out[ $key ] = floatval( $raw[ $key ] );
            } elseif ( is_array( $raw[ $key ] ) ) {
                $out[ $key ] = array_map( 'sanitize_text_field', array_slice( $raw[ $key ], 0, 20 ) );
            }
        }
        return $out;
    }
}
