<?php
/**
 * Blocked-attempt counting.
 *
 * Two things matter here and only one of them is "the number goes up":
 *
 *  1. Counting must not happen on render. The comment status filters run on
 *     every archive page; if a counter were wired to those, an ordinary page
 *     view would inflate the totals and put a write on every request.
 *  2. The write must happen once per request, on shutdown — not per attempt.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

dc_check( 'counting is on by default', $dc->blocked_stats_enabled() );

$dc->reset_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'a reset zeroes the total', 0 === $stats['total'] );
dc_check( 'every vector is present after a reset', 3 === count( $stats['counts'] ) );
dc_check( 'XML-RPC is deliberately not a vector', !isset( $stats['counts']['xmlrpc'] ) );
dc_check( 'the trackback vector is named for what it covers', 'Trackbacks' === $dc->get_blocked_vectors()['trackback'] );

global $wpdb;

// ---------------------------------------------------------------------------
echo "\n-- rendering must not count --\n";

$post_id = dc_make_test_post( 'post' );

// The runner has comments disabled everywhere, so these all take the
// "disabled" branch — the exact path a counter would be tempting to sit on.
for ( $i = 0; $i < 5; $i++ ) {
	comments_open( $post_id );
	get_comments_number( $post_id );
	pings_open( $post_id );
}

$dc->flush_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'reading comment status 15 times counted nothing', 0 === $stats['total'] );

// ---------------------------------------------------------------------------
echo "\n-- a rejected comment submission counts --\n";

// This is the action core fires when it turns a real submission away.
do_action( 'comment_closed', $post_id );
do_action( 'comment_closed', $post_id );

$before_flush = $dc->get_blocked_stats();
dc_check( 'nothing is written before shutdown', 0 === $before_flush['total'] );

$dc->flush_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'both submissions were counted', 2 === $stats['counts']['comment'] );
dc_check( 'they landed on the comment vector only', 2 === $stats['total'] );

// ---------------------------------------------------------------------------
echo "\n-- a trackback to a closed post counts, an open one does not --\n";

// The recorder is called directly rather than by firing pre_trackback_post:
// core hangs wp_maybe_disable_trackback_for_environment() on that action, and
// it calls trackback_response(), which only exists inside wp-trackback.php.
// Firing the action here would fatal on core's listener, not ours — so the
// wiring is asserted separately.
dc_check(
	'the recorder is hooked to core\'s pre_trackback_post',
	false !== has_action( 'pre_trackback_post', array( $dc, 'record_blocked_trackback' ) )
);

$dc->record_blocked_trackback( $post_id );
$dc->flush_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'the blocked trackback was counted', 1 === $stats['counts']['trackback'] );

// A post whose pings are genuinely open must not be counted as blocked. Core
// checks pings_open() straight after firing that action, so the recorder has
// to ask the same question rather than assuming a rejection.
add_filter( 'pings_open', '__return_true', 99 );
$dc->record_blocked_trackback( $post_id );
remove_filter( 'pings_open', '__return_true', 99 );

$dc->flush_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'a trackback that would have gone through was not counted', 1 === $stats['counts']['trackback'] );

// ---------------------------------------------------------------------------
echo "\n-- a blocked REST request counts --\n";

$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
$result  = $dc->filter_rest_comment_dispatch( null, null, $request );

dc_check( 'the request was actually blocked', is_wp_error( $result ) );

$dc->flush_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'the blocked REST request was counted', 1 === $stats['counts']['rest'] );

// A route we do not police must neither be blocked nor counted.
$other  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
$result = $dc->filter_rest_comment_dispatch( null, null, $other );

$dc->flush_blocked_stats();
$stats = $dc->get_blocked_stats();

dc_check( 'an unrelated route passed through', null === $result );

// Another plugin's rejection is not ours to replace or to count. WordPress
// keeps running later rest_pre_dispatch filters after one returns an error.
// No reset here — the running totals are asserted further down.
$rest_before = $dc->get_blocked_stats()['counts']['rest'];
$upstream    = new WP_Error( 'rest_forbidden', 'Blocked by something else', array( 'status' => 401 ) );
$passed      = $dc->filter_rest_comment_dispatch( $upstream, null, new WP_REST_Request( 'POST', '/wp/v2/comments' ) );
$dc->flush_blocked_stats();

dc_check( 'an upstream error is returned unchanged', $upstream === $passed );
dc_check( 'and is not counted as one of our blocks', $rest_before === $dc->get_blocked_stats()['counts']['rest'] );
dc_check( 'and was not counted', 1 === $stats['counts']['rest'] );

// ---------------------------------------------------------------------------
echo "\n-- a blocked REST READ is refused but not counted --\n";

// The number an admin reads as spam pressure must not be padded by traffic
// that never tried to leave a comment: a crawler, an uptime check or a feed
// reader polling /wp/v2/comments. Blocking is unchanged — only the counting
// was over-broad.
foreach ( array( 'GET', 'HEAD', 'OPTIONS' ) as $read_method ) {
	$read_before = $dc->get_blocked_stats()['counts']['rest'];
	$read_result = $dc->filter_rest_comment_dispatch( null, null, new WP_REST_Request( $read_method, '/wp/v2/comments' ) );
	$dc->flush_blocked_stats();

	dc_check( "a {$read_method} to /wp/v2/comments is still blocked", is_wp_error( $read_result ) );
	dc_check( "and is not counted", $read_before === $dc->get_blocked_stats()['counts']['rest'] );
}

// The counter is flushed on shutdown, so counting a read also meant an
// anonymous GET could force a database write on every request. Nothing pending
// means nothing written.
$dc->filter_rest_comment_dispatch( null, null, new WP_REST_Request( 'GET', '/wp/v2/comments' ) );
$queries_before = $wpdb->num_queries;
$dc->flush_blocked_stats();

dc_check( 'a blocked read leaves nothing to flush, so it writes nothing', $queries_before === $wpdb->num_queries );

// ---------------------------------------------------------------------------
echo "\n-- every write verb is still counted --\n";

// Not just POST. A refused PUT, PATCH or DELETE is an attempt the plugin
// turned away, and leaving them out would trade over-counting for
// under-counting.
foreach ( array( 'POST', 'PUT', 'PATCH', 'DELETE' ) as $write_method ) {
	$write_before = $dc->get_blocked_stats()['counts']['rest'];
	$write_result = $dc->filter_rest_comment_dispatch( null, null, new WP_REST_Request( $write_method, '/wp/v2/comments/1' ) );
	$dc->flush_blocked_stats();

	dc_check( "a {$write_method} to a comment route is blocked", is_wp_error( $write_result ) );
	dc_check( "and is counted", ( $write_before + 1 ) === $dc->get_blocked_stats()['counts']['rest'] );
}

// ---------------------------------------------------------------------------
echo "\n-- one write per request, not per attempt --\n";

// Counting queries rather than update_option calls: increments now go through
// one atomic UPDATE per vector, so read-modify-write cannot lose a concurrent
// writer's increment.
$before_queries = $wpdb->num_queries;

for ( $i = 0; $i < 25; $i++ ) {
	do_action( 'comment_closed', $post_id );
}

dc_check( 'nothing is written while the request is still running', $before_queries === $wpdb->num_queries );

$dc->flush_blocked_stats();

$stats = $dc->get_blocked_stats();
dc_check( 'all 25 were counted', 27 === $stats['counts']['comment'] );
dc_check( 'the flush stayed cheap', ( $wpdb->num_queries - $before_queries ) < 10 );

// The increment is done by the database, so two writers cannot clobber each
// other. Simulate the interleaving: read the value, let another request write,
// then flush — the earlier read must not overwrite the later write.
$dc->count_blocked_attempt( 'comment' );
$stale = $dc->get_blocked_stats();
update_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', $stale['counts']['comment'] + 100 );
$dc->flush_blocked_stats();

dc_check(
	'a concurrent write is not clobbered',
	128 === $dc->get_blocked_stats()['counts']['comment']
);

// ---------------------------------------------------------------------------
echo "\n-- flushing with nothing pending writes nothing --\n";

$before_queries = $wpdb->num_queries;
$dc->flush_blocked_stats();
$dc->flush_blocked_stats();

dc_check( 'an empty flush is a no-op', $before_queries === $wpdb->num_queries );

// ---------------------------------------------------------------------------
echo "\n-- the filter turns counting off --\n";

add_filter( 'disable_comments_count_blocked_attempts', '__return_false' );

dc_check( 'blocked_stats_enabled() reports off', false === $dc->blocked_stats_enabled() );

$before = $dc->get_blocked_stats();
do_action( 'comment_closed', $post_id );
$dc->flush_blocked_stats();
$after = $dc->get_blocked_stats();

dc_check( 'nothing is counted while off', $before['total'] === $after['total'] );

remove_filter( 'disable_comments_count_blocked_attempts', '__return_false' );

// ---------------------------------------------------------------------------
echo "\n-- corrupt stored data does not fatal --\n";

update_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', 'not-a-number' );
update_option( Disable_Comments::BLOCKED_SINCE_OPTION, 'not-a-timestamp' );
$stats = $dc->get_blocked_stats();

dc_check( 'a junk count reads as zero rather than fataling', 0 === $stats['counts']['comment'] );
dc_check( 'and still reports every vector', 3 === count( $stats['counts'] ) );
dc_check( 'a junk timestamp falls back to now', $stats['since'] > 0 );

$dc->reset_blocked_stats();

// ---------------------------------------------------------------------------
echo "\n-- Site Health --\n";

$info = apply_filters( 'debug_information', array() );
$dc_fields = isset( $info['disable-comments']['fields'] ) ? $info['disable-comments']['fields'] : array();

dc_check( 'Site Health reports when counting started', isset( $dc_fields['blocked_since'] ) );
dc_check( 'Site Health reports the comment vector', isset( $dc_fields['blocked_comment'] ) );
dc_check( 'Site Health reports the trackback vector', isset( $dc_fields['blocked_trackback'] ) );
dc_check( 'Site Health reports the REST vector', isset( $dc_fields['blocked_rest'] ) );

// ---------------------------------------------------------------------------
echo "\n-- only closures this plugin caused are counted --\n";

// core fires comment_closed whenever a submission is refused, including posts
// closed by their own comment_status or by another plugin. Crediting ourselves
// for those would make the number meaningless.
$dc->reset_blocked_stats();

$natively_closed = wp_insert_post(
	array(
		'post_title'     => 'DC smoke natively closed ' . uniqid(),
		'post_status'    => 'publish',
		'post_type'      => 'dc_uncovered_type',
		'comment_status' => 'closed',
	),
	true
);

// A post of a type this plugin does not touch, closed on its own.
register_post_type( 'dc_uncovered_type', array( 'public' => true, 'supports' => array( 'title', 'comments' ) ) );

do_action( 'comment_closed', 0 );
$dc->flush_blocked_stats();
dc_check( 'a submission with no resolvable post is not counted', 0 === $dc->get_blocked_stats()['total'] );

// A post inside a covered post type that was already closed on its own. Our
// filters would have closed it too, so the result looks identical — the stored
// comment_status is the only thing that distinguishes them, and it is the one
// thing this plugin never writes.
$self_closed = wp_insert_post(
	array(
		'post_title'     => 'DC smoke self-closed ' . uniqid(),
		'post_status'    => 'publish',
		'post_type'      => 'post',
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	),
	true
);

$dc->reset_blocked_stats();
do_action( 'comment_closed', $self_closed );
$dc->flush_blocked_stats();

dc_check( 'a natively closed post is not credited to this plugin', 0 === $dc->get_blocked_stats()['total'] );

// The status has to match the attempt. A post with comments closed on its own
// but pings still open rejects a comment for its own reasons — the open ping
// status says nothing about that, and ORing the two credited us anyway.
$split = wp_insert_post(
	array(
		'post_title'     => 'DC smoke split status ' . uniqid(),
		'post_status'    => 'publish',
		'post_type'      => 'post',
		'comment_status' => 'closed',
		'ping_status'    => 'open',
	),
	true
);

$dc->reset_blocked_stats();
do_action( 'comment_closed', $split );
$dc->flush_blocked_stats();
dc_check( 'comment_status closed, ping_status open: the comment is not ours', 0 === $dc->get_blocked_stats()['counts']['comment'] );

// And the inverse for trackbacks.
$split_inverse = wp_insert_post(
	array(
		'post_title'     => 'DC smoke split status inverse ' . uniqid(),
		'post_status'    => 'publish',
		'post_type'      => 'post',
		'comment_status' => 'open',
		'ping_status'    => 'closed',
	),
	true
);

$dc->reset_blocked_stats();
$dc->record_blocked_trackback( $split_inverse );
$dc->flush_blocked_stats();
dc_check( 'ping_status closed, comment_status open: the trackback is not ours', 0 === $dc->get_blocked_stats()['counts']['trackback'] );

// And the ordinary case still counts.
$dc->reset_blocked_stats();
do_action( 'comment_closed', $post_id );
$dc->flush_blocked_stats();

dc_check( 'a post this plugin closed still counts', 1 === $dc->get_blocked_stats()['total'] );

// ---------------------------------------------------------------------------
echo "\n-- the counters are cleaned up --\n";

$dc->reset_blocked_stats();
do_action( 'comment_closed', $post_id );
$dc->flush_blocked_stats();

dc_check( 'a counter option exists to clean up', false !== get_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', false ) );

$dc->delete_blocked_stats_options();

dc_check( 'the vector options are gone', false === get_option( Disable_Comments::BLOCKED_STATS_OPTION . '_comment', false ) );
dc_check( 'and the start date with them', false === get_option( Disable_Comments::BLOCKED_SINCE_OPTION, false ) );
dc_check( 'reading after cleanup starts from zero, not from stale counts', 0 === $dc->get_blocked_stats()['total'] );

// ---------------------------------------------------------------------------
echo "\n-- the start date is fixed once, not recomputed on every read --\n";

// With nothing blocked yet the option did not exist, and every read answered
// time() without storing it — so the settings panel and Site Health said
// "counting since" today, then tomorrow, then the day after. Nothing had been
// reset; the start date simply had nowhere to live until the first real block
// stamped it.
delete_option( Disable_Comments::BLOCKED_SINCE_OPTION );

dc_check( 'nothing has stamped a start date yet', false === get_option( Disable_Comments::BLOCKED_SINCE_OPTION, false ) );

$first = (int) $dc->get_blocked_stats()['since'];

dc_check( 'a first read still reports a start date', $first > 0 );
dc_check( 'and stores it, so tomorrow cannot answer differently', $first === (int) get_option( Disable_Comments::BLOCKED_SINCE_OPTION, 0 ) );

// A date that moves is the whole bug, and two reads a second apart cannot show
// it. Pin the stored value into the past instead: every later read has to keep
// answering with it rather than with the current time.
$backdated = time() - ( 10 * DAY_IN_SECONDS );
update_option( Disable_Comments::BLOCKED_SINCE_OPTION, $backdated );

dc_check( 'a stored start date is reported as-is', $backdated === (int) $dc->get_blocked_stats()['since'] );

// Nor may the first real block restamp it — counting started when the counters
// were armed, not when they first had something to count.
$dc->reset_blocked_stats();
update_option( Disable_Comments::BLOCKED_SINCE_OPTION, $backdated );

do_action( 'comment_closed', $post_id );
$dc->flush_blocked_stats();

dc_check( 'the first blocked attempt does not move it', $backdated === (int) $dc->get_blocked_stats()['since'] );
dc_check( 'and is still counted', $dc->get_blocked_stats()['total'] > 0 );

// Corrupt data drifted the same way: a value that is not a timestamp reads as
// zero, so it fell through to "now" on every request exactly like a missing
// row. Repairing it only overwrites something that never meant anything.
update_option( Disable_Comments::BLOCKED_SINCE_OPTION, 'not-a-timestamp' );

$repaired = (int) $dc->get_blocked_stats()['since'];

dc_check( 'a junk start date is replaced with a real one', $repaired > 0 );
dc_check( 'and that one is stored too', $repaired === (int) get_option( Disable_Comments::BLOCKED_SINCE_OPTION, 0 ) );

// ---------------------------------------------------------------------------
echo "\n-- attempts blocked only by a conditional rule still count --\n";

// Attribution asked whether the global toggle or the post-type list covered the
// post. On a site configured purely with rules neither does, so every attempt a
// rule turned away was missing from the totals — invisible on exactly the sites
// using the mode this release introduces. It asks is_disabled_for_post() now,
// the same decision the per-post filters make, and still checks the stored
// native status so a closure by core or another plugin is not credited to us.
$term    = wp_insert_term( 'dc-counter-' . uniqid(), 'category' );
$term_id = is_wp_error( $term ) ? 1 : (int) $term['term_id'];

$dc_save_warnings = array();
set_error_handler(
	function ( $severity, $message ) use ( &$dc_save_warnings ) {
		$dc_save_warnings[] = $message;
		return true;
	},
	E_WARNING | E_NOTICE
);
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

restore_error_handler();
dc_check( 'saving conditional rules without mode emits no PHP warnings: ' . implode( '; ', $dc_save_warnings ), array() === $dc_save_warnings );

$ruled = dc_make_test_post( 'post' );
wp_set_object_terms( $ruled, array( $term_id ), 'category' );

$options = get_option( 'disable_comments_options' );
dc_check( 'nothing is disabled globally or by post type', empty( $options['remove_everywhere'] ) && 0 === count( (array) $options['disabled_post_types'] ) );
dc_check( 'but the rule closes this post', true === $dc->is_disabled_for_post( $ruled ) );

$attribute = new ReflectionMethod( 'Disable_Comments', 'closed_by_this_plugin' );
$attribute->setAccessible( true );

dc_check( 'so an attempt on it is attributed to the plugin', true === $attribute->invoke( $dc, $ruled, 'comment' ) );

// The guard against crediting someone else's closure has to survive that.
$foreign = dc_make_test_post( 'post' );
wp_update_post( array( 'ID' => $foreign, 'comment_status' => 'closed' ) );
dc_check( 'while a post closed by something else is still not ours', false === $attribute->invoke( $dc, $foreign, 'comment' ) );

dc_smoke_finish( 'blocked-stats' );
