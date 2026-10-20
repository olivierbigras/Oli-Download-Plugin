<?php
/**
 * Multiple plugin ZIP upload.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install several plugin archives from the Download screen.
 */
class Oli_DLP_Upload {

	/**
	 * Singleton instance.
	 *
	 * @var Oli_DLP_Upload|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Oli_DLP_Upload
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
		add_action( 'admin_init', array( $this, 'maybe_handle_upload' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_activate' ) );
	}

	/**
	 * Whether the current user may install plugins.
	 *
	 * @return bool
	 */
	public static function user_can_upload() {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_plugins' );
		}

		return current_user_can( 'install_plugins' );
	}

	/**
	 * Whether the current user may activate uploaded plugins.
	 *
	 * @return bool
	 */
	public static function user_can_activate() {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_plugins' );
		}

		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Transient key for plugins waiting to be activated.
	 *
	 * @return string
	 */
	public static function pending_activate_key() {
		return 'oli_dlp_pending_activate_' . get_current_user_id();
	}

	/**
	 * Sanitize a plugin file key (plugin-dir/file.php).
	 *
	 * @param string $file Plugin basename.
	 * @return string
	 */
	public static function sanitize_plugin_file( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		$file = ltrim( $file, '/' );

		if ( '' === $file || false !== strpos( $file, '..' ) ) {
			return '';
		}

		if ( ! preg_match( '/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*\.php$/', $file ) ) {
			return '';
		}

		return $file;
	}

	/**
	 * Render the multi-upload form.
	 */
	public static function render_form() {
		if ( ! self::user_can_upload() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags after redirect.
		if ( isset( $_GET['oli_dlp_uploaded'] ) ) {
			$count = absint( wp_unslash( $_GET['oli_dlp_uploaded'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of plugins installed. */
					__( '%d plugin(s) installed.', 'oli-download-plugin' ),
					$count
				)
			);
			echo '</p></div>';
		}

		if ( isset( $_GET['oli_dlp_activated'] ) ) {
			$count = absint( wp_unslash( $_GET['oli_dlp_activated'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of plugins activated. */
					__( '%d plugin(s) activated.', 'oli-download-plugin' ),
					$count
				)
			);
			echo '</p></div>';
		}

		if ( isset( $_GET['oli_dlp_upload_errors'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Some archives could not be installed. Use a valid WordPress plugin ZIP.', 'oli-download-plugin' ) . '</p></div>';
		}

		if ( isset( $_GET['oli_dlp_activate_errors'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Some plugins could not be activated.', 'oli-download-plugin' ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		self::render_activate_form();

		$action = add_query_arg( 'page', 'oli-download-plugin', is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
		?>
		<form method="post" action="<?php echo esc_url( $action ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'oli_dlp_upload_plugins' ); ?>
			<input type="hidden" name="oli_dlp_upload_plugins" value="1" />
			<p>
				<label for="oli_dlp_plugin_zips"><?php esc_html_e( 'Plugin ZIP files', 'oli-download-plugin' ); ?></label><br />
				<input type="file" name="oli_dlp_plugin_zips[]" id="oli_dlp_plugin_zips" accept=".zip,application/zip" multiple="multiple" required />
			</p>
			<?php submit_button( __( 'Install plugins', 'oli-download-plugin' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Handle the multi-upload POST.
	 */
	public function maybe_handle_upload() {
		if ( ! isset( $_POST['oli_dlp_upload_plugins'] ) ) {
			return;
		}

		check_admin_referer( 'oli_dlp_upload_plugins' );

		if ( ! self::user_can_upload() ) {
			wp_die( esc_html__( 'You do not have permission to install plugins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		if ( empty( $_FILES['oli_dlp_plugin_zips'] ) || ! is_array( $_FILES['oli_dlp_plugin_zips'] ) ) {
			$this->redirect_upload( 0, true );
		}

		$files   = $this->normalize_files( $_FILES['oli_dlp_plugin_zips'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- files array normalized below.
		$ok      = 0;
		$failed  = false;
		$pending = array();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		foreach ( $files as $file ) {
			if ( ! $this->is_usable_zip( $file ) ) {
				$failed = true;
				continue;
			}

			$skin     = new Automatic_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $file['tmp_name'] );

			if ( true === $result ) {
				++$ok;
				$installed = $upgrader->plugin_info();
				$installed = self::sanitize_plugin_file( (string) $installed );
				if ( '' !== $installed ) {
					$pending[] = $installed;
				}
			} else {
				$failed = true;
			}
		}

		if ( ! empty( $pending ) ) {
			set_transient( self::pending_activate_key(), array_values( array_unique( $pending ) ), 15 * MINUTE_IN_SECONDS );
		}

		$this->redirect_upload( $ok, $failed );
	}

	/**
	 * Activate plugins installed by the last multi-upload (nonce + activate_plugins).
	 */
	public function maybe_handle_activate() {
		if ( ! isset( $_POST['oli_dlp_activate_plugins'] ) ) {
			return;
		}

		check_admin_referer( 'oli_dlp_activate_plugins' );

		if ( ! self::user_can_activate() ) {
			wp_die( esc_html__( 'You do not have permission to activate plugins.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$requested = isset( $_POST['oli_dlp_plugins'] ) ? (array) wp_unslash( $_POST['oli_dlp_plugins'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$installed = array_keys( get_plugins() );
		$ok        = 0;
		$failed    = false;
		$network   = is_multisite() && is_network_admin();

		foreach ( $requested as $plugin_file ) {
			$plugin_file = self::sanitize_plugin_file( $plugin_file );
			if ( '' === $plugin_file || ! in_array( $plugin_file, $installed, true ) ) {
				$failed = true;
				continue;
			}

			if ( $network ? is_plugin_active_for_network( $plugin_file ) : is_plugin_active( $plugin_file ) ) {
				++$ok;
				continue;
			}

			$result = activate_plugin( $plugin_file, '', $network );
			if ( is_wp_error( $result ) ) {
				$failed = true;
			} else {
				++$ok;
			}
		}

		delete_transient( self::pending_activate_key() );

		$args = array( 'page' => 'oli-download-plugin' );
		if ( $ok > 0 ) {
			$args['oli_dlp_activated'] = $ok;
		}
		if ( $failed ) {
			$args['oli_dlp_activate_errors'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Offer to activate plugins from the last successful upload.
	 */
	private static function render_activate_form() {
		if ( ! self::user_can_activate() ) {
			return;
		}

		$pending = get_transient( self::pending_activate_key() );
		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return;
		}

		$action = add_query_arg( 'page', 'oli-download-plugin', is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
		?>
		<div class="notice notice-info" id="oli-dlp-activate">
			<p><?php esc_html_e( 'Activate the plugins you just installed.', 'oli-download-plugin' ); ?></p>
			<form method="post" action="<?php echo esc_url( $action ); ?>">
				<?php wp_nonce_field( 'oli_dlp_activate_plugins' ); ?>
				<input type="hidden" name="oli_dlp_activate_plugins" value="1" />
				<ul>
					<?php foreach ( $pending as $plugin_file ) : ?>
						<?php
						$plugin_file = self::sanitize_plugin_file( $plugin_file );
						if ( '' === $plugin_file ) {
							continue;
						}
						?>
						<li>
							<input type="hidden" name="oli_dlp_plugins[]" value="<?php echo esc_attr( $plugin_file ); ?>" />
							<code><?php echo esc_html( $plugin_file ); ?></code>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php submit_button( __( 'Activate all', 'oli-download-plugin' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Turn $_FILES['oli_dlp_plugin_zips'] into a list of single-file arrays.
	 *
	 * @param array $bag Uploaded files bag.
	 * @return array
	 */
	private function normalize_files( $bag ) {
		$out   = array();
		$names = isset( $bag['name'] ) ? (array) $bag['name'] : array();

		foreach ( $names as $index => $name ) {
			$out[] = array(
				'name'     => sanitize_file_name( (string) $name ),
				'type'     => isset( $bag['type'][ $index ] ) ? (string) $bag['type'][ $index ] : '',
				'tmp_name' => isset( $bag['tmp_name'][ $index ] ) ? (string) $bag['tmp_name'][ $index ] : '',
				'error'    => isset( $bag['error'][ $index ] ) ? (int) $bag['error'][ $index ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $bag['size'][ $index ] ) ? (int) $bag['size'][ $index ] : 0,
			);
		}

		return $out;
	}

	/**
	 * Whether an upload looks like a ZIP on disk.
	 *
	 * @param array $file Normalized file row.
	 * @return bool
	 */
	private function is_usable_zip( $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] || '' === $file['tmp_name'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'zip' !== $ext ) {
			return false;
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'zip' => 'application/zip' ) );
		if ( empty( $check['ext'] ) || 'zip' !== $check['ext'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Redirect back to the Download screen after upload.
	 *
	 * @param int  $ok     Installed count.
	 * @param bool $failed Whether any file failed.
	 */
	private function redirect_upload( $ok, $failed ) {
		$args = array( 'page' => 'oli-download-plugin' );
		if ( $ok > 0 ) {
			$args['oli_dlp_uploaded'] = $ok;
		}
		if ( $failed ) {
			$args['oli_dlp_upload_errors'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) ) );
		exit;
	}
}
