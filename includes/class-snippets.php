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
	 * Transient key for cached snippets
	 *
	 * @var string
	 */
	const CACHE_KEY = 'acspm_snippets_all';

	/**
	 * In-memory cache for "everywhere" snippets within a single request
	 *
	 * @var array|null
	 */
	private $everywhere_snippets_cache = null;

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
	 * Get all active snippets from cache or database
	 *
	 * Uses a transient to avoid repeated DB queries on every page load.
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
			'post_status'    => 'any',
		);

		$query_args = wp_parse_args( $args, $defaults );

		// For the standard "get all published" query, use the transient cache
		$use_cache = (
			isset( $query_args['post_status'] ) &&
			'publish' === $query_args['post_status'] &&
			! empty( $query_args['meta_query'] )
		);

		if ( $use_cache ) {
			$cache_key = self::CACHE_KEY . '_' . md5( wp_json_encode( $query_args ) );
			$cached    = get_transient( $cache_key );

			if ( false !== $cached ) {
				return $cached;
			}
		}

		$posts = get_posts( $query_args );

		// Prime the meta cache for all posts in one query (eliminates N+1)
		if ( ! empty( $posts ) ) {
			$post_ids = wp_list_pluck( $posts, 'ID' );
			update_meta_cache( 'post', $post_ids );
		}

		$snippets = array();
		foreach ( $posts as $post ) {
			$snippets[] = $this->format_snippet( $post );
		}

		// Sort by priority
		usort(
			$snippets,
			function ( $a, $b ) {
				return $a['priority'] - $b['priority'];
			}
		);

		if ( $use_cache ) {
			set_transient( $cache_key, $snippets, HOUR_IN_SECONDS );
		}

		return $snippets;
	}

	/**
	 * Clear the snippet cache
	 *
	 * Called whenever snippets are created, updated, deleted, or toggled.
	 */
	public function clear_cache() {
		global $wpdb;

		// Delete all acspm transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_acspm_snippets_%',
				'_transient_timeout_acspm_snippets_%'
			)
		);

		// Clear in-memory cache
		$this->everywhere_snippets_cache = null;
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
	 * @param array $data       Snippet data.
	 */
	private function update_snippet_meta( $snippet_id, $data ) {
		if ( isset( $data['code'] ) ) {
			update_post_meta( $snippet_id, '_acspm_code', wp_unslash( $data['code'] ) );
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
	 * Get cached "everywhere" snippets for the current request
	 *
	 * Eliminates duplicate queries for "everywhere" snippets that are
	 * fetched multiple times per page load (head, footer, init).
	 *
	 * @return array Everywhere snippets.
	 */
	private function get_everywhere_snippets() {
		if ( null === $this->everywhere_snippets_cache ) {
			$this->everywhere_snippets_cache = $this->get_snippets(
				array(
					'post_status' => 'publish',
					'meta_query'  => array(
						array(
							'key'   => '_acspm_location',
							'value' => 'everywhere',
						),
					),
				)
			);
		}

		return $this->everywhere_snippets_cache;
	}

	/**
	 * Get active snippets for a specific location
	 *
	 * @param string $location Location (wp_head, wp_footer, custom).
	 * @return array Active snippets for the location.
	 */
	private function get_active_snippets_for_location( $location ) {
		return $this->get_snippets(
			array(
				'post_status' => 'publish',
				'meta_query'  => array(
					array(
						'key'   => '_acspm_location',
						'value' => $location,
					),
				),
			)
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
	 * Uses in-memory cache to avoid re-querying for each hook.
	 *
	 * @param string $position Either 'head' or 'footer'.
	 */
	private function execute_everywhere_output_snippets( $position ) {
		$snippets = $this->get_everywhere_snippets();

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
		$snippets = $this->get_everywhere_snippets();

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
		$snippets = $this->get_snippets(
			array(
				'post_status' => 'publish',
				'meta_query'  => array(
					array(
						'key'   => '_acspm_location',
						'value' => 'custom',
					),
				),
			)
		);

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

		if ( empty( trim( $snippet['code'] ) ) ) {
			return;
		}

		switch ( $snippet['code_type'] ) {
			case 'php':
				$this->execute_php_snippet( $snippet );
				break;

			case 'js':
				// Output JavaScript (raw — only admins can create snippets)
				echo '<script>' . "\n" . $snippet['code'] . "\n" . '</script>' . "\n";
				break;

			case 'css':
				// Output CSS (raw — only admins can create snippets)
				echo '<style>' . "\n" . $snippet['code'] . "\n" . '</style>' . "\n";
				break;
		}
	}

	/**
	 * Execute a PHP snippet safely
	 *
	 * Uses a temp file instead of eval() so that <?php and ?> tags work
	 * properly inside callbacks (e.g., for outputting HTML within hooks).
	 *
	 * @param array $snippet Snippet data.
	 */
	private function execute_php_snippet( $snippet ) {
		// Create a unique temp file with uniqid to prevent race conditions
		$temp_file = wp_tempnam( 'acspm_' . $snippet['id'] . '_' . uniqid( '', true ) );

		if ( ! $temp_file ) {
			$this->log_snippet_error( $snippet['name'], 'Could not create temp file' );
			return;
		}

		// Write the PHP code to the temp file
		// Wrap in <?php tag so the file is valid PHP
		$code = '<?php' . "\n" . $snippet['code'];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $temp_file, $code );

		if ( false === $written ) {
			wp_delete_file( $temp_file );
			$this->log_snippet_error( $snippet['name'], 'Could not write temp file' );
			return;
		}

		// Execute the temp file
		try {
			include $temp_file;
		} catch ( Throwable $e ) {
			$this->log_snippet_error( $snippet['name'], $e->getMessage() );
		}

		// Clean up the temp file
		wp_delete_file( $temp_file );
	}

	/**
	 * Log a snippet error
	 *
	 * Always logs to error_log. Also outputs HTML comment when WP_DEBUG is on.
	 *
	 * @param string $snippet_name Snippet name.
	 * @param string $message      Error message.
	 */
	private function log_snippet_error( $snippet_name, $message ) {
		// Always log to PHP error log
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'ACSPM Snippet Error (' . $snippet_name . '): ' . $message );

		// Also output HTML comment when debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			echo '<!-- ACSPM Snippet Error (' . esc_html( $snippet_name ) . '): ' . esc_html( $message ) . ' -->';
		}
	}
}
