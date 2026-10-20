<?php
/**
 * Lightweight test runner (no WordPress bootstrap).
 *
 * @package Oli_Download_Plugin
 */

$oli_dlp_failed = 0;
$oli_dlp_passed = 0;

/**
 * Record an assertion result.
 *
 * @param bool   $condition Whether the assertion passed.
 * @param string $message   Description.
 */
function oli_dlp_assert( $condition, $message ) {
	global $oli_dlp_failed, $oli_dlp_passed;

	if ( $condition ) {
		echo 'PASS  ' . $message . PHP_EOL;
		++$oli_dlp_passed;
		return;
	}

	echo 'FAIL  ' . $message . PHP_EOL;
	++$oli_dlp_failed;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-oli-dlp-validator.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-dlp-csv.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-dlp-export.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-dlp-upload.php';
require_once __DIR__ . '/test-slug-validation.php';
require_once __DIR__ . '/test-exclusion-filter.php';
require_once __DIR__ . '/test-zip-paths.php';
require_once __DIR__ . '/test-csv.php';
require_once __DIR__ . '/test-export-caps.php';
require_once __DIR__ . '/test-upload-activate.php';

echo PHP_EOL . $oli_dlp_passed . ' passed, ' . $oli_dlp_failed . ' failed' . PHP_EOL;

exit( $oli_dlp_failed > 0 ? 1 : 0 );
