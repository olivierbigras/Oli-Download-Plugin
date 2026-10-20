<?php
/**
 * Uninstall cleanup.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove options, cron events, and leftover temp files.
 */
function oli_dlp_uninstall() {
	delete_option( 'oli_dlp_enable_mu_plugins' );
	delete_option( 'oli_dlp_enable_dropins' );
	delete_site_option( 'oli_dlp_enable_mu_plugins' );
	delete_site_option( 'oli_dlp_enable_dropins' );

	wp_clear_scheduled_hook( 'oli_dlp_daily_cleanup' );

	$oli_dlp_upload_dir = wp_upload_dir( null, false );
	if ( empty( $oli_dlp_upload_dir['basedir'] ) ) {
		return;
	}

	$oli_dlp_dir = trailingslashit( $oli_dlp_upload_dir['basedir'] ) . 'oli-download-plugin-tmp/';
	if ( ! is_dir( $oli_dlp_dir ) ) {
		return;
	}

	$oli_dlp_zips = glob( $oli_dlp_dir . '*.zip' );
	if ( is_array( $oli_dlp_zips ) ) {
		foreach ( $oli_dlp_zips as $oli_dlp_zip ) {
			if ( is_file( $oli_dlp_zip ) ) {
				wp_delete_file( $oli_dlp_zip );
			}
		}
	}

	if ( file_exists( $oli_dlp_dir . 'index.php' ) ) {
		wp_delete_file( $oli_dlp_dir . 'index.php' );
	}

	if ( file_exists( $oli_dlp_dir . '.htaccess' ) ) {
		wp_delete_file( $oli_dlp_dir . '.htaccess' );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- uninstall cleanup of empty temp folder.
	@rmdir( $oli_dlp_dir );
}

oli_dlp_uninstall();
