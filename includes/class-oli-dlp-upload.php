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

		if ( isset( $_GET['oli_dlp_upload_errors'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Some archives could not be installed. Use a valid WordPress plugin ZIP.', 'oli-download-plugin' ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

		$files  = $this->normalize_files( $_FILES['oli_dlp_plugin_zips'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- files array normalized below.
		$ok     = 0;
		$failed = false;

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
			} else {
				$failed = true;
			}
		}

		$this->redirect_upload( $ok, $failed );
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
