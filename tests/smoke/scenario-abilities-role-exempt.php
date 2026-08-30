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

dc_smoke_finish( 'abilities-role-exempt' );
