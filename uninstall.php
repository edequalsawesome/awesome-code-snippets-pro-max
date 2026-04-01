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

	foreach ( $snippets as $snippet_id ) {
		wp_delete_post( $snippet_id, true );
	}
} while ( count( $snippets ) === $batch_size );

// Delete options.
delete_option( 'acspm_header_code' );
delete_option( 'acspm_footer_code' );

// Delete all cached transients (wildcard cleanup matching clear_cache pattern).
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_acspm_%',
		'_transient_timeout_acspm_%'
	)
);

// Clean up PHP snippet cache directory.
$upload_dir = wp_upload_dir();
$cache_dir  = $upload_dir['basedir'] . '/acspm-cache';

if ( is_dir( $cache_dir ) ) {
	$files = glob( $cache_dir . '/*' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	rmdir( $cache_dir );
}
