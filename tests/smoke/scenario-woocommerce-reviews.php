<?php
/**
 * WooCommerce product reviews.
 *
 * Reviews are comments on the `product` post type, so the plugin already
 * governed them — it just never said so in language a store owner would
 * recognise, and the delete tool could not offer them until one existed.
 *
 * This scenario runs with WooCommerce active. scenario-woocommerce-absent.php
 * asserts the other half: that every one of these touch-points degrades
 * quietly when WooCommerce is not installed.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

dc_check( 'the runner activated WooCommerce', $dc->is_woocommerce_active() );

// The cross-check below compares the reported status against a real
// comments_open() call. That only means anything if the per-post filters were
// registered, which is decided at construct from the seeded options.
dc_check(
	'the per-post comment filters are registered',
	false !== has_filter( 'comments_open', array( $dc, 'filter_comment_status' ) )
);

/**
 * Apply a configuration through the real save handler.
 */
function dc_save( $dc, $args ) {
	$dc->disable_comments_settings( $args );
}

// ---------------------------------------------------------------------------
echo "\n-- reviews are offerable before any exist --\n";

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM $wpdb->comments" );

$options = $dc->get_available_comment_type_options();

dc_check( 'review is a known comment type with no reviews in the database', isset( $options['review'] ) );
dc_check( 'and is labelled for a store owner', false !== strpos( $options['review'], 'Product reviews' ) );

// ---------------------------------------------------------------------------
echo "\n-- status reporting --\n";

dc_save( $dc, array( 'mode' => 'selected_types', 'disabled_types' => array() ) );
$status = $dc->get_product_review_status();

dc_check( 'reviews report as enabled when nothing is disabled', false === $status['reviews_disabled'] );
dc_check( 'and WooCommerce is reported active', true === $status['woocommerce_active'] );

dc_save( $dc, array( 'mode' => 'selected_types', 'disabled_types' => array( 'product' ) ) );
$status = $dc->get_product_review_status();

dc_check( 'disabling the product post type disables reviews', true === $status['reviews_disabled'] );

dc_save( $dc, array( 'mode' => 'remove_everywhere' ) );
$status = $dc->get_product_review_status();

dc_check( 'disabling everywhere disables reviews', true === $status['reviews_disabled'] );

// The allowlist keeps existing reviews readable. It does NOT reopen the review
// form: comments_open() is one boolean per post, so a product with comments
// disabled has no form regardless. Claiming otherwise was the bug here — the
// status said "enabled" while customers could not submit anything.
dc_save(
	$dc,
	array(
		'mode'                  => 'remove_everywhere',
		'allowed_comment_types' => array( 'review' ),
	)
);
$status = $dc->get_product_review_status();

dc_check( 'allowlisting is disclosed', true === $status['reviews_allowlisted'] );
dc_check( 'but reviews are still reported disabled, because they are', true === $status['reviews_disabled'] );
dc_check( 'and the blocker is named', 'disable-comments' === $status['disabled_by'] );

// The status has to agree with what a customer would actually experience.
$allowlist_product = wp_insert_post(
	array(
		'post_title'     => 'DC smoke allowlist product ' . uniqid(),
		'post_status'    => 'publish',
		'post_type'      => 'product',
		'comment_status' => 'open',
	),
	true
);
dc_check(
	'reviews_disabled matches comments_open() on a real product',
	$status['reviews_disabled'] === ( false === comments_open( $allowlist_product ) )
);

// ---------------------------------------------------------------------------
echo "\n-- WooCommerce's own review switch --\n";

dc_save( $dc, array( 'mode' => 'selected_types', 'disabled_types' => array(), 'allowed_comment_types' => array() ) );
dc_check( 'reviews are enabled to start with', false === $dc->get_product_review_status()['reviews_disabled'] );

update_option( 'woocommerce_enable_reviews', 'no' );
$status = $dc->get_product_review_status();

dc_check( 'a store that turned reviews off is not reported as enabled', true === $status['reviews_disabled'] );
dc_check( 'and WooCommerce is named as the blocker', 'woocommerce' === $status['disabled_by'] );

update_option( 'woocommerce_enable_reviews', 'yes' );

// ---------------------------------------------------------------------------
echo "\n-- the report describes the site, not the caller --\n";

// This ability is only callable by settings-capable users, who are exactly the
// people a role exemption usually covers. "Are reviews off?" must not answer
// "not for you".
dc_save(
	$dc,
	array(
		'mode'                   => 'remove_everywhere',
		'enable_exclude_by_role' => 1,
		'exclude_by_role'        => array( 'administrator' ),
	)
);

dc_check(
	'an exempt administrator still sees the site configuration',
	true === $dc->get_product_review_status()['reviews_disabled']
);

dc_save( $dc, array( 'mode' => 'selected_types', 'disabled_types' => array(), 'enable_exclude_by_role' => 0, 'exclude_by_role' => array() ) );

// ---------------------------------------------------------------------------
echo "\n-- a review really does survive the allowlist --\n";

$product_id = wp_insert_post(
	array(
		'post_title'     => 'DC smoke product ' . uniqid(),
		'post_status'    => 'publish',
		'post_type'      => 'product',
		'comment_status' => 'open',
	),
	true
);
dc_check( 'a product was created', !is_wp_error( $product_id ) );

$review_id = wp_insert_comment(
	array(
		'comment_post_ID'  => $product_id,
		'comment_content'  => 'dc smoke review',
		'comment_type'     => 'review',
		'comment_approved' => '1',
		'comment_author'   => 'DC Smoke',
	)
);
$plain_id = wp_insert_comment(
	array(
		'comment_post_ID'  => $product_id,
		'comment_content'  => 'dc smoke comment',
		'comment_approved' => '1',
		'comment_author'   => 'DC Smoke',
	)
);

dc_save( $dc, array( 'mode' => 'remove_everywhere', 'allowed_comment_types' => array( 'review' ) ) );
dc_check( 'the review is recognised as allowed', in_array( 'review', (array) $dc->get_allowed_comment_types_list(), true ) );

// ---------------------------------------------------------------------------
echo "\n-- the delete tool can target reviews separately --\n";

// An allowlisted type must not be deletable by name — that is what the
// allowlist means. Clear it first so reviews are a legitimate target.
dc_save( $dc, array( 'mode' => 'selected_types', 'disabled_types' => array(), 'allowed_comment_types' => array() ) );

$types = $dc->get_all_comment_types();

dc_check( 'review appears as a deletable comment type', isset( $types['review'] ) );
dc_check( 'labelled for a store owner rather than as "Review (review)"', false !== strpos( $types['review'], 'Product reviews' ) );

$dc->delete_comments_settings(
	array(
		'delete'               => true,
		'delete_mode'          => 'selected_delete_comment_types',
		'delete_comment_types' => array( 'review' ),
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$remaining_reviews = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_type = 'review'" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$remaining_plain = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_type = 'comment'" );

dc_check( 'the review was deleted', 0 === $remaining_reviews );
dc_check( 'the plain comment on the same product was not', 1 === $remaining_plain );

// ---------------------------------------------------------------------------
echo "\n-- Abilities API --\n";

if ( function_exists( 'wp_get_ability' ) ) {
	wp_set_current_user( 1 );
	$ability = wp_get_ability( 'disable-comments/get-status' );

	if ( $ability ) {
		$payload = $ability->execute();

		dc_check( 'the ability still validates against its schema', !is_wp_error( $payload ) );

		if ( !is_wp_error( $payload ) ) {
			dc_check( 'the payload reports WooCommerce as active', true === $payload['woocommerce_active'] );
			dc_check( 'the payload answers the review question directly', isset( $payload['product_reviews_disabled'] ) );
			dc_check( 'and names what is blocking them', isset( $payload['product_reviews_disabled_by'] ) );
		}
	}
} else {
	echo "  SKIP  Abilities API not available on this WordPress\n";
}

dc_smoke_finish( 'woocommerce-reviews' );
