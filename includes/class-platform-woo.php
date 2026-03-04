<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce platform integration.
 *
 * Sends the server-side Purchase event via the woocommerce_thankyou hook
 * (full order data, advanced matching) and injects the matching browser
 * pixel event on the same page for deduplication.
 */
class FB_Capi_Platform_Woo {

    /** @var string|null Shared event ID for browser/server deduplication. */
    private ?string $purchase_event_id   = null;
    private array   $purchase_pixel_data = [];

    public function __construct() {
        if ( ! function_exists( 'WC' ) ) return;

        add_action( 'woocommerce_thankyou', [ $this, 'handle_purchase' ], 10, 1 );
        add_action( 'wp_footer',            [ $this, 'inject_purchase_pixel' ], 20 );
    }

    // ── woocommerce_thankyou ─────────────────────────────────────────────────

    public function handle_purchase( int $order_id ): void {
        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_purchase'] ) || empty( $options['enable_capi'] ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $event_id = 'fb_purchase_' . $order_id;

        // Deduplication: always prepare pixel data so the browser event fires
        // (same event_id → Meta deduplicates naturally on reload).
        $total    = (float) $order->get_total();
        $currency = strtoupper( $order->get_currency() );

        if ( ! empty( $options['enable_pixel_js'] ) ) {
            $this->purchase_event_id  = $event_id;
            $this->purchase_pixel_data = [
                'value'    => $total,
                'currency' => $currency,
            ];
        }

        $processed = get_option( 'fb_capi_processed_orders', [] );
        if ( in_array( (string) $order_id, $processed, true ) ) return;

        $processed[] = (string) $order_id;
        if ( count( $processed ) > 500 ) {
            $processed = array_slice( $processed, -500 );
        }
        update_option( 'fb_capi_processed_orders', $processed );

        // Build content_ids and num_items from order line items.
        $content_ids = [];
        $num_items   = 0;
        foreach ( $order->get_items() as $item ) {
            /** @var WC_Order_Item_Product $item */
            $content_ids[] = (string) $item->get_product_id();
            $num_items    += $item->get_quantity();
        }

        $custom_data = [
            'value'        => $total,
            'currency'     => $currency,
            'content_type' => 'product',
            'num_items'    => $num_items,
        ];
        if ( $content_ids ) {
            $custom_data['content_ids'] = $content_ids;
        }

        // Advanced matching from billing details.
        $raw_user_data = array_filter( [
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'phone'      => $order->get_billing_phone(),
            'city'       => $order->get_billing_city(),
            'state'      => $order->get_billing_state(),
            'zip'        => $order->get_billing_postcode(),
            'country'    => $order->get_billing_country(),
        ] );

        ( new FB_Capi_Sender() )->send(
            'Purchase',
            $event_id,
            $custom_data,
            $order->get_checkout_order_received_url(),
            $order->get_billing_email(),
            true,
            $raw_user_data
        );
    }

    // ── wp_footer (priority 20) ──────────────────────────────────────────────

    public function inject_purchase_pixel(): void {
        if ( ! $this->purchase_event_id ) return;

        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_pixel_js'] ) || empty( $options['enable_purchase'] ) ) return;

        $event_id = esc_js( $this->purchase_event_id );
        $value    = (float) $this->purchase_pixel_data['value'];
        $currency = esc_js( $this->purchase_pixel_data['currency'] );
        ?>
        <script>
        if (typeof fbq === 'function') {
            fbq('track', 'Purchase', {
                value:    <?php echo $value; ?>,
                currency: '<?php echo $currency; ?>'
            }, { eventID: '<?php echo $event_id; ?>' });
        }
        </script>
        <?php
    }
}
