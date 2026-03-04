<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the WordPress admin menu, asset enqueuing, and form processing.
 */
class FB_Capi_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            'FB CAPI Manager',
            'FB CAPI',
            FB_CAPI_CAPABILITY,
            'fb-capi',
            [ $this, 'render_page' ],
            'dashicons-chart-area',
            100
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'toplevel_page_fb-capi' ) return;

        wp_enqueue_style(
            'fb-capi-admin',
            FB_CAPI_URL . 'admin/css/admin.css',
            [],
            FB_CAPI_VERSION
        );

        wp_enqueue_script(
            'fb-capi-admin',
            FB_CAPI_URL . 'admin/js/admin.js',
            [],
            FB_CAPI_VERSION,
            true
        );

        wp_localize_script( 'fb-capi-admin', 'fbCapiAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fb_capi_refresh_logs' ),
        ] );
    }

    // ── Page rendering ────────────────────────────────────────────────────────

    public function render_page(): void {
        $notice      = '';
        $active_tab  = 'settings';
        $test_result = null;

        // ── Save main settings (Réglages + Événements) ────────────────────────
        if ( isset( $_POST['fb_capi_save'] ) && check_admin_referer( 'fb_capi_nonce' ) ) {
            update_option( 'fb_capi_options', FB_Capi_Options::sanitize_all( $_POST ) );
            FB_Capi_Options::invalidate_cache();
            $notice     = '<div class="fbc-alert success">' . fbc_t( '✅ Settings saved successfully!' ) . '</div>';
            $active_tab = 'settings';
        }

        // ── Save logs settings ────────────────────────────────────────────────
        if ( isset( $_POST['fb_capi_save_logs_settings'] ) && check_admin_referer( 'fb_capi_nonce' ) ) {
            $current = FB_Capi_Options::get();

            $current['logs_enabled']    = isset( $_POST['logs_enabled'] ) ? 1 : 0;
            $retention                  = (int) ( $_POST['logs_retention_days'] ?? 30 );
            $current['logs_retention_days'] = in_array( $retention, [ 0, 1, 3, 7, 14, 30, 60, 90 ], true )
                ? $retention : 30;

            $events_list            = [ 'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'AddPaymentInfo', 'Purchase' ];
            $current['logs_events'] = [];
            foreach ( $events_list as $ev ) {
                $current['logs_events'][ $ev ] = isset( $_POST['logs_events'][ $ev ] ) ? 1 : 0;
            }

            update_option( 'fb_capi_options', $current );
            FB_Capi_Options::invalidate_cache();
            $notice     = '<div class="fbc-alert success">' . fbc_t( '✅ Log settings saved!' ) . '</div>';
            $active_tab = 'logs';
        }

        // ── Clear logs ────────────────────────────────────────────────────────
        if ( isset( $_POST['fb_capi_clear_logs'] ) && check_admin_referer( 'fb_capi_nonce' ) ) {
            ( new FB_Capi_Sender() )->clear_logs();
            $notice     = '<div class="fbc-alert success">' . fbc_t( '🗑️ Logs cleared successfully!' ) . '</div>';
            $active_tab = 'logs';
        }

        // ── Test event ────────────────────────────────────────────────────────
        if ( isset( $_POST['fb_capi_test'] ) && check_admin_referer( 'fb_capi_nonce' ) ) {
            $test_result = ( new FB_Capi_Sender() )->send_test();
            $active_tab  = 'test';
        }

        // ── Prepare view data ─────────────────────────────────────────────────
        $options        = FB_Capi_Options::get();
        $logs           = ( new FB_Capi_Sender() )->read_logs();
        $total_events   = count( $logs );
        $success_events = count( array_filter( $logs, fn( $l ) => ( $l['status'] ?? '' ) === 'success' ) );
        $error_events   = $total_events - $success_events;
        $success_rate   = $total_events > 0 ? round( ( $success_events / $total_events ) * 100 ) : 0;
        $is_configured  = ! empty( $options['pixel_id'] ) && ! empty( $options['access_token'] );
        $active_events  = array_sum( [
            $options['enable_pageview'],
            $options['enable_viewcontent'],
            $options['enable_addtocart'],
            $options['enable_initiatecheckout'],
            $options['enable_purchase'],
            $options['enable_addpaymentinfo'],
        ] );

        include FB_CAPI_DIR . 'admin/views/page-main.php';
    }
}
