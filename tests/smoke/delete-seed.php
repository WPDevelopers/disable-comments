<?php
/**
 * Shared fixtures for the Delete Comments scenarios.
 *
 * Both delete scenarios assert exact counts, so they need an identical,
 * known starting state rather than whatever the previous scenario left behind.
 *
 * @package Disable_Comments
 */

/**
 * Seed a known comment set and return the post ids it hangs off.
 *
 * Deliberately mixes post types, comment types and spam so every delete mode
 * has something it must remove and something it must leave alone:
 *
 *   post: 2 approved, 1 pingback, 1 spam, 1 note   (5)
 *   page: 1 approved, 1 trackback, 1 spam          (3)
 *
 * Plain comments are seeded with an empty comment_type, the way a comment form
 * submits them. WordPress normalises that to 'comment' on insert, so assertions
 * about them must query 'comment' rather than ''.
 *
 * @return array {
 *     @type int $post Post id.
 *     @type int $page Page id.
 * }
 */
function dc_seed_comments() {
	global $wpdb;

	// Start from a clean table so counts are exact, not relative.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM $wpdb->commentmeta" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM $wpdb->comments" );

	$post_id = dc_make_test_post( 'post' );
	$page_id = dc_make_test_post( 'page' );

	$seed = array(
		// post id, comment_type, comment_approved
		array( $post_id, '', '1' ),
		array( $post_id, '', '1' ),
		array( $post_id, 'pingback', '1' ),
		array( $post_id, '', 'spam' ),
		array( $page_id, '', '1' ),
		array( $page_id, 'trackback', '1' ),
		array( $page_id, '', 'spam' ),
		array( $post_id, 'note', '1' ),
	);

	foreach ( $seed as $row ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $row[0],
				'comment_content'      => 'dc smoke ' . $row[1] . ' ' . uniqid(),
				'comment_type'         => $row[1],
				'comment_approved'     => $row[2],
				'comment_author'       => 'DC Smoke',
				'comment_author_email' => 'smoke@example.com',
			)
		);
		// Give every comment a meta row so commentmeta cleanup is observable.
		add_comment_meta( $comment_id, 'dc_smoke_marker', '1' );
	}

	return array(
		'post' => $post_id,
		'page' => $page_id,
	);
}

/**
 * Count comments matching a raw WHERE clause against the `comments` alias.
 *
 * @param string $where SQL predicate.
 * @return int
 */
function dc_comment_count( $where = '1=1' ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments comments WHERE $where" );
}

/**
 * @return int Rows in the comment meta table.
 */
function dc_meta_count() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->commentmeta" );
}

/**
 * Run one delete mode through the real handler.
 *
 * Passing $_args puts the handler on its WP-CLI path, which is the same route
 * `wp disable-comments delete` takes — nonce bypassed, capability check
 * skipped for CLI, everything else identical.
 *
 * @param Disable_Comments $dc   Plugin instance.
 * @param array            $args Form data.
 * @return string Log message.
 */
function dc_run_delete( $dc, $args, $ceilings = null ) {
	$args['delete'] = true;
	return $dc->delete_comments_settings( $args, $ceilings );
}

/**
 * @return int Comments currently marked as spam.
 */
function dc_spam_count() {
	return dc_comment_count( "comments.comment_approved = 'spam'" );
}
