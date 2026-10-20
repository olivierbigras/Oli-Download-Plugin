<?php
/**
 * ZIP path-containment and symlink unit tests.
 *
 * @package Oli_Download_Plugin
 */

$oli_dlp_base = sys_get_temp_dir() . '/oli-dlp-zip-' . uniqid( '', true );
$oli_dlp_dir  = $oli_dlp_base . '/name';
$oli_dlp_out  = $oli_dlp_base . '/outside';

mkdir( $oli_dlp_base );
mkdir( $oli_dlp_dir );
mkdir( $oli_dlp_dir . '/includes' );
mkdir( $oli_dlp_out );

file_put_contents( $oli_dlp_dir . '/readme.txt', 'ok' );
file_put_contents( $oli_dlp_dir . '/includes/class-foo.php', '<?php' );
file_put_contents( $oli_dlp_out . '/secret.php', 'secret' );

$oli_dlp_readme = new SplFileInfo( $oli_dlp_dir . '/readme.txt' );
$oli_dlp_rel    = Oli_DLP_Validator::zip_entry_relative_path( $oli_dlp_readme, $oli_dlp_dir, 'name' );

oli_dlp_assert(
	'name/readme.txt' === $oli_dlp_rel,
	'Relative ZIP path is name/readme.txt (from getPathname, not "ame")'
);

oli_dlp_assert(
	'ame' !== $oli_dlp_rel && 'ame/readme.txt' !== $oli_dlp_rel,
	'Relative path is not the broken "ame" substr of name'
);

$oli_dlp_nested = Oli_DLP_Validator::zip_entry_relative_path(
	new SplFileInfo( $oli_dlp_dir . '/includes/class-foo.php' ),
	$oli_dlp_dir,
	'name'
);

oli_dlp_assert(
	'name/includes/class-foo.php' === $oli_dlp_nested,
	'Nested relative path keeps the directory prefix'
);

symlink( $oli_dlp_dir . '/missing.txt', $oli_dlp_dir . '/broken-link' );
$oli_dlp_broken = new SplFileInfo( $oli_dlp_dir . '/broken-link' );

oli_dlp_assert(
	true === $oli_dlp_broken->isLink(),
	'Broken symlink is reported as a link'
);

oli_dlp_assert(
	false === $oli_dlp_broken->getRealPath(),
	'Broken symlink getRealPath() is false'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::zip_entry_relative_path( $oli_dlp_broken, $oli_dlp_dir, 'name' ),
	'Broken symlink is omitted from the ZIP'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_skip_source_file( $oli_dlp_broken, $oli_dlp_dir ),
	'PclZip helper skips a broken symlink'
);

symlink( $oli_dlp_out . '/secret.php', $oli_dlp_dir . '/escape.php' );
$oli_dlp_escape = new SplFileInfo( $oli_dlp_dir . '/escape.php' );

oli_dlp_assert(
	true === $oli_dlp_escape->isLink(),
	'Escaping symlink is reported as a link'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::zip_entry_relative_path( $oli_dlp_escape, $oli_dlp_dir, 'name' ),
	'Symlink that leaves the plugin is omitted from the ZIP'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_skip_source_file( $oli_dlp_escape, $oli_dlp_dir ),
	'PclZip helper skips a symlink that leaves the plugin'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_skip_source_file( $oli_dlp_out . '/secret.php', $oli_dlp_dir ),
	'PclZip helper skips an absolute path outside the source'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_skip_source_file( $oli_dlp_dir . '/readme.txt', $oli_dlp_dir ),
	'Regular file inside the plugin is not skipped'
);

$oli_dlp_entries  = Oli_DLP_Validator::collect_zip_entries( $oli_dlp_dir, 'name' );
$oli_dlp_relpaths = array();
foreach ( $oli_dlp_entries as $oli_dlp_entry ) {
	$oli_dlp_relpaths[] = $oli_dlp_entry['relative'];
}
sort( $oli_dlp_relpaths );

oli_dlp_assert(
	array( 'name/includes/class-foo.php', 'name/readme.txt' ) === $oli_dlp_relpaths,
	'collect_zip_entries returns only regular files with correct relative paths'
);

oli_dlp_assert(
	! in_array( 'name/broken-link', $oli_dlp_relpaths, true ),
	'collect_zip_entries does not include the broken symlink'
);

oli_dlp_assert(
	! in_array( 'name/escape.php', $oli_dlp_relpaths, true ),
	'collect_zip_entries does not include the escaping symlink'
);

$oli_dlp_secret_rels = array();
foreach ( $oli_dlp_entries as $oli_dlp_entry ) {
	if ( false !== strpos( $oli_dlp_entry['realpath'], 'secret.php' ) ) {
		$oli_dlp_secret_rels[] = $oli_dlp_entry['relative'];
	}
}

oli_dlp_assert(
	array() === $oli_dlp_secret_rels,
	'Escaping symlink target is not packed under a plugin-relative name'
);

@unlink( $oli_dlp_dir . '/broken-link' );
@unlink( $oli_dlp_dir . '/escape.php' );
@unlink( $oli_dlp_dir . '/includes/class-foo.php' );
@rmdir( $oli_dlp_dir . '/includes' );
@unlink( $oli_dlp_dir . '/readme.txt' );
@rmdir( $oli_dlp_dir );
@unlink( $oli_dlp_out . '/secret.php' );
@rmdir( $oli_dlp_out );
@rmdir( $oli_dlp_base );
