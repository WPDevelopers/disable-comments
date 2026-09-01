<?php
/**
 * Smoke scenario: Abilities API payload for a role-exempt caller.
 *
 * Regression cover for the second Codex review round on PR #8.
 *
 * Role exclusion is a per-user override: `is_remove_everywhere()` returns false
 * for an exempt user so comments stay open for them. That makes
 * `get_current_comment_status()` skip its "all" branch and fall back to the
 * stored post-type list — which would make this ability report
 * `comments_disabled_everywhere: false` to an exempt administrator even though
 * the site *is* configured to disable comments everywhere, and the payload is
 * documented as describing site configuration.
 *
 * Also covers the stale network-wide option: `disable_comments_sitewide_settings`
 * survives a network deactivation, so it must not be reported on a per-site
 * activation.
 *
 * And the same guard on the write ability: `set-status` computed its
 * `previous_status` / `status` summaries from the per-user method, so on this
 * configuration it contradicted the read ability it tells callers to consult
 * first.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: true,
 *                                disabled_post_types: [post],
 *                                enable_exclude_by_role: true,
 *                                exclude_by_role: [administrator] }
 *
 * Run: bin/wp eval-file tests/smoke/scenario-abilities-role-exempt.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_register_ability' ) ) {
	echo "  SKIP  Abilities API not available (WordPress < 6.9)\n";
	echo "\nabilities-role-exempt: skipped\n";
	return;
}

$ability = wp_get_ability( 'disable-comments/get-status' );
dc_check( 'ability is available', (bool) $ability );

if ( ! $ability ) {
	dc_smoke_finish( 'abilities-role-exempt' );
	return;
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

$instance = Disable_Comments::get_instance();
$exclusion = $instance->get_role_exclusion_state();
dc_check( 'role exclusion is configured for this scenario', ! empty( $exclusion['enabled'] ) );
dc_check( 'the calling administrator is genuinely exempt', ! empty( $exclusion['excluded'] ) );

$result = $ability->execute();
dc_check( 'execute succeeds and passes schema validation', ! is_wp_error( $result ) );

if ( is_wp_error( $result ) ) {
	echo '        ' . $result->get_error_message() . "\n";
	dc_smoke_finish( 'abilities-role-exempt' );
	return;
}

// The exemption must be disclosed...
dc_check( 'role_exclusion_enabled is true', true === $result['role_exclusion_enabled'] );
dc_check( 'excluded_for_current_user is true', true === $result['excluded_for_current_user'] );

// ...but it must not rewrite the site configuration the payload reports.
dc_check( 'status still reports the global setting as "all"', 'all' === $result['status'] );
dc_check( 'comments_disabled_everywhere is still true', true === $result['comments_disabled_everywhere'] );
dc_check(
	'no available post type is left out of the disabled list',
	array() === array_diff( $result['available_post_types'], $result['disabled_post_types'] )
);

// ── Stale network-wide option on a per-site activation ──────────────────────
// bin/smoke-test.sh sets disable_comments_sitewide_settings before this process
// boots, so the plugin loads it into $sitewide_settings at construct time — the
// same state left behind by a network deactivation. networkactive is false
// here, so the payload must not advertise network-wide settings.
dc_check( 'plugin is not network active in this sandbox', false === $result['is_network_active'] );
dc_check(
	'the stale sitewide option really is loaded (guards this test)',
	! empty( $instance->sitewide_settings )
);
dc_check(
	'is_network_wide_setting stays false when not network active',
	false === $result['is_network_wide_setting']
);

// ── The write ability describes the same site the same way ─────────────────
// get-status guarded this from the start and set-status did not, so on this
// exact configuration the read ability answered "all" while the write ability,
// one call later, called the same site "posts" — the stored post-type list
// get_current_comment_status() falls back to for an exempt caller. An agent
// reading that back would conclude its own write had failed, or hand somebody
// a summary contradicting the read it made moments earlier.
//
// Only the two summary strings were ever affected: `changed`, `previous_mode`
// and the post-type lists are role-independent already, and are asserted below
// so a fix to the summaries cannot quietly move them.

$set = wp_get_ability( 'disable-comments/set-status' );
dc_check( 'set-status is available', (bool) $set );

if ( $set ) {
	// The seeded state is the one where the two readings diverge: the global
	// setting is on, but the stored list holds only 'post'. Without that, both
	// readings agree and everything below passes on any build.
	dc_check(
		'the site is configured to disable everywhere (guards this test)',
		true === $instance->is_remove_everywhere_configured()
	);
	dc_check(
		'while the per-user reading still summarises the stored list',
		'all' !== $instance->get_current_comment_status()
	);

	$write = $set->execute( array( 'mode' => 'off', 'confirm' => true ) );

	dc_check( 'the write succeeds', ! is_wp_error( $write ) );

	if ( ! is_wp_error( $write ) ) {
		dc_check( 'previous_status reports the site, matching get-status', 'all' === $write['previous_status'] );

		// Everything the caller actually undoes with was already correct.
		dc_check( 'previous_mode still points the way back', 'everywhere' === $write['previous_mode'] );
		dc_check( 'previous_disabled_post_types still carries the stored list', array( 'post' ) === $write['previous_disabled_post_types'] );
		dc_check( 'the change is still reported', true === $write['changed'] );

		// And the write path itself is untouched: the exemption never had any
		// business deciding what gets stored.
		dc_check( 'the setting really was turned off', empty( get_option( 'disable_comments_options' )['remove_everywhere'] ) );
		dc_check( 'and nothing is left disabled', array() === $write['disabled_post_types'] );

		// The exemption survives a partial write, so the two abilities are
		// still being asked by an exempt caller here.
		dc_check( 'the caller is still exempt after the write', ! empty( $instance->get_role_exclusion_state()['excluded'] ) );

		$after = $ability->execute();
		dc_check( 'and both abilities now agree on the new state', $after['status'] === $write['status'] );
	}
}

dc_smoke_finish( 'abilities-role-exempt' );
