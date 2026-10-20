<?php
/**
 * CSV cell sanitization and fputcsv() helpers.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Oli_DLP_Csv' ) ) {
	/**
	 * CSV helpers with no WordPress runtime dependency.
	 */
	class Oli_DLP_Csv {

		/**
		 * Characters that start a spreadsheet formula.
		 *
		 * @return string[]
		 */
		public static function formula_prefixes() {
			return array( '=', '+', '-', '@', "\t", "\r" );
		}

		/**
		 * Neutralize a CSV cell against formula injection.
		 *
		 * Prefixes an apostrophe when the value starts with =, +, -, @,
		 * a tab, or a carriage return.
		 *
		 * @param mixed $value Cell value.
		 * @return string
		 */
		public static function sanitize_cell( $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$value = (string) $value;
			} else {
				$value = (string) $value;
			}

			if ( '' === $value ) {
				return '';
			}

			$first = substr( $value, 0, 1 );
			if ( in_array( $first, self::formula_prefixes(), true ) ) {
				return "'" . $value;
			}

			return $value;
		}

		/**
		 * Sanitize every cell in a row (list or associative).
		 *
		 * @param array $row Row values.
		 * @return array
		 */
		public static function sanitize_row( $row ) {
			$out = array();
			if ( ! is_array( $row ) ) {
				return $out;
			}

			foreach ( $row as $key => $cell ) {
				$out[ $key ] = self::sanitize_cell( $cell );
			}

			return $out;
		}

		/**
		 * Explicit fputcsv() $escape value.
		 *
		 * PHP 7.4+ accepts an empty string. PHP 8.4 deprecates omitting the
		 * argument; older 7.x builds that reject '' fall back to a backslash.
		 *
		 * @return string
		 */
		public static function escape_char() {
			if ( PHP_VERSION_ID >= 70400 ) {
				return '';
			}

			return '\\';
		}

		/**
		 * Write a CSV row with an explicit escape parameter and sanitized cells.
		 *
		 * @param resource $handle Output stream.
		 * @param array    $fields Row values.
		 * @return int|false
		 */
		public static function putcsv( $handle, $fields ) {
			$fields = array_values( self::sanitize_row( is_array( $fields ) ? $fields : array() ) );

			return fputcsv( $handle, $fields, ',', '"', self::escape_char() );
		}
	}
}
