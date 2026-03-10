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

// Delete all snippet posts and their meta.
$snippets = get_posts(
	array(
		'post_type'      => 'acspm_snippet',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $snippets as $snippet_id ) {
	wp_delete_post( $snippet_id, true );
}

// Delete options.
delete_option( 'acspm_header_code' );
delete_option( 'acspm_footer_code' );

// Delete cached transients.
delete_transient( 'acspm_snippets_all' );
