<?php
/**
 * Multi-upload activate-offer helpers.
 *
 * @package Oli_Download_Plugin
 */

oli_dlp_assert(
	'akismet/akismet.php' === Oli_DLP_Upload::sanitize_plugin_file( 'akismet/akismet.php' ),
	'Valid directory plugin file is accepted'
);

oli_dlp_assert(
	'hello.php' === Oli_DLP_Upload::sanitize_plugin_file( 'hello.php' ),
	'Valid single-file plugin is accepted'
);

oli_dlp_assert(
	'' === Oli_DLP_Upload::sanitize_plugin_file( '../evil.php' ),
	'Plugin file traversal is rejected'
);

oli_dlp_assert(
	'' === Oli_DLP_Upload::sanitize_plugin_file( '/etc/passwd' ),
	'Absolute plugin file is rejected'
);

oli_dlp_assert(
	'' === Oli_DLP_Upload::sanitize_plugin_file( 'akismet/akismet.php.txt' ),
	'Non-PHP plugin file is rejected'
);

$oli_dlp_upload_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-oli-dlp-upload.php' );

oli_dlp_assert(
	false !== strpos( $oli_dlp_upload_src, 'maybe_handle_activate' ) && false !== strpos( $oli_dlp_upload_src, 'oli_dlp_activate_plugins' ),
	'Upload handler offers a nonce-protected activate action'
);

oli_dlp_assert(
	false !== strpos( $oli_dlp_upload_src, 'activate_plugins' ) && false !== strpos( $oli_dlp_upload_src, 'Activate all' ),
	'Activate all requires the activate_plugins capability'
);
