<?php
/**
 * CSV export capability tests (per type).
 *
 * @package Oli_Download_Plugin
 */

$oli_dlp_types = Oli_DLP_Export::export_types();

oli_dlp_assert(
	array( 'user', 'post', 'page', 'comment', 'term' ) === $oli_dlp_types,
	'Export types are user, post, page, comment, and term'
);

oli_dlp_assert(
	'manage_options' === Oli_DLP_Export::required_export_capability( false ),
	'Single-site exports require manage_options'
);

oli_dlp_assert(
	'manage_network' === Oli_DLP_Export::required_export_capability( true ),
	'Multisite exports require manage_network'
);

$oli_dlp_editor_caps = array(
	'read'              => true,
	'edit_posts'        => true,
	'edit_pages'        => true,
	'edit_others_posts' => true,
	'edit_others_pages' => true,
	'publish_posts'     => true,
	'moderate_comments' => true,
	'manage_categories' => true,
	'upload_files'      => true,
	'list_users'        => false,
	'manage_options'    => false,
	'manage_network'    => false,
);

$oli_dlp_admin_caps = array(
	'manage_options'    => true,
	'edit_posts'        => true,
	'moderate_comments' => true,
	'manage_categories' => true,
	'list_users'        => true,
	'manage_network'    => false,
);

$oli_dlp_network_caps = array(
	'manage_network'    => true,
	'manage_options'    => false,
	'edit_posts'        => true,
	'moderate_comments' => true,
	'manage_categories' => true,
	'list_users'        => true,
);

foreach ( $oli_dlp_types as $oli_dlp_type ) {
	oli_dlp_assert(
		false === Oli_DLP_Export::export_allowed_for_caps( $oli_dlp_type, false, $oli_dlp_editor_caps ),
		'Editor cannot export ' . $oli_dlp_type . ' (single site)'
	);

	oli_dlp_assert(
		false === Oli_DLP_Export::export_allowed_for_caps( $oli_dlp_type, true, $oli_dlp_editor_caps ),
		'Editor cannot export ' . $oli_dlp_type . ' (multisite)'
	);

	oli_dlp_assert(
		true === Oli_DLP_Export::export_allowed_for_caps( $oli_dlp_type, false, $oli_dlp_admin_caps ),
		'Administrator can export ' . $oli_dlp_type
	);

	oli_dlp_assert(
		true === Oli_DLP_Export::export_allowed_for_caps( $oli_dlp_type, true, $oli_dlp_network_caps ),
		'Network administrator can export ' . $oli_dlp_type
	);
}

oli_dlp_assert(
	false === Oli_DLP_Export::export_allowed_for_caps( 'media', false, $oli_dlp_admin_caps ),
	'Unknown export type is rejected'
);

$oli_dlp_export_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-oli-dlp-export.php' );

foreach ( array( 'user_row_actions', 'post_row_actions', 'comment_row_actions', 'term_row_actions', 'register_bulk', 'maybe_export', 'handle_users_bulk', 'handle_posts_bulk', 'handle_comments_bulk', 'handle_terms_bulk' ) as $oli_dlp_method ) {
	oli_dlp_assert(
		false !== strpos( $oli_dlp_export_src, 'function ' . $oli_dlp_method ),
		'Export class defines ' . $oli_dlp_method
	);
}

oli_dlp_assert(
	false !== strpos( $oli_dlp_export_src, 'page_row_actions' ) && false !== strpos( $oli_dlp_export_src, 'post_row_actions' ),
	'Pages reuse the post row-action handler'
);

oli_dlp_assert(
	1 === preg_match( '/current_user_can\(\s*self::required_export_capability/', $oli_dlp_export_src ),
	'Runtime gate uses current_user_can( required_export_capability() )'
);

$oli_dlp_weak_caps = array(
	"current_user_can( 'edit_post'",
	'current_user_can( "edit_post"',
	"current_user_can( 'list_users'",
	"current_user_can( 'moderate_comments'",
	"current_user_can( 'manage_categories'",
);
$oli_dlp_weak_found = false;
foreach ( $oli_dlp_weak_caps as $oli_dlp_weak ) {
	if ( false !== strpos( $oli_dlp_export_src, $oli_dlp_weak ) ) {
		$oli_dlp_weak_found = true;
	}
}

oli_dlp_assert(
	false === $oli_dlp_weak_found,
	'Export handlers no longer trust Editor-level capabilities'
);

oli_dlp_assert(
	false !== strpos( $oli_dlp_export_src, 'user_can_export' ) && false !== strpos( $oli_dlp_export_src, 'deny_if_cannot_export' ),
	'UI and handlers share user_can_export / deny_if_cannot_export'
);
