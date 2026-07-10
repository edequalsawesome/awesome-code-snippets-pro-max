<?php
/**
 * Uninstall handler for Awesome Code Snippets Pro Max
 *
 * Removes all plugin data when deleted via the WordPress admin.
 *
 * @package Awesome_Code_Snippets_Pro_Max
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all snippet posts and their meta in batches to avoid timeout.
$batch_size = 100;
do {
	$snippets = get_posts(
		array(
			'post_type'      => 'acspm_snippet',
			'posts_per_page' => $batch_size,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);

	$deleted = 0;
	foreach ( $snippets as $snippet_id ) {
		if ( wp_delete_post( $snippet_id, true ) ) {
			$deleted++;
		}
	}
	// Stop if a full batch made no progress (e.g. another plugin is
	// short-circuiting deletion) so uninstall can't spin until timeout.
} while ( count( $snippets ) === $batch_size && $deleted > 0 );

// Delete options.
delete_option( 'acspm_header_code' );
delete_option( 'acspm_footer_code' );
delete_option( 'acspm_db_version' );

// Delete known transients via API first (handles external object caches like Redis/Memcached).
delete_transient( 'acspm_active_snippets' );

// Delete all cached transients from the DB (wildcard cleanup for any remaining DB-backed rows).
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_acspm_%',
		'_transient_timeout_acspm_%'
	)
);

// Clean up PHP snippet cache directory (current and legacy locations).
$cache_dirs = array(
	WP_CONTENT_DIR . '/cache/acspm',
);
$upload_dir = wp_upload_dir();
$cache_dirs[] = $upload_dir['basedir'] . '/acspm-cache';

foreach ( $cache_dirs as $cache_dir ) {
	if ( is_dir( $cache_dir ) ) {
		$files = array_merge(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
			glob( $cache_dir . '/*' ) ?: array(),
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
			glob( $cache_dir . '/.*' ) ?: array()
		);
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( in_array( basename( $file ), array( '.', '..' ), true ) ) {
					continue;
				}
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $cache_dir );
	}
}
