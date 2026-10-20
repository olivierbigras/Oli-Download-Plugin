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
	$oli_dlp_options = array(
		'oli_dlp_enable_mu_plugins',
		'oli_dlp_enable_dropins',
		'oli_dlp_custom_exclusions',
	);

	foreach ( $oli_dlp_options as $oli_dlp_option ) {
		delete_option( $oli_dlp_option );
		delete_site_option( $oli_dlp_option );
	}

	wp_clear_scheduled_hook( 'oli_dlp_daily_cleanup' );

	if ( function_exists( 'get_temp_dir' ) ) {
		$oli_dlp_sys       = trailingslashit( get_temp_dir() );
		$oli_dlp_sys_files = array_merge(
			(array) glob( $oli_dlp_sys . 'oli-dlp-*.tmp' ),
			(array) glob( $oli_dlp_sys . 'oli-dlp-*.zip' )
		);
		foreach ( $oli_dlp_sys_files as $oli_dlp_tmp ) {
			if ( is_string( $oli_dlp_tmp ) && is_file( $oli_dlp_tmp ) ) {
				wp_delete_file( $oli_dlp_tmp );
			}
		}
	}

	$oli_dlp_upload_dir = wp_upload_dir( null, false );
	if ( empty( $oli_dlp_upload_dir['basedir'] ) ) {
		return;
	}

	$oli_dlp_dir = trailingslashit( $oli_dlp_upload_dir['basedir'] ) . 'oli-download-plugin-tmp/';
	if ( ! is_dir( $oli_dlp_dir ) ) {
		return;
	}

	$oli_dlp_temps = array_merge(
		(array) glob( $oli_dlp_dir . '*.zip' ),
		(array) glob( $oli_dlp_dir . 'oli-dlp-*.tmp' )
	);
	foreach ( $oli_dlp_temps as $oli_dlp_zip ) {
		if ( is_string( $oli_dlp_zip ) && is_file( $oli_dlp_zip ) ) {
			wp_delete_file( $oli_dlp_zip );
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
