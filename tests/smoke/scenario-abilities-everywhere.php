<?php
/**
 * Smoke scenario: Abilities API payload in "disable everywhere" mode.
 *
 * Regression cover for the Codex review on PR #8: the global setting closes
 * comments on *any* post type, including ones registered after the settings
 * were saved, while the stored selection only holds what was ticked at save
 * time. The ability must not report `comments_disabled_everywhere: true`
 * alongside a partial `disabled_post_types` list.
 *
 * This runs in its own WordPress process so the plugin singleton reads the
 * everywhere-mode options at construct time.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: true,
 *                                disabled_post_types: [post] }
 *   — deliberately a *partial* stored list, so a regression would show up.
 *
 * Run: bin/wp eval-file tests/smoke/scenario-abilities-everywhere.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	echo "  SKIP  Abilities API not available (WordPress < 6.9)\n";
	echo "\nabilities-everywhere: skipped\n";
	return;
}

// Register a comment-capable post type *after* the plugin booted, mimicking a
// theme or plugin that registers its CPT on a later hook than the settings save.
register_post_type(
	'dc_smoke_cpt',
	array(
		'public'   => true,
		'label'    => 'DC Smoke CPT',
		'supports' => array( 'title', 'comments' ),
	)
);

$ability = wp_get_ability( 'disable-comments/get-status' );
dc_check( 'ability is available', (bool) $ability );

if ( ! $ability ) {
	dc_smoke_finish( 'abilities-everywhere' );
	return;
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

$result = $ability->execute();
dc_check( 'execute succeeds and passes schema validation', ! is_wp_error( $result ) );

if ( is_wp_error( $result ) ) {
	echo '        ' . $result->get_error_message() . "\n";
	dc_smoke_finish( 'abilities-everywhere' );
	return;
}

dc_check( 'status is "all"', 'all' === $result['status'] );
dc_check( 'comments_disabled_everywhere is true', true === $result['comments_disabled_everywhere'] );

// The core of the regression: the late-registered type is covered by the global
// setting, so it must appear as disabled even though it was never saved.
dc_check(
	'late-registered post type appears in available_post_types',
	in_array( 'dc_smoke_cpt', $result['available_post_types'], true )
);
dc_check(
	'late-registered post type is reported as disabled',
	in_array( 'dc_smoke_cpt', $result['disabled_post_types'], true )
);
dc_check(
	'no available post type is left out of the disabled list',
	array() === array_diff( $result['available_post_types'], $result['disabled_post_types'] )
);

// ── Allowlisted comment types survive "disable everywhere" ──────────────────
// bin/smoke-test.sh allows the WP 6.9+ "note" type in this scenario. The plugin
// keeps those comments readable and creatable, so reporting only
// comments_disabled_everywhere would tell an agent that notes are unavailable.
dc_check(
	'allowed_comment_types discloses the "note" exception',
	in_array( 'note', $result['allowed_comment_types'], true )
);
dc_check(
	'allowed_comment_types is a JSON list, not an object',
	array_values( $result['allowed_comment_types'] ) === $result['allowed_comment_types']
);

// ── Global mode blocks REST even without the dedicated toggle ───────────────
// init_filters()'s everywhere branch installs the same rest_pre_dispatch /
// rest_endpoints / rest_comment_query filters as the REST setting, so
// non-allowlisted /wp/v2/comments requests get a 403. This scenario never sets
// remove_rest_API_comments, so the flag must come from global mode alone —
// otherwise a REST client is told it may post, then rejected.
$options = get_option( 'disable_comments_options' );
dc_check(
	'the dedicated REST toggle is off in this scenario (guards this test)',
	empty( $options['remove_rest_API_comments'] )
);
dc_check(
	'rest_api_comments_blocked is true from global mode alone',
	true === $result['rest_api_comments_blocked']
);
// Global mode does not remove XML-RPC comment methods; only its own toggle does.
dc_check(
	'xmlrpc_comments_blocked stays false without its toggle',
	false === $result['xmlrpc_comments_blocked']
);

dc_smoke_finish( 'abilities-everywhere' );
