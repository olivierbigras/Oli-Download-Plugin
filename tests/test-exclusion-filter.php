<?php
/**
 * Exclusion-pattern unit tests.
 *
 * @package Oli_Download_Plugin
 */

$oli_dlp_defaults = Oli_DLP_Validator::default_excluded_patterns();

oli_dlp_assert(
	array( '.git', 'node_modules', '.DS_Store' ) === $oli_dlp_defaults,
	'Default exclusions are .git, node_modules, and .DS_Store'
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

$oli_dlp_filtered = $oli_dlp_defaults;
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
