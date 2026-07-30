<?php
/**
 * Smoke scenario: page cache purging across a multisite network.
 *
 * Regression cover for the Codex review on PR #8: the delete handler switches
 * into each selected subsite, deletes, then restores — so a single purge after
 * the loop runs in the *network* site's context. Integrations that only clear
 * the site they are called from (WP Rocket, SiteGround Optimizer, W3 Total
 * Cache) would leave every subsite serving deleted comments and stale counts.
 *
 * Verified before the fix: the purge fired once, for blog 1, while comments
 * were deleted on blogs 2 and 3.
 *
 * Requires a multisite sandbox with the plugin network-activated — see
 * bin/smoke-test-multisite.sh. Skips cleanly on single site.
 *
 * Run: bin/smoke-test-multisite.sh
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! is_multisite() ) {
	echo "  SKIP  not a multisite install\n";
	echo "\ncache-purge-multisite: skipped\n";
	return;
}

// is_network_admin_ajax_context() reads this pair to recognise a network admin
// request, exactly as the settings JS does when it appends is_network_admin=1.
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}
$_GET['is_network_admin'] = '1';

$instance = Disable_Comments::get_instance();
dc_check( 'plugin is network active', (bool) $instance->networkactive );

$site_ids = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
$site_ids = array_map( 'intval', $site_ids );
dc_check( 'network has at least two subsites to test with', count( $site_ids ) >= 3 );

if ( ! $instance->networkactive || count( $site_ids ) < 3 ) {
	dc_smoke_finish( 'cache-purge-multisite' );
	return;
}

// The in-loop capability check is not bypassed for CLI, so an unprivileged
// caller silently skips every site — which would make these assertions pass for
// the wrong reason.
$admins = get_users( array( 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );
dc_check( 'test runs as a super admin', is_super_admin() );

$purged = array();
add_action(
	'disable_comments_purge_caches',
	function () use ( &$purged ) {
		// Recorded from inside the handler: this is the site a per-site
		// integration would actually clear.
		$purged[] = get_current_blog_id();
	}
);

// ── Deleting comments purges each selected subsite ──────────────────────────
$instance->delete_comments_settings(
	array(
		'delete'         => true,
		'delete_mode'    => 'selected_delete_types',
		'delete_types'   => array( 'post' ),
		'disabled_sites' => array( 'site_2' => 1, 'site_3' => 1 ),
	)
);
sort( $purged );

dc_check( 'delete purges the selected subsites', array( 2, 3 ) === $purged );
dc_check(
	'delete does not purge only the network site',
	array( 1 ) !== $purged
);
dc_check( 'blog context is restored after the purge loop', 1 === (int) get_current_blog_id() );

// ── A network settings save invalidates every site ──────────────────────────
$purged = array();
$instance->disable_comments_settings(
	array(
		'mode'           => 'selected_types',
		'disabled_types' => array( 'post' ),
	)
);
sort( $purged );

dc_check( 'network settings save purges every site in the network', $site_ids === $purged );
dc_check( 'blog context is restored after a network save', 1 === (int) get_current_blog_id() );

// ── Large networks can narrow the list ──────────────────────────────────────
$narrow = function () {
	return array( 2 );
};
add_filter( 'disable_comments_purge_blog_ids', $narrow );

$purged = array();
$instance->disable_comments_settings(
	array(
		'mode'           => 'selected_types',
		'disabled_types' => array( 'post' ),
	)
);
remove_filter( 'disable_comments_purge_blog_ids', $narrow );

dc_check( 'disable_comments_purge_blog_ids narrows the purge list', array( 2 ) === $purged );

// ── A per-site save still purges only its own site ──────────────────────────
// Without the network flag the save is a subsite save, and purging the whole
// network off the back of one site's settings would be wrong.
unset( $_GET['is_network_admin'] );
$purged = array();
$instance->disable_comments_settings(
	array(
		'mode'           => 'selected_types',
		'disabled_types' => array( 'post' ),
	)
);
dc_check( 'a subsite save purges only the current site', array( 1 ) === $purged );

dc_smoke_finish( 'cache-purge-multisite' );
