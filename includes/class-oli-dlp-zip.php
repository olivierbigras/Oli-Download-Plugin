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
	 * Absolute temp directory with trailing slash.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Exclusion patterns (path segments).
	 *
	 * @var string[]
	 */
	private $excluded_patterns = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->temp_dir          = Oli_DLP_Cleanup::get_temp_dir();
		$this->excluded_patterns = $this->get_excluded_patterns();

		Oli_DLP_Cleanup::ensure_temp_dir();
	}

	/**
	 * Exclusion patterns, filterable via oli_dlp_excluded_patterns.
	 *
	 * @return string[]
	 */
	public function get_excluded_patterns() {
		$patterns = Oli_DLP_Validator::default_excluded_patterns();

		/**
		 * Filter path-segment names excluded from generated ZIP archives.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $patterns Default: .git, node_modules, .DS_Store.
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
		$zip_file = $this->temp_zip_path( $filename );
		$result   = $this->create_zip( $source_path, $zip_file );

		if ( ! $result ) {
			wp_die( esc_html__( 'Failed to create ZIP file.', 'oli-download-plugin' ) );
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
		$zip_file = $this->temp_zip_path( $filename );
		$result   = $this->create_zip_multiple( $sources, $zip_file );

		if ( ! $result ) {
			wp_die( esc_html__( 'Failed to create ZIP file.', 'oli-download-plugin' ) );
		}

		$this->stream_file( $zip_file, $filename );
	}

	/**
	 * Absolute path for a temp ZIP, confined to the temp directory.
	 *
	 * @param string $filename Sanitized filename.
	 * @return string
	 */
	private function temp_zip_path( $filename ) {
		$filename = sanitize_file_name( $filename );

		return $this->temp_dir . $filename;
	}

	/**
	 * Create a ZIP of one source.
	 *
	 * @param string $source_path Source path.
	 * @param string $zip_file    Destination ZIP.
	 * @return bool
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
	 * @return bool
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
	 * @return bool
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
		} elseif ( is_file( $source_path ) ) {
			$zip->addFile( $source_path, basename( $source_path ) );
		} else {
			$zip->close();
			return false;
		}

		$zip->close();

		return file_exists( $zip_file );
	}

	/**
	 * Recursively add a directory, applying exclusion patterns.
	 *
	 * @param ZipArchive $zip       Archive.
	 * @param string     $dir       Absolute directory.
	 * @param string     $base_path Path prefix inside the archive.
	 */
	private function add_directory_to_zip( $zip, $dir, $base_path ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				continue;
			}

			$file_path     = $file->getRealPath();
			$relative_path = $base_path . '/' . substr( $file_path, strlen( $dir ) + 1 );
			$relative_path = str_replace( '\\', '/', $relative_path );

			if ( Oli_DLP_Validator::should_exclude( $relative_path, $this->excluded_patterns ) ) {
				continue;
			}

			$zip->addFile( $file_path, $relative_path );
		}
	}

	/**
	 * ZipArchive implementation for several sources.
	 *
	 * @param array  $sources  Map of slug => path.
	 * @param string $zip_file Destination ZIP.
	 * @return bool
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
			} elseif ( is_file( $source_path ) ) {
				$zip->addFile( $source_path, basename( $source_path ) );
			}
		}

		$zip->close();

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

			$parent_dir = dirname( $source_path );

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
	 * PclZip pre-add callback used to skip excluded paths.
	 *
	 * @param int   $event Event type.
	 * @param array $file  File header (passed by reference by PclZip).
	 * @return int 1 to keep, 0 to skip.
	 */
	public function pclzip_callback( $event, &$file ) {
		unset( $event );

		if ( isset( $file['filename'] ) && Oli_DLP_Validator::should_exclude( $file['filename'], $this->excluded_patterns ) ) {
			return 0;
		}

		return 1;
	}

	/**
	 * Stream a ZIP and delete the temp file afterwards.
	 *
	 * @param string $file     Absolute path.
	 * @param string $filename Download filename.
	 */
	private function stream_file( $file, $filename ) {
		if ( ! file_exists( $file ) ) {
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

		wp_delete_file( $file );
		exit;
	}
}
