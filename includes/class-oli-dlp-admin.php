<?php
/**
 * Admin UI: action links, bulk actions, theme overlay, settings.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin integration.
 */
class Oli_DLP_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Oli_DLP_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Oli_DLP_Admin
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
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'theme_action_links', array( $this, 'theme_action_links' ), 10, 2 );
		add_filter( 'network_admin_theme_action_links', array( $this, 'theme_action_links' ), 10, 2 );

		add_filter( 'bulk_actions-plugins', array( $this, 'register_bulk_action' ) );
		add_filter( 'bulk_actions-plugins-network', array( $this, 'register_bulk_action' ) );
		add_action( 'load-plugins.php', array( $this, 'handle_bulk_plugins' ) );

		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'network_admin_menu', array( $this, 'register_network_settings_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_bulk_empty_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_bulk_empty_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_upload_notice_button' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_theme_script' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add Download to each plugin row.
	 *
	 * @param string[] $links       Existing action links.
	 * @param string   $plugin_file Plugin file key.
	 * @return string[]
	 */
	public function plugin_action_links( $links, $plugin_file ) {
		if ( ! Oli_DLP_Core::user_can_download_plugins() ) {
			return $links;
		}

		$slug = $this->slug_from_plugin_file( $plugin_file );
		if ( ! $slug ) {
			return $links;
		}

		$url = Oli_DLP_Core::get_download_url( 'plugin', $slug );

		$links['oli_dlp_download'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Download', 'oli-download-plugin' )
		);

		return $links;
	}

	/**
	 * Add Download to theme action links (network themes table, some UIs).
	 *
	 * @param string[] $links Existing links.
	 * @param WP_Theme $theme Theme object.
	 * @return string[]
	 */
	public function theme_action_links( $links, $theme ) {
		if ( ! Oli_DLP_Core::user_can_download_themes() ) {
			return $links;
		}

		if ( ! $theme instanceof WP_Theme ) {
			return $links;
		}

		$slug = $theme->get_stylesheet();
		if ( ! Oli_DLP_Validator::is_safe_slug( $slug ) ) {
			return $links;
		}

		$url = Oli_DLP_Core::get_download_url( 'theme', $slug );

		$links['oli_dlp_download'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Download', 'oli-download-plugin' )
		);

		return $links;
	}

	/**
	 * Register the plugins-screen bulk action.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		if ( ! Oli_DLP_Core::user_can_download_plugins() ) {
			return $actions;
		}

		$actions['oli_dlp_download'] = __( 'Download', 'oli-download-plugin' );

		return $actions;
	}

	/**
	 * Handle bulk plugin download from the Plugins screen.
	 */
	public function handle_bulk_plugins() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( '-1' === $action && isset( $_REQUEST['action2'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}

		if ( 'oli_dlp_download' !== $action ) {
			return;
		}

		check_admin_referer( 'bulk-plugins' );

		if ( ! Oli_DLP_Core::user_can_download_plugins() ) {
			wp_die( esc_html__( 'You do not have permission to download plugins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$checked = isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['checked'] ) ) : array();
		if ( empty( $checked ) ) {
			Oli_DLP_Core::instance()->redirect_empty_bulk();
		}

		Oli_DLP_Core::instance()->download_bulk_plugins();
	}

	/**
	 * Notice when the bulk Download action ran with nothing selected.
	 */
	public function maybe_bulk_empty_notice() {
		if ( ! isset( $_GET['oli_dlp_bulk_empty'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Select at least one plugin to download.', 'oli-download-plugin' ) . '</p></div>';
	}

	/**
	 * “Upload Multiple Plugins” action on the Add Plugins screen.
	 */
	public function maybe_upload_notice_button() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugin-install' !== $screen->id ) {
			return;
		}

		if ( ! current_user_can( is_multisite() ? 'manage_network_plugins' : 'install_plugins' ) ) {
			return;
		}

		$url = add_query_arg( 'page', 'oli-download-plugin', is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
		echo '<div class="wrap" style="margin:0 0 8px;"><a class="page-title-action" href="' . esc_url( $url ) . '#oli-dlp-upload">' . esc_html__( 'Upload Multiple Plugins', 'oli-download-plugin' ) . '</a></div>';
	}

	/**
	 * Settings / Download hub on single-site installs.
	 */
	public function register_settings_page() {
		if ( is_multisite() ) {
			return;
		}

		add_menu_page(
			__( 'Download', 'oli-download-plugin' ),
			__( 'Download', 'oli-download-plugin' ),
			'activate_plugins',
			'oli-download-plugin',
			array( $this, 'render_settings_page' ),
			'dashicons-media-archive',
			99
		);
	}

	/**
	 * Settings / Download hub on network admin.
	 */
	public function register_network_settings_page() {
		add_menu_page(
			__( 'Download', 'oli-download-plugin' ),
			__( 'Download', 'oli-download-plugin' ),
			'manage_network_plugins',
			'oli-download-plugin',
			array( $this, 'render_settings_page' ),
			'dashicons-media-archive',
			99
		);
	}

	/**
	 * Save settings from the settings form.
	 */
	public function maybe_save_settings() {
		if ( ! isset( $_POST['oli_dlp_save_settings'] ) ) {
			return;
		}

		check_admin_referer( 'oli_dlp_save_settings' );

		if ( ! $this->user_can_manage_settings() ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$enable_mu      = isset( $_POST['oli_dlp_enable_mu_plugins'] ) ? 1 : 0;
		$enable_dropins = isset( $_POST['oli_dlp_enable_dropins'] ) ? 1 : 0;
		$exclusions     = '';
		if ( isset( $_POST['oli_dlp_custom_exclusions'] ) ) {
			$exclusions = sanitize_textarea_field( wp_unslash( $_POST['oli_dlp_custom_exclusions'] ) );
		}
		$clean_lines = Oli_DLP_Validator::sanitize_exclusion_lines( $exclusions );

		Oli_DLP_Core::update_option( 'oli_dlp_enable_mu_plugins', $enable_mu );
		Oli_DLP_Core::update_option( 'oli_dlp_enable_dropins', $enable_dropins );
		Oli_DLP_Core::update_option( 'oli_dlp_custom_exclusions', implode( "\n", $clean_lines ) );

		$redirect = add_query_arg(
			array(
				'page'            => 'oli-download-plugin',
				'oli_dlp_updated' => '1',
			),
			is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the settings screen.
	 */
	public function render_settings_page() {
		if ( ! $this->user_can_manage_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		$enable_mu      = (int) Oli_DLP_Core::get_option( 'oli_dlp_enable_mu_plugins', 0 );
		$enable_dropins = (int) Oli_DLP_Core::get_option( 'oli_dlp_enable_dropins', 0 );
		$exclusions     = (string) Oli_DLP_Core::get_option( 'oli_dlp_custom_exclusions', '' );
		$core           = Oli_DLP_Core::instance();

		if ( isset( $_GET['oli_dlp_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after redirect.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'oli-download-plugin' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Download installed plugins and themes as ZIP files from the Plugins and Themes screens. Administrators can also download users, posts, pages, comments, and terms as CSV.', 'oli-download-plugin' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'oli_dlp_save_settings' ); ?>
				<input type="hidden" name="oli_dlp_save_settings" value="1" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Must-use plugins', 'oli-download-plugin' ); ?></th>
						<td>
							<label for="oli_dlp_enable_mu_plugins">
								<input type="checkbox" name="oli_dlp_enable_mu_plugins" id="oli_dlp_enable_mu_plugins" value="1" <?php checked( $enable_mu, 1 ); ?> />
								<?php esc_html_e( 'Allow download of must-use plugins (mu-plugins).', 'oli-download-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Drop-ins', 'oli-download-plugin' ); ?></th>
						<td>
							<label for="oli_dlp_enable_dropins">
								<input type="checkbox" name="oli_dlp_enable_dropins" id="oli_dlp_enable_dropins" value="1" <?php checked( $enable_dropins, 1 ); ?> />
								<?php esc_html_e( 'Allow download of WordPress drop-in files (object-cache.php, advanced-cache.php, and others).', 'oli-download-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oli_dlp_custom_exclusions"><?php esc_html_e( 'Custom ZIP exclusions', 'oli-download-plugin' ); ?></label></th>
						<td>
							<textarea name="oli_dlp_custom_exclusions" id="oli_dlp_custom_exclusions" rows="6" cols="40" class="large-text code"><?php echo esc_textarea( $exclusions ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One pattern per line (for example vendor or *.bak). Added to the default exclusions and the oli_dlp_excluded_patterns filter.', 'oli-download-plugin' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'oli-download-plugin' ) ); ?>
			</form>

			<?php if ( $enable_mu ) : ?>
				<h2><?php esc_html_e( 'Must-use plugins', 'oli-download-plugin' ); ?></h2>
				<?php $this->render_named_list( $core->list_mu_plugin_names(), 'mu_plugin' ); ?>
			<?php endif; ?>

			<?php if ( $enable_dropins ) : ?>
				<h2><?php esc_html_e( 'Drop-ins', 'oli-download-plugin' ); ?></h2>
				<?php $this->render_named_list( $core->list_dropin_names(), 'dropin' ); ?>
			<?php endif; ?>

			<?php if ( current_user_can( is_multisite() ? 'manage_network_plugins' : 'install_plugins' ) ) : ?>
				<h2 id="oli-dlp-upload"><?php esc_html_e( 'Upload Multiple Plugins', 'oli-download-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Install several plugin ZIP files in one go. Only valid WordPress plugin archives are accepted.', 'oli-download-plugin' ); ?></p>
				<?php Oli_DLP_Upload::render_form(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a simple download table.
	 *
	 * @param string[] $names Item names.
	 * @param string   $type  Download type.
	 */
	private function render_named_list( $names, $type ) {
		if ( empty( $names ) ) {
			echo '<p>' . esc_html__( 'None found.', 'oli-download-plugin' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'oli-download-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Download', 'oli-download-plugin' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $names as $name ) {
			$url = Oli_DLP_Core::get_download_url( $type, $name );
			echo '<tr><td>' . esc_html( $name ) . '</td><td><a href="' . esc_url( $url ) . '">' . esc_html__( 'Download', 'oli-download-plugin' ) . '</a></td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Enqueue the theme-details Download button script.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_theme_script( $hook ) {
		if ( 'themes.php' !== $hook ) {
			return;
		}

		if ( ! Oli_DLP_Core::user_can_download_themes() ) {
			return;
		}

		$urls = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			unset( $theme );
			if ( Oli_DLP_Validator::is_safe_slug( $stylesheet ) ) {
				$urls[ $stylesheet ] = Oli_DLP_Core::get_download_url( 'theme', $stylesheet );
			}
		}

		wp_enqueue_style(
			'oli-dlp-themes',
			OLI_DLP_PLUGIN_URL . 'assets/css/themes.css',
			array(),
			OLI_DLP_VERSION
		);

		wp_enqueue_script(
			'oli-dlp-themes',
			OLI_DLP_PLUGIN_URL . 'assets/js/themes.js',
			array( 'jquery' ),
			OLI_DLP_VERSION,
			true
		);

		wp_localize_script(
			'oli-dlp-themes',
			'oliDlpThemes',
			array(
				'urls'  => $urls,
				'label' => __( 'Download', 'oli-download-plugin' ),
			)
		);
	}

	/**
	 * Extra meta link on this plugin's row.
	 *
	 * @param string[] $links       Meta links.
	 * @param string   $plugin_file Plugin file.
	 * @return string[]
	 */
	public function plugin_row_meta( $links, $plugin_file ) {
		if ( OLI_DLP_PLUGIN_BASENAME !== $plugin_file ) {
			return $links;
		}

		$links[] = '<a href="https://github.com/olivierbigras/Oli-Download-Plugin">' . esc_html__( 'GitHub', 'oli-download-plugin' ) . '</a>';

		return $links;
	}

	/**
	 * Capability for the settings screen.
	 *
	 * @return bool
	 */
	private function user_can_manage_settings() {
		return Oli_DLP_Core::user_can_download_plugins();
	}

	/**
	 * Convert a plugin file key to a download slug.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return string
	 */
	private function slug_from_plugin_file( $plugin_file ) {
		if ( false !== strpos( $plugin_file, '/' ) ) {
			$slug = dirname( $plugin_file );
		} else {
			$slug = ( substr( $plugin_file, -4 ) === '.php' ) ? substr( $plugin_file, 0, -4 ) : $plugin_file;
		}

		return Oli_DLP_Validator::is_safe_slug( $slug ) ? $slug : '';
	}
}
