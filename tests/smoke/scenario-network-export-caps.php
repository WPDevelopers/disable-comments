<?php
/**
 * Smoke scenario: a network export honours per-site permissions.
 *
 * Regression cover for the Codex review on PR #19.
 *
 * delete_comments_settings() checks, inside each switched-to subsite, that the
 * current user is a super admin or holds manage_options there, and skips the
 * site otherwise. The matching export loop did not, so the two disagreed — in
 * the worse direction. A user granted manage_network_plugins without being a
 * super admin (a delegation role plugins do make) could not delete a subsite's
 * comments but could export them: author names, email addresses and IP
 * addresses out of a site they hold no rights on.
 *
 * The CLI is exempt from the check for the same reason the delete path never
 * reaches it there: WP-CLI runs with no current user, so a capability test
 * would refuse every site.
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
	echo "\nnetwork-export-caps: skipped\n";
	return;
}

$dc = Disable_Comments::get_instance();
dc_check( 'plugin is network active', (bool) $dc->networkactive );

$site_ids = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
dc_check( 'the network has at least two sites', count( $site_ids ) > 1 );

$main    = (int) $site_ids[0];
$subsite = (int) $site_ids[1];

/**
 * Put one spam comment on a site and return its body.
 */
function dc_caps_seed( $blog_id ) {
	switch_to_blog( $blog_id );

	$post = wp_insert_post(
		array(
			'post_title'     => 'caps probe',
			'post_status'    => 'publish',
			'comment_status' => 'open',
		),
		true
	);

	$body = 'caps-probe-body-' . $blog_id;

	wp_insert_comment(
		array(
			'comment_post_ID'      => $post,
			'comment_content'      => $body,
			'comment_approved'     => 'spam',
			'comment_author'       => 'Caps Probe',
			'comment_author_email' => "probe$blog_id@example.com",
		)
	);

	restore_current_blog();

	return $body;
}

/**
 * Spam comments actually on one site, counted independently of the plugin so
 * the assertions are not checking the plugin against itself. The sandbox is
 * shared with earlier scenarios, so these are read rather than assumed.
 */
function dc_caps_spam_on( $blog_id ) {
	global $wpdb;

	switch_to_blog( $blog_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam'" );
	restore_current_blog();

	return $count;
}

$main_body    = dc_caps_seed( $main );
$subsite_body = dc_caps_seed( $subsite );

// A real account: administrator on the main site, no membership at all on the
// subsite, and manage_network_plugins granted the way a delegation plugin
// grants it. Not a super admin.
$login = 'dc_caps_' . uniqid();
$user  = wp_insert_user(
	array(
		'user_login' => $login,
		'user_pass'  => wp_generate_password(),
		'user_email' => $login . '@example.com',
		'role'       => 'administrator',
	)
);

if ( is_wp_error( $user ) ) {
	echo 'Could not create the probe user: ' . $user->get_error_message() . "\n";
	exit( 1 );
}

add_user_to_blog( $main, $user, 'administrator' );

$grant = function ( $caps ) {
	$caps['manage_network_plugins'] = true;

	return $caps;
};
add_filter( 'user_has_cap', $grant );

$previous = get_current_user_id();
wp_set_current_user( $user );

dc_check( 'the probe user is not a super admin', ! is_super_admin( $user ) );
dc_check( 'and holds manage_network_plugins', current_user_can( 'manage_network_plugins' ) );

switch_to_blog( $subsite );
$can_on_subsite = current_user_can( 'manage_options' );
restore_current_blog();

dc_check( 'but cannot manage the subsite, so the delete would skip it', ! $can_on_subsite );

// ---------------------------------------------------------------------------
echo "\n-- the export skips exactly the sites the delete would skip --\n";

$args = array(
	'delete_mode'    => 'delete_spam',
	'disabled_sites' => array( "site_$main" => 1, "site_$subsite" => 1 ),
);

$csv = $dc->export_comments_csv( $args, true );

dc_check( 'the export includes the site the user administers', false !== strpos( $csv, $main_body ) );
dc_check( 'and excludes the subsite it may not touch', false === strpos( $csv, $subsite_body ) );
dc_check( 'so no address from that subsite leaves it', false === strpos( $csv, "probe$subsite@example.com" ) );

// The preview is the third view of the same operation, and it has to agree
// with the other two: a total that counts a site the delete will skip promises
// the operator more than the delete can remove, and discloses a count from a
// site they hold no rights on.
$restricted = $dc->count_comments_for_delete( $args, true );

dc_check( 'the preview counts the site the user administers', $restricted['total'] > 0 );
dc_check( 'and counts only that site, matching what the delete would do', $restricted['total'] === dc_caps_spam_on( $main ) );

wp_set_current_user( $previous );
remove_filter( 'user_has_cap', $grant );

// A super admin is entitled to both sites, so nothing here narrows what the
// network admin screen has always been able to export.
grant_super_admin( $user );
wp_set_current_user( $user );

$csv = $dc->export_comments_csv( $args, true );

dc_check( 'a super admin still exports every selected site', false !== strpos( $csv, $main_body ) && false !== strpos( $csv, $subsite_body ) );

$full = $dc->count_comments_for_delete( $args, true );
dc_check( 'and still previews every selected site', $full['total'] === dc_caps_spam_on( $main ) + dc_caps_spam_on( $subsite ) );
dc_check( 'which is strictly more than the restricted user saw', $full['total'] > $restricted['total'] );

revoke_super_admin( $user );
wp_set_current_user( $previous );
wpmu_delete_user( $user );

dc_smoke_finish( 'network-export-caps' );
