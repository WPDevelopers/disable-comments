<?php
/**
 * Shared helpers for WP-CLI smoke tests (run via `bin/wp eval-file`).
 *
 * These run inside a fully loaded WordPress with the plugin active, so they
 * verify real runtime behaviour — unlike the PHPUnit suite, they need no
 * MySQL or WP test library.
 *
 * @package Disable_Comments
 */

$GLOBALS['dc_smoke_failures'] = array();

function dc_check( $label, $condition ) {
	if ( $condition ) {
		echo "  PASS  $label\n";
	} else {
		echo "  FAIL  $label\n";
		$GLOBALS['dc_smoke_failures'][] = $label;
	}
}

function dc_smoke_finish( $scenario ) {
	if ( ! empty( $GLOBALS['dc_smoke_failures'] ) ) {
		echo "\n$scenario: " . count( $GLOBALS['dc_smoke_failures'] ) . " assertion(s) FAILED\n";
		exit( 1 );
	}
	echo "\n$scenario: all assertions passed\n";
}

/**
 * Create a published test post with comments explicitly open, so any
 * "closed" result can only come from the plugin's filters.
 */
function dc_make_test_post( $post_type = 'post' ) {
	$id = wp_insert_post(
		array(
			'post_title'     => 'DC smoke ' . $post_type . ' ' . uniqid(),
			'post_status'    => 'publish',
			'post_type'      => $post_type,
			'comment_status' => 'open',
			'ping_status'    => 'open',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		echo 'Could not create test post: ' . $id->get_error_message() . "\n";
		exit( 1 );
	}
	return $id;
}
