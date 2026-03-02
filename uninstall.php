<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all stored options and log files.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'fb_capi_options' );
delete_option( 'fb_capi_processed_orders' );

// Remove log files
$upload_dir = wp_upload_dir();
$log_dir    = $upload_dir['basedir'] . '/fb-capi-logs';

if ( is_dir( $log_dir ) ) {
    array_map( 'unlink', glob( $log_dir . '/*' ) );
    rmdir( $log_dir );
}
