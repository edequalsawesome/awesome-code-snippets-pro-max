<?php
/**
 * Run on a disposable WordPress install:
 * wp eval-file tests/snippet-permissions.php
 * Calls the real XML-RPC methods without an HTTP transport.
 */

if ( is_multisite() ) {
	throw new RuntimeException( 'Run this check on a disposable single-site install.' );
}

require_once ABSPATH . 'wp-includes/class-IXR.php';
require_once ABSPATH . 'wp-includes/class-wp-xmlrpc-server.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

function acspm_permissions_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$original_user = get_current_user_id();
$users         = array();
$posts         = array();
$password      = wp_generate_password( 32 );
$server        = new wp_xmlrpc_server();
$manager       = ACSPM_Snippets::get_instance();

set_error_handler( function ( $severity, $message, $file, $line ) {
	if ( ! ( error_reporting() & $severity ) ) {
		return false;
	}
	throw new RuntimeException( "$message at $file:$line" );
}, E_WARNING | E_NOTICE );

try {
	foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
		$login = 'acspm_permission_' . wp_generate_password( 12, false );
		$id    = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'role' => $role ) );
		acspm_permissions_assert( ! is_wp_error( $id ), 'Could not create test user.' );
		$users[ $role ] = array( 'id' => $id, 'login' => $login );
	}

	foreach ( array( 'administrator', 'editor' ) as $role ) {
		$user = $users[ $role ];
		$count = count( $manager->get_snippets( array( 'post_status' => 'any' ) ) );
		$result = $server->wp_newPost( array( 1, $user['login'], $password, array( 'post_type' => ACSPM_Snippets::POST_TYPE, 'post_title' => 'XML-RPC create fixture', 'post_status' => 'draft' ) ) );
		if ( ! ( $result instanceof IXR_Error ) ) {
			$posts[] = (int) $result;
		}
		if ( 'administrator' === $role ) {
			acspm_permissions_assert( ! ( $result instanceof IXR_Error ) && get_post( (int) $result ), 'Administrator XML-RPC creation failed.' );
		} else {
			acspm_permissions_assert( $result instanceof IXR_Error && 401 === $result->code, 'Editor XML-RPC creation was not denied.' );
			acspm_permissions_assert( $count === count( $manager->get_snippets( array( 'post_status' => 'any' ) ) ), 'Denied creation added a snippet.' );
		}
		foreach ( array( 'draft' => 'publish', 'publish' => 'draft' ) as $before => $after ) {
			$id = $manager->create_snippet( array( 'name' => 'XML-RPC fixture', 'code' => '// inert PHP fixture', 'code_type' => 'php', 'active' => 'publish' === $before ) );
			acspm_permissions_assert( ! is_wp_error( $id ), 'Could not create snippet.' );
			$posts[] = $id;
			$result = $server->wp_getPost( array( 1, $user['login'], $password, $id ) );
			acspm_permissions_assert( 'administrator' === $role ? is_array( $result ) && $id === (int) $result['post_id'] : $result instanceof IXR_Error && 401 === $result->code, "$role: unexpected XML-RPC read result." );
			$result = $server->wp_getPosts( array( 1, $user['login'], $password, array( 'post_type' => ACSPM_Snippets::POST_TYPE ) ) );
			acspm_permissions_assert( 'administrator' === $role ? is_array( $result ) && ! empty( $result ) : $result instanceof IXR_Error && 401 === $result->code, "$role: unexpected XML-RPC list result." );
			$result = $server->wp_editPost( array( 1, $user['login'], $password, $id, array( 'post_status' => $after ) ) );
			acspm_permissions_assert( 'administrator' === $role ? true === $result : $result instanceof IXR_Error && 401 === $result->code, "$role: unexpected XML-RPC edit result." );
			acspm_permissions_assert( ( 'administrator' === $role ? $after : $before ) === get_post_status( $id ), "$role: unexpected snippet status." );
			$result = $server->wp_deletePost( array( 1, $user['login'], $password, $id ) );
			acspm_permissions_assert( 'administrator' === $role ? true === $result : $result instanceof IXR_Error && 401 === $result->code, "$role: unexpected XML-RPC delete result." );
			if ( 'editor' === $role ) {
				acspm_permissions_assert( $before === get_post_status( $id ), 'Denied deletion changed snippet.' );
			} else {
				acspm_permissions_assert( null === get_post( $id ), 'Administrator deletion left the snippet in storage.' );
			}
		}
	}

	foreach ( array( 'draft', 'publish', 'private', 'trash' ) as $status ) {
		$id = wp_insert_post( array( 'post_type' => ACSPM_Snippets::POST_TYPE, 'post_title' => 'Permission fixture', 'post_status' => $status, 'post_author' => $users['editor']['id'] ), true );
		acspm_permissions_assert( ! is_wp_error( $id ), 'Could not create fixture.' );
		$posts[] = $id;
		foreach ( $users as $role => $user ) {
			wp_set_current_user( $user['id'] );
			foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_page', 'read_page', 'delete_page' ) as $cap ) {
				acspm_permissions_assert( ( 'administrator' === $role ) === current_user_can( $cap, $id ), "$role: unexpected $cap access for $status snippet." );
			}
		}
	}

	$type = get_post_type_object( ACSPM_Snippets::POST_TYPE );
	foreach ( $users as $role => $user ) {
		wp_set_current_user( $user['id'] );
		foreach ( array( 'create_posts', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'delete_private_posts', 'delete_published_posts' ) as $cap ) {
			acspm_permissions_assert( ( 'administrator' === $role ) === current_user_can( $type->cap->$cap ), "$role: unexpected $cap access." );
		}
	}

	wp_set_current_user( $users['editor']['id'] );
	acspm_permissions_assert( current_user_can( 'edit_posts' ) && current_user_can( 'publish_posts' ) && current_user_can( 'delete_posts' ), 'Normal Editor post permissions changed.' );
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Normal post fixture', 'post_status' => 'draft', 'post_author' => $users['administrator']['id'] ), true );
	acspm_permissions_assert( ! is_wp_error( $id ), 'Could not create normal post fixture.' );
	$posts[] = $id;
	foreach ( array( 'editor', 'author' ) as $role ) {
		$user = $users[ $role ];
		wp_set_current_user( $user['id'] );
		acspm_permissions_assert( ( 'editor' === $role ) === current_user_can( 'edit_post', $id ) && ( 'editor' === $role ) === current_user_can( 'delete_post', $id ), "$role: normal post object permissions changed." );
		$result = $server->wp_editPost( array( 1, $user['login'], $password, $id, array( 'post_title' => 'Normal post edited by Editor' ) ) );
		acspm_permissions_assert( 'editor' === $role ? true === $result : $result instanceof IXR_Error && 401 === $result->code, "$role: unexpected normal post edit result." );
	}
	acspm_permissions_assert( 'Normal post edited by Editor' === get_post( $id )->post_title, 'Normal post edit was not saved.' );
	echo "Snippet capability and XML-RPC permission checks passed.\n";
} finally {
	restore_error_handler();
	wp_set_current_user( $original_user );
	foreach ( $posts as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $users as $user ) {
		wp_delete_user( $user['id'] );
	}
}
