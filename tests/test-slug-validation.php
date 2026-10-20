<?php
/**
 * Slug validation unit tests.
 *
 * @package Oli_Download_Plugin
 */

$oli_dlp_plugins = array(
	'akismet/akismet.php'   => array( 'Name' => 'Akismet' ),
	'hello.php'             => array( 'Name' => 'Hello Dolly' ),
	'oli-download-plugin/oli-download-plugin.php' => array( 'Name' => 'Oli Download Plugin' ),
);

$oli_dlp_themes = array( 'twentytwentyfive', 'twentytwentyfour', 'my-child-theme' );

oli_dlp_assert(
	false !== Oli_DLP_Validator::match_plugin_slug( 'akismet', $oli_dlp_plugins ),
	'Directory plugin slug matches installed plugin'
);

oli_dlp_assert(
	'akismet/akismet.php' === Oli_DLP_Validator::match_plugin_slug( 'akismet', $oli_dlp_plugins ),
	'Directory plugin slug returns the plugin file key'
);

oli_dlp_assert(
	'hello.php' === Oli_DLP_Validator::match_plugin_slug( 'hello', $oli_dlp_plugins ),
	'Single-file plugin matches basename without .php'
);

oli_dlp_assert(
	'hello.php' === Oli_DLP_Validator::match_plugin_slug( 'hello.php', $oli_dlp_plugins ),
	'Single-file plugin matches filename'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_plugin_slug( 'not-installed', $oli_dlp_plugins ),
	'Unknown plugin slug is rejected'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_plugin_slug( '../akismet', $oli_dlp_plugins ),
	'Parent-directory traversal is rejected'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_plugin_slug( 'akismet/../hello.php', $oli_dlp_plugins ),
	'Embedded traversal is rejected'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_plugin_slug( 'akismet/akismet.php', $oli_dlp_plugins ),
	'Plugin file path is not accepted as a slug'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_plugin_slug( '', $oli_dlp_plugins ),
	'Empty slug is rejected'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::is_safe_slug( "akismet\0evil" ),
	'Null-byte slug is rejected'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::is_safe_slug( 'foo\\bar' ),
	'Backslash slug is rejected'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::match_theme_slug( 'my-child-theme', $oli_dlp_themes ),
	'Child theme slug matches installed themes'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_theme_slug( 'twenty-missing', $oli_dlp_themes ),
	'Unknown theme slug is rejected'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_theme_slug( '../twentytwentyfive', $oli_dlp_themes ),
	'Theme path traversal is rejected'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::match_named_file( 'object-cache.php', array( 'object-cache.php', 'advanced-cache.php' ) ),
	'Drop-in filename matches allow-list'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::match_named_file( '../wp-config.php', array( 'object-cache.php' ) ),
	'Drop-in traversal is rejected'
);
