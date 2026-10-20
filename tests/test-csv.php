<?php
/**
 * CSV formula-injection and fputcsv() tests.
 *
 * @package Oli_Download_Plugin
 */

oli_dlp_assert(
	array( '=', '+', '-', '@', "\t", "\r" ) === Oli_DLP_Csv::formula_prefixes(),
	'CSV formula prefixes are = + - @ tab CR'
);

oli_dlp_assert(
	"'=1+1" === Oli_DLP_Csv::sanitize_cell( '=1+1' ),
	'Cell starting with = is prefixed with an apostrophe'
);

oli_dlp_assert(
	"'+cmd" === Oli_DLP_Csv::sanitize_cell( '+cmd' ),
	'Cell starting with + is prefixed with an apostrophe'
);

oli_dlp_assert(
	"'-1+2" === Oli_DLP_Csv::sanitize_cell( '-1+2' ),
	'Cell starting with - is prefixed with an apostrophe'
);

oli_dlp_assert(
	"'@SUM(A1)" === Oli_DLP_Csv::sanitize_cell( '@SUM(A1)' ),
	'Cell starting with @ is prefixed with an apostrophe'
);

oli_dlp_assert(
	"'\tHYPERLINK" === Oli_DLP_Csv::sanitize_cell( "\tHYPERLINK" ),
	'Cell starting with a tab is prefixed with an apostrophe'
);

oli_dlp_assert(
	"'\r=cmd" === Oli_DLP_Csv::sanitize_cell( "\r=cmd" ),
	'Cell starting with CR is prefixed with an apostrophe'
);

oli_dlp_assert(
	'Hello' === Oli_DLP_Csv::sanitize_cell( 'Hello' ),
	'Plain text cell is unchanged'
);

oli_dlp_assert(
	'' === Oli_DLP_Csv::sanitize_cell( '' ),
	'Empty cell stays empty'
);

oli_dlp_assert(
	'42' === Oli_DLP_Csv::sanitize_cell( 42 ),
	'Integer cell is stringified without a prefix'
);

$oli_dlp_row = Oli_DLP_Csv::sanitize_row(
	array(
		'title'   => '=cmd',
		'excerpt' => 'ok',
		'email'   => '+evil',
	)
);

oli_dlp_assert(
	"'=cmd" === $oli_dlp_row['title'] && 'ok' === $oli_dlp_row['excerpt'] && "'+evil" === $oli_dlp_row['email'],
	'Sanitize_row prefixes only dangerous cells'
);

oli_dlp_assert(
	'' === Oli_DLP_Csv::escape_char() || '\\' === Oli_DLP_Csv::escape_char(),
	'fputcsv escape is an explicit empty string or backslash'
);

$oli_dlp_fh = fopen( 'php://temp', 'r+' );
oli_dlp_assert( is_resource( $oli_dlp_fh ), 'Temporary CSV stream opens' );

Oli_DLP_Csv::putcsv( $oli_dlp_fh, array( 'post_title', 'post_content' ) );
Oli_DLP_Csv::putcsv( $oli_dlp_fh, array( '=1+2', 'Hello' ) );
Oli_DLP_Csv::putcsv( $oli_dlp_fh, array( '@SUM(A1)', "\tcmd" ) );
rewind( $oli_dlp_fh );
$oli_dlp_csv = stream_get_contents( $oli_dlp_fh );
fclose( $oli_dlp_fh );

oli_dlp_assert(
	is_string( $oli_dlp_csv ) && false !== strpos( $oli_dlp_csv, "'=1+2" ),
	'putcsv writes an apostrophe before a leading ='
);

oli_dlp_assert(
	is_string( $oli_dlp_csv ) && false !== strpos( $oli_dlp_csv, "'@SUM(A1)" ),
	'putcsv writes an apostrophe before a leading @'
);

oli_dlp_assert(
	is_string( $oli_dlp_csv ) && false === strpos( $oli_dlp_csv, "\n=1+2" ) && 0 !== strpos( (string) $oli_dlp_csv, '=1+2' ),
	'putcsv never writes a raw formula as the first character of a cell'
);
