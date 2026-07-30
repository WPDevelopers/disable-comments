<?php
/**
 * Smoke scenario: endpoint-level blocking is reported independently.
 *
 * Regression cover for the fifth Codex review round on PR #8.
 *
 * `remove_rest_API_comments` and `remove_xmlrpc_comments` are independent of the
 * post-type configuration: either can block comments over its transport while
 * every post type still has comments open. A payload derived only from
 * post-type settings would report `status: "none"` — "comments are enabled" —
 * to a REST client that is about to receive a 403. That is the worst possible
 * answer for this ability's primary audience.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: false,
 *                                disabled_post_types: [],
 *                                remove_rest_API_comments: 1,
 *                                remove_xmlrpc_comments: 1 }
 *
 * Run: bin/wp eval-file tests/smoke/scenario-abilities-endpoints.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	echo "  SKIP  Abilities API not available (WordPress < 6.9)\n";
	echo "\nabilities-endpoints: skipped\n";
	return;
}

$ability = wp_get_ability( 'disable-comments/get-status' );
dc_check( 'ability is available', (bool) $ability );

if ( ! $ability ) {
	dc_smoke_finish( 'abilities-endpoints' );
	return;
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

$result = $ability->execute();
dc_check( 'execute succeeds and passes schema validation', ! is_wp_error( $result ) );

if ( is_wp_error( $result ) ) {
	echo '        ' . $result->get_error_message() . "\n";
	dc_smoke_finish( 'abilities-endpoints' );
	return;
}

// Post types really are untouched in this scenario — that is the whole point:
// the endpoint blocking is invisible to post-type-derived fields.
dc_check( 'post-type status is "none" in this scenario', 'none' === $result['status'] );
dc_check( 'comments_disabled_everywhere is false', false === $result['comments_disabled_everywhere'] );
dc_check( 'no post types are reported disabled', array() === $result['disabled_post_types'] );

// ...and yet both transports are blocked, which the payload must say.
dc_check( 'rest_api_comments_blocked is true', true === $result['rest_api_comments_blocked'] );
dc_check( 'xmlrpc_comments_blocked is true', true === $result['xmlrpc_comments_blocked'] );

// ── The two flags do not block the same amount ──────────────────────────────
// xmlrpc_comments_blocked is CREATION-ONLY: disable_xmlrc_comments() unsets
// wp.newComment and nothing else, so reading and moderating still work. REST
// blocking is broad by comparison (routes removed, dispatch 403'd, queries
// filtered). The schema and docs say so; these assertions pin the behaviour
// they describe, so widening or narrowing the blocking forces the docs to
// follow instead of quietly drifting. (Codex review, PR #8.)
$methods = apply_filters(
	'xmlrpc_methods',
	array(
		'wp.newComment'           => '__return_true',
		'wp.getComment'           => '__return_true',
		'wp.getComments'          => '__return_true',
		'wp.editComment'          => '__return_true',
		'wp.deleteComment'        => '__return_true',
		'wp.getCommentCount'      => '__return_true',
		'wp.getCommentStatusList' => '__return_true',
	)
);

dc_check( 'wp.newComment is removed', ! isset( $methods['wp.newComment'] ) );

foreach ( array( 'wp.getComment', 'wp.getComments', 'wp.editComment', 'wp.deleteComment', 'wp.getCommentCount', 'wp.getCommentStatusList' ) as $method ) {
	// Documented as still available: an agent must not skip moderation over
	// XML-RPC just because xmlrpc_comments_blocked is true.
	dc_check( "$method survives (blocking is creation-only)", isset( $methods[ $method ] ) );
}

dc_smoke_finish( 'abilities-endpoints' );
