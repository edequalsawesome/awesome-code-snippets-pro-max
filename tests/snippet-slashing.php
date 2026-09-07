<?php
/**
 * Run with: wp eval-file tests/snippet-slashing.php
 */

if ( ! class_exists( 'ACSPM_Snippets' ) ) {
	throw new RuntimeException( 'Activate Awesome Code Snippets Pro Max before running this check.' );
}

function acspm_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$manager = ACSPM_Snippets::get_instance();
$ids     = array();
$fixtures = array(
	'php' => "echo \\\"café\\\\path\\\";",
	'js'  => "const pattern = /\\\\w+\\\\s+\\\"é\\\"/;",
	'css' => ".thing::before { content: '\\\\ café'; }",
);

try {
	foreach ( $fixtures as $type => $code ) {
		$name = 'Slash "' . $type . '\\ café';
		$id   = $manager->create_snippet(
			array(
				'name'      => $name,
				'code'      => $code,
				'code_type' => $type,
				'active'    => false,
			)
		);
		acspm_test_assert( ! is_wp_error( $id ), 'Could not create ' . $type . ' fixture.' );
		$ids[] = $id;
		$snippet = $manager->get_snippet( $id );
		acspm_test_assert( $name === $snippet['name'], 'Name changed during ' . $type . ' create.' );
		acspm_test_assert( $code === $snippet['code'], 'Code changed during ' . $type . ' create.' );

		$result = $manager->update_snippet(
			$id,
			array(
				'name'      => $name,
				'code'      => $code,
				'code_type' => $type,
				'active'    => false,
			)
		);
		acspm_test_assert( ! is_wp_error( $result ), 'Could not repeat-save ' . $type . ' fixture.' );
		$snippet = $manager->get_snippet( $id );
		acspm_test_assert( $code === $snippet['code'], 'Code changed during repeated ' . $type . ' save.' );

		$updated_name = 'Updated ' . $name;
		$updated_code = $code . "\n// second save \\\"\\\\";
		$result       = $manager->update_snippet(
			$id,
			array(
				'name'      => $updated_name,
				'code'      => $updated_code,
				'code_type' => $type,
				'active'    => false,
			)
		);
		acspm_test_assert( ! is_wp_error( $result ), 'Could not update ' . $type . ' fixture.' );

		$snippet = $manager->get_snippet( $id );
		acspm_test_assert( $updated_name === $snippet['name'], 'Name changed during ' . $type . ' save.' );
		acspm_test_assert( $updated_code === $snippet['code'], 'Code changed during ' . $type . ' save.' );
	}
} finally {
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

WP_CLI::success( 'Snippet names and code survive create/update slashing.' );
