<?php
/**
 * Plugin Name: FB CAPI & Pixel Manager
 * Description: Gestionnaire Facebook Pixel & Conversions API pour SureCart
 * Version:     1.0.0
 * Author:      Wasabi Custom Dev
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License:     GPL-2.0-or-later
 * Text Domain: fb-capi
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FB_CAPI_VERSION',       '2.1.0' );
define( 'FB_CAPI_FILE',          __FILE__ );
define( 'FB_CAPI_DIR',           plugin_dir_path( __FILE__ ) );
define( 'FB_CAPI_URL',           plugin_dir_url( __FILE__ ) );
define( 'FB_CAPI_GRAPH_VERSION', 'v25.0' );
define( 'FB_CAPI_CAPABILITY',    'manage_options' );

require_once FB_CAPI_DIR . 'includes/class-options.php';
require_once FB_CAPI_DIR . 'includes/class-capi.php';
require_once FB_CAPI_DIR . 'includes/class-pixel.php';
require_once FB_CAPI_DIR . 'includes/class-rest.php';

if ( is_admin() ) {
    require_once FB_CAPI_DIR . 'admin/class-admin.php';
}

add_action( 'plugins_loaded', function () {
    new FB_Capi_Pixel();
    new FB_Capi_Rest();
    if ( is_admin() ) {
        new FB_Capi_Admin();
    }
} );
