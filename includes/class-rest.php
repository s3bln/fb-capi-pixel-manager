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

        // Verify HMAC signature.
        $webhook_secret = $options['webhook_secret'] ?? '';
        if ( empty( $webhook_secret ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Webhook secret not configured' ], 403 );
        }

        $received_hash = sanitize_text_field(
            $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_SC_SIGNATURE'] ?? ''
        );
        if ( empty( $received_hash ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Missing signature' ], 403 );
        }

        $raw_body      = $request->get_body();
        $expected_hash = hash_hmac( 'sha256', $raw_body, $webhook_secret );
        if ( ! hash_equals( $expected_hash, $received_hash ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Invalid signature' ], 403 );
        }

        // Parse payload.
        $body = json_decode( $raw_body, true );
        $type = $body['type'] ?? '';

        $accepted_types = [ 'order.paid', 'checkout.completed', 'purchase.created', 'order.completed' ];
        if ( ! in_array( $type, $accepted_types, true ) ) {
            return new WP_REST_Response( [ 'status' => 'ignored' ], 200 );
        }

        $data     = $body['data'] ?? [];
        $email    = $data['email'] ?? ( $data['customer']['email'] ?? '' );
        $amount   = $data['total_amount'] ?? ( $data['amount'] ?? 0 );
        $currency = strtoupper( $data['currency'] ?? 'EUR' );

        // Convert cents to major unit if value looks like cents (> 1000).
        if ( $amount > 1000 ) {
            $amount = $amount / 100;
        }

        // Deduplication by order ID.
        $order_id = $data['id'] ?? ( $data['order_id'] ?? '' );
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

        $event_id = 'fb_purchase_' . ( $order_id ?: bin2hex( random_bytes( 12 ) ) );

        ( new FB_Capi_Sender() )->send( 'Purchase', $event_id, [
            'value'    => (float) $amount,
            'currency' => $currency,
        ], '', $email );

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
