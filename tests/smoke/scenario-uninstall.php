<?php
/**
 * Smoke scenario: uninstall leaves nothing behind.
 *
 * uninstall.php is a plain file that runs with the plugin *not* loaded, so it
 * names every option literally. That is exactly the kind of list that drifts:
 * the review prompt shipped its trigger option and its per-user dismissal
 * meta, and neither was added here — a reinstall greeted the new owner with
 * "Disable Comments just cleared 512 comments for you" about a delete they
 * never performed, and every admin who had dismissed the prompt stayed
 * silently dismissed forever.
 *
 * So this runs the real file rather than asserting against a copy of the list.
 *
 * Runs last in both runners: it deletes the plugin's settings, and every
 * scenario before it depends on those.
 *
 * Run: bin/smoke-test.sh (and bin/smoke-test-multisite.sh)
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

wp_set_current_user( 1 );

/**
 * Pretend we are on the plugin's settings screen.
 *
 * should_show_review_prompt() asks get_current_screen(), which is null under
 * WP-CLI — without this every prompt assertion below would pass for the wrong
 * reason.
 *
 * @param string $id Screen id.
 */
function dc_uninstall_set_screen( $id ) {
	set_current_screen( 'dashboard' );
	$screen                    = get_current_screen();
	$screen->id                = $id;
	$GLOBALS['current_screen'] = $screen;
}

dc_uninstall_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );

$dc_sites = is_multisite()
	? array_map( 'intval', get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) )
	: array( get_current_blog_id() );

// ---------------------------------------------------------------------------
echo "\n-- there is something to clean up --\n";

// A prompt armed on this site, by the same call a real bulk delete makes.
delete_user_meta( 1, Disable_Comments::REVIEW_DISMISSED_META );
$dc->record_review_trigger( 512 );

dc_check( 'a bulk delete armed the review prompt', true === $dc->should_show_review_prompt() );

// Counters, on every site of the network. Each subsite has its own options
// table, so seeding only the current one would let a single-pass uninstall
// look correct.
foreach ( $dc_sites as $dc_blog_id ) {
	if ( is_multisite() ) {
		switch_to_blog( $dc_blog_id );
	}

	update_option( Disable_Comments::REVIEW_TRIGGER_OPTION, array( 'at' => time(), 'deleted' => 7 ), false );
	update_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', 3 );
	update_option( Disable_Comments::BLOCKED_SINCE_OPTION, time() );

	if ( is_multisite() ) {
		restore_current_blog();
	}
}

dc_check( 'the trigger option is stored', false !== get_option( Disable_Comments::REVIEW_TRIGGER_OPTION, false ) );
dc_check( 'and a counter with it', false !== get_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', false ) );

// Two admins who each said "don't ask again". Nothing records who they are, so
// the cleanup has to clear the meta key for everyone rather than walk a list.
$dc_second = wp_insert_user(
	array(
		'user_login' => 'dc-uninstall-' . uniqid(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'administrator',
	)
);
$dc_second = is_wp_error( $dc_second ) ? 0 : (int) $dc_second;

update_user_meta( 1, Disable_Comments::REVIEW_DISMISSED_META, time() );
if ( $dc_second ) {
	update_user_meta( $dc_second, Disable_Comments::REVIEW_DISMISSED_META, time() );
}

dc_check( 'the first admin has dismissed the prompt', '' !== (string) get_user_meta( 1, Disable_Comments::REVIEW_DISMISSED_META, true ) );
dc_check( 'and a dismissed admin is not asked again', false === $dc->should_show_review_prompt() );

// ---------------------------------------------------------------------------
echo "\n-- uninstall --\n";

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'disable-comments/disable-comments.php' );
}

require dirname( dirname( __DIR__ ) ) . '/uninstall.php';

// ---------------------------------------------------------------------------
echo "\n-- nothing of ours is left --\n";

dc_check( 'the settings are gone', false === get_site_option( 'disable_comments_options', false ) );

foreach ( $dc_sites as $dc_blog_id ) {
	if ( is_multisite() ) {
		switch_to_blog( $dc_blog_id );
	}

	dc_check( "the review trigger is gone on site {$dc_blog_id}", false === get_option( Disable_Comments::REVIEW_TRIGGER_OPTION, false ) );
	dc_check( "the blocked counter is gone on site {$dc_blog_id}", false === get_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', false ) );
	dc_check( "the counting start date is gone on site {$dc_blog_id}", false === get_option( Disable_Comments::BLOCKED_SINCE_OPTION, false ) );

	if ( is_multisite() ) {
		restore_current_blog();
	}
}

dc_check(
	"the first admin's dismissal is gone",
	'' === (string) get_user_meta( 1, Disable_Comments::REVIEW_DISMISSED_META, true )
);

if ( $dc_second ) {
	dc_check(
		"and the second admin's too, without a list of who dismissed it",
		'' === (string) get_user_meta( $dc_second, Disable_Comments::REVIEW_DISMISSED_META, true )
	);
}

// The symptom, not just the storage: a reinstall must not open with a prompt
// naming somebody else's deletion — and must not be quiet merely because a
// dismissal from the last installation survived, which is the same silence for
// the opposite reason.
dc_check(
	'a reinstall starts with no prompt armed, and no leftover dismissal keeping it quiet',
	false === $dc->should_show_review_prompt()
		&& '' === (string) get_user_meta( 1, Disable_Comments::REVIEW_DISMISSED_META, true )
);

if ( $dc_second ) {
	if ( is_multisite() ) {
		if ( function_exists( 'wpmu_delete_user' ) ) {
			wpmu_delete_user( $dc_second );
		}
	} else {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $dc_second );
	}
}

dc_smoke_finish( 'uninstall' );
