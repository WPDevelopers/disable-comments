<?php
/**
 * Theme conflict scanner.
 *
 * The plugin's #1 FAQ has been "the comment form still appears — your theme
 * checks comment status wrong" for over a decade, answered with a link to a
 * blog post. This scanner turns that into a diagnosis.
 *
 * Two layers are tested:
 *
 *  1. interpret_theme_scan() maps findings to a verdict. Pure, no HTTP, always
 *     runs — including on CI where loopback may not work.
 *  2. A real end-to-end scan over HTTP against a co-operating theme and a
 *     deliberately broken one. Skipped, loudly, when the front end is not
 *     reachable, because the alternative is a green run that proved nothing.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

/**
 * Build a findings array with sensible defaults, overridden per case.
 */
function dc_scan_fixture( $overrides = array() ) {
	return array_merge(
		array(
			'url'                => 'http://example.test/hello-world/',
			'reached'            => true,
			'template_called'    => true,
			'comments_template'  => '/themes/x/comments.php',
			'dummy_used'         => true,
			'comments_open'      => false,
			'markup_found'       => array(),
			'preserved'          => false,
			'theme'              => 'Test Theme',
			'theme_comments_php' => '/themes/x/comments.php',
			'x_cache'            => '',
			'http_code'          => 200,
		),
		$overrides
	);
}

// ---------------------------------------------------------------------------
echo "\n-- verdicts --\n";

$clean = $dc->interpret_theme_scan( dc_scan_fixture() );
dc_check( 'a co-operating theme is clean', 'clean' === $clean['verdict'] );

// The marker is printed from inside the scanned request. Without it we cannot
// tell a co-operating theme from a cached page, so we must not claim success.
$cached = $dc->interpret_theme_scan( dc_scan_fixture( array( 'reached' => false ) ) );
dc_check( 'a page we could not report from is inconclusive, not clean', 'inconclusive' === $cached['verdict'] );
dc_check( 'and the message blames the cache, not the theme', false !== strpos( $cached['message'], 'cache' ) );

// Nothing to hide yet — saying "your theme is broken" here would be wrong.
$open = $dc->interpret_theme_scan( dc_scan_fixture( array( 'comments_open' => true, 'markup_found' => array( 'comment_form' ) ) ) );
dc_check( 'comments still open is reported as a settings problem', 'not_disabled' === $open['verdict'] );

// The classic bug: theme draws the comment area itself.
$ignores = $dc->interpret_theme_scan(
	dc_scan_fixture(
		array(
			'template_called' => false,
			'dummy_used'      => false,
			'markup_found'    => array( 'comment_form' ),
		)
	)
);
dc_check( 'a theme that never calls comments_template() is named', 'theme_ignores_template' === $ignores['verdict'] );
dc_check( 'and the message names the theme', false !== strpos( $ignores['message'], 'Test Theme' ) );

// Something else won the comments_template filter.
$overridden = $dc->interpret_theme_scan(
	dc_scan_fixture(
		array(
			'dummy_used'   => false,
			'markup_found' => array( 'comment_form' ),
		)
	)
);
dc_check( 'an overridden template is distinguished from an ignored one', 'template_overridden' === $overridden['verdict'] );
dc_check( 'and the message names the template that rendered', false !== strpos( $overridden['message'], 'comments.php' ) );

// Our template went in, but markup is on the page from somewhere else.
$outside = $dc->interpret_theme_scan( dc_scan_fixture( array( 'markup_found' => array( 'comment_list' ) ) ) );
dc_check( 'markup outside the template is its own verdict', 'markup_outside_template' === $outside['verdict'] );

// When several pages are checked, the reported one has to be the page with the
// real problem — a clean verdict from a different template is precisely how a
// user gets told there is nothing to fix.
$rank = Closure::bind(
	function ( $verdict ) {
		return $this->scan_verdict_rank( $verdict );
	},
	$dc,
	'Disable_Comments'
);

dc_check( 'a broken theme outranks a clean one', $rank( 'theme_ignores_template' ) > $rank( 'clean' ) );
dc_check( 'and outranks a deliberately preserved one', $rank( 'theme_ignores_template' ) > $rank( 'preserved_by_setting' ) );
dc_check( 'an inconclusive result outranks clean', $rank( 'inconclusive' ) > $rank( 'clean' ) );
dc_check( 'an unknown verdict does not outrank a real problem', $rank( 'something-else' ) < $rank( 'markup_outside_template' ) );

// A deliberately preserved comment area is not a theme conflict.
$preserved = $dc->interpret_theme_scan(
	dc_scan_fixture(
		array(
			'preserved'    => true,
			'dummy_used'   => false,
			'markup_found' => array( 'comment_list' ),
		)
	)
);
dc_check( 'a preserved comment area is not blamed on the theme', 'preserved_by_setting' === $preserved['verdict'] );
dc_check( 'and the message points at the setting', false !== strpos( $preserved['message'], 'Show Existing Comments' ) );

// ---------------------------------------------------------------------------
echo "\n-- markup detection ignores CSS, JS and text --\n";

// A theme that merely styles its comment area must not be reported as
// rendering one. This is checked through the real scan path below; here the
// stripping itself is exercised via a response that only mentions the classes.
$strip = Closure::bind(
	function ( $body ) {
		$found = array();
		$markup = $this->strip_non_markup( $body );
		foreach ( $this->get_comment_markup_signals() as $signal => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $markup ) ) {
					$found[] = $signal;
					break;
				}
			}
		}
		return $found;
	},
	$dc,
	'Disable_Comments'
);

dc_check(
	'inline CSS mentioning .comment-form is not markup',
	array() === $strip( '<html><head><style>.comment-form{display:none}.comment-list{margin:0}</style></head><body><p>Hello</p></body></html>' )
);
dc_check(
	'inline JS mentioning the selectors is not markup',
	array() === $strip( '<html><body><script>var x = ".comment-form"; document.querySelector("#comments");</script></body></html>' )
);
dc_check(
	'the words in a text node are not markup',
	array() === $strip( '<html><body><p>Our comment-form and comment-list are disabled.</p></body></html>' )
);
dc_check(
	'a real comment form still is',
	in_array( 'comment_form', $strip( '<html><body><form id="commentform" method="post"></form></body></html>' ), true )
);
dc_check(
	'and a real comment list',
	in_array( 'comment_list', $strip( '<html><body><ol class="comment-list"><li>hi</li></ol></body></html>' ), true )
);

// ---------------------------------------------------------------------------
echo "\n-- the probe is inert without a valid token --\n";

// A visitor must never be able to make the site emit its internals.
$_GET[ Disable_Comments::SCAN_QUERY_ARG ] = 'not-the-real-token';
$dc->maybe_arm_scan_probe();
dc_check(
	'a wrong token arms nothing',
	false === has_action( 'wp_footer', array( $dc, 'print_scan_marker' ) )
);
unset( $_GET[ Disable_Comments::SCAN_QUERY_ARG ] );

$dc->maybe_arm_scan_probe();
dc_check(
	'no token at all arms nothing',
	false === has_action( 'wp_footer', array( $dc, 'print_scan_marker' ) )
);

// ---------------------------------------------------------------------------
echo "\n-- a target is only chosen where comments are actually off --\n";

$dc->disable_comments_settings( array( 'mode' => 'selected_types', 'disabled_types' => array() ) );
dc_check( 'no target when nothing is disabled', false === $dc->find_scan_target() );

$dc->disable_comments_settings( array( 'mode' => 'selected_types', 'disabled_types' => array( 'post' ) ) );
$target = $dc->find_scan_target();
dc_check( 'a post is chosen once posts are disabled', is_int( $target ) && $target > 0 );
dc_check( 'and it is a published post', 'publish' === get_post_status( $target ) );

// Checking one arbitrary post is how a scanner reports "clean" while the page
// the user is complaining about is broken: themes routinely hand different post
// types to different templates.
dc_make_test_post( 'page' );
$dc->disable_comments_settings( array( 'mode' => 'selected_types', 'disabled_types' => array( 'post', 'page' ) ) );
$targets = $dc->find_scan_targets();

dc_check( 'both disabled post types get a representative', 2 === count( $targets ) );
dc_check( 'and they are different posts', $targets[0] !== $targets[1] );

$types = array_map( 'get_post_type', $targets );
sort( $types );
dc_check( 'one of each type, not two of the newest', array( 'page', 'post' ) === $types );

$dc->disable_comments_settings( array( 'mode' => 'selected_types', 'disabled_types' => array( 'post' ) ) );

// ---------------------------------------------------------------------------
echo "\n-- end to end over HTTP --\n";

$probe = wp_remote_get( get_permalink( $target ), array( 'timeout' => 5, 'sslverify' => false ) );

if ( is_wp_error( $probe ) || 200 !== (int) wp_remote_retrieve_response_code( $probe ) ) {
	echo "  SKIP  the front end is not reachable from here\n";
	echo "        (start it with: bin/wp server --host=127.0.0.1 --port=8888)\n";
	dc_smoke_finish( 'theme-scanner' );
	return;
}

$original_theme = get_stylesheet();

// A theme that calls comments_template() the way WordPress expects.
if ( wp_get_theme( 'dc-correct' )->exists() ) {
	switch_theme( 'dc-correct' );

	$result = $dc->run_theme_scan( get_permalink( $target ) );

	dc_check( 'the scan reached the front end', ! is_wp_error( $result ) );

	if ( ! is_wp_error( $result ) ) {
		$result = $dc->interpret_theme_scan( $result );

		dc_check( 'the probe reported from inside the request', true === $result['reached'] );
		dc_check( 'the theme called comments_template()', true === $result['template_called'] );
		dc_check( 'and our empty template was substituted', true === $result['dummy_used'] );
		dc_check( 'so the verdict is clean', 'clean' === $result['verdict'] );
		dc_check( 'and no comment markup survived', empty( $result['markup_found'] ) );
	}
} else {
	echo "  SKIP  the dc-correct test theme is not installed\n";
}

// A theme that draws the comment area itself — the actual support issue.
if ( wp_get_theme( 'dc-broken' )->exists() ) {
	switch_theme( 'dc-broken' );

	$result = $dc->interpret_theme_scan( $dc->run_theme_scan( get_permalink( $target ) ) );

	dc_check( 'the broken theme is caught', 'theme_ignores_template' === $result['verdict'] );
	dc_check( 'the comment form was seen on the page', in_array( 'comment_form', $result['markup_found'], true ) );
	dc_check( 'the offending theme is named', false !== strpos( $result['message'], 'DC Broken Theme' ) );
	dc_check( 'and it does not point at a core file', false === strpos( (string) $result['theme_comments_php'], 'wp-includes' ) );
} else {
	echo "  SKIP  the dc-broken test theme is not installed\n";
}

switch_theme( $original_theme );

// The one-time token must not survive the scan that used it.
// Each scan holds its own transient slot, so two administrators scanning at
// once cannot overwrite each other's token and both fail validation.
$slots = $GLOBALS['wpdb']->get_var(
	"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '_transient_" . Disable_Comments::SCAN_TOKEN_TRANSIENT . "%'"
);
dc_check( 'no scan token slot is left behind', 0 === (int) $slots );

// ---------------------------------------------------------------------------
echo "\n-- a rules-only site still has something to scan --\n";

// Target discovery required a disabled post type. A site configured purely with
// conditional rules has none, so the scanner answered "no disabled content" to
// exactly the person most likely to need it: someone whose rule closed a post
// and whose theme still draws a comment form on it.
$before = $dc->find_scan_targets();

$term    = wp_insert_term( 'dc-scan-' . uniqid(), 'category' );
$term_id = is_wp_error( $term ) ? 1 : (int) $term['term_id'];

$dc->apply_settings(
	array(
		'enable_conditional_rules' => 1,
		'conditional_rules'        => array(
			array( 'type' => 'taxonomy', 'action' => 'disable', 'taxonomy' => 'category', 'terms' => array( $term_id ) ),
		),
		'disabled_types'           => array(),
	),
	false,
	false
);

$ruled = dc_make_test_post( 'post' );
wp_set_object_terms( $ruled, array( $term_id ), 'category' );

$options = get_option( 'disable_comments_options' );
dc_check( 'nothing is disabled by post type', empty( $options['remove_everywhere'] ) && 0 === count( (array) $options['disabled_post_types'] ) );

$targets = $dc->find_scan_targets();

dc_check( 'the scanner finds the post the rule closed', in_array( $ruled, array_map( 'intval', $targets ), true ) );
dc_check( 'and every target it returns really is closed', ! empty( $targets ) && count( $targets ) === count( array_filter( $targets, array( $dc, 'is_disabled_for_post' ) ) ) );

// With no rules and no disabled types there is genuinely nothing to scan, and
// it must still say so rather than offering an open post.
$dc->apply_settings( array( 'enable_conditional_rules' => 0, 'conditional_rules' => array(), 'disabled_types' => array() ), false, false );
dc_check( 'an unconfigured site still reports nothing to scan', array() === $dc->find_scan_targets() );

// ---------------------------------------------------------------------------
echo "\n-- an auto-close-only site is scanned from the old end too --\n";

// The candidate pool added above took get_posts()'s default order, which is
// newest first. An age rule closes the OLDEST posts, so a site with fifty posts
// still inside the age window handed back fifty open ones and the scanner again
// reported nothing to scan — while every older post was shut.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_title LIKE 'ac-scan-%'" );

$old_ids = array();
for ( $i = 0; $i < 3; $i++ ) {
	$old_ids[] = wp_insert_post(
		array(
			'post_title'     => 'ac-scan-old-' . $i,
			'post_status'    => 'publish',
			'post_date'      => gmdate( 'Y-m-d H:i:s', time() - 200 * DAY_IN_SECONDS ),
			'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - 200 * DAY_IN_SECONDS ),
			'comment_status' => 'open',
		),
		true
	);
}

// More recent posts than the candidate cap, so a newest-first pool sees only
// these — none of which the age rule closes.
for ( $i = 0; $i < 55; $i++ ) {
	wp_insert_post( array( 'post_title' => 'ac-scan-new-' . $i, 'post_status' => 'publish', 'comment_status' => 'open' ), true );
}

$dc->apply_settings(
	array( 'disabled_types' => array(), 'enable_conditional_rules' => 1, 'conditional_rules' => array(), 'auto_close_days' => 30 ),
	false,
	false
);

dc_check( 'the age rule really does close an old post', true === $dc->is_disabled_for_post( $old_ids[0] ) );

$aged = array_map( 'intval', $dc->find_scan_targets() );

dc_check( 'the scanner finds something on an auto-close-only site', ! empty( $aged ) );
dc_check( 'and what it finds includes an old post, not just recent ones', ! empty( array_intersect( $aged, array_map( 'intval', $old_ids ) ) ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_title LIKE 'ac-scan-%'" );

dc_smoke_finish( 'theme-scanner' );
