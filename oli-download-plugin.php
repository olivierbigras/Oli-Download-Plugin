<?php
/**
 * Plugin Name:       Oli Download Plugin
 * Plugin URI:        https://github.com/olivierbigras/Oli-Download-Plugin
 * Description:       Download installed plugins, themes, and child themes as ZIP files from the WordPress admin.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Olivier Bigras
 * Author URI:        https://olivierbigras.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oli-download-plugin
 * Domain Path:       /languages
 * Network:           true
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OLI_DLP_VERSION', '0.1.0' );
define( 'OLI_DLP_PLUGIN_FILE', __FILE__ );
define( 'OLI_DLP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLI_DLP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OLI_DLP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once OLI_DLP_PLUGIN_DIR . 'includes/class-oli-dlp-validator.php';
require_once OLI_DLP_PLUGIN_DIR . 'includes/class-oli-dlp-cleanup.php';
require_once OLI_DLP_PLUGIN_DIR . 'includes/class-oli-dlp-zip.php';
require_once OLI_DLP_PLUGIN_DIR . 'includes/class-oli-dlp-core.php';
require_once OLI_DLP_PLUGIN_DIR . 'includes/class-oli-dlp-admin.php';

/**
 * Load translations and boot admin-only services.
 */
function oli_dlp_init() {
	load_plugin_textdomain(
		'oli-download-plugin',
		false,
		dirname( OLI_DLP_PLUGIN_BASENAME ) . '/languages'
	);

	if ( is_admin() ) {
		Oli_DLP_Core::instance();
		Oli_DLP_Admin::instance();
		Oli_DLP_Cleanup::instance();
	}
}
add_action( 'plugins_loaded', 'oli_dlp_init' );

/**
 * Activation callback.
 */
function oli_dlp_activate() {
	Oli_DLP_Cleanup::schedule_cleanup();
}
register_activation_hook( __FILE__, 'oli_dlp_activate' );

/**
 * Deactivation callback.
 */
function oli_dlp_deactivate() {
	Oli_DLP_Cleanup::clear_schedule();
}
register_deactivation_hook( __FILE__, 'oli_dlp_deactivate' );
