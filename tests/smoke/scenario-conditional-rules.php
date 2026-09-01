<?php
/**
 * Conditional rules: taxonomy, page template and auto-close.
 *
 * Assertions go through comments_open(), not the decision helper, so the
 * filter wiring is covered as well as the logic — a rule that evaluates
 * correctly but never reaches a filter is still a rule that does nothing.
 *
 * The runner seeds enable_conditional_rules before this file runs. That
 * matters: the per-post filters are registered at wp_loaded based on the
 * construct-time options, so a run that started with the feature off would
 * pass the logic and prove nothing about the wiring.
 *
 * Each configuration is applied through the real save handler, which updates
 * the in-memory options as well as persisting them.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

/**
 * A REST request asking for one post's comments.
 *
 * @param int $post_id Post id.
 * @return WP_REST_Request
 */
function dc_comment_query_request( $post_id ) {
	$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
	$request->set_param( 'post', $post_id );

	return $request;
}

dc_check( 'the runner enabled conditional rules before construct', $dc->has_conditional_rules() );

/**
 * Apply a configuration through the save handler.
 *
 * @param Disable_Comments $dc   Plugin instance.
 * @param array            $args Form data.
 */
function dc_save( $dc, $args ) {
	$args = array_merge(
		array(
			'mode'                     => 'selected_types',
			'disabled_types'           => array(),
			'enable_conditional_rules' => 1,
			'auto_close_days'          => 0,
			'conditional_rules'        => array(),
		),
		$args
	);
	$dc->disable_comments_settings( $args );
}

$closed_cat = wp_create_category( 'dc-smoke-closed-' . uniqid() );
$open_cat   = wp_create_category( 'dc-smoke-open-' . uniqid() );

$in_closed_cat = dc_make_test_post( 'post' );
$in_open_cat   = dc_make_test_post( 'post' );
wp_set_post_categories( $in_closed_cat, array( $closed_cat ) );
wp_set_post_categories( $in_open_cat, array( $open_cat ) );

// ---------------------------------------------------------------------------
echo "\n-- taxonomy rule: disable in one category --\n";

dc_save(
	$dc,
	array(
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'disable',
				'taxonomy' => 'category',
				'terms'    => array( $closed_cat ),
			),
		),
	)
);

dc_check( 'closed on a post in the targeted category', false === comments_open( $in_closed_cat ) );
dc_check( 'open on a post outside it', true === comments_open( $in_open_cat ) );

// ---------------------------------------------------------------------------
echo "\n-- exception rule beats the global toggle --\n";

dc_save(
	$dc,
	array(
		'mode'              => 'remove_everywhere',
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'enable',
				'taxonomy' => 'category',
				'terms'    => array( $open_cat ),
			),
		),
	)
);

dc_check( 'the global toggle still closes a post with no exception', false === comments_open( $in_closed_cat ) );
dc_check( 'an exception reopens a post the global toggle would close', true === comments_open( $in_open_cat ) );

// ---------------------------------------------------------------------------
echo "\n-- page template rule --\n";

$templated = dc_make_test_post( 'page' );
$plain     = dc_make_test_post( 'page' );
update_post_meta( $templated, '_wp_page_template', 'template-smoke.php' );

dc_save(
	$dc,
	array(
		'conditional_rules' => array(
			array(
				'type'     => 'template',
				'action'   => 'disable',
				'template' => 'template-smoke.php',
			),
		),
	)
);

dc_check( 'closed on a page using the targeted template', false === comments_open( $templated ) );
dc_check( 'open on a page using no template', true === comments_open( $plain ) );

// ---------------------------------------------------------------------------
echo "\n-- auto-close by age --\n";

$fresh = dc_make_test_post( 'post' );
$stale = dc_make_test_post( 'post' );
wp_update_post(
	array(
		'ID'            => $stale,
		'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
	)
);

dc_save( $dc, array( 'auto_close_days' => 30 ) );

dc_check( 'a post past the auto-close window is closed', false === comments_open( $stale ) );
dc_check( 'a post inside the window stays open', true === comments_open( $fresh ) );

// An exception is a deliberate "keep this open"; the age limit must not
// quietly override it later.
wp_set_post_categories( $stale, array( $open_cat ) );
dc_save(
	$dc,
	array(
		'auto_close_days'   => 30,
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'enable',
				'taxonomy' => 'category',
				'terms'    => array( $open_cat ),
			),
		),
	)
);

dc_check( 'an exception survives the auto-close window', true === comments_open( $stale ) );

// ---------------------------------------------------------------------------
echo "\n-- rules off means nothing changes --\n";

dc_save(
	$dc,
	array(
		'enable_conditional_rules' => 0,
		'auto_close_days'          => 30,
		'conditional_rules'        => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'disable',
				'taxonomy' => 'category',
				'terms'    => array( $closed_cat ),
			),
		),
	)
);

dc_check( 'a stored rule does nothing while the feature is off', true === comments_open( $in_closed_cat ) );
dc_check( 'the age limit does nothing while the feature is off', true === comments_open( $stale ) );
dc_check( 'has_conditional_rules() reports off', false === $dc->has_conditional_rules() );
dc_check( 'get_auto_close_days() reports 0 while off', 0 === $dc->get_auto_close_days() );

// ---------------------------------------------------------------------------
echo "\n-- sanitisation --\n";

dc_save(
	$dc,
	array(
		'auto_close_days'   => '-5',
		'conditional_rules' => array(
			// Complete: kept.
			array(
				'type'     => 'taxonomy',
				'action'   => 'enable',
				'taxonomy' => 'category',
				'terms'    => array( (string) $open_cat, '0', '' ),
			),
			// No terms - would sit in the options looking active, matching nothing.
			array(
				'type'     => 'taxonomy',
				'action'   => 'disable',
				'taxonomy' => 'category',
				'terms'    => array(),
			),
			// Unknown type.
			array(
				'type'   => 'evil',
				'action' => 'disable',
			),
			// Template rule with no template.
			array(
				'type'     => 'template',
				'action'   => 'disable',
				'template' => '',
			),
			// Complete: kept.
			array(
				'type'     => 'template',
				'action'   => 'disable',
				'template' => 'template-full.php',
			),
		),
	)
);

$rules = $dc->get_conditional_rules();

dc_check( 'incomplete and unknown rules are dropped', 2 === count( $rules ) );
dc_check( 'the surviving taxonomy rule kept its action', 'enable' === $rules[0]['action'] );
dc_check( 'empty term ids are stripped and the rest cast to int', array( (int) $open_cat ) === $rules[0]['terms'] );
dc_check( 'the surviving template rule kept its template', 'template-full.php' === $rules[1]['template'] );
dc_check( 'a negative auto-close window is floored at zero', 0 === $dc->get_auto_close_days() );

// An unknown action must not become an accidental exception.
dc_save(
	$dc,
	array(
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'something-else',
				'taxonomy' => 'category',
				'terms'    => array( $closed_cat ),
			),
		),
	)
);
$rules = $dc->get_conditional_rules();
dc_check( 'an unrecognised action falls back to disable, not enable', 'disable' === $rules[0]['action'] );

// ---------------------------------------------------------------------------
echo "\n-- a rule naming a taxonomy that no longer exists --\n";

dc_save(
	$dc,
	array(
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'disable',
				'taxonomy' => 'dc_smoke_gone',
				'terms'    => array( 999 ),
			),
		),
	)
);

dc_check( 'the rule is kept in storage rather than silently dropped', 1 === count( $dc->get_conditional_rules() ) );
dc_check( 'but it matches nothing while the taxonomy is unregistered', true === comments_open( $in_closed_cat ) );

// ---------------------------------------------------------------------------
echo "\n-- reporting --\n";

dc_check( 'is_configured() counts a rules-only setup', $dc->is_configured() );

// ---------------------------------------------------------------------------
echo "\n-- an exception reaches every enforcement path, not just comments_open --\n";

// The bug this covers: comments_open() said open while check_comment_template()
// still consulted the base settings and substituted the empty template, so the
// "exception" produced a post with no comment form on it. An exception that
// only reaches one of the enforcement paths is not an exception.
dc_save(
	$dc,
	array(
		'mode'              => 'remove_everywhere',
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'enable',
				'taxonomy' => 'category',
				'terms'    => array( $open_cat ),
			),
		),
	)
);

/**
 * Does the comments template get replaced with the plugin's empty one?
 *
 * check_comment_template() only acts on a singular query, so the global query
 * has to actually be on the post.
 */
function dc_template_blanked( $dc, $post_id ) {
	global $wp_query, $post;

	$saved_query = $wp_query;
	$saved_post  = $post;

	$wp_query = new WP_Query( array( 'p' => $post_id, 'post_type' => 'any' ) );
	$wp_query->the_post();

	remove_all_filters( 'comments_template' );
	$dc->check_comment_template();
	$blanked = has_filter( 'comments_template', array( $dc, 'dummy_comments_template' ) );

	wp_reset_postdata();
	$wp_query = $saved_query;
	$post     = $saved_post;

	return false !== $blanked;
}

dc_check( 'a post with no exception still has its template blanked', true === dc_template_blanked( $dc, $in_closed_cat ) );
dc_check( 'a post kept open by an exception keeps its template', false === dc_template_blanked( $dc, $in_open_cat ) );

// And the same decision has to reach the REST create path.
$blocked = $dc->disable_rest_API_comments(
	array( 'comment_post_ID' => $in_closed_cat ),
	new WP_REST_Request( 'POST', '/wp/v2/comments' )
);
$allowed = $dc->disable_rest_API_comments(
	array( 'comment_post_ID' => $in_open_cat ),
	new WP_REST_Request( 'POST', '/wp/v2/comments' )
);

dc_check( 'REST creation is still blocked where no exception applies', null === $blocked );

// Reading has to agree with creating. Letting the request past pre-dispatch and
// then forcing an empty result set returns a 200 with nothing in it — a subtler
// kind of wrong than a 403.
$closed_query = $dc->filter_rest_comment_query( array(), dc_comment_query_request( $in_closed_cat ) );
$open_query   = $dc->filter_rest_comment_query( array(), dc_comment_query_request( $in_open_cat ) );

dc_check( 'the comment query is still emptied where no exception applies', array( 0 ) === $closed_query['comment__in'] );
dc_check( 'but not on an excepted post', ! isset( $open_query['comment__in'] ) );
dc_check( 'REST creation goes through on an excepted post', is_array( $allowed ) );

// ---------------------------------------------------------------------------
echo "\n-- the dedicated REST toggle is not overridden by an exception --\n";

// "Disable comments via REST API" is a statement about the API, not about any
// one post. An exception rule must not quietly switch it back on — which is
// exactly what the first version of the exception fix did.
dc_save(
	$dc,
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'remove_rest_API_comments' => 1,
		'conditional_rules'        => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'enable',
				'taxonomy' => 'category',
				'terms'    => array( $open_cat ),
			),
		),
	)
);

$excepted = $dc->disable_rest_API_comments(
	array( 'comment_post_ID' => $in_open_cat ),
	new WP_REST_Request( 'POST', '/wp/v2/comments' )
);
$fresh_post_result = $dc->disable_rest_API_comments(
	array( 'comment_post_ID' => dc_make_test_post( 'post' ) ),
	new WP_REST_Request( 'POST', '/wp/v2/comments' )
);

dc_check( 'the REST toggle still blocks an excepted post', null === $excepted );
dc_check( 'and a post no rule mentions', null === $fresh_post_result );

// With the toggle off, the exception works again.
dc_save(
	$dc,
	array(
		'mode'                     => 'remove_everywhere',
		'remove_rest_API_comments' => 0,
		'conditional_rules'        => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'enable',
				'taxonomy' => 'category',
				'terms'    => array( $open_cat ),
			),
		),
	)
);

dc_check(
	'without the toggle, the exception still opens REST',
	is_array( $dc->disable_rest_API_comments( array( 'comment_post_ID' => $in_open_cat ), new WP_REST_Request( 'POST', '/wp/v2/comments' ) ) )
);

// ---------------------------------------------------------------------------
echo "\n-- switching the feature off does not delete the rules --\n";

dc_save(
	$dc,
	array(
		'conditional_rules' => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'disable',
				'taxonomy' => 'category',
				'terms'    => array( $closed_cat ),
			),
		),
	)
);

// Switch the feature off the way the form does — resubmitting whatever the
// screen rendered.
// Note: no `conditional_rules` key. The handler keeps the stored value for
// anything the payload omits, which is what "just switch the feature off"
// looks like.
$dc->disable_comments_settings(
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'enable_conditional_rules' => 0,
	)
);

$stored = get_option( 'disable_comments_options' );

dc_check( 'the rule is still stored while the feature is off', 1 === count( (array) $stored['conditional_rules'] ) );
dc_check( 'but get_conditional_rules() reports none, so nothing is enforced', array() === $dc->get_conditional_rules() );

// The real risk is the settings screen rendering blanks for those stored
// rules: the next save would submit the blanks and sanitize_conditional_rules()
// would drop them, turning a temporary "off" into silent deletion.
$render = Closure::bind(
	function () {
		ob_start();
		include DC_PLUGIN_VIEWS_PATH . 'partials/_settings.php';
		return ob_get_clean();
	},
	$dc,
	'Disable_Comments'
);
$html = $render();

dc_check(
	'the screen still renders the saved taxonomy while the feature is off',
	false !== strpos( $html, 'value="category"' )
);
dc_check(
	'and the saved term is still selected',
	false !== strpos( $html, 'value="' . (int) $closed_cat . '" selected' )
);

// ---------------------------------------------------------------------------
echo "\n-- the term picker can reach every term, not just the first 200 --\n";

// A taxonomy big enough to have had the problem. The picker was handed the
// taxonomy's first 200 terms and nothing else, so on a shop with a few thousand
// product categories every term after those 200 was unreachable for a new rule.
register_taxonomy( 'dc_picker_smoke', 'post', array( 'public' => true, 'show_ui' => true ) );

// Terms outlive the registration that created them, so a re-run would count the
// previous run's rows as well and the page assertions below would drift.
$dc_existing = get_terms( array( 'taxonomy' => 'dc_picker_smoke', 'hide_empty' => false, 'fields' => 'ids', 'number' => 0 ) );
if ( ! is_wp_error( $dc_existing ) ) {
	foreach ( $dc_existing as $dc_stale_term ) {
		wp_delete_term( $dc_stale_term, 'dc_picker_smoke' );
	}
}

$page_size = Disable_Comments::TERM_PAGE_SIZE;

// Deliberately past the old ceiling, and named so they sort predictably.
for ( $i = 1; $i <= 210; $i++ ) {
	wp_insert_term( sprintf( 'DC picker %03d', $i ), 'dc_picker_smoke' );
}

$first = $dc->get_taxonomy_terms_page( 'dc_picker_smoke' );

dc_check( 'a request comes back one page long', $page_size === count( $first['terms'] ) );
dc_check( 'and says there is more behind it', true === $first['more'] );
dc_check( 'starting at the top of the taxonomy', 'DC picker 001' === $first['terms'][0]['name'] );
dc_check( 'the answer names the taxonomy it is for', 'dc_picker_smoke' === $first['taxonomy'] );

// The term this is all about: number 205, which nothing could reach before.
$beyond = 'DC picker 205';

dc_check(
	'a term past the old 200-term ceiling is not on the first page',
	! in_array( $beyond, wp_list_pluck( $first['terms'], 'name' ), true )
);

$last = $dc->get_taxonomy_terms_page( 'dc_picker_smoke', '', 5 );

dc_check( 'paging reaches it', in_array( $beyond, wp_list_pluck( $last['terms'], 'name' ), true ) );
dc_check( 'and the last page admits it is the last', false === $last['more'] );

// Which is the long way round. Searching is how anyone would actually find it.
$found = $dc->get_taxonomy_terms_page( 'dc_picker_smoke', '205' );

dc_check( 'searching finds it without paging at all', 1 === count( $found['terms'] ) && $beyond === $found['terms'][0]['name'] );
dc_check( 'a search matching nothing returns nothing', array() === $dc->get_taxonomy_terms_page( 'dc_picker_smoke', 'no-such-term-here' )['terms'] );

// A page past the end is empty rather than an error or a wrapped first page.
dc_check( 'a page past the end is simply empty', array() === $dc->get_taxonomy_terms_page( 'dc_picker_smoke', '', 99 )['terms'] );

// Nothing is answered for a taxonomy this site does not have.
dc_check( 'an unregistered taxonomy returns nothing', array() === $dc->get_taxonomy_terms_page( 'dc_not_a_taxonomy' )['terms'] );
dc_check( 'and so does an empty one', array() === $dc->get_taxonomy_terms_page( '' )['terms'] );

// The endpoint enumerates every term on the site, so it stays behind the nonce
// and manage_options check in the AJAX wrapper - and off the logged-out hook.
dc_check(
	'the term endpoint is still wired for logged-in callers',
	false !== has_action( 'wp_ajax_disable_comments_get_terms', array( $dc, 'get_taxonomy_terms' ) )
);
dc_check(
	'and is not exposed to logged-out ones',
	false === has_action( 'wp_ajax_nopriv_disable_comments_get_terms' )
);

// The screen does not ship a fixed list of the taxonomy either: the saved terms
// are rendered because an option that is not rendered is not submitted, and
// everything else is searched for on demand.
$unused_name = 'DC picker unused ' . uniqid();
$unused      = wp_insert_term( $unused_name, 'category' );
$html        = $render();

dc_check( 'the saved term is still rendered', false !== strpos( $html, 'value="' . (int) $closed_cat . '" selected' ) );
dc_check( 'but a term nothing selected is not shipped with the page', false === strpos( $html, $unused_name ) );

if ( ! is_wp_error( $unused ) ) {
	wp_delete_term( (int) $unused['term_id'], 'category' );
}

// These panels used to be small <h4> sub-labels stacked inside the Disable
// Comments tab, so they read as minor items rather than features. They are now
// top-level sections on a tab of their own — a structural promise, not a
// cosmetic one, since the heading level is what conveys the hierarchy to a
// screen reader as much as to the eye.
dc_check(
	'the conditional rules panel is a top-level section heading',
	1 === preg_match( '/<h3 id="conditional-rules-heading" class="title">/', $html )
);
dc_check(
	'and the heading id survived, so aria-labelledby still resolves',
	false !== strpos( $html, 'aria-labelledby="conditional-rules-heading"' )
);

// Moved, not copied. Two panels writing the same field names into one form
// would submit whichever the browser serialised last.
$render_disable_tab = Closure::bind(
	function () {
		ob_start();
		include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php';
		return ob_get_clean();
	},
	$dc,
	'Disable_Comments'
);
$first_tab = $render_disable_tab();

foreach ( array( 'conditional_rules_wrapper', 'blocked_attempts_wrapper', 'settings_portability_wrapper', 'theme_scanner_wrapper' ) as $moved ) {
	dc_check( "{$moved} left the Disable Comments tab", false === strpos( $first_tab, $moved ) );
	dc_check( "and arrived on the Settings tab", false !== strpos( $html, $moved ) );
}

// The core disable choice must not have followed them across.
dc_check( 'the post-type checklist stayed on the Disable Comments tab', false !== strpos( $first_tab, 'disable__post__types' ) );
dc_check( 'and did not leak onto the Settings tab', false === strpos( $html, 'disable__post__types' ) );

// The term picker is upgraded to select2 by the settings JS, which finds it by
// class. As a bare <select multiple> it is effectively one-way: a plain click
// replaces the selection and removing a term needs a Ctrl/Cmd-click almost
// nobody finds. Nothing here can run the JS, so assert the hook it needs —
// dropping the class would silently return the control to that state.
dc_check(
	'the term picker is marked for the select2 upgrade',
	1 === preg_match( '/<select id="conditional_rule_terms"\s+class="[^"]*\bdc-select2\b/', $html )
);

dc_smoke_finish( 'conditional-rules' );
