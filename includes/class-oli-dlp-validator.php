<?php
/**
 * Slug validation and exclusion helpers.
 *
 * This class has no WordPress runtime dependency so unit tests can load it
 * without bootstrapping WordPress.
 *
 * @package Oli_Download_Plugin
 */

if ( ! class_exists( 'Oli_DLP_Validator' ) ) {
	/**
	 * Validation helpers.
	 */
	class Oli_DLP_Validator {

		/**
		 * Default path-segment patterns excluded from ZIPs.
		 *
		 * @return string[]
		 */
		public static function default_excluded_patterns() {
			return array(
				'.git',
				'node_modules',
				'.DS_Store',
			);
		}

		/**
		 * Whether a slug is structurally safe (no traversal or separators).
		 *
		 * @param mixed $slug Raw slug.
		 * @return bool
		 */
		public static function is_safe_slug( $slug ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				return false;
			}

			if ( strlen( $slug ) > 200 ) {
				return false;
			}

			if ( false !== strpos( $slug, '..' ) || false !== strpos( $slug, '/' ) || false !== strpos( $slug, '\\' ) || false !== strpos( $slug, "\0" ) ) {
				return false;
			}

			return (bool) preg_match( '/^[A-Za-z0-9._-]+$/', $slug );
		}

		/**
		 * Match a plugin slug against a get_plugins() style list.
		 *
		 * Directory plugins use the folder name. Single-file plugins accept
		 * either `hello` or `hello.php`.
		 *
		 * @param string $slug        Candidate slug.
		 * @param array  $all_plugins Map of plugin file => plugin header data.
		 * @return string|false Plugin file key on success, false otherwise.
		 */
		public static function match_plugin_slug( $slug, $all_plugins ) {
			if ( ! self::is_safe_slug( $slug ) || ! is_array( $all_plugins ) ) {
				return false;
			}

			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				unset( $plugin_data );

				if ( false !== strpos( $plugin_file, '/' ) ) {
					if ( dirname( $plugin_file ) === $slug ) {
						return $plugin_file;
					}
				} elseif ( $plugin_file === $slug || basename( $plugin_file, '.php' ) === $slug ) {
					return $plugin_file;
				}
			}

			return false;
		}

		/**
		 * Match a theme stylesheet/slug against installed theme slugs.
		 *
		 * @param string   $slug        Candidate slug.
		 * @param string[] $theme_slugs Installed theme slugs.
		 * @return bool
		 */
		public static function match_theme_slug( $slug, $theme_slugs ) {
			if ( ! self::is_safe_slug( $slug ) || ! is_array( $theme_slugs ) ) {
				return false;
			}

			return in_array( $slug, $theme_slugs, true );
		}

		/**
		 * Match a must-use plugin or drop-in filename against an allow-list.
		 *
		 * @param string   $slug  Candidate filename or directory name.
		 * @param string[] $allow Installed names.
		 * @return bool
		 */
		public static function match_named_file( $slug, $allow ) {
			if ( ! self::is_safe_slug( $slug ) || ! is_array( $allow ) ) {
				return false;
			}

			return in_array( $slug, $allow, true );
		}

		/**
		 * Whether a relative ZIP path should be excluded.
		 *
		 * Patterns match complete path segments (so `.git` does not match `my.git`).
		 *
		 * @param string   $path     Relative path using `/` or `\`.
		 * @param string[] $patterns Exclusion patterns.
		 * @return bool
		 */
		public static function should_exclude( $path, $patterns ) {
			$normalized = str_replace( '\\', '/', (string) $path );
			$segments   = explode( '/', $normalized );

			if ( ! is_array( $patterns ) ) {
				return false;
			}

			foreach ( $patterns as $pattern ) {
				$pattern = (string) $pattern;
				if ( '' === $pattern ) {
					continue;
				}

				foreach ( $segments as $segment ) {
					if ( $segment === $pattern ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Confirm a resolved path stays inside an allowed root.
		 *
		 * @param string $path Absolute path to check.
		 * @param string $root Allowed root directory or file parent.
		 * @return string|false Real path on success.
		 */
		public static function resolve_inside( $path, $root ) {
			$real_path = realpath( $path );
			$real_root = realpath( $root );

			if ( false === $real_path || false === $real_root ) {
				return false;
			}

			$root_with_sep = rtrim( $real_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

			if ( $real_path === $real_root ) {
				return $real_path;
			}

			if ( 0 !== strpos( $real_path, $root_with_sep ) ) {
				return false;
			}

			return $real_path;
		}
	}
}
