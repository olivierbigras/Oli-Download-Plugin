<?php
/**
 * Temp directory protection and daily cleanup.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protects the uploads temp folder and deletes leftover ZIP files.
 */
class Oli_DLP_Cleanup {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const HOOK = 'oli_dlp_daily_cleanup';

	/**
	 * Temp folder name inside uploads.
	 *
	 * @var string
	 */
	const TEMP_FOLDER = 'oli-download-plugin-tmp';

	/**
	 * Singleton instance.
	 *
	 * @var Oli_DLP_Cleanup|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Oli_DLP_Cleanup
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( self::HOOK, array( $this, 'cleanup' ) );
	}

	/**
	 * Absolute temp directory with trailing slash.
	 *
	 * @return string
	 */
	public static function get_temp_dir() {
		$upload_dir = wp_upload_dir( null, false );

		if ( empty( $upload_dir['basedir'] ) ) {
			return '';
		}

		return trailingslashit( $upload_dir['basedir'] ) . self::TEMP_FOLDER . '/';
	}

	/**
	 * Create the temp directory and write protection files.
	 */
	public static function ensure_temp_dir() {
		$dir = self::get_temp_dir();
		if ( '' === $dir ) {
			return;
		}

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$index_file = $dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- protection file, no WP_Filesystem during download.
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$htaccess_file = $dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- protection file, no WP_Filesystem during download.
			file_put_contents( $htaccess_file, "Deny from all\n" );
		}
	}

	/**
	 * Schedule the daily cleanup event.
	 */
	public static function schedule_cleanup() {
		self::ensure_temp_dir();

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/**
	 * Clear the scheduled cleanup event.
	 */
	public static function clear_schedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}

		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Delete leftover ZIP files from the temp directory.
	 */
	public function cleanup() {
		self::delete_temp_zips();
	}

	/**
	 * Remove leftover temp archives; keep protection files.
	 */
	public static function delete_temp_zips() {
		self::delete_matching_files( self::get_temp_dir(), array( '*.zip', 'oli-dlp-*.tmp' ) );

		if ( function_exists( 'get_temp_dir' ) ) {
			self::delete_matching_files(
				trailingslashit( get_temp_dir() ),
				array( 'oli-dlp-*.tmp', 'oli-dlp-*.zip' )
			);
		}
	}

	/**
	 * Delete files matching the given globs in one directory.
	 *
	 * @param string   $dir      Directory with trailing slash.
	 * @param string[] $patterns glob() patterns relative to $dir.
	 */
	private static function delete_matching_files( $dir, $patterns ) {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$files = array();
		foreach ( $patterns as $pattern ) {
			$found = glob( $dir . $pattern );
			if ( is_array( $found ) ) {
				$files = array_merge( $files, $found );
			}
		}

		foreach ( $files as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Remove the entire temp directory (used on uninstall).
	 */
	public static function delete_temp_dir() {
		self::delete_temp_zips();

		$dir = self::get_temp_dir();
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$index = $dir . 'index.php';
		if ( file_exists( $index ) ) {
			wp_delete_file( $index );
		}

		$htaccess = $dir . '.htaccess';
		if ( file_exists( $htaccess ) ) {
			wp_delete_file( $htaccess );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort removal of the empty temp folder.
		@rmdir( $dir );
	}
}
