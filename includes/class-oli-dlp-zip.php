<?php
/**
 * ZIP creation and streaming.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates plugin/theme ZIP archives and streams them to the browser.
 */
class Oli_DLP_Zip {

	/**
	 * Result code when ZipArchive::close() fails.
	 *
	 * @var string
	 */
	const RESULT_CLOSE_FAILED = 'close';

	/**
	 * Exclusion patterns (path segments or globs).
	 *
	 * @var string[]
	 */
	private $excluded_patterns = array();

	/**
	 * Absolute path of the temp ZIP for this request.
	 *
	 * @var string
	 */
	private $temp_zip_file = '';

	/**
	 * Whether a shutdown cleaner is already registered.
	 *
	 * @var bool
	 */
	private $cleanup_registered = false;

	/**
	 * Current source root used by the PclZip pre-add callback.
	 *
	 * @var string
	 */
	private $pclzip_source_root = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->excluded_patterns = $this->get_excluded_patterns();
	}

	/**
	 * Exclusion patterns, filterable via oli_dlp_excluded_patterns.
	 *
	 * @return string[]
	 */
	public function get_excluded_patterns() {
		$patterns = Oli_DLP_Validator::default_excluded_patterns();

		/**
		 * Filter path-segment names and globs excluded from generated ZIP archives.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $patterns Default: .git, node_modules, .DS_Store, .env, VCS/IDE dirs, *.log, *.sql.
		 */
		$filtered = apply_filters( 'oli_dlp_excluded_patterns', $patterns );

		return is_array( $filtered ) ? $filtered : $patterns;
	}

	/**
	 * Create a ZIP of one path and stream it.
	 *
	 * @param string $source_path Directory or file to archive.
	 * @param string $filename    Download filename.
	 */
	public function create_and_stream( $source_path, $filename ) {
		$this->prepare_runtime();

		$zip_file = $this->allocate_temp_zip();
		if ( ! $zip_file ) {
			wp_die( esc_html__( 'Failed to create a temporary ZIP file.', 'oli-download-plugin' ) );
		}

		$result = $this->create_zip( $source_path, $zip_file );
		if ( true !== $result ) {
			$this->fail_zip_creation( $result );
		}

		$this->stream_file( $zip_file, $filename );
	}

	/**
	 * Create a ZIP of several paths and stream it.
	 *
	 * @param array  $sources  Map of slug => absolute path.
	 * @param string $filename Download filename.
	 */
	public function create_and_stream_multiple( $sources, $filename ) {
		$this->prepare_runtime();

		$zip_file = $this->allocate_temp_zip();
		if ( ! $zip_file ) {
			wp_die( esc_html__( 'Failed to create a temporary ZIP file.', 'oli-download-plugin' ) );
		}

		$result = $this->create_zip_multiple( $sources, $zip_file );
		if ( true !== $result ) {
			$this->fail_zip_creation( $result );
		}

		$this->stream_file( $zip_file, $filename );
	}

	/**
	 * Raise limits and refuse to abort mid-stream so the temp file can be deleted.
	 */
	private function prepare_runtime() {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_time_limit, WordPress.PHP.NoSilencedErrors.Discouraged -- large plugin archives.
			@set_time_limit( 300 );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- keep going after client disconnect so the temp ZIP is deleted.
		@ignore_user_abort( true );
	}

	/**
	 * Create a randomly named temp ZIP outside the web root when possible.
	 *
	 * The pretty download name is used only in Content-Disposition, never as
	 * the on-disk filename.
	 *
	 * @return string|false Absolute path.
	 */
	private function allocate_temp_zip() {
		$token = wp_generate_password( 12, false );

		$path = $this->allocate_in_directory( get_temp_dir(), $token );
		if ( $path ) {
			$this->register_temp_cleanup( $path );
			return $path;
		}

		Oli_DLP_Cleanup::ensure_temp_dir();
		$path = $this->allocate_in_directory( Oli_DLP_Cleanup::get_temp_dir(), $token );
		if ( $path ) {
			$this->register_temp_cleanup( $path );
			return $path;
		}

		return false;
	}

	/**
	 * Create a unique temp file in $dir.
	 *
	 * @param string $dir   Directory.
	 * @param string $token Random token.
	 * @return string|false
	 */
	private function allocate_in_directory( $dir, $token ) {
		if ( ! is_string( $dir ) || '' === $dir ) {
			return false;
		}

		$dir = trailingslashit( $dir );
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return false;
		}

		$file = wp_tempnam( 'oli-dlp-' . $token, $dir );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Register shutdown deletion for the current temp ZIP.
	 *
	 * @param string $file Absolute path.
	 */
	private function register_temp_cleanup( $file ) {
		$this->temp_zip_file = $file;

		if ( $this->cleanup_registered ) {
			return;
		}

		$this->cleanup_registered = true;
		register_shutdown_function( array( $this, 'delete_temp_zip_file' ) );
	}

	/**
	 * Delete the registered temp ZIP if it is still on disk.
	 */
	public function delete_temp_zip_file() {
		if ( '' === $this->temp_zip_file ) {
			return;
		}

		$file                = $this->temp_zip_file;
		$this->temp_zip_file = '';

		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Delete any leftover temp ZIP and abort with a translated error.
	 *
	 * @param string|bool $result create_zip() result.
	 */
	private function fail_zip_creation( $result ) {
		$this->delete_temp_zip_file();

		if ( self::RESULT_CLOSE_FAILED === $result ) {
			wp_die( esc_html__( 'Failed to finalize the ZIP archive.', 'oli-download-plugin' ) );
		}

		wp_die( esc_html__( 'Failed to create ZIP file.', 'oli-download-plugin' ) );
	}

	/**
	 * Create a ZIP of one source.
	 *
	 * @param string $source_path Source path.
	 * @param string $zip_file    Destination ZIP.
	 * @return bool|string
	 */
	private function create_zip( $source_path, $zip_file ) {
		if ( class_exists( 'ZipArchive' ) ) {
			return $this->create_zip_with_ziparchive( $source_path, $zip_file );
		}

		return $this->create_zip_with_pclzip( $source_path, $zip_file );
	}

	/**
	 * Create a ZIP of several sources.
	 *
	 * @param array  $sources  Map of slug => path.
	 * @param string $zip_file Destination ZIP.
	 * @return bool|string
	 */
	private function create_zip_multiple( $sources, $zip_file ) {
		if ( class_exists( 'ZipArchive' ) ) {
			return $this->create_zip_multiple_with_ziparchive( $sources, $zip_file );
		}

		return $this->create_zip_multiple_with_pclzip( $sources, $zip_file );
	}

	/**
	 * ZipArchive implementation for one source.
	 *
	 * @param string $source_path Source path.
	 * @param string $zip_file    Destination ZIP.
	 * @return bool|string
	 */
	private function create_zip_with_ziparchive( $source_path, $zip_file ) {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$source_path = realpath( $source_path );
		if ( false === $source_path ) {
			$zip->close();
			return false;
		}

		if ( is_dir( $source_path ) ) {
			$this->add_directory_to_zip( $zip, $source_path, basename( $source_path ) );
		} elseif ( is_file( $source_path ) && ! is_link( $source_path ) ) {
			$this->add_file_to_zip( $zip, $source_path, basename( $source_path ) );
		} else {
			$zip->close();
			return false;
		}

		if ( true !== $zip->close() ) {
			return self::RESULT_CLOSE_FAILED;
		}

		return file_exists( $zip_file );
	}

	/**
	 * Recursively add a directory without following symbolic links.
	 *
	 * @param ZipArchive $zip       Archive.
	 * @param string     $dir       Absolute directory.
	 * @param string     $base_path Path prefix inside the archive.
	 */
	private function add_directory_to_zip( $zip, $dir, $base_path ) {
		$entries = Oli_DLP_Validator::collect_zip_entries( $dir, $base_path, $this->excluded_patterns );

		foreach ( $entries as $entry ) {
			$this->add_file_to_zip( $zip, $entry['realpath'], $entry['relative'] );
		}
	}

	/**
	 * Add one file, swallowing ValueError / other throwables from addFile().
	 *
	 * @param ZipArchive $zip           Archive.
	 * @param string     $absolute_path Real path on disk.
	 * @param string     $relative_path Path inside the archive.
	 */
	private function add_file_to_zip( $zip, $absolute_path, $relative_path ) {
		try {
			$zip->addFile( $absolute_path, $relative_path );
		} catch ( \ValueError $exception ) {
			unset( $exception );
		} catch ( \Throwable $exception ) {
			unset( $exception );
		}
	}

	/**
	 * ZipArchive implementation for several sources.
	 *
	 * @param array  $sources  Map of slug => path.
	 * @param string $zip_file Destination ZIP.
	 * @return bool|string
	 */
	private function create_zip_multiple_with_ziparchive( $sources, $zip_file ) {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		foreach ( $sources as $source_path ) {
			$source_path = realpath( $source_path );
			if ( false === $source_path ) {
				continue;
			}

			if ( is_dir( $source_path ) ) {
				$this->add_directory_to_zip( $zip, $source_path, basename( $source_path ) );
			} elseif ( is_file( $source_path ) && ! is_link( $source_path ) ) {
				$this->add_file_to_zip( $zip, $source_path, basename( $source_path ) );
			}
		}

		if ( true !== $zip->close() ) {
			return self::RESULT_CLOSE_FAILED;
		}

		return file_exists( $zip_file );
	}

	/**
	 * PclZip fallback for one source.
	 *
	 * @param string $source_path Source path.
	 * @param string $zip_file    Destination ZIP.
	 * @return bool
	 */
	private function create_zip_with_pclzip( $source_path, $zip_file ) {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$source_path = realpath( $source_path );
		if ( false === $source_path ) {
			return false;
		}

		$this->pclzip_source_root = $source_path;

		$zip        = new PclZip( $zip_file );
		$parent_dir = dirname( $source_path );
		$result     = $zip->create(
			$source_path,
			PCLZIP_OPT_REMOVE_PATH,
			$parent_dir,
			PCLZIP_CB_PRE_ADD,
			array( $this, 'pclzip_callback' )
		);

		return ( false !== $result && file_exists( $zip_file ) );
	}

	/**
	 * PclZip fallback for several sources.
	 *
	 * @param array  $sources  Map of slug => path.
	 * @param string $zip_file Destination ZIP.
	 * @return bool
	 */
	private function create_zip_multiple_with_pclzip( $sources, $zip_file ) {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$zip   = new PclZip( $zip_file );
		$first = true;

		foreach ( $sources as $source_path ) {
			$source_path = realpath( $source_path );
			if ( false === $source_path ) {
				continue;
			}

			$this->pclzip_source_root = $source_path;
			$parent_dir               = dirname( $source_path );

			if ( $first ) {
				$result = $zip->create(
					$source_path,
					PCLZIP_OPT_REMOVE_PATH,
					$parent_dir,
					PCLZIP_CB_PRE_ADD,
					array( $this, 'pclzip_callback' )
				);
				$first  = false;
			} else {
				$result = $zip->add(
					$source_path,
					PCLZIP_OPT_REMOVE_PATH,
					$parent_dir,
					PCLZIP_CB_PRE_ADD,
					array( $this, 'pclzip_callback' )
				);
			}

			if ( false === $result ) {
				return false;
			}
		}

		return file_exists( $zip_file );
	}

	/**
	 * PclZip pre-add callback: skip links, escaped paths, and exclusions.
	 *
	 * @param int   $event Event type.
	 * @param array $file  File header (passed by reference by PclZip).
	 * @return int 1 to keep, 0 to skip.
	 */
	public function pclzip_callback( $event, &$file ) {
		unset( $event );

		$filename = isset( $file['filename'] ) ? $file['filename'] : '';
		if ( Oli_DLP_Validator::should_skip_source_file( $filename, $this->pclzip_source_root ) ) {
			return 0;
		}

		$stored = isset( $file['stored_filename'] ) ? $file['stored_filename'] : $filename;
		if ( Oli_DLP_Validator::should_exclude( $stored, $this->excluded_patterns ) ) {
			return 0;
		}

		return 1;
	}

	/**
	 * Stream a ZIP and delete the temp file afterwards.
	 *
	 * @param string $file     Absolute path.
	 * @param string $filename Download filename (Content-Disposition only).
	 */
	private function stream_file( $file, $filename ) {
		if ( ! file_exists( $file ) ) {
			$this->delete_temp_zip_file();
			wp_die( esc_html__( 'ZIP file not found.', 'oli-download-plugin' ) );
		}

		$filename = sanitize_file_name( $filename );

		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv -- prevent gzip/buffer from corrupting the ZIP stream.
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'zlib.output_compression', 'Off' );
		}
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $file ) );
		header( 'Content-Transfer-Encoding: binary' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a temp ZIP to the browser.
		readfile( $file );

		$this->delete_temp_zip_file();
		exit;
	}
}
