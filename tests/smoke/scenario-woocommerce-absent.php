<?php
/**
 * Every WooCommerce touch-point with WooCommerce not installed.
 *
 * This is the half that matters for the 1.7M sites that will never run a
 * store. A settings screen that fatals, warns, or invents a "Product reviews"
 * control on a site with no products is a regression for almost everybody.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

dc_check( 'the runner deactivated WooCommerce', false === $dc->is_woocommerce_active() );

// ---------------------------------------------------------------------------
echo "\n-- nothing WooCommerce-shaped is offered --\n";

$options = $dc->get_available_comment_type_options();
dc_check( 'review is not offered as a known comment type', !isset( $options['review'] ) );
dc_check( 'but the WP 6.9 note type still is', isset( $options['note'] ) );

// ---------------------------------------------------------------------------
echo "\n-- status reporting degrades quietly --\n";

$status = $dc->get_product_review_status();

dc_check( 'status is still an array, not an error', is_array( $status ) );
dc_check( 'WooCommerce is reported inactive', false === $status['woocommerce_active'] );
dc_check( 'reviews are not claimed to be disabled', false === $status['reviews_disabled'] );
dc_check( 'nor claimed to be allowlisted', false === $status['reviews_allowlisted'] );
dc_check( 'nor claimed to be blocklisted', false === $status['reviews_blocklisted'] );

// Even with comments off everywhere, there are no reviews to report on, and
// claiming otherwise would be a false statement to an agent.
$dc->disable_comments_settings( array( 'mode' => 'remove_everywhere' ) );
$status = $dc->get_product_review_status();

dc_check( 'disable-everywhere does not invent a disabled review state', false === $status['reviews_disabled'] );

// Same for the blocklist. `review` can be stored on a site that has never had
// WooCommerce — an imported settings file is enough — and reporting reviews as
// closed on a site with no products would be a statement about nothing.
$dc->disable_comments_settings(
	array(
		'mode'                  => 'selected_types',
		'disabled_types'        => array(),
		'blocked_comment_types' => array( 'review' ),
	)
);
$status = $dc->get_product_review_status();

dc_check( 'a stored review blocklist does not invent one either', false === $status['reviews_disabled'] );
dc_check( 'and the WooCommerce hooks stay unregistered', false === has_filter( 'woocommerce_product_tabs', array( $dc, 'remove_woocommerce_reviews_tab' ) ) );

// ---------------------------------------------------------------------------
echo "\n-- no notices or warnings from the settings screen --\n";

$errors = array();
set_error_handler(
	function ( $errno, $errstr ) use ( &$errors ) {
		$errors[] = $errstr;
		return true;
	}
);

$dc->is_woocommerce_active();
$dc->get_product_review_status();
$dc->get_available_comment_type_options();
$dc->get_all_comment_types();

restore_error_handler();

dc_check( 'the WooCommerce paths raise nothing', empty( $errors ) );

// ---------------------------------------------------------------------------
echo "\n-- the settings view renders --\n";

// The panel sits inside `if ($this->is_woocommerce_active())`, so rendering
// the partial is the real check that it stays out of the way. The view reads
// $this->options, so it has to be included with $this bound to the plugin —
// including it from CLI scope would only prove that CLI scope has no options.
$render = Closure::bind(
	function () {
		ob_start();
		include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php';
		return ob_get_clean();
	},
	$dc,
	'Disable_Comments'
);

$errors = array();
set_error_handler(
	function ( $errno, $errstr ) use ( &$errors ) {
		$errors[] = $errstr;
		return true;
	}
);

$rendered = true;
$html     = '';
try {
	$html = $render();
} catch ( Throwable $e ) {
	$rendered = false;
}

restore_error_handler();

dc_check( 'rendering the settings partial raises no warnings', empty( $errors ) );

dc_check( 'the settings partial renders without throwing', $rendered );
dc_check( 'and contains no WooCommerce panel', false === strpos( (string) $html, 'woocommerce_reviews_wrapper' ) );

// ---------------------------------------------------------------------------
echo "\n-- Abilities API --\n";

if ( function_exists( 'wp_get_ability' ) ) {
	wp_set_current_user( 1 );
	$ability = wp_get_ability( 'disable-comments/get-status' );

	if ( $ability ) {
		$payload = $ability->execute();

		dc_check( 'the ability still validates against its schema', !is_wp_error( $payload ) );

		if ( !is_wp_error( $payload ) ) {
			dc_check( 'the payload reports WooCommerce inactive', false === $payload['woocommerce_active'] );
			dc_check( 'and does not claim reviews are disabled', false === $payload['product_reviews_disabled'] );
		}
	}
} else {
	echo "  SKIP  Abilities API not available on this WordPress\n";
}

dc_smoke_finish( 'woocommerce-absent' );
