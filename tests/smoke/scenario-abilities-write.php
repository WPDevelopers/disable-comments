<?php
/**
 * The write abilities.
 *
 * A read ability that gets its permission check wrong leaks a setting. A write
 * ability that gets it wrong lets an unauthenticated caller empty the comments
 * table. Most of this file is therefore about refusing to act — the confirm
 * flag, the dry run, and who is allowed to call at all.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_get_ability' ) ) {
	echo "  SKIP  Abilities API not available on this WordPress\n";
	return;
}

$dc = Disable_Comments::get_instance();

$set    = wp_get_ability( 'disable-comments/set-status' );
$delete = wp_get_ability( 'disable-comments/delete-comments' );

dc_check( 'set-status is registered', (bool) $set );
dc_check( 'delete-comments is registered', (bool) $delete );

if ( ! $set || ! $delete ) {
	dc_smoke_finish( 'abilities-write' );
	return;
}

// ---------------------------------------------------------------------------
echo "\n-- permission --\n";

wp_set_current_user( 0 );
dc_check( 'a logged-out caller cannot set status', true !== $set->check_permissions() );
dc_check( 'a logged-out caller cannot delete comments', true !== $delete->check_permissions() );

$subscriber = wp_insert_user(
	array(
		'user_login' => 'dc-abil-sub-' . uniqid(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
if ( ! is_wp_error( $subscriber ) ) {
	wp_set_current_user( $subscriber );
	dc_check( 'a subscriber cannot set status', true !== $set->check_permissions() );
	dc_check( 'a subscriber cannot delete comments', true !== $delete->check_permissions() );
}

$editor = wp_insert_user(
	array(
		'user_login' => 'dc-abil-ed-' . uniqid(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'editor',
	)
);
if ( ! is_wp_error( $editor ) ) {
	wp_set_current_user( $editor );
	// An editor moderates comments but does not configure plugins.
	dc_check( 'an editor cannot set status', true !== $set->check_permissions() );
	dc_check( 'an editor cannot delete comments', true !== $delete->check_permissions() );
}

wp_set_current_user( 1 );
dc_check( 'an administrator can', true === $set->check_permissions() );

// ---------------------------------------------------------------------------
echo "\n-- set-status refuses to act without confirmation --\n";

$dc->disable_comments_settings( array( 'mode' => 'selected_types', 'disabled_types' => array() ) );
$before = $dc->get_current_comment_status();

$result = $set->execute( array( 'mode' => 'everywhere' ) );

dc_check( 'an unconfirmed call is an error', is_wp_error( $result ) );
dc_check( 'and the error explains how to proceed', false !== strpos( $result->get_error_message(), 'confirm' ) );
dc_check( 'and the site was not changed', $before === $dc->get_current_comment_status() );

$result = $set->execute( array( 'mode' => 'everywhere', 'confirm' => false ) );
dc_check( 'confirm:false is not confirmation', is_wp_error( $result ) );
dc_check( 'and still nothing changed', $before === $dc->get_current_comment_status() );

// ---------------------------------------------------------------------------
echo "\n-- set-status applies and reports --\n";

$result = $set->execute( array( 'mode' => 'everywhere', 'confirm' => true ) );

dc_check( 'a confirmed call succeeds', ! is_wp_error( $result ) );
dc_check( 'it reports the change', true === $result['changed'] );
dc_check( 'it reports the status it moved to', 'all' === $result['status'] );
dc_check( 'and the status it came from, so it can be undone', $before === $result['previous_status'] );
dc_check( 'the site really did change', 'all' === $dc->get_current_comment_status() );

// The previous_status is only useful if it round-trips.
$result = $set->execute( array( 'mode' => 'off', 'confirm' => true ) );
dc_check( 'the change can be undone', 'none' === $dc->get_current_comment_status() );

// Applying the same input twice must land on the same state, as the
// idempotent annotation promises.
$set->execute( array( 'mode' => 'post_types', 'post_types' => array( 'post' ), 'confirm' => true ) );
$first  = $dc->get_current_comment_status();
$result = $set->execute( array( 'mode' => 'post_types', 'post_types' => array( 'post' ), 'confirm' => true ) );

dc_check( 'reapplying the same input is idempotent', $first === $dc->get_current_comment_status() );
dc_check( 'and reports that nothing changed', false === $result['changed'] );

// ---------------------------------------------------------------------------
echo "\n-- unknown post types are reported, not silently swallowed --\n";

$result = $set->execute(
	array(
		'mode'       => 'post_types',
		'post_types' => array( 'post', 'dc_not_registered' ),
		'confirm'    => true,
	)
);

dc_check( 'the unknown slug is named in the response', array( 'dc_not_registered' ) === $result['unknown_post_types'] );
dc_check( 'the known slug was still applied', in_array( 'post', $result['disabled_post_types'], true ) );
dc_check( 'the unknown slug was not stored', ! in_array( 'dc_not_registered', $result['disabled_post_types'], true ) );

$result = $set->execute( array( 'mode' => 'nonsense', 'confirm' => true ) );
dc_check( 'an unknown mode is rejected', is_wp_error( $result ) );

// extra_post_types is appended by get_disabled_post_types() on a network
// install, so carrying it through a partial update would leave "off" still
// disabling a custom type and stop a post_types replacement removing one. The
// mode is a statement about the whole disabled set, so it is rebuilt with it.
$set->execute( array( 'mode' => 'post_types', 'post_types' => array( 'post', 'dc_not_registered' ), 'confirm' => true ) );
$stored = get_option( 'disable_comments_options' );
dc_check( 'an unregistered slug lands in extra_post_types, not the main list', ! in_array( 'dc_not_registered', (array) $stored['disabled_post_types'], true ) );

$set->execute( array( 'mode' => 'off', 'confirm' => true ) );
$stored = get_option( 'disable_comments_options' );
dc_check( '"off" clears extra_post_types too', empty( $stored['extra_post_types'] ) );
dc_check( 'so nothing is left disabled', array() === array_values( (array) $dc->get_disabled_post_types() ) );

// ---------------------------------------------------------------------------
echo "\n-- delete defaults to a dry run --\n";

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM $wpdb->comments" );

$post_id = dc_make_test_post( 'post' );
for ( $i = 0; $i < 4; $i++ ) {
	wp_insert_comment(
		array(
			'comment_post_ID'  => $post_id,
			'comment_content'  => 'dc smoke ' . uniqid(),
			'comment_approved' => ( $i < 2 ) ? '1' : 'spam',
			'comment_author'   => 'DC Smoke',
		)
	);
}

function dc_total_comments() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments" );
}

// Omitting dry_run entirely must NOT mean "go ahead". A missing field is the
// most likely thing an agent gets wrong, and the safe reading is the count.
$result = $delete->execute( array( 'mode' => 'spam' ) );

dc_check( 'omitting dry_run defaults to a dry run', true === $result['dry_run'] );
dc_check( 'the dry run deleted nothing', 0 === $result['deleted'] );
dc_check( 'and still reports what it matched', 2 === $result['matched'] );
dc_check( 'the comments are all still there', 4 === dc_total_comments() );

// ---------------------------------------------------------------------------
echo "\n-- a real delete needs dry_run off AND confirmation --\n";

$result = $delete->execute( array( 'mode' => 'spam', 'dry_run' => false ) );

dc_check( 'dry_run:false without confirm is refused', is_wp_error( $result ) );
dc_check( 'and nothing was deleted', 4 === dc_total_comments() );

$result = $delete->execute( array( 'mode' => 'spam', 'dry_run' => false, 'confirm' => true ) );

dc_check( 'a confirmed delete succeeds', ! is_wp_error( $result ) );
dc_check( 'it reports it was not a dry run', false === $result['dry_run'] );
dc_check( 'it reports how many it removed', 2 === $result['deleted'] );
dc_check( 'the spam is gone', 2 === dc_total_comments() );

$result = $delete->execute( array( 'mode' => 'nonsense', 'dry_run' => true ) );
dc_check( 'an unknown delete mode is rejected', is_wp_error( $result ) );

// ---------------------------------------------------------------------------
echo "\n-- annotations tell a host what it is calling --\n";

$read = wp_get_ability( 'disable-comments/get-status' );

dc_check( 'reading is still marked readonly', true === $read->get_meta()['annotations']['readonly'] );
dc_check( 'set-status is not readonly', false === $set->get_meta()['annotations']['readonly'] );
dc_check( 'set-status is reversible, so not destructive', false === $set->get_meta()['annotations']['destructive'] );
dc_check( 'delete-comments is marked destructive', true === $delete->get_meta()['annotations']['destructive'] );
dc_check( 'delete-comments is not idempotent', false === $delete->get_meta()['annotations']['idempotent'] );
dc_check( 'both are discoverable over MCP', ! empty( $set->get_meta()['mcp']['public'] ) && ! empty( $delete->get_meta()['mcp']['public'] ) );

// ---------------------------------------------------------------------------
echo "\n-- the REST/MCP transport, not just WP-CLI --\n";

// This is the gap that let a P1 through: `wp eval-file` sets $is_CLI, which is
// the one path disable_comments_settings() acts on without an AJAX nonce. Over
// the abilities' own advertised transport neither is true, so the write was
// skipped and the request was killed by wp_die() before the ability returned.
$dc->is_CLI = false;

$dc->apply_settings( array( 'mode' => 'selected_types', 'disabled_types' => array() ), false, false );

$result = $set->execute( array( 'mode' => 'everywhere', 'confirm' => true ) );

dc_check( 'the ability returns instead of terminating the request', is_array( $result ) );
dc_check( 'and the write actually landed', 'all' === $dc->get_current_comment_status() );
dc_check( 'and it is persisted, not just in memory', ! empty( get_option( 'disable_comments_options' )['remove_everywhere'] ) );

// A partial update must not clear fields it never mentioned.
$dc->apply_settings(
	array(
		'mode'                  => 'selected_types',
		'disabled_types'        => array(),
		'allowed_comment_types' => array( 'note' ),
	),
	false,
	false
);

$set->execute( array( 'mode' => 'everywhere', 'confirm' => true ) );
$options = get_option( 'disable_comments_options' );

dc_check(
	'a partial ability write leaves the allowlist alone',
	in_array( 'note', (array) $options['allowed_comment_types'], true )
);

// The delete ability has the same transport problem.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM $wpdb->comments" );
$spam_post = dc_make_test_post( 'post' );
wp_insert_comment(
	array(
		'comment_post_ID'  => $spam_post,
		'comment_content'  => 'dc smoke ' . uniqid(),
		'comment_approved' => 'spam',
		'comment_author'   => 'DC Smoke',
	)
);

$result = $delete->execute( array( 'mode' => 'spam', 'dry_run' => false, 'confirm' => true ) );

dc_check( 'the delete ability returns over the same transport', is_array( $result ) );
dc_check( 'and reports what it removed', 1 === $result['deleted'] );
dc_check( 'and the comment is really gone', 0 === dc_total_comments() );

$dc->is_CLI = true;

// ---------------------------------------------------------------------------
echo "\n-- a write can actually be undone --\n";

$dc->apply_settings( array( 'mode' => 'selected_types', 'disabled_types' => array( 'page' ) ), false, false );

$result = $set->execute( array( 'mode' => 'everywhere', 'confirm' => true ) );

dc_check( 'previous_mode is a value set-status accepts', in_array( $result['previous_mode'], array( 'everywhere', 'post_types', 'off' ), true ) );
dc_check( 'previous_disabled_post_types carries the list', array( 'page' ) === $result['previous_disabled_post_types'] );

// Round-trip it: the response has to be sufficient on its own.
$set->execute(
	array(
		'mode'       => $result['previous_mode'],
		'post_types' => $result['previous_disabled_post_types'],
		'confirm'    => true,
	)
);

dc_check( 'replaying the response restores the previous state', array( 'page' ) === array_values( $dc->get_disabled_post_types() ) );

// previous_status is a summary, not a mode. The docs used to say to pass it
// back; the input schema rejects values like 'pages' and 'multiple'.
$modes = $set->get_input_schema()['properties']['mode']['enum'];
dc_check( 'previous_status is not itself a valid mode', ! in_array( $result['previous_status'], $modes, true ) );

// ---------------------------------------------------------------------------
echo "\n-- a partial write leaves unrelated settings alone --\n";

// apply_settings()'s fallbacks decide what happens to a field the payload does
// not carry. Three of them — the conditional-rules trio — keyed on $this->is_CLI
// rather than the $preserve_existing flag, because when they were written WP-CLI
// was the only partial-update caller and the two meant the same thing. An
// Abilities API call is a partial update that is explicitly NOT CLI, so a
// set-status changing only the base mode wiped the site's whole rules
// configuration without saying so.
//
// is_CLI has to be forced off here: this scenario runs under wp eval-file, where
// it is true — which is exactly why the suite did not catch this on its own.
$was_cli    = $dc->is_CLI;
$dc->is_CLI = false;

$dc->apply_settings(
	array(
		'enable_conditional_rules' => 1,
		'auto_close_days'          => 30,
		'conditional_rules'        => array(
			array( 'type' => 'taxonomy', 'action' => 'disable', 'taxonomy' => 'category', 'terms' => array( 1 ) ),
		),
		'disabled_types'           => array( 'post' ),
	),
	false,
	false
);

$seeded = get_option( 'disable_comments_options' );
dc_check( 'the site starts with rules configured', ! empty( $seeded['enable_conditional_rules'] ) && 1 === count( (array) $seeded['conditional_rules'] ) && 30 === (int) $seeded['auto_close_days'] );

// Exactly what the ability does: partial payload, preserve_existing = true.
$dc->apply_settings( array( 'disabled_types' => array( 'post', 'page' ) ), false, true );

$after = get_option( 'disable_comments_options' );

dc_check( 'a partial write keeps enable_conditional_rules', ! empty( $after['enable_conditional_rules'] ) );
dc_check( 'and keeps the rules themselves', 1 === count( (array) $after['conditional_rules'] ) );
dc_check( 'and keeps auto_close_days', 30 === (int) $after['auto_close_days'] );
dc_check( 'while still applying what it did carry', in_array( 'page', (array) $after['disabled_post_types'], true ) );

$dc->is_CLI = $was_cli;

dc_smoke_finish( 'abilities-write' );
