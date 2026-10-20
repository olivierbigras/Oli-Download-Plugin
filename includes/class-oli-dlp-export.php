<?php
/**
 * CSV downloads for users, posts, pages, comments, and terms.
 *
 * @package Oli_Download_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Row-action and bulk CSV exporters.
 */
class Oli_DLP_Export {

	/**
	 * Singleton instance.
	 *
	 * @var Oli_DLP_Export|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Oli_DLP_Export
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'user_row_actions', array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
		add_filter( 'comment_row_actions', array( $this, 'comment_row_actions' ), 10, 2 );
		add_filter( 'tag_row_actions', array( $this, 'term_row_actions' ), 10, 2 );

		add_filter( 'bulk_actions-users', array( $this, 'register_bulk' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_users_bulk' ), 10, 3 );
		add_filter( 'bulk_actions-edit-comments', array( $this, 'register_bulk' ) );
		add_filter( 'handle_bulk_actions-edit-comments', array( $this, 'handle_comments_bulk' ), 10, 3 );

		add_action( 'admin_init', array( $this, 'register_post_type_hooks' ) );
		add_action( 'admin_init', array( $this, 'maybe_export' ) );
	}

	/**
	 * Bulk Download action on post types and taxonomies.
	 */
	public function register_post_type_hooks() {
		foreach ( get_post_types( array( 'show_ui' => true ), 'names' ) as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'register_bulk' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_posts_bulk' ), 10, 3 );
		}

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $taxonomy ) {
			add_filter( 'bulk_actions-edit-' . $taxonomy, array( $this, 'register_bulk' ) );
			add_filter( 'handle_bulk_actions-edit-' . $taxonomy, array( $this, 'handle_terms_bulk' ), 10, 3 );
		}
	}

	/**
	 * CSV export types (row + bulk).
	 *
	 * @return string[]
	 */
	public static function export_types() {
		return array( 'user', 'post', 'page', 'comment', 'term' );
	}

	/**
	 * Capability required for every CSV export.
	 *
	 * @param bool|null $is_multisite Override for tests. Null uses is_multisite().
	 * @return string
	 */
	public static function required_export_capability( $is_multisite = null ) {
		if ( null === $is_multisite ) {
			$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
		}

		return $is_multisite ? 'manage_network' : 'manage_options';
	}

	/**
	 * Whether a user with the given caps may export $type.
	 *
	 * Editors (edit_posts, moderate_comments, manage_categories, …) are denied.
	 *
	 * @param string $type         Export type.
	 * @param bool   $is_multisite Whether the site is a network.
	 * @param array  $user_caps    Map of cap => bool.
	 * @return bool
	 */
	public static function export_allowed_for_caps( $type, $is_multisite, $user_caps ) {
		if ( ! self::type_is_exportable( $type ) || ! is_array( $user_caps ) ) {
			return false;
		}

		$needed = self::required_export_capability( (bool) $is_multisite );

		return ! empty( $user_caps[ $needed ] );
	}

	/**
	 * Whether $type is a supported CSV export.
	 *
	 * @param string $type Type key.
	 * @return bool
	 */
	public static function type_is_exportable( $type ) {
		return in_array( (string) $type, self::export_types(), true );
	}

	/**
	 * Whether the current user may export any CSV (UI and handlers).
	 *
	 * @return bool
	 */
	public static function user_can_export() {
		return current_user_can( self::required_export_capability() );
	}

	/**
	 * Register the bulk Download action.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function register_bulk( $actions ) {
		if ( ! self::user_can_export() ) {
			return $actions;
		}

		$actions['oli_dlp_export'] = __( 'Download', 'oli-download-plugin' );
		return $actions;
	}

	/**
	 * Download link on a user row.
	 *
	 * @param string[] $actions Actions.
	 * @param WP_User  $user    User.
	 * @return string[]
	 */
	public function user_row_actions( $actions, $user ) {
		if ( ! self::user_can_export() || ! $user instanceof WP_User ) {
			return $actions;
		}

		$actions['oli_dlp_download'] = $this->action_link(
			array(
				'oli_dlp_export' => 'user',
				'id'             => $user->ID,
			),
			'oli_dlp_export_user_' . $user->ID
		);

		return $actions;
	}

	/**
	 * Download link on a post/page/CPT row.
	 *
	 * @param string[] $actions Actions.
	 * @param WP_Post  $post    Post.
	 * @return string[]
	 */
	public function post_row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || ! self::user_can_export() ) {
			return $actions;
		}

		$actions['oli_dlp_download'] = $this->action_link(
			array(
				'oli_dlp_export' => 'post',
				'id'             => $post->ID,
			),
			'oli_dlp_export_post_' . $post->ID
		);

		return $actions;
	}

	/**
	 * Download link on a comment row.
	 *
	 * @param string[]   $actions Actions.
	 * @param WP_Comment $comment Comment.
	 * @return string[]
	 */
	public function comment_row_actions( $actions, $comment ) {
		if ( ! self::user_can_export() || ! $comment instanceof WP_Comment ) {
			return $actions;
		}

		$actions['oli_dlp_download'] = $this->action_link(
			array(
				'oli_dlp_export' => 'comment',
				'id'             => $comment->comment_ID,
			),
			'oli_dlp_export_comment_' . $comment->comment_ID
		);

		return $actions;
	}

	/**
	 * Download link on a term row.
	 *
	 * @param string[] $actions Actions.
	 * @param WP_Term  $term    Term.
	 * @return string[]
	 */
	public function term_row_actions( $actions, $term ) {
		if ( ! self::user_can_export() || ! $term instanceof WP_Term ) {
			return $actions;
		}

		$actions['oli_dlp_download'] = $this->action_link(
			array(
				'oli_dlp_export' => 'term',
				'id'             => $term->term_id,
				'taxonomy'       => $term->taxonomy,
			),
			'oli_dlp_export_term_' . $term->term_id
		);

		return $actions;
	}

	/**
	 * Handle single-item CSV requests.
	 */
	public function maybe_export() {
		if ( ! isset( $_GET['oli_dlp_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
			return;
		}

		$type = sanitize_key( wp_unslash( $_GET['oli_dlp_export'] ) );
		$id   = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		if ( ! self::type_is_exportable( $type ) ) {
			return;
		}

		switch ( $type ) {
			case 'user':
				check_admin_referer( 'oli_dlp_export_user_' . $id );
				$this->deny_if_cannot_export();
				$this->stream_users( array( $id ), 'user-' . $id . '.csv' );
				break;
			case 'page':
			case 'post':
				check_admin_referer( 'oli_dlp_export_post_' . $id );
				$this->deny_if_cannot_export();
				$this->stream_posts( array( $id ), 'post-' . $id . '.csv' );
				break;
			case 'comment':
				check_admin_referer( 'oli_dlp_export_comment_' . $id );
				$this->deny_if_cannot_export();
				$this->stream_comments( array( $id ), 'comment-' . $id . '.csv' );
				break;
			case 'term':
				check_admin_referer( 'oli_dlp_export_term_' . $id );
				$this->deny_if_cannot_export();
				$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
				$this->stream_terms( array( $id ), $taxonomy, 'term-' . $id . '.csv' );
				break;
		}
	}

	/**
	 * Bulk users.
	 *
	 * @param string $sendback Send-back URL.
	 * @param string $doaction Action.
	 * @param int[]  $ids      IDs.
	 * @return string
	 */
	public function handle_users_bulk( $sendback, $doaction, $ids ) {
		if ( 'oli_dlp_export' !== $doaction ) {
			return $sendback;
		}

		$this->deny_if_cannot_export();

		if ( empty( $ids ) ) {
			return add_query_arg( 'oli_dlp_bulk_empty', '1', $sendback );
		}

		$this->stream_users( array_map( 'absint', $ids ), 'users.csv' );
		return $sendback;
	}

	/**
	 * Bulk posts.
	 *
	 * @param string $sendback Send-back URL.
	 * @param string $doaction Action.
	 * @param int[]  $ids      IDs.
	 * @return string
	 */
	public function handle_posts_bulk( $sendback, $doaction, $ids ) {
		if ( 'oli_dlp_export' !== $doaction ) {
			return $sendback;
		}

		$this->deny_if_cannot_export();

		$allowed = array();
		foreach ( array_map( 'absint', $ids ) as $id ) {
			if ( $id ) {
				$allowed[] = $id;
			}
		}

		if ( empty( $allowed ) ) {
			return add_query_arg( 'oli_dlp_bulk_empty', '1', $sendback );
		}

		$this->stream_posts( $allowed, 'posts.csv' );
		return $sendback;
	}

	/**
	 * Bulk comments.
	 *
	 * @param string $sendback Send-back URL.
	 * @param string $doaction Action.
	 * @param int[]  $ids      IDs.
	 * @return string
	 */
	public function handle_comments_bulk( $sendback, $doaction, $ids ) {
		if ( 'oli_dlp_export' !== $doaction ) {
			return $sendback;
		}

		$this->deny_if_cannot_export();

		if ( empty( $ids ) ) {
			return add_query_arg( 'oli_dlp_bulk_empty', '1', $sendback );
		}

		$this->stream_comments( array_map( 'absint', $ids ), 'comments.csv' );
		return $sendback;
	}

	/**
	 * Bulk terms.
	 *
	 * @param string $sendback Send-back URL.
	 * @param string $doaction Action.
	 * @param int[]  $ids      IDs.
	 * @return string
	 */
	public function handle_terms_bulk( $sendback, $doaction, $ids ) {
		if ( 'oli_dlp_export' !== $doaction ) {
			return $sendback;
		}

		$this->deny_if_cannot_export();

		if ( empty( $ids ) ) {
			return add_query_arg( 'oli_dlp_bulk_empty', '1', $sendback );
		}

		$taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- bulk handler already verified.
		$this->stream_terms( array_map( 'absint', $ids ), $taxonomy, 'terms.csv' );
		return $sendback;
	}

	/**
	 * Abort with 403 unless the user may export CSV.
	 */
	private function deny_if_cannot_export() {
		if ( self::user_can_export() ) {
			return;
		}

		wp_die( esc_html__( 'You do not have permission to export this data.', 'oli-download-plugin' ), '', array( 'response' => 403 ) );
	}

	/**
	 * Build a row-action anchor.
	 *
	 * @param array  $args  Query args.
	 * @param string $nonce Nonce action.
	 * @return string
	 */
	private function action_link( $args, $nonce ) {
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), $nonce );

		return '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Download', 'oli-download-plugin' ) . '</a>';
	}

	/**
	 * Stream user CSV (no password hashes).
	 *
	 * @param int[]  $ids      User IDs.
	 * @param string $filename Filename.
	 */
	private function stream_users( $ids, $filename ) {
		$rows = array();
		foreach ( $ids as $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}
			$rows[] = array(
				'ID'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'display_name'    => $user->display_name,
				'roles'           => implode( ',', $user->roles ),
				'user_registered' => $user->user_registered,
			);
		}

		$this->stream_csv(
			$filename,
			array( 'ID', 'user_login', 'user_email', 'display_name', 'roles', 'user_registered' ),
			$rows
		);
	}

	/**
	 * Stream post CSV.
	 *
	 * @param int[]  $ids      Post IDs.
	 * @param string $filename Filename.
	 */
	private function stream_posts( $ids, $filename ) {
		$rows = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$rows[] = array(
				'ID'           => $post->ID,
				'post_type'    => $post->post_type,
				'post_status'  => $post->post_status,
				'post_title'   => $post->post_title,
				'post_name'    => $post->post_name,
				'post_date'    => $post->post_date,
				'post_author'  => $post->post_author,
				'post_excerpt' => $post->post_excerpt,
				'post_content' => $post->post_content,
			);
		}

		$this->stream_csv(
			$filename,
			array( 'ID', 'post_type', 'post_status', 'post_title', 'post_name', 'post_date', 'post_author', 'post_excerpt', 'post_content' ),
			$rows
		);
	}

	/**
	 * Comment CSV columns. Author email and IP are never exported.
	 *
	 * @return string[]
	 */
	public static function comment_csv_headers() {
		return array(
			'comment_ID',
			'comment_post_ID',
			'comment_author',
			'comment_date',
			'comment_approved',
			'comment_content',
		);
	}

	/**
	 * Fields that must never appear in a comment CSV, including for administrators.
	 *
	 * @return string[]
	 */
	public static function comment_csv_forbidden_fields() {
		return array(
			'comment_author_email',
			'comment_author_IP',
			'comment_author_ip',
			'user_email',
		);
	}

	/**
	 * Map a comment object to a CSV row without email or IP.
	 *
	 * @param object|array $comment Comment data.
	 * @return array<string, mixed>
	 */
	public static function comment_csv_row( $comment ) {
		$row = array();
		foreach ( self::comment_csv_headers() as $key ) {
			if ( is_object( $comment ) && isset( $comment->{$key} ) ) {
				$row[ $key ] = $comment->{$key};
			} elseif ( is_array( $comment ) && isset( $comment[ $key ] ) ) {
				$row[ $key ] = $comment[ $key ];
			} else {
				$row[ $key ] = '';
			}
		}

		foreach ( self::comment_csv_forbidden_fields() as $forbidden ) {
			unset( $row[ $forbidden ] );
		}

		return $row;
	}

	/**
	 * Stream comment CSV.
	 *
	 * @param int[]  $ids      Comment IDs.
	 * @param string $filename Filename.
	 */
	private function stream_comments( $ids, $filename ) {
		$headers = self::comment_csv_headers();
		$rows    = array();
		foreach ( $ids as $id ) {
			$comment = get_comment( $id );
			if ( ! $comment ) {
				continue;
			}
			$rows[] = self::comment_csv_row( $comment );
		}

		$this->stream_csv( $filename, $headers, $rows );
	}

	/**
	 * Stream term CSV.
	 *
	 * @param int[]  $ids      Term IDs.
	 * @param string $taxonomy Taxonomy.
	 * @param string $filename Filename.
	 */
	private function stream_terms( $ids, $taxonomy, $filename ) {
		$rows = array();
		foreach ( $ids as $id ) {
			$term = get_term( $id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$rows[] = array(
				'term_id'  => $term->term_id,
				'taxonomy' => $term->taxonomy,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => $term->parent,
				'count'    => $term->count,
			);
		}

		$this->stream_csv(
			$filename,
			array( 'term_id', 'taxonomy', 'name', 'slug', 'parent', 'count' ),
			$rows
		);
	}

	/**
	 * Send a CSV download and exit.
	 *
	 * @param string $filename Filename.
	 * @param array  $headers  Column names.
	 * @param array  $rows     Associative rows.
	 */
	private function stream_csv( $filename, $headers, $rows ) {
		$filename = sanitize_file_name( $filename );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to create CSV file.', 'oli-download-plugin' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV stream.
		fwrite( $output, "\xEF\xBB\xBF" );
		Oli_DLP_Csv::putcsv( $output, $headers );

		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $key ) {
				$line[] = isset( $row[ $key ] ) ? $row[ $key ] : '';
			}
			Oli_DLP_Csv::putcsv( $output, $line );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV stream.
		fclose( $output );
		exit;
	}
}
