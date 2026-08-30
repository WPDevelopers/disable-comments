<?php
/**
 * Smoke scenario: WordPress Abilities API integration (WP 6.9+).
 *
 * Asserts that the plugin's ability category and abilities register with core,
 * that the read-only ability returns schema-valid data for a privileged user,
 * and — most importantly — that it is denied for users without `manage_options`.
 *
 * Skips cleanly on WordPress < 6.9, where the Abilities API does not exist.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: false,
 *                                disabled_post_types: [post] }
 *
 * Run: bin/wp eval-file tests/smoke/scenario-abilities.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	echo "  SKIP  Abilities API not available (WordPress < 6.9)\n";
	echo "\nabilities: skipped\n";
	return;
}

// ── Registration ─────────────────────────────────────────────────────────────
$categories = WP_Ability_Categories_Registry::get_instance();
dc_check( 'ability category "disable-comments" is registered', (bool) $categories->get_registered( 'disable-comments' ) );

$registry = WP_Abilities_Registry::get_instance();
$ability  = $registry->get_registered( 'disable-comments/get-status' );
dc_check( 'ability "disable-comments/get-status" is registered', (bool) $ability );

if ( ! $ability ) {
	dc_smoke_finish( 'abilities' );
	return;
}

dc_check( 'ability belongs to the disable-comments category', 'disable-comments' === $ability->get_category() );
dc_check( 'ability has a non-empty description for agents', '' !== trim( $ability->get_description() ) );

// ── Output schema is a real contract, not a hint ─────────────────────────────
// Without a `required` list core's validator accepts a partial object — even an
// empty one — so the documented "a successful call returns all these fields"
// guarantee would be unenforceable. (Codex review, PR #8.)
$schema = $ability->get_output_schema();
dc_check( 'output schema declares properties', ! empty( $schema['properties'] ) );
dc_check( 'output schema declares a required list', ! empty( $schema['required'] ) );
dc_check(
	'every documented property is required',
	array() === array_diff( array_keys( $schema['properties'] ), (array) $schema['required'] )
);
dc_check(
	'an empty payload is rejected by the schema',
	is_wp_error( rest_validate_value_from_schema( array(), $schema, 'output' ) )
);
dc_check(
	'a partial payload is rejected by the schema',
	is_wp_error( rest_validate_value_from_schema( array( 'status' => 'posts' ), $schema, 'output' ) )
);

$meta = $ability->get_meta();
dc_check( 'ability is exposed in the REST API', ! empty( $meta['show_in_rest'] ) );

// MCP exposure is a SEPARATE gate from show_in_rest. An MCP server skips any
// ability without meta.mcp.public — silently, with no warning and nothing
// logged. Every other assertion in this suite reaches the ability through
// execute() or wp-abilities/v1, both of which bypass that gate, so this bug
// hid behind a fully green suite until a live MCP client found it. (Issue #9.)
dc_check( 'ability is exposed to MCP clients', isset( $meta['mcp']['public'] ) && true === $meta['mcp']['public'] );
dc_check( 'ability declares its MCP type', isset( $meta['mcp']['type'] ) && 'tool' === $meta['mcp']['type'] );
dc_check( 'ability is annotated readonly', ! empty( $meta['annotations']['readonly'] ) );
dc_check( 'ability is annotated non-destructive', isset( $meta['annotations']['destructive'] ) && false === $meta['annotations']['destructive'] );

// ── Permission gating (the security-critical part) ───────────────────────────
wp_set_current_user( 0 );
$logged_out = $ability->execute();
dc_check( 'logged-out execute is denied', is_wp_error( $logged_out ) );

$subscriber = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $subscriber ) ) {
	$subscriber = array(
		wp_insert_user(
			array(
				'user_login' => 'dc_smoke_subscriber',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'dc_smoke_subscriber@example.test',
				'role'       => 'subscriber',
			)
		),
	);
}
wp_set_current_user( (int) $subscriber[0] );
$as_subscriber = $ability->execute();
dc_check( 'subscriber execute is denied', is_wp_error( $as_subscriber ) );

// ── Privileged execution + payload shape ─────────────────────────────────────
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
dc_check( 'an administrator exists to test with', ! empty( $admins ) );
wp_set_current_user( (int) $admins[0] );

$result = $ability->execute();
// execute() runs core's output-schema validation, so a non-WP_Error result
// means the payload matched the declared schema.
dc_check( 'administrator execute succeeds and passes schema validation', ! is_wp_error( $result ) );

if ( is_wp_error( $result ) ) {
	echo '        ' . $result->get_error_message() . "\n";
	dc_smoke_finish( 'abilities' );
	return;
}

foreach ( array(
	'status',
	'comments_disabled_everywhere',
	'disabled_post_types',
	'available_post_types',
	'is_network_active',
	'is_network_wide_setting',
	'rest_api_comments_blocked',
	'xmlrpc_comments_blocked',
	'role_exclusion_enabled',
	'excluded_for_current_user',
	'allowed_comment_types',
	'plugin_version',
) as $key ) {
	dc_check( "payload contains \"$key\"", array_key_exists( $key, $result ) );
}

// Values must reflect the live plugin state set by bin/smoke-test.sh.
dc_check( 'status matches get_current_comment_status()', $result['status'] === Disable_Comments::get_instance()->get_current_comment_status() );
dc_check( 'status reports "posts" for the post-only scenario', 'posts' === $result['status'] );
dc_check( 'comments_disabled_everywhere is false in the post-only scenario', false === $result['comments_disabled_everywhere'] );
dc_check( 'disabled_post_types contains "post"', in_array( 'post', $result['disabled_post_types'], true ) );
dc_check( 'disabled_post_types is a JSON list, not an object', array_values( $result['disabled_post_types'] ) === $result['disabled_post_types'] );
dc_check( 'available_post_types is a JSON list, not an object', array_values( $result['available_post_types'] ) === $result['available_post_types'] );
dc_check( 'plugin_version matches DC_VERSION', $result['plugin_version'] === DC_VERSION );
dc_check( 'role_exclusion_enabled is false when unconfigured', false === $result['role_exclusion_enabled'] );
dc_check( 'excluded_for_current_user is false when unconfigured', false === $result['excluded_for_current_user'] );

dc_smoke_finish( 'abilities' );
