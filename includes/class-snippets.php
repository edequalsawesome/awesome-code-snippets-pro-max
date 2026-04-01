<?php
/**
 * Code Snippets functionality
 *
 * @package Awesome_Code_Snippets_Pro_Max
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snippets management class
 */
class ACSPM_Snippets {

	/**
	 * Singleton instance
	 *
	 * @var ACSPM_Snippets
	 */
	private static $instance = null;

	/**
	 * Custom post type name
	 *
	 * @var string
	 */
	const POST_TYPE = 'acspm_snippet';

	/**
	 * Transient key for cached active snippets
	 *
	 * @var string
	 */
	const CACHE_KEY = 'acspm_active_snippets';

	/**
	 * In-memory cache for active snippets within a single request
	 *
	 * @var array|null
	 */
	private $active_snippets_cache = null;

	/**
	 * Get singleton instance
	 *
	 * @return ACSPM_Snippets
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Frontend hooks
		add_action( 'wp_head', array( $this, 'execute_head_snippets' ), 1 );
		add_action( 'wp_footer', array( $this, 'execute_footer_snippets' ), 999 );

		// Admin hooks (for "everywhere" JS/CSS)
		add_action( 'admin_head', array( $this, 'execute_admin_head_snippets' ), 1 );
		add_action( 'admin_footer', array( $this, 'execute_admin_footer_snippets' ), 999 );

		// Custom hooks and "everywhere" PHP
		add_action( 'init', array( $this, 'execute_custom_hook_snippets' ), 20 );
		add_action( 'init', array( $this, 'execute_everywhere_snippets' ), 20 );
	}

	/**
	 * Register the custom post type for snippets
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Code Snippets', 'post type general name', 'awesome-code-snippets-pro-max' ),
			'singular_name'      => _x( 'Code Snippet', 'post type singular name', 'awesome-code-snippets-pro-max' ),
			'add_new_item'       => __( 'Add New Snippet', 'awesome-code-snippets-pro-max' ),
			'edit_item'          => __( 'Edit Snippet', 'awesome-code-snippets-pro-max' ),
			'new_item'           => __( 'New Snippet', 'awesome-code-snippets-pro-max' ),
			'view_item'          => __( 'View Snippet', 'awesome-code-snippets-pro-max' ),
			'search_items'       => __( 'Search Snippets', 'awesome-code-snippets-pro-max' ),
			'not_found'          => __( 'No snippets found', 'awesome-code-snippets-pro-max' ),
			'not_found_in_trash' => __( 'No snippets found in Trash', 'awesome-code-snippets-pro-max' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get all active (published) snippets from cache
	 *
	 * Uses a single transient for all active snippets. Location filtering
	 * happens in PHP — the dataset is small (typically <100 snippets) so
	 * one query + filter is faster than multiple cached DB queries.
	 *
	 * @return array Array of active snippet objects.
	 */
	public function get_all_active_snippets() {
		if ( null !== $this->active_snippets_cache ) {
			return $this->active_snippets_cache;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			$this->active_snippets_cache = $cached;
			return $cached;
		}

		$snippets = $this->get_snippets();

		set_transient( self::CACHE_KEY, $snippets, HOUR_IN_SECONDS );
		$this->active_snippets_cache = $snippets;

		return $snippets;
	}

	/**
	 * Get snippets from database
	 *
	 * Direct database query with no caching layer. Used for the admin listing
	 * (with post_status=any) and as the backing query for get_all_active_snippets().
	 *
	 * Primes the post meta cache to eliminate N+1 queries.
	 *
	 * @param array $args Optional query arguments.
	 * @return array Array of snippet objects.
	 */
	public function get_snippets( $args = array() ) {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$query_args = wp_parse_args( $args, $defaults );
		$posts      = get_posts( $query_args );

		// Prime the meta cache for all posts in one query (eliminates N+1)
		if ( ! empty( $posts ) ) {
			$post_ids = wp_list_pluck( $posts, 'ID' );
			update_meta_cache( 'post', $post_ids );
		}

		$snippets = array();
		foreach ( $posts as $post ) {
			$snippets[] = $this->format_snippet( $post );
		}

		return $snippets;
	}

	/**
	 * Clear the snippet cache
	 *
	 * Called whenever snippets are created, updated, deleted, or toggled.
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
		$this->active_snippets_cache = null;
		$this->clean_php_cache();
	}

	/**
	 * Get a single snippet by ID
	 *
	 * @param int $snippet_id Snippet post ID.
	 * @return array|false Snippet data or false if not found.
	 */
	public function get_snippet( $snippet_id ) {
		$post = get_post( $snippet_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return $this->format_snippet( $post );
	}

	/**
	 * Format a snippet post into a usable array
	 *
	 * Meta values are already primed by update_meta_cache() in get_snippets(),
	 * so individual get_post_meta() calls hit the object cache, not the DB.
	 *
	 * @param WP_Post $post The snippet post object.
	 * @return array Formatted snippet data.
	 */
	private function format_snippet( $post ) {
		$priority_raw = get_post_meta( $post->ID, '_acspm_priority', true );

		return array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'code'        => get_post_meta( $post->ID, '_acspm_code', true ),
			'code_type'   => get_post_meta( $post->ID, '_acspm_code_type', true ) ?: 'php',
			'location'    => get_post_meta( $post->ID, '_acspm_location', true ) ?: 'wp_head',
			'custom_hook' => get_post_meta( $post->ID, '_acspm_custom_hook', true ),
			'priority'    => '' !== $priority_raw ? max( 1, min( 999, (int) $priority_raw ) ) : 10,
			'active'      => 'publish' === $post->post_status,
		);
	}

	/**
	 * Create a new snippet
	 *
	 * @param array $data Snippet data.
	 * @return int|WP_Error Snippet ID on success, WP_Error on failure.
	 */
	public function create_snippet( $data ) {
		$priority  = isset( $data['priority'] ) ? max( 1, min( 999, (int) $data['priority'] ) ) : 10;
		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => sanitize_text_field( $data['name'] ),
			'post_status' => ! empty( $data['active'] ) ? 'publish' : 'draft',
			'menu_order'  => $priority,
		);

		$snippet_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $snippet_id ) ) {
			return $snippet_id;
		}

		$this->update_snippet_meta( $snippet_id, $data );
		$this->clear_cache();

		return $snippet_id;
	}

	/**
	 * Update an existing snippet
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $data       Snippet data.
	 * @return int|WP_Error Snippet ID on success, WP_Error on failure.
	 */
	public function update_snippet( $snippet_id, $data ) {
		$priority  = isset( $data['priority'] ) ? max( 1, min( 999, (int) $data['priority'] ) ) : 10;
		$post_data = array(
			'ID'          => $snippet_id,
			'post_title'  => sanitize_text_field( $data['name'] ),
			'post_status' => ! empty( $data['active'] ) ? 'publish' : 'draft',
			'menu_order'  => $priority,
		);

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_snippet_meta( $snippet_id, $data );
		$this->clear_cache();

		return $snippet_id;
	}

	/**
	 * Update snippet meta fields
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $data       Snippet data (already wp_unslash'd by caller).
	 */
	private function update_snippet_meta( $snippet_id, $data ) {
		if ( isset( $data['code'] ) ) {
			update_post_meta( $snippet_id, '_acspm_code', $data['code'] );
		}

		if ( isset( $data['code_type'] ) ) {
			$allowed_types = array( 'php', 'js', 'css' );
			$code_type     = in_array( $data['code_type'], $allowed_types, true ) ? $data['code_type'] : 'php';
			update_post_meta( $snippet_id, '_acspm_code_type', $code_type );
		}

		if ( isset( $data['location'] ) ) {
			$allowed_locations = array( 'wp_head', 'wp_footer', 'everywhere', 'custom' );
			$location          = in_array( $data['location'], $allowed_locations, true ) ? $data['location'] : 'wp_head';
			update_post_meta( $snippet_id, '_acspm_location', $location );
		}

		if ( isset( $data['custom_hook'] ) ) {
			$hook_name = sanitize_text_field( $data['custom_hook'] );
			// Validate hook name: only allow valid PHP function/hook characters
			if ( ! empty( $hook_name ) && ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_\/\-]*$/', $hook_name ) ) {
				$hook_name = '';
			}
			update_post_meta( $snippet_id, '_acspm_custom_hook', $hook_name );
		}

		if ( isset( $data['priority'] ) ) {
			$priority = max( 1, min( 999, (int) $data['priority'] ) );
			update_post_meta( $snippet_id, '_acspm_priority', $priority );
		}
	}

	/**
	 * Delete a snippet
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_snippet( $snippet_id ) {
		$post = get_post( $snippet_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$result = (bool) wp_delete_post( $snippet_id, true );

		if ( $result ) {
			$this->clear_cache();
		}

		return $result;
	}

	/**
	 * Toggle snippet active status
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool New active status.
	 */
	public function toggle_snippet( $snippet_id ) {
		$post = get_post( $snippet_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$new_status = 'publish' === $post->post_status ? 'draft' : 'publish';
		wp_update_post(
			array(
				'ID'          => $snippet_id,
				'post_status' => $new_status,
			)
		);

		$this->clear_cache();

		return 'publish' === $new_status;
	}

	/**
	 * Get active snippets filtered by location
	 *
	 * Filters from the single cached set of all active snippets.
	 *
	 * @param string $location Location (wp_head, wp_footer, everywhere, custom).
	 * @return array Active snippets for the location.
	 */
	private function get_active_snippets_for_location( $location ) {
		return array_filter(
			$this->get_all_active_snippets(),
			function ( $snippet ) use ( $location ) {
				return $snippet['location'] === $location;
			}
		);
	}

	/**
	 * Execute snippets for wp_head (frontend)
	 */
	public function execute_head_snippets() {
		$this->execute_snippets_for_location( 'wp_head' );
		$this->execute_everywhere_output_snippets( 'head' );
	}

	/**
	 * Execute snippets for wp_footer (frontend)
	 */
	public function execute_footer_snippets() {
		$this->execute_snippets_for_location( 'wp_footer' );
		$this->execute_everywhere_output_snippets( 'footer' );
	}

	/**
	 * Execute snippets for admin_head
	 */
	public function execute_admin_head_snippets() {
		$this->execute_everywhere_output_snippets( 'head' );
	}

	/**
	 * Execute snippets for admin_footer
	 */
	public function execute_admin_footer_snippets() {
		$this->execute_everywhere_output_snippets( 'footer' );
	}

	/**
	 * Execute "everywhere" JS/CSS snippets for head or footer
	 *
	 * @param string $position Either 'head' or 'footer'.
	 */
	private function execute_everywhere_output_snippets( $position ) {
		$snippets = $this->get_active_snippets_for_location( 'everywhere' );

		foreach ( $snippets as $snippet ) {
			// Only JS and CSS get output in head/footer
			if ( ! in_array( $snippet['code_type'], array( 'js', 'css' ), true ) ) {
				continue;
			}

			// CSS goes in head, JS can go in either (default to footer for performance)
			if ( 'css' === $snippet['code_type'] && 'head' === $position ) {
				$this->execute_snippet( $snippet );
			} elseif ( 'js' === $snippet['code_type'] && 'footer' === $position ) {
				$this->execute_snippet( $snippet );
			}
		}
	}

	/**
	 * Execute "everywhere" PHP snippets on init
	 */
	public function execute_everywhere_snippets() {
		$snippets = $this->get_active_snippets_for_location( 'everywhere' );

		foreach ( $snippets as $snippet ) {
			// Only PHP runs on init
			if ( 'php' !== $snippet['code_type'] ) {
				continue;
			}
			$this->execute_snippet( $snippet );
		}
	}

	/**
	 * Execute snippets for custom hooks
	 */
	public function execute_custom_hook_snippets() {
		$snippets = $this->get_active_snippets_for_location( 'custom' );

		foreach ( $snippets as $snippet ) {
			if ( ! empty( $snippet['custom_hook'] ) ) {
				add_action(
					$snippet['custom_hook'],
					function () use ( $snippet ) {
						$this->execute_snippet( $snippet );
					},
					$snippet['priority']
				);
			}
		}
	}

	/**
	 * Execute snippets for a specific location
	 *
	 * @param string $location Location (wp_head, wp_footer).
	 */
	private function execute_snippets_for_location( $location ) {
		$snippets = $this->get_active_snippets_for_location( $location );

		foreach ( $snippets as $snippet ) {
			$this->execute_snippet( $snippet );
		}
	}

	/**
	 * Execute a single snippet
	 *
	 * JS and CSS are output as raw inline code. This is intentional — escaping
	 * would break the code. Only administrators can create snippets.
	 *
	 * @param array $snippet Snippet data.
	 */
	private function execute_snippet( $snippet ) {
		// Safe mode blocks all snippet execution
		if ( function_exists( 'acspm_is_safe_mode' ) && acspm_is_safe_mode() ) {
			return;
		}

		if ( '' === trim( $snippet['code'] ) ) {
			return;
		}

		switch ( $snippet['code_type'] ) {
			case 'php':
				$this->execute_php_snippet( $snippet );
				break;

			case 'js':
				// Escape closing script tag to prevent premature HTML tag closure (case-insensitive)
				$safe_code = preg_replace( '~</script\b~i', '<\/script', $snippet['code'] );
				echo '<script>' . "\n" . $safe_code . "\n" . '</script>' . "\n";
				break;

			case 'css':
				// Escape closing style tag to prevent premature HTML tag closure (case-insensitive)
				$safe_code = preg_replace( '~</style\b~i', '<\/style', $snippet['code'] );
				echo '<style>' . "\n" . $safe_code . "\n" . '</style>' . "\n";
				break;
		}
	}

	/**
	 * Execute a PHP snippet safely
	 *
	 * Uses cached temp files so each snippet is written to disk only when
	 * its code changes, not on every page load. Files are stored in
	 * wp-content/uploads/acspm-cache/ with restrictive permissions.
	 *
	 * @param array $snippet Snippet data.
	 */
	private function execute_php_snippet( $snippet ) {
		// Strip leading <?php tag if user included one (common mistake)
		$snippet_code = preg_replace( '/^\s*<\?php\b/', '', $snippet['code'] );

		if ( '' === trim( $snippet_code ) ) {
			return;
		}

		$cache_dir = $this->ensure_php_cache_dir();
		if ( ! $cache_dir ) {
			$this->log_snippet_error( $snippet['name'], 'Could not create PHP cache directory' );
			return;
		}

		$code_hash  = md5( $snippet_code );
		$cache_file = $cache_dir . '/snippet-' . $snippet['id'] . '-' . $code_hash . '.php';

		if ( ! file_exists( $cache_file ) ) {
			$code = "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n" . $snippet_code;
			$tmp_file = $cache_file . '.tmp';

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$written = file_put_contents( $tmp_file, $code, LOCK_EX );

			if ( false === $written ) {
				$this->log_snippet_error( $snippet['name'], 'Could not write cache file' );
				return;
			}

			chmod( $tmp_file, 0600 );

			// Atomic rename — no window where include sees a missing file
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( ! rename( $tmp_file, $cache_file ) ) {
				wp_delete_file( $tmp_file );
				$this->log_snippet_error( $snippet['name'], 'Could not rename cache file' );
				return;
			}

			// Clean up old cache files for this snippet ID (exclude current)
			$old_files = glob( $cache_dir . '/snippet-' . $snippet['id'] . '-*.php' );
			if ( $old_files ) {
				foreach ( $old_files as $old_file ) {
					if ( $old_file !== $cache_file ) {
						wp_delete_file( $old_file );
					}
				}
			}
		}

		try {
			include $cache_file;
		} catch ( Throwable $e ) {
			$this->log_snippet_error( $snippet['name'], $e->getMessage() );
		}
	}

	/**
	 * Get the PHP snippet cache directory path
	 *
	 * @return string Cache directory path.
	 */
	private function get_php_cache_dir() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/acspm-cache';
	}

	/**
	 * Ensure the PHP cache directory exists with proper protection
	 *
	 * @return string|false Cache directory path, or false on failure.
	 */
	private function ensure_php_cache_dir() {
		$cache_dir = $this->get_php_cache_dir();

		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );

			// Prevent direct web access to cached PHP files
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $cache_dir . '/.htaccess', "Deny from all\n" );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $cache_dir . '/index.php', "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n// Silence is golden.\n" );
		}

		return is_dir( $cache_dir ) ? $cache_dir : false;
	}

	/**
	 * Clean all cached PHP snippet files
	 */
	private function clean_php_cache() {
		$cache_dir = $this->get_php_cache_dir();

		if ( is_dir( $cache_dir ) ) {
			$files = glob( $cache_dir . '/snippet-*.php' );
			if ( $files ) {
				foreach ( $files as $file ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Log a snippet error
	 *
	 * Always logs to error_log. Also outputs HTML comment for logged-in admins
	 * when WP_DEBUG is on.
	 *
	 * @param string $snippet_name Snippet name.
	 * @param string $message      Error message.
	 */
	private function log_snippet_error( $snippet_name, $message ) {
		// Always log to PHP error log
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'ACSPM Snippet Error (' . $snippet_name . '): ' . $message );

		// Only output debug info to logged-in admins
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
			echo '<!-- ACSPM Snippet Error (' . esc_html( $snippet_name ) . '): ' . esc_html( $message ) . ' -->';
		}
	}
}
