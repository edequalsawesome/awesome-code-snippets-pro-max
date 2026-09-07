<?php
/** Run with: wp eval-file tests/safe-mode-links.php --user=<test-admin> */

if ( ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Run this check as an administrator.' );
}

$original_get = $_GET;
$original_user = get_current_user_id();
$original_screen = $GLOBALS['current_screen'] ?? null;
set_current_screen( 'dashboard' );

try {
	$_GET = array( 'acspm-safe-mode' => '1' );
	$url  = acspm_admin_url( 'tools.php?page=acspm-snippets&edit=7' );
	parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
	if ( '1' !== ( $query['acspm-safe-mode'] ?? null ) || '7' !== ( $query['edit'] ?? null ) ) {
		throw new RuntimeException( 'Authorized URL safe mode was not retained.' );
	}

	foreach ( array( 'absent' => array(), 'zero' => array( 'acspm-safe-mode' => '0' ), 'array' => array( 'acspm-safe-mode' => array( '1' ) ) ) as $case => $get ) {
		$_GET = $get;
		parse_str( wp_parse_url( acspm_admin_url( 'tools.php?page=acspm-snippets' ), PHP_URL_QUERY ), $query );
		if ( isset( $query['acspm-safe-mode'] ) ) {
			throw new RuntimeException( 'Invalid safe-mode flag was accepted: ' . $case );
		}
	}

	wp_set_current_user( 0 );
	$_GET = array( 'acspm-safe-mode' => '1' );
	parse_str( wp_parse_url( acspm_admin_url( 'tools.php?page=acspm-snippets' ), PHP_URL_QUERY ), $query );
	if ( isset( $query['acspm-safe-mode'] ) ) {
		throw new RuntimeException( 'Anonymous safe-mode flag was accepted.' );
	}

	wp_set_current_user( $original_user );
	$_GET = array();
	if ( ! defined( 'ACSPM_SAFE_MODE' ) ) {
		define( 'ACSPM_SAFE_MODE', true );
	}
	if ( ACSPM_SAFE_MODE ) {
		parse_str( wp_parse_url( acspm_admin_url( 'tools.php?page=acspm-snippets' ), PHP_URL_QUERY ), $query );
		if ( ! acspm_is_safe_mode() || isset( $query['acspm-safe-mode'] ) ) {
			throw new RuntimeException( 'Constant-only safe mode manufactured a URL flag.' );
		}
	}
} finally {
	$_GET = $original_get;
	wp_set_current_user( $original_user );
	$GLOBALS['current_screen'] = $original_screen;
}

WP_CLI::success( 'Safe-mode admin URLs retain only an authorized scalar URL flag.' );
