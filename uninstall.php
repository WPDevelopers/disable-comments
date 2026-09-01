<?php
/**
 * Uninstall script
 *
 * @package Disable_Comments
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'disable_comments_options' );

/**
 * Per-site options this plugin writes at runtime.
 *
 * Left behind they are stale plugin data, and on a reinstall they are worse
 * than that: the counters present a previous installation's numbers and start
 * date as current telemetry, and the review trigger makes our own screens
 * announce a bulk delete this installation never performed. Named literally
 * rather than read from the class, because the plugin is not loaded during
 * uninstall.
 */
$dc_site_options = array(
	// Blocked-attempt counters (Disable_Comments::BLOCKED_STATS_OPTION plus a
	// vector suffix) and the date counting started.
	'disable_comments_blocked_stats_comment',
	'disable_comments_blocked_stats_trackback',
	'disable_comments_blocked_stats_rest',
	'disable_comments_blocked_since',
	// When a bulk delete last succeeded, and how many it removed
	// (Disable_Comments::REVIEW_TRIGGER_OPTION).
	'disable_comments_review_trigger',
);

/**
 * Delete this plugin's per-site options on whichever site is current.
 *
 * @param array $option_names Option names to remove.
 * @return void
 */
function disable_comments_uninstall_site_options( $option_names ) {
	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

if ( is_multisite() && function_exists( 'get_sites' ) && class_exists( 'WP_Site_Query' ) ) {
	// Each subsite has its own options table, so a single pass would leave
	// these on every site but the one uninstall happens to run in.
	foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $dc_blog_id ) {
		switch_to_blog( $dc_blog_id );
		disable_comments_uninstall_site_options( $dc_site_options );
		restore_current_blog();
	}
} else {
	disable_comments_uninstall_site_options( $dc_site_options );
}

/**
 * The review prompt's per-user dismissal
 * (Disable_Comments::REVIEW_DISMISSED_META).
 *
 * Removed once rather than inside the loop above: usermeta is a single
 * network-wide table, so switching blogs would only re-delete the same rows.
 * The $delete_all argument clears it for every user in one query — nothing
 * records which users dismissed the prompt, so there is no list to walk.
 */
delete_metadata( 'user', 0, 'disable_comments_review_dismissed', '', true );
