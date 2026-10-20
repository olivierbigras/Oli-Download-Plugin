<?php
/**
 * Download request handling.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles authenticated download requests from wp-admin.
 */
class Oli_DLP_Core {

	/**
	 * Singleton instance.
	 *
	 * @var Oli_DLP_Core|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Oli_DLP_Core
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
		add_action( 'admin_init', array( $this, 'handle_download' ) );
	}

	/**
	 * Whether the current user may download plugins.
	 *
	 * @return bool
	 */
	public static function user_can_download_plugins() {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_plugins' );
		}

		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Whether the current user may download themes.
	 *
	 * @return bool
	 */
	public static function user_can_download_themes() {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_themes' );
		}

		return current_user_can( 'switch_themes' ) || current_user_can( 'edit_themes' );
	}

	/**
	 * Whether MU-plugin downloads are enabled.
	 *
	 * @return bool
	 */
	public static function mu_plugins_enabled() {
		return (bool) self::get_option( 'oli_dlp_enable_mu_plugins', 0 );
	}

	/**
	 * Whether drop-in downloads are enabled.
	 *
	 * @return bool
	 */
	public static function dropins_enabled() {
		return (bool) self::get_option( 'oli_dlp_enable_dropins', 0 );
	}

	/**
	 * Get a plugin option (network-aware).
	 *
	 * @param string $key     Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_option( $key, $default = false ) {
		if ( is_multisite() ) {
			return get_site_option( $key, $default );
		}

		return get_option( $key, $default );
	}

	/**
	 * Update a plugin option (network-aware).
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public static function update_option( $key, $value ) {
		if ( is_multisite() ) {
			return update_site_option( $key, $value );
		}

		return update_option( $key, $value );
	}

	/**
	 * Build a nonce-protected download URL.
	 *
	 * @param string $type Item type.
	 * @param string $slug Item slug.
	 * @return string
	 */
	public static function get_download_url( $type, $slug ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'oli_dlp_download' => $slug,
					'type'             => $type,
				),
				admin_url( 'admin.php' )
			),
			'oli_dlp_download'
		);
	}

	/**
	 * Handle download requests from admin.
	 */
	public function handle_download() {
		if ( ! isset( $_GET['oli_dlp_download'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below.
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'oli_dlp_download' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$slug = sanitize_text_field( wp_unslash( $_GET['oli_dlp_download'] ) );

		switch ( $type ) {
			case 'plugin':
				$this->download_plugin( $slug );
				break;
			case 'theme':
				$this->download_theme( $slug );
				break;
			case 'mu_plugin':
				$this->download_mu_plugin( $slug );
				break;
			case 'dropin':
				$this->download_dropin( $slug );
				break;
			case 'bulk_plugins':
				$this->download_bulk_plugins();
				break;
			default:
				wp_die( esc_html__( 'Invalid download type.', 'oli-download-plugin' ), '', array( 'response' => 400 ) );
		}
	}

	/**
	 * Download one installed plugin.
	 *
	 * @param string $slug Plugin directory or single-file slug.
	 */
	private function download_plugin( $slug ) {
		if ( ! self::user_can_download_plugins() ) {
			wp_die( esc_html__( 'You do not have permission to download plugins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$plugin_file = $this->validate_plugin_slug( $slug );
		if ( ! $plugin_file ) {
			wp_die( esc_html__( 'Invalid plugin.', 'oli-download-plugin' ), '', array( 'response' => 400 ) );
		}

		$source = $this->plugin_source_path( $plugin_file );
		if ( ! $source ) {
			wp_die( esc_html__( 'Plugin files not found.', 'oli-download-plugin' ), '', array( 'response' => 404 ) );
		}

		$zip_slug = $this->plugin_zip_slug( $plugin_file );
		$filename = $this->build_filename( $zip_slug, $this->get_plugin_version( $plugin_file ) );

		$zip = new Oli_DLP_Zip();
		$zip->create_and_stream( $source, $filename );
	}

	/**
	 * Download one installed theme (including child themes).
	 *
	 * @param string $slug Theme stylesheet slug.
	 */
	private function download_theme( $slug ) {
		if ( ! self::user_can_download_themes() ) {
			wp_die( esc_html__( 'You do not have permission to download themes.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$theme = $this->validate_theme_slug( $slug );
		if ( ! $theme ) {
			wp_die( esc_html__( 'Invalid theme.', 'oli-download-plugin' ), '', array( 'response' => 400 ) );
		}

		$theme_root = get_theme_root( $slug );
		$source     = Oli_DLP_Validator::resolve_inside( trailingslashit( $theme_root ) . $slug, $theme_root );

		if ( ! $source ) {
			wp_die( esc_html__( 'Theme files not found.', 'oli-download-plugin' ), '', array( 'response' => 404 ) );
		}

		$version  = $theme->get( 'Version' );
		$filename = $this->build_filename( $slug, $version );

		$zip = new Oli_DLP_Zip();
		$zip->create_and_stream( $source, $filename );
	}

	/**
	 * Download one must-use plugin file or directory.
	 *
	 * @param string $slug Filename or directory name.
	 */
	private function download_mu_plugin( $slug ) {
		if ( ! self::user_can_download_plugins() || ! self::mu_plugins_enabled() ) {
			wp_die( esc_html__( 'You do not have permission to download must-use plugins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$allowed = $this->list_mu_plugin_names();
		if ( ! Oli_DLP_Validator::match_named_file( $slug, $allowed ) ) {
			wp_die( esc_html__( 'Invalid must-use plugin.', 'oli-download-plugin' ), '', array( 'response' => 400 ) );
		}

		$root   = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		$source = Oli_DLP_Validator::resolve_inside( trailingslashit( $root ) . $slug, $root );

		if ( ! $source ) {
			wp_die( esc_html__( 'Must-use plugin files not found.', 'oli-download-plugin' ), '', array( 'response' => 404 ) );
		}

		$version  = is_file( $source ) ? $this->get_file_header_version( $source ) : '1.0.0';
		$filename = $this->build_filename( $this->strip_php_suffix( $slug ), $version );

		$zip = new Oli_DLP_Zip();
		$zip->create_and_stream( $source, $filename );
	}

	/**
	 * Download one drop-in file.
	 *
	 * @param string $slug Drop-in filename.
	 */
	private function download_dropin( $slug ) {
		if ( ! self::user_can_download_plugins() || ! self::dropins_enabled() ) {
			wp_die( esc_html__( 'You do not have permission to download drop-ins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$allowed = $this->list_dropin_names();
		if ( ! Oli_DLP_Validator::match_named_file( $slug, $allowed ) ) {
			wp_die( esc_html__( 'Invalid drop-in.', 'oli-download-plugin' ), '', array( 'response' => 400 ) );
		}

		$source = Oli_DLP_Validator::resolve_inside( trailingslashit( WP_CONTENT_DIR ) . $slug, WP_CONTENT_DIR );

		if ( ! $source || ! is_file( $source ) ) {
			wp_die( esc_html__( 'Drop-in file not found.', 'oli-download-plugin' ), '', array( 'response' => 404 ) );
		}

		$version  = $this->get_file_header_version( $source );
		$filename = $this->build_filename( $this->strip_php_suffix( $slug ), $version );

		$zip = new Oli_DLP_Zip();
		$zip->create_and_stream( $source, $filename );
	}

	/**
	 * Download several plugins as a single ZIP.
	 *
	 * Accepts `plugins[]` query slugs or `checked[]` plugin files from the
	 * Plugins screen bulk action.
	 */
	public function download_bulk_plugins() {
		if ( ! self::user_can_download_plugins() ) {
			wp_die( esc_html__( 'You do not have permission to download plugins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$slugs = array();

		if ( isset( $_REQUEST['plugins'] ) && is_array( $_REQUEST['plugins'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in handle_download or bulk handler.
			$slugs = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['plugins'] ) );
		} elseif ( isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$files = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['checked'] ) );
			foreach ( $files as $plugin_file ) {
				$slugs[] = $this->plugin_zip_slug( $plugin_file );
			}
		}

		$paths = array();

		foreach ( $slugs as $slug ) {
			$plugin_file = $this->validate_plugin_slug( $slug );
			if ( ! $plugin_file ) {
				continue;
			}

			$source = $this->plugin_source_path( $plugin_file );
			if ( $source ) {
				$paths[ $this->plugin_zip_slug( $plugin_file ) ] = $source;
			}
		}

		if ( empty( $paths ) ) {
			wp_die( esc_html__( 'No valid plugins to download.', 'oli-download-plugin' ), '', array( 'response' => 400 ) );
		}

		$filename = sanitize_file_name( 'plugins-' . gmdate( 'Y-m-d-His' ) . '.zip' );

		$zip = new Oli_DLP_Zip();
		$zip->create_and_stream_multiple( $paths, $filename );
	}

	/**
	 * Validate a plugin slug against installed plugins.
	 *
	 * @param string $slug Plugin slug.
	 * @return string|false Plugin file on success.
	 */
	public function validate_plugin_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return Oli_DLP_Validator::match_plugin_slug( $slug, get_plugins() );
	}

	/**
	 * Validate a theme slug against installed themes.
	 *
	 * @param string $slug Theme slug.
	 * @return WP_Theme|false
	 */
	public function validate_theme_slug( $slug ) {
		if ( ! Oli_DLP_Validator::is_safe_slug( $slug ) ) {
			return false;
		}

		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() ) {
			return false;
		}

		$installed = array();
		foreach ( wp_get_themes() as $stylesheet => $installed_theme ) {
			unset( $installed_theme );
			$installed[] = $stylesheet;
		}

		if ( ! Oli_DLP_Validator::match_theme_slug( $slug, $installed ) ) {
			return false;
		}

		return $theme;
	}

	/**
	 * List MU-plugin files and companion directories.
	 *
	 * @return string[]
	 */
	public function list_mu_plugin_names() {
		$root = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $root ) ) {
			return array();
		}

		$names = array();

		if ( ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_mu_plugins() ) as $file ) {
			$names[] = $file;
		}

		$entries = scandir( $root );
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				if ( is_dir( trailingslashit( $root ) . $entry ) && Oli_DLP_Validator::is_safe_slug( $entry ) ) {
					$names[] = $entry;
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * List installed drop-in filenames.
	 *
	 * @return string[]
	 */
	public function list_dropin_names() {
		if ( ! function_exists( '_get_dropins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$names = array();
		foreach ( array_keys( _get_dropins() ) as $file ) {
			if ( Oli_DLP_Validator::is_safe_slug( $file ) && file_exists( trailingslashit( WP_CONTENT_DIR ) . $file ) ) {
				$names[] = $file;
			}
		}

		return $names;
	}

	/**
	 * Resolve an installed plugin path and confirm it stays in WP_PLUGIN_DIR.
	 *
	 * @param string $plugin_file Plugin file key from get_plugins().
	 * @return string|false
	 */
	private function plugin_source_path( $plugin_file ) {
		if ( false !== strpos( $plugin_file, '/' ) ) {
			$path = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		} else {
			$path = WP_PLUGIN_DIR . '/' . $plugin_file;
		}

		return Oli_DLP_Validator::resolve_inside( $path, WP_PLUGIN_DIR );
	}

	/**
	 * Slug used in ZIP filenames and query args.
	 *
	 * @param string $plugin_file Plugin file key.
	 * @return string
	 */
	private function plugin_zip_slug( $plugin_file ) {
		if ( false !== strpos( $plugin_file, '/' ) ) {
			return dirname( $plugin_file );
		}

		return $this->strip_php_suffix( $plugin_file );
	}

	/**
	 * Plugin version from headers.
	 *
	 * @param string $plugin_file Plugin file key.
	 * @return string
	 */
	private function get_plugin_version( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( isset( $all_plugins[ $plugin_file ]['Version'] ) && '' !== $all_plugins[ $plugin_file ]['Version'] ) {
			return $all_plugins[ $plugin_file ]['Version'];
		}

		return '0.0.0';
	}

	/**
	 * Read a Version header from a PHP file.
	 *
	 * @param string $file Absolute path.
	 * @return string
	 */
	private function get_file_header_version( $file ) {
		if ( ! function_exists( 'get_file_data' ) ) {
			return '0.0.0';
		}

		$data = get_file_data( $file, array( 'Version' => 'Version' ) );

		return ( isset( $data['Version'] ) && '' !== $data['Version'] ) ? $data['Version'] : '0.0.0';
	}

	/**
	 * Build `slug-version.zip`.
	 *
	 * @param string $slug    Slug.
	 * @param string $version Version.
	 * @return string
	 */
	private function build_filename( $slug, $version ) {
		if ( '' === (string) $version ) {
			$version = '0.0.0';
		}

		return sanitize_file_name( $slug . '-' . $version . '.zip' );
	}

	/**
	 * Strip a trailing `.php` suffix.
	 *
	 * @param string $name Filename.
	 * @return string
	 */
	private function strip_php_suffix( $name ) {
		if ( substr( $name, -4 ) === '.php' ) {
			return substr( $name, 0, -4 );
		}

		return $name;
	}
}
