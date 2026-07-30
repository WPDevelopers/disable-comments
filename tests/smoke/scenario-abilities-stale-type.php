<?php
/**
 * Smoke scenario: post types that no longer exist are not reported.
 *
 * Regression cover for the Codex review on PR #8: the stored selection outlives
 * the post types in it. Disable comments on a CPT, then deactivate the plugin
 * that registered it, and the slug stays in disable_comments_options forever.
 * The ability reported it as a disabled post type while available_post_types
 * correctly omitted it, so the payload contradicted itself and named a post
 * type the site no longer has. The stale slug also inflated the summary from
 * "posts" to "multiple".
 *
 * The second half of this scenario matters as much as the first: the fix must
 * NOT reach the enforcement path. get_disabled_post_types() is consulted while
 * filters are installed, before CPTs register on `init` — filtering there would
 * drop every custom post type and leave comments open on all of them.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: false,
 *                                disabled_post_types: [post, dc_gone_smoke] }
 *   — dc_gone_smoke is deliberately never registered.
 *
 * Run: bin/wp eval-file tests/smoke/scenario-abilities-stale-type.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	echo "  SKIP  Abilities API not available (WordPress < 6.9)\n";
	echo "\nabilities-stale-type: skipped\n";
	return;
}

$instance = Disable_Comments::get_instance();

dc_check( 'the stale post type really is unregistered (guards this test)', ! post_type_exists( 'dc_gone_smoke' ) );

// ── The enforcement path must keep the stale slug ───────────────────────────
// If a future change "tidies" get_disabled_post_types() by filtering it, this
// fails — and it should. That method runs before CPTs register, so filtering it
// would silently stop disabling comments on every custom post type.
dc_check(
	'get_disabled_post_types() still returns the stored slug (enforcement untouched)',
	in_array( 'dc_gone_smoke', (array) $instance->get_disabled_post_types(), true )
);

// ── The reporting path must drop it ─────────────────────────────────────────
dc_check(
	'get_disabled_post_types_registered() excludes the unregistered slug',
	! in_array( 'dc_gone_smoke', (array) $instance->get_disabled_post_types_registered(), true )
);
dc_check(
	'the registered list still contains the real post type',
	in_array( 'post', (array) $instance->get_disabled_post_types_registered(), true )
);

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

$result = wp_get_ability( 'disable-comments/get-status' )->execute();
dc_check( 'execute succeeds and passes schema validation', ! is_wp_error( $result ) );

if ( is_wp_error( $result ) ) {
	echo '        ' . $result->get_error_message() . "\n";
	dc_smoke_finish( 'abilities-stale-type' );
	return;
}

dc_check(
	'the payload does not advertise a post type that no longer exists',
	! in_array( 'dc_gone_smoke', $result['disabled_post_types'], true )
);
dc_check( 'the real disabled post type is still reported', in_array( 'post', $result['disabled_post_types'], true ) );

// The invariant worth keeping: anything named as disabled must actually exist.
$phantom = array();
foreach ( $result['disabled_post_types'] as $type ) {
	if ( ! post_type_exists( $type ) ) {
		$phantom[] = $type;
	}
}
dc_check( 'every reported disabled post type is registered', array() === $phantom );

// The stale slug used to push the count past one and summarise as "multiple".
dc_check( 'status is not inflated by the stale slug', 'posts' === $result['status'] );

dc_smoke_finish( 'abilities-stale-type' );
