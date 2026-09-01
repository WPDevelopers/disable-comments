<?php
/**
 * Closing one comment type while comments stay open around it.
 *
 * The blocklist is the mirror of the allowlist. The allowlist keeps a named
 * type working where comments are off; the blocklist closes a named type where
 * comments are on. It exists because comments_open() is one boolean per post
 * and cannot separate WooCommerce's `review` from the `product` carrying it.
 *
 * The load-bearing assertion in this file is the negative one: comments_open()
 * on a product must stay TRUE while reviews are closed. Filtering it to false
 * would be a one-line implementation that passes every other check here and
 * takes every other comment type on the product down with it — which is the
 * whole thing this setting exists to avoid.
 *
 * Runs with WooCommerce if the sandbox has it; the WooCommerce-specific
 * assertions announce themselves as skipped when it does not.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

/**
 * Turn a wp_die() into a catchable exception.
 *
 * The blocklist refuses a submission the way WordPress refuses a closed one —
 * by ending the request. In a CLI process that would take the whole scenario
 * down, so the refusal is captured rather than suffered.
 */
function dc_blocklist_die_handler() {
	return 'dc_blocklist_die_thrower';
}

function dc_blocklist_die_thrower( $message, $title = '', $args = array() ) {
	throw new Exception( is_wp_error( $message ) ? $message->get_error_message() : (string) $message );
}

// ---------------------------------------------------------------------------
echo "\n-- the option is in force --\n";

dc_check(
	'the runner seeded a blocklist',
	in_array( 'review', (array) $dc->get_blocked_comment_types_list(), true )
);

// Registered at plugins_loaded from the stored option. Every assertion below
// calls the callbacks directly, so without this one they would all be asserting
// against code no request ever reaches.
dc_check(
	'the comment-form guard is registered',
	false !== has_filter( 'preprocess_comment', array( $dc, 'reject_blocked_comment_type' ) )
);
dc_check(
	'the REST guard is registered',
	false !== has_filter( 'rest_pre_insert_comment', array( $dc, 'reject_blocked_comment_type_rest' ) )
);

// ---------------------------------------------------------------------------
echo "\n-- a blocked type is refused, an ordinary comment is not --\n";

// current_user_can('moderate_comments') exempts a moderator by design, so the
// refusal has to be asserted as the public, not as the CLI's default user.
wp_set_current_user( 0 );

add_filter( 'wp_die_handler', 'dc_blocklist_die_handler', 9999 );

$refused = null;
try {
	$dc->reject_blocked_comment_type(
		array(
			'comment_type'    => 'review',
			'comment_content' => 'dc smoke blocked review',
		)
	);
} catch ( Exception $e ) {
	$refused = $e->getMessage();
}

dc_check( 'a review submission is refused', null !== $refused );

// WooCommerce writes '' for the default type on older WordPress and 'comment'
// on 5.5+. A blocklist that only recognised one of them would let the other
// through, so both spellings are asserted rather than assumed equivalent.
$passed_through = $dc->reject_blocked_comment_type(
	array(
		'comment_type'    => 'comment',
		'comment_content' => 'dc smoke plain comment',
	)
);
dc_check( 'an ordinary comment passes through untouched', is_array( $passed_through ) );

$passed_through_legacy = $dc->reject_blocked_comment_type(
	array(
		'comment_type'    => '',
		'comment_content' => 'dc smoke legacy-typed comment',
	)
);
dc_check( 'so does one carrying the pre-5.5 empty type', is_array( $passed_through_legacy ) );

remove_filter( 'wp_die_handler', 'dc_blocklist_die_handler', 9999 );

// ---------------------------------------------------------------------------
echo "\n-- REST is refused with an error, not a fatal --\n";

$rest_result = $dc->reject_blocked_comment_type_rest(
	array( 'comment_type' => 'review' ),
	null
);
dc_check( 'a review over REST returns WP_Error', is_wp_error( $rest_result ) );
dc_check(
	'and it carries a 403',
	is_wp_error( $rest_result ) && 403 === (int) $rest_result->get_error_data()['status']
);

$rest_ok = $dc->reject_blocked_comment_type_rest( array( 'comment_type' => 'comment' ), null );
dc_check( 'an ordinary comment over REST is untouched', is_array( $rest_ok ) );

// An earlier filter may have refused the request and returned nothing. Passing
// that through keeps its refusal; replacing it would mask a stricter setting.
dc_check(
	'a request already refused upstream is left as it was',
	null === $dc->reject_blocked_comment_type_rest( null, null )
);

// ---------------------------------------------------------------------------
echo "\n-- a moderator is exempt --\n";

wp_set_current_user( 1 );

$moderator_review = $dc->reject_blocked_comment_type( array( 'comment_type' => 'review' ) );
dc_check( 'someone who can moderate comments may still add one', is_array( $moderator_review ) );

wp_set_current_user( 0 );

// ---------------------------------------------------------------------------
echo "\n-- reviews already left are not touched --\n";

$host_post = dc_make_test_post();
$existing_review = wp_insert_comment(
	array(
		'comment_post_ID'  => $host_post,
		'comment_content'  => 'dc smoke pre-existing review',
		'comment_type'     => 'review',
		'comment_approved' => '1',
		'comment_author'   => 'DC Smoke',
	)
);

// Closing a comment type is about what may be submitted from now on. The rows
// already in the table are the store's own content and stay exactly where they
// are — wp_insert_comment() does not run preprocess_comment, which is the
// documented line between "a submission" and "a write by the site itself".
dc_check( 'an existing review is still stored', $existing_review > 0 );
dc_check(
	'and still readable',
	null !== get_comment( $existing_review )
);

// The allowlist protects a type from deletion. The blocklist is the opposite
// instruction, so it must not: a store closing reviews may well want to clear
// the ones it has. get_all_comment_types() is driven off rows that exist, which
// is why the review above had to be created first.
$deletable = $dc->get_all_comment_types();
dc_check( 'a blocked type is still offered for deletion', isset( $deletable['review'] ) );

// ---------------------------------------------------------------------------
echo "\n-- WooCommerce --\n";

if ( $dc->is_woocommerce_active() ) {
	$product_id = wp_insert_post(
		array(
			'post_title'     => 'DC smoke blocklist product ' . uniqid(),
			'post_status'    => 'publish',
			'post_type'      => 'product',
			'comment_status' => 'open',
		),
		true
	);
	dc_check( 'a product was created', ! is_wp_error( $product_id ) );

	// The whole point of the feature. If this ever reads false, the blocklist
	// has been implemented by closing the post instead of the comment type,
	// and every other comment type on products has gone down with it.
	dc_check(
		'comments stay OPEN on the product while reviews are closed',
		true === comments_open( $product_id )
	);

	$status = $dc->get_product_review_status();

	dc_check( 'reviews report as disabled', true === $status['reviews_disabled'] );
	dc_check( 'the blocklist is disclosed', true === $status['reviews_blocklisted'] );
	dc_check( 'and this plugin is named as the blocker', 'disable-comments' === $status['disabled_by'] );

	dc_check(
		'the Reviews tab is dropped from the product page',
		false !== has_filter( 'woocommerce_product_tabs', array( $dc, 'remove_woocommerce_reviews_tab' ) )
	);

	$tabs = $dc->remove_woocommerce_reviews_tab(
		array(
			'description' => array( 'title' => 'Description' ),
			'reviews'     => array( 'title' => 'Reviews' ),
		)
	);
	dc_check( 'reviews really is the tab removed', ! isset( $tabs['reviews'] ) );
	dc_check( 'and the other tabs are left alone', isset( $tabs['description'] ) );
} else {
	echo "  SKIP  WooCommerce is not active in this sandbox\n";

	// Everything above still has to be true without a store: the blocklist is
	// a generic comment-type setting that WooCommerce only motivated.
	$status = $dc->get_product_review_status();
	dc_check( 'the review status degrades quietly', false === $status['woocommerce_active'] );
	dc_check( 'and reports no blocklist rather than erroring', false === $status['reviews_blocklisted'] );
}

// ---------------------------------------------------------------------------
echo "\n-- a type ticked in both lists resolves to closed --\n";

$dc->disable_comments_settings(
	array(
		'mode'                  => 'selected_types',
		'disabled_types'        => array(),
		'allowed_comment_types' => array( 'note', 'review' ),
		'blocked_comment_types' => array( 'review' ),
	)
);

dc_check(
	'the contradiction is not stored',
	! in_array( 'review', (array) $dc->get_allowed_comment_types_list(), true )
);
dc_check(
	'the type that was only allowlisted is untouched',
	in_array( 'note', (array) $dc->get_allowed_comment_types_list(), true )
);
dc_check(
	'and the blocklist keeps it',
	in_array( 'review', (array) $dc->get_blocked_comment_types_list(), true )
);

// ---------------------------------------------------------------------------
echo "\n-- a partial update does not silently reopen the type --\n";

// The settings screen posts every field, so an omitted blocklist there means
// "cleared". A partial programmatic update means "unchanged" — the Abilities
// API writes one this way, and reopening a closed comment type as a side
// effect of changing the base mode would be a security-shaped surprise.
$dc->apply_settings( array( 'mode' => 'selected_types', 'disabled_types' => array( 'post' ) ), false, true );

dc_check(
	'the blocklist survives a partial write that omits it',
	in_array( 'review', (array) $dc->get_blocked_comment_types_list(), true )
);

// ---------------------------------------------------------------------------
echo "\n-- Abilities API --\n";

if ( function_exists( 'wp_get_ability' ) ) {
	wp_set_current_user( 1 );
	$ability = wp_get_ability( 'disable-comments/get-status' );

	if ( $ability ) {
		$payload = $ability->execute();

		dc_check( 'the ability still validates against its schema', ! is_wp_error( $payload ) );

		if ( ! is_wp_error( $payload ) ) {
			dc_check( 'the payload discloses the blocklist', isset( $payload['blocked_comment_types'] ) );
			dc_check(
				'and names the closed type',
				isset( $payload['blocked_comment_types'] )
					&& in_array( 'review', (array) $payload['blocked_comment_types'], true )
			);
			dc_check(
				'while the allowlist no longer claims it',
				isset( $payload['allowed_comment_types'] )
					&& ! in_array( 'review', (array) $payload['allowed_comment_types'], true )
			);
		}
	}
} else {
	echo "  SKIP  Abilities API not available on this WordPress\n";
}

dc_smoke_finish( 'comment-type-blocklist' );
