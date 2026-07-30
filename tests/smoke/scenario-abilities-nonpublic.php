<?php
/**
 * Smoke scenario: "every box ticked" is not "disabled everywhere".
 *
 * Regression cover for the Codex review on PR #8: get_current_comment_status()
 * returns 'all' once every post type it knows about is disabled — but it only
 * knows about `public` post types, because that is all the settings screen
 * lists. A non-public post type that supports comments stays open in that mode.
 *
 * Before the fix the ability derived comments_disabled_everywhere from that
 * 'all' summary, so it claimed the whole site was closed while comments were
 * demonstrably still open. Only the global "remove everywhere" setting actually
 * covers non-public types — verified in scenario-abilities-everywhere.php.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: false,
 *                                disabled_post_types: [post, page, attachment] }
 *   — i.e. every public comment-capable type, global toggle OFF.
 *
 * Run: bin/wp eval-file tests/smoke/scenario-abilities-nonpublic.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	echo "  SKIP  Abilities API not available (WordPress < 6.9)\n";
	echo "\nabilities-nonpublic: skipped\n";
	return;
}

// A comment-capable post type the settings screen never shows.
register_post_type(
	'dc_smoke_private',
	array(
		'public'   => false,
		'show_ui'  => true,
		'label'    => 'DC Smoke Private',
		'supports' => array( 'title', 'comments' ),
	)
);

$instance = Disable_Comments::get_instance();

$available = array_keys( $instance->get_all_post_types() );
dc_check(
	'the settings-screen list excludes non-public types (the root cause)',
	! in_array( 'dc_smoke_private', $available, true )
);
dc_check(
	'every public comment-capable type is disabled in this scenario (guards this test)',
	array() === array_diff( $available, (array) $instance->get_disabled_post_types() )
);
dc_check(
	'the global setting is off in this scenario (guards this test)',
	false === $instance->is_remove_everywhere_configured()
);

// The premise, proven rather than assumed: comments really are still open on
// the non-public type. Without this the assertions below could pass against a
// site where nothing was open anyway.
$private_post = wp_insert_post(
	array(
		'post_type'      => 'dc_smoke_private',
		'post_title'     => 'DC smoke private post',
		'post_status'    => 'publish',
		'comment_status' => 'open',
	)
);
dc_check( 'the non-public post was created', $private_post > 0 );
dc_check(
	'comments really are still open on the non-public type',
	true === comments_open( $private_post )
);

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

$result = wp_get_ability( 'disable-comments/get-status' )->execute();
dc_check( 'execute succeeds and passes schema validation', ! is_wp_error( $result ) );

if ( is_wp_error( $result ) ) {
	echo '        ' . $result->get_error_message() . "\n";
	dc_smoke_finish( 'abilities-nonpublic' );
	return;
}

// The plugin's own summary is unchanged — 'all' still means "every post type
// the settings screen offers". That is what README_COMMENT_STATUS.md documents.
dc_check( 'status still reports the plugin summary "all"', 'all' === $result['status'] );

// The core of the regression: the site is NOT closed, so the payload must not
// say it is. An agent reading `true` here would wrongly conclude no comment can
// be created anywhere on the site.
dc_check(
	'comments_disabled_everywhere is false while a type is still open',
	false === $result['comments_disabled_everywhere']
);
dc_check(
	'the still-open type is not claimed as disabled',
	! in_array( 'dc_smoke_private', $result['disabled_post_types'], true )
);
dc_check(
	'the public types that are disabled are still reported',
	array() === array_diff( $available, $result['disabled_post_types'] )
);

dc_smoke_finish( 'abilities-nonpublic' );
