<?php
/**
 * Exclusion-pattern unit tests.
 *
 * @package Oli_Download_Plugin
 */

$oli_dlp_defaults = Oli_DLP_Validator::default_excluded_patterns();

oli_dlp_assert(
	array( '.git', 'node_modules', '.DS_Store', '.env', '.svn', '.hg', '.idea', '.vscode', '*.log', '*.sql' ) === $oli_dlp_defaults,
	'Default exclusions include VCS/IDE dirs, .env, *.log, and *.sql'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/.git/config', $oli_dlp_defaults ),
	'.git directory segment is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/node_modules/leftpad/index.js', $oli_dlp_defaults ),
	'node_modules directory segment is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/assets/.DS_Store', $oli_dlp_defaults ),
	'.DS_Store file is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/.env', $oli_dlp_defaults ),
	'.env file is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/.svn/entries', $oli_dlp_defaults ),
	'.svn directory segment is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/.hg/store', $oli_dlp_defaults ),
	'.hg directory segment is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/.idea/workspace.xml', $oli_dlp_defaults ),
	'.idea directory segment is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/.vscode/settings.json', $oli_dlp_defaults ),
	'.vscode directory segment is excluded'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/debug.log', $oli_dlp_defaults ),
	'*.log glob excludes debug.log'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/sql/dump.sql', $oli_dlp_defaults ),
	'*.sql glob excludes dump.sql'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_exclude( 'akismet/akismet.php', $oli_dlp_defaults ),
	'Normal plugin file is not excluded'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_exclude( 'akismet/my.gitkeep.php', $oli_dlp_defaults ),
	'Partial .git match in a filename is not excluded'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_exclude( 'akismet/vendor/node_modules_helper.php', $oli_dlp_defaults ),
	'Partial node_modules match in a filename is not excluded'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_exclude( 'akismet/includes/logger.php', $oli_dlp_defaults ),
	'logger.php is not matched by *.log'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_exclude( 'akismet/includes/class-sql.php', $oli_dlp_defaults ),
	'class-sql.php is not matched by *.sql'
);

$oli_dlp_filtered   = $oli_dlp_defaults;
$oli_dlp_filtered[] = 'vendor';

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/vendor/autoload.php', $oli_dlp_filtered ),
	'Additional filterable pattern is honored'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::should_exclude( 'akismet/includes/class-foo.php', $oli_dlp_filtered ),
	'Unrelated path stays included after extra pattern'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'theme\\node_modules\\pkg\\index.js', $oli_dlp_defaults ),
	'Windows-style separators are normalized'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::is_glob_pattern( '*.log' ),
	'*.log is detected as a glob pattern'
);

oli_dlp_assert(
	false === Oli_DLP_Validator::is_glob_pattern( '.env' ),
	'.env is not a glob pattern'
);

$oli_dlp_custom = Oli_DLP_Validator::sanitize_exclusion_lines( "vendor\n*.bak\n../etc\nbad/path\n\n.cache\n" );

oli_dlp_assert(
	array( 'vendor', '*.bak', '.cache' ) === $oli_dlp_custom,
	'Custom exclusion lines are trimmed and path-unsafe rows dropped'
);

oli_dlp_assert(
	true === Oli_DLP_Validator::should_exclude( 'akismet/vendor/autoload.php', array_merge( $oli_dlp_defaults, $oli_dlp_custom ) ),
	'Custom vendor exclusion is honored with defaults'
);
