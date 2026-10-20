<?php
/**
 * Slug validation and exclusion helpers.
 *
 * This class has no WordPress function dependency so unit tests can load it
 * after defining ABSPATH, without bootstrapping WordPress.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
				'.env',
				'.svn',
				'.hg',
				'.idea',
				'.vscode',
				'*.log',
				'*.sql',
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
		 * Exact patterns match complete path segments (so `.git` does not match
		 * `my.git`). Patterns containing glob metacharacters are matched with
		 * fnmatch() against each segment (so `*.log` matches `debug.log`).
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

			$fnmatch_flags = defined( 'FNM_CASEFOLD' ) ? FNM_CASEFOLD : 0;

			foreach ( $patterns as $pattern ) {
				$pattern = (string) $pattern;
				if ( '' === $pattern ) {
					continue;
				}

				$is_glob = self::is_glob_pattern( $pattern );

				foreach ( $segments as $segment ) {
					if ( $segment === $pattern ) {
						return true;
					}

					if ( $is_glob && fnmatch( $pattern, $segment, $fnmatch_flags ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Whether a pattern uses glob metacharacters.
		 *
		 * @param string $pattern Exclusion pattern.
		 * @return bool
		 */
		public static function is_glob_pattern( $pattern ) {
			return (bool) strpbrk( (string) $pattern, '*?[' );
		}

		/**
		 * Sanitize custom exclusion patterns (one per line).
		 *
		 * @param string $raw Raw textarea value.
		 * @return string[]
		 */
		public static function sanitize_exclusion_lines( $raw ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
			$clean = array();

			if ( ! is_array( $lines ) ) {
				return $clean;
			}

			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				$line = preg_replace( '/[\x00-\x1F\x7F]/', '', $line );
				if ( ! is_string( $line ) || '' === $line || strlen( $line ) > 200 ) {
					continue;
				}

				if ( false !== strpos( $line, '..' ) || false !== strpos( $line, '/' ) || false !== strpos( $line, '\\' ) ) {
					continue;
				}

				if ( ! preg_match( '/^[A-Za-z0-9._*?\[\]-]+$/', $line ) ) {
					continue;
				}

				$clean[] = $line;
			}

			return array_values( array_unique( $clean ) );
		}

		/**
		 * Whether $path sits inside PHP open_basedir (string prefix, no resolve).
		 *
		 * @param string $path Filesystem path.
		 * @return bool
		 */
		public static function path_inside_open_basedir( $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				return false;
			}

			$basedir = ini_get( 'open_basedir' );
			if ( ! is_string( $basedir ) || '' === $basedir ) {
				return true;
			}

			$path = self::normalize_separators( $path );

			foreach ( explode( PATH_SEPARATOR, $basedir ) as $root ) {
				$root = self::normalize_dir( trim( (string) $root ) );
				if ( '' === $root ) {
					continue;
				}

				if ( $path === $root || 0 === strpos( $path, $root . DIRECTORY_SEPARATOR ) ) {
					return true;
				}

				// Parents of an allowed root (e.g. /var when basedir is /var/www).
				if ( 0 === strpos( $root, $path . DIRECTORY_SEPARATOR ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Run $callback while swallowing only open_basedir warnings.
		 *
		 * Other errors are forwarded to the previous handler. Throwables
		 * become $fallback.
		 *
		 * @param callable $callback Filesystem work.
		 * @param mixed    $fallback Value if the probe throws.
		 * @return mixed
		 */
		public static function with_open_basedir_guard( $callback, $fallback = false ) {
			if ( ! is_callable( $callback ) ) {
				return $fallback;
			}

			$previous = null;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- local handler swallows only open_basedir.
			$previous = set_error_handler(
				static function ( $errno, $errstr, $errfile = '', $errline = 0 ) use ( &$previous ) {
					if ( is_string( $errstr ) && false !== stripos( $errstr, 'open_basedir' ) ) {
						return true;
					}

					if ( is_callable( $previous ) ) {
						return (bool) call_user_func( $previous, $errno, $errstr, $errfile, $errline );
					}

					return false;
				}
			);

			try {
				return $callback();
			} catch ( Exception $exception ) {
				unset( $exception );
				return $fallback;
			} catch ( Throwable $exception ) {
				unset( $exception );
				return $fallback;
			} finally {
				restore_error_handler();
			}
		}

		/**
		 * Whether $path is a symbolic link, using lstat (does not follow).
		 *
		 * Paths outside open_basedir are treated as links so callers skip them
		 * without invoking a following stat.
		 *
		 * @param string $path Filesystem path.
		 * @return bool
		 */
		public static function path_is_link( $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				return false;
			}

			if ( ! self::path_inside_open_basedir( $path ) ) {
				return true;
			}

			return (bool) self::with_open_basedir_guard(
				static function () use ( $path ) {
					clearstatcache( true, $path );
					return is_link( $path );
				},
				true
			);
		}

		/**
		 * Safe realpath() that never follows links and never fatals on open_basedir.
		 *
		 * @param string $path Path.
		 * @return string|false
		 */
		public static function safe_realpath( $path ) {
			if ( ! is_string( $path ) || '' === $path || self::path_is_link( $path ) || ! self::path_inside_open_basedir( $path ) ) {
				return false;
			}

			$real = self::with_open_basedir_guard(
				static function () use ( $path ) {
					$resolved = realpath( $path );
					return is_string( $resolved ) ? $resolved : false;
				},
				false
			);

			if ( ! is_string( $real ) ) {
				return false;
			}

			if ( ! self::path_inside_open_basedir( $real ) ) {
				return false;
			}

			return $real;
		}

		/**
		 * Safe file_exists() that never fatals on open_basedir.
		 *
		 * @param string $path Path.
		 * @return bool
		 */
		public static function safe_file_exists( $path ) {
			if ( ! is_string( $path ) || '' === $path || self::path_is_link( $path ) || ! self::path_inside_open_basedir( $path ) ) {
				return false;
			}

			return (bool) self::with_open_basedir_guard(
				static function () use ( $path ) {
					return file_exists( $path );
				},
				false
			);
		}

		/**
		 * Safe is_file() that never follows links or fatals on open_basedir.
		 *
		 * @param string $path Path.
		 * @return bool
		 */
		public static function safe_is_file( $path ) {
			if ( ! is_string( $path ) || '' === $path || self::path_is_link( $path ) || ! self::path_inside_open_basedir( $path ) ) {
				return false;
			}

			return (bool) self::with_open_basedir_guard(
				static function () use ( $path ) {
					return is_file( $path );
				},
				false
			);
		}

		/**
		 * Safe is_dir() that never follows links or fatals on open_basedir.
		 *
		 * @param string $path Path.
		 * @return bool
		 */
		public static function safe_is_dir( $path ) {
			if ( ! is_string( $path ) || '' === $path || self::path_is_link( $path ) || ! self::path_inside_open_basedir( $path ) ) {
				return false;
			}

			return (bool) self::with_open_basedir_guard(
				static function () use ( $path ) {
					return is_dir( $path );
				},
				false
			);
		}

		/**
		 * Whether a filesystem entry must be omitted from a ZIP.
		 *
		 * Skips symbolic links, broken paths, and anything whose real path
		 * escapes $root. is_link() is always checked first and never follows.
		 *
		 * @param SplFileInfo|string $file File info or absolute path.
		 * @param string             $root Allowed source directory or file.
		 * @return bool
		 */
		public static function should_skip_source_file( $file, $root ) {
			try {
				$root = self::normalize_dir( $root );
				if ( '' === $root ) {
					return true;
				}

				$filename = ( $file instanceof SplFileInfo ) ? $file->getPathname() : (string) $file;
				if ( '' === $filename || self::path_is_link( $filename ) ) {
					return true;
				}

				$real_path = self::safe_realpath( $filename );
				if ( false === $real_path ) {
					return true;
				}

				return ! self::is_real_path_inside( $real_path, $root );
			} catch ( Exception $exception ) {
				unset( $exception );
				return true;
			} catch ( Throwable $exception ) {
				unset( $exception );
				return true;
			}
		}

		/**
		 * Archive-relative path for a file under $dir, or false to skip it.
		 *
		 * The relative portion is taken from getPathname() (not getRealPath()),
		 * so a directory named `name` yields `name/readme.txt` rather than `ame`.
		 *
		 * @param SplFileInfo $file      Iterator entry.
		 * @param string      $dir       Absolute source directory (no trailing slash).
		 * @param string      $base_path Path prefix inside the archive.
		 * @return string|false
		 */
		public static function zip_entry_relative_path( $file, $dir, $base_path ) {
			try {
				if ( ! $file instanceof SplFileInfo ) {
					return false;
				}

				$pathname = $file->getPathname();
				if ( self::path_is_link( $pathname ) || ! self::safe_is_file( $pathname ) ) {
					return false;
				}

				$real_path = self::safe_realpath( $pathname );
				if ( false === $real_path ) {
					return false;
				}

				$dir = self::normalize_dir( $dir );
				if ( '' === $dir || ! self::is_real_path_inside( $real_path, $dir ) ) {
					return false;
				}

				$pathname   = self::normalize_separators( $pathname );
				$dir_prefix = $dir . DIRECTORY_SEPARATOR;

				if ( 0 !== strpos( $pathname, $dir_prefix ) ) {
					return false;
				}

				$relative = substr( $pathname, strlen( $dir_prefix ) );
				if ( false === $relative || '' === $relative ) {
					return false;
				}

				$base_path = str_replace( '\\', '/', (string) $base_path );
				$relative  = str_replace( '\\', '/', $relative );

				return $base_path . '/' . $relative;
			} catch ( Exception $exception ) {
				unset( $exception );
				return false;
			} catch ( Throwable $exception ) {
				unset( $exception );
				return false;
			}
		}

		/**
		 * Collect safe ZIP entries under a directory without following links.
		 *
		 * @param string   $dir       Absolute source directory.
		 * @param string   $base_path Archive path prefix.
		 * @param string[] $patterns  Exclusion patterns.
		 * @return array<int, array{realpath:string, relative:string}>
		 */
		public static function collect_zip_entries( $dir, $base_path, $patterns = array() ) {
			$entries = array();
			$dir     = self::normalize_dir( $dir );

			if ( '' === $dir || ! self::safe_is_dir( $dir ) ) {
				return $entries;
			}

			$collected = self::with_open_basedir_guard(
				static function () use ( $dir, $base_path, $patterns ) {
					$found = array();

					try {
						$directory = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
						$filtered  = new RecursiveCallbackFilterIterator(
							$directory,
							static function ( $current ) {
								try {
									$path = $current->getPathname();
									return ! Oli_DLP_Validator::path_is_link( $path );
								} catch ( Exception $exception ) {
									unset( $exception );
									return false;
								} catch ( Throwable $exception ) {
									unset( $exception );
									return false;
								}
							}
						);
						$iterator  = new RecursiveIteratorIterator( $filtered, RecursiveIteratorIterator::LEAVES_ONLY );
					} catch ( Exception $exception ) {
						unset( $exception );
						return $found;
					} catch ( Throwable $exception ) {
						unset( $exception );
						return $found;
					}

					foreach ( $iterator as $file ) {
						try {
							$relative = self::zip_entry_relative_path( $file, $dir, $base_path );
							if ( false === $relative ) {
								continue;
							}

							if ( self::should_exclude( $relative, $patterns ) ) {
								continue;
							}

							$pathname  = $file->getPathname();
							$real_path = self::safe_realpath( $pathname );
							if ( false === $real_path ) {
								continue;
							}

							$found[] = array(
								'realpath' => $real_path,
								'relative' => $relative,
							);
						} catch ( Exception $exception ) {
							unset( $exception );
							continue;
						} catch ( Throwable $exception ) {
							unset( $exception );
							continue;
						}
					}

					return $found;
				},
				array()
			);

			return is_array( $collected ) ? $collected : $entries;
		}

		/**
		 * Normalize a directory path (native separators, no trailing slash).
		 *
		 * @param string $path Path.
		 * @return string
		 */
		public static function normalize_dir( $path ) {
			$path = self::normalize_separators( $path );
			return rtrim( $path, DIRECTORY_SEPARATOR );
		}

		/**
		 * Convert slashes to DIRECTORY_SEPARATOR.
		 *
		 * @param string $path Path.
		 * @return string
		 */
		private static function normalize_separators( $path ) {
			return str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, (string) $path );
		}

		/**
		 * Whether $real_path is $root or a descendant of $root.
		 *
		 * @param string $real_path Canonical path.
		 * @param string $root      Canonical root (no trailing slash).
		 * @return bool
		 */
		private static function is_real_path_inside( $real_path, $root ) {
			$real_path = self::normalize_separators( $real_path );
			$root      = self::normalize_dir( $root );

			if ( $real_path === $root ) {
				return true;
			}

			return ( 0 === strpos( $real_path, $root . DIRECTORY_SEPARATOR ) );
		}

		/**
		 * Confirm a resolved path stays inside an allowed root.
		 *
		 * @param string $path Absolute path to check.
		 * @param string $root Allowed root directory or file parent.
		 * @return string|false Real path on success.
		 */
		public static function resolve_inside( $path, $root ) {
			$real_path = self::safe_realpath( $path );
			$real_root = self::safe_realpath( $root );

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
