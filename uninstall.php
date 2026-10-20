<?php
/**
 * Uninstall cleanup.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'oli_dlp_enable_mu_plugins' );
delete_option( 'oli_dlp_enable_dropins' );
delete_site_option( 'oli_dlp_enable_mu_plugins' );
delete_site_option( 'oli_dlp_enable_dropins' );

wp_clear_scheduled_hook( 'oli_dlp_daily_cleanup' );

$upload_dir = wp_upload_dir( null, false );
if ( ! empty( $upload_dir['basedir'] ) ) {
	$dir = trailingslashit( $upload_dir['basedir'] ) . 'oli-download-plugin-tmp/';
	if ( is_dir( $dir ) ) {
		$zips = glob( $dir . '*.zip' );
		if ( is_array( $zips ) ) {
			foreach ( $zips as $zip ) {
				if ( is_file( $zip ) ) {
					wp_delete_file( $zip );
				}
			}
		}
		if ( file_exists( $dir . 'index.php' ) ) {
			wp_delete_file( $dir . 'index.php' );
		}
		if ( file_exists( $dir . '.htaccess' ) ) {
			wp_delete_file( $dir . '.htaccess' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- uninstall cleanup of empty temp folder.
		@rmdir( $dir );
	}
}
