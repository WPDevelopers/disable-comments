<?php
/**
 * Baseline coverage for the Delete Comments tool.
 *
 * Every delete mode is asserted here — what it removes AND what it must leave
 * behind. The dry-run preview is asserted against the same seeded state, so a
 * drift between "what the preview counted" and "what the delete removed" fails
 * the run rather than surfacing on a user's site.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/delete-seed.php';

$dc = Disable_Comments::get_instance();

// ---------------------------------------------------------------------------
echo "\n-- seeding --\n";
$ids = dc_seed_comments();
dc_check( 'seed created 8 comments', 8 === dc_comment_count() );
dc_check( 'seed created 8 comment meta rows', 8 === dc_meta_count() );

// ---------------------------------------------------------------------------
echo "\n-- mode: selected_delete_types (post) --\n";

$preview = $dc->count_comments_for_delete(
	array(
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);
// Five comments hang off the post: 2 plain, 1 pingback, 1 spam and 1 note.
// No allowed types are configured in this run, so the note is fair game.
dc_check( 'preview counts the 5 comments on the post', 5 === $preview['total'] );
dc_check( 'preview breaks the count down by target', isset( $preview['breakdown'] ) && is_array( $preview['breakdown'] ) );
dc_check( 'preview deletes nothing', 8 === dc_comment_count() );

$before = dc_comment_count();
dc_run_delete(
	$dc,
	array(
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'comments on other post types survive', 3 === dc_comment_count() );
dc_check( 'orphaned comment meta is cleaned up too', 3 === dc_meta_count() );

// ---------------------------------------------------------------------------
echo "\n-- mode: selected_delete_comment_types (pingback) --\n";
dc_seed_comments();

$preview = $dc->count_comments_for_delete(
	array(
		'delete_mode'          => 'selected_delete_comment_types',
		'delete_comment_types' => array( 'pingback' ),
	)
);
dc_check( 'preview counts the single pingback', 1 === $preview['total'] );

$before = dc_comment_count();
dc_run_delete(
	$dc,
	array(
		'delete_mode'          => 'selected_delete_comment_types',
		'delete_comment_types' => array( 'pingback' ),
	)
);
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'the trackback was not touched', 1 === dc_comment_count( "comments.comment_type = 'trackback'" ) );
dc_check( 'plain comments were not touched', 5 === dc_comment_count( "comments.comment_type = 'comment'" ) );

// ---------------------------------------------------------------------------
echo "\n-- mode: delete_spam --\n";
dc_seed_comments();

$preview  = $dc->count_comments_for_delete( array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'preview counts both spam comments', 2 === $preview['total'] );

$before = dc_comment_count();
dc_run_delete( $dc, array( 'delete_mode' => 'delete_spam' ) );
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'no spam remains', 0 === dc_comment_count( "comments.comment_approved = 'spam'" ) );
dc_check( 'approved comments survive', 6 === dc_comment_count() );

// ---------------------------------------------------------------------------
echo "\n-- mode: delete_everywhere --\n";
dc_seed_comments();

$preview = $dc->count_comments_for_delete( array( 'delete_mode' => 'delete_everywhere' ) );
dc_check( 'preview counts every comment', 8 === $preview['total'] );

$before = dc_comment_count();
dc_run_delete( $dc, array( 'delete_mode' => 'delete_everywhere' ) );
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'the comments table is empty', 0 === dc_comment_count() );
dc_check( 'the comment meta table is empty', 0 === dc_meta_count() );

// ---------------------------------------------------------------------------
echo "\n-- the run reports how much it removed --\n";

// The settings screen reloads itself after a delete so the server can render
// the review prompt the run just earned. Only a run that removed something can
// earn one, so the screen needs to tell the two apart — and the log string is
// prose, not a number. This is where that number comes from.
dc_seed_comments();

dc_run_delete( $dc, array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'a delete that removed rows reports the count', 2 === $dc->get_last_deleted_count() );

// Same request, same handler, nothing left matching: the count has to drop
// back rather than carry the previous run's number forward.
dc_run_delete( $dc, array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'a delete that matched nothing reports zero', 0 === $dc->get_last_deleted_count() );

// A mode that selects nothing at all takes a different path through
// get_delete_targets(), and must report zero just the same.
dc_run_delete( $dc, array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array() ) );
dc_check( 'a delete that matched no targets reports zero', 0 === $dc->get_last_deleted_count() );

// ---------------------------------------------------------------------------
echo "\n-- CSV export --\n";
$ids = dc_seed_comments();

$csv = $dc->export_comments_csv( array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'export returns a string', is_string( $csv ) && '' !== $csv );

$lines = array_values( array_filter( explode( "\n", trim( $csv ) ) ) );
dc_check( 'export has a header row plus one row per counted comment', 3 === count( $lines ) );
dc_check( 'export header names the columns', false !== strpos( $lines[0], 'comment_ID' ) );
dc_check( 'and carries the site id, so network rows can be attributed', false !== strpos( $lines[0], 'blog_id' ) );

// This file is offered as a backup taken before an irreversible delete, so it
// has to be able to reconstruct the rows — not just describe them.
foreach ( array( 'comment_parent', 'user_id', 'comment_author_url', 'comment_date_gmt', 'comment_agent', 'comment_author_IP', 'comment_karma' ) as $restorable ) {
	dc_check( "the export carries {$restorable}", false !== strpos( $lines[0], $restorable ) );
}

dc_check( 'the export carries comment_meta', false !== strpos( $lines[0], 'comment_meta' ) );

// The delete removes commentmeta too, so a file without it cannot restore
// everything the operation destroyed. The seed gives every comment a meta row.
dc_check( 'and a row actually contains its meta', false !== strpos( $lines[1], 'dc_smoke_marker' ) );

$row = dc_read_csv_line( $lines[1] );
dc_check( 'every header column has a value column', count( $row ) === count( dc_read_csv_line( $lines[0] ) ) );
dc_check( 'export did not delete anything', 8 === dc_comment_count() );

// A comment containing a formula must not be handed to a spreadsheet as one.
$injected = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => '=cmd|/C calc!A1',
		'comment_approved' => 'spam',
		'comment_author'   => 'DC Smoke',
	)
);
$csv = $dc->export_comments_csv( array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'export neutralises spreadsheet formula injection', false !== strpos( $csv, "'=cmd|" ) );
dc_check( 'the raw formula never starts a cell', false === strpos( $csv, ',=cmd|' ) && false === strpos( $csv, '"=cmd|' ) );
wp_delete_comment( $injected, true );

// The guard has to cover every column that holds free text, not just the ones
// a reader thinks of as free text. comment_author_IP is an address only by
// convention — the column is a varchar, and a plugin writing a forwarded header
// into it decides what lands there. comment_type and comment_approved are the
// same shape. All three used to be written raw.
$ip_payload   = '=HYPERLINK("http://evil.test","click")';
$type_payload = '=1+1';
$smuggled     = wp_insert_comment(
	array(
		'comment_post_ID'   => $ids['post'],
		'comment_content'   => 'dc smoke smuggled columns',
		'comment_approved'  => 'spam',
		'comment_author'    => 'DC Smoke',
		'comment_author_IP' => $ip_payload,
		'comment_type'      => $type_payload,
	)
);

// wp_insert_comment() writes these through without sanitising them, but if a
// future WordPress ever did, the assertions below would pass while proving
// nothing. Confirm the payloads really reached the database first.
$stored_comment = get_comment( $smuggled );
dc_check( 'the seeded IP really is a formula in the database', $ip_payload === $stored_comment->comment_author_IP );
dc_check( 'and so is the seeded comment type', $type_payload === $stored_comment->comment_type );

$csv       = $dc->export_comments_csv( array( 'delete_mode' => 'delete_spam' ) );
$csv_rows  = array_values( array_filter( explode( "\n", trim( $csv ) ) ) );
$csv_head  = dc_read_csv_line( $csv_rows[0] );

// Asserted against the parsed row, not by searching the raw file. The IP
// payload contains double quotes, so the writer wraps the field and doubles
// them — a substring search for the payload finds nothing whether it was
// escaped or not, and would pass for the wrong reason.
$smuggled_row = null;
foreach ( array_slice( $csv_rows, 1 ) as $csv_row ) {
	$parsed = dc_read_csv_line( $csv_row );
	if ( isset( $parsed[0] ) && in_array( (string) $smuggled, $parsed, true ) ) {
		$smuggled_row = array_combine( $csv_head, $parsed );
	}
}

dc_check( 'the smuggled comment is in the export', null !== $smuggled_row );

if ( null !== $smuggled_row ) {
	dc_check( 'the IP column is escaped', "'" . $ip_payload === $smuggled_row['comment_author_IP'] );
	dc_check( 'and unescapes back to what was stored', $ip_payload === $dc->csv_unescape( $smuggled_row['comment_author_IP'] ) );
	dc_check( 'the comment type column is escaped', "'" . $type_payload === $smuggled_row['comment_type'] );

	// Escaping must not have leaked into the columns a restore reads back
	// verbatim. Ids and dates are typed by MySQL so the guard could never fire
	// on them anyway — but an inverted list is exactly where that could go
	// wrong, and a restore that does not unescape would silently corrupt them.
	dc_check( 'the id survived unescaped', (string) $smuggled === $smuggled_row['comment_ID'] );
	dc_check( 'and so did the date', $stored_comment->comment_date === $smuggled_row['comment_date'] );
}

wp_delete_comment( $smuggled, true );

// A comment body carrying a backslash directly before a quote — a regex or a
// pasted code snippet. fputcsv() doubles the quote for the enclosure but
// leaves the backslash alone, emitting \"" where a conforming reader sees the
// backslash as ordinary text and the quote that follows it as the end of the
// field. Everything after it shifts a column, and the CLI can then delete the
// source against a backup whose columns no longer line up.
//
// wp_insert_comment() unslashes what it is given, so the backslashes have to
// be slashed on the way in or they never reach the database at all.
$nasty  = 'regex \\"quoted\\" tail';
$gnarly = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => wp_slash( $nasty ),
		'comment_approved' => 'spam',
		'comment_author'   => 'DC Smoke',
	)
);
$stored = get_comment( $gnarly )->comment_content;
dc_check( 'the seeded body really does carry a backslash before a quote', false !== strpos( $stored, '\\"' ) );

$csv       = $dc->export_comments_csv( array( 'delete_mode' => 'delete_spam' ) );
$csv_lines = array_values( array_filter( explode( "\n", trim( $csv ) ) ) );
$width     = count( dc_read_csv_line( $csv_lines[0] ) );

$round_tripped = false;
foreach ( array_slice( $csv_lines, 1 ) as $csv_line ) {
	$parsed = dc_read_csv_line( $csv_line );
	if ( in_array( $stored, $parsed, true ) ) {
		$round_tripped = true;
	}
	dc_check( 'every exported row keeps its column count', count( $parsed ) === $width );
}

dc_check( 'a body mixing backslashes and quotes survives the export intact', $round_tripped );
wp_delete_comment( $gnarly, true );

// ---------------------------------------------------------------------------
echo "\n-- a failed export is never reported as a complete backup --\n";

// A destination that opens fine and then refuses writes is exactly the disk-
// full case. Treating the truncated file as a backup and deleting against it
// is the failure this flag exists to prevent.
$readonly = fopen( 'php://memory', 'r' );

$threw = false;
try {
	$dc->stream_comments_csv( $readonly, array( 'delete_mode' => 'delete_spam' ) );
} catch ( Exception $e ) {
	$threw = true;
}
fclose( $readonly );

dc_check( 'a stream that cannot be written to raises rather than returning a count', $threw );
dc_check( 'and nothing was deleted', 8 === dc_comment_count() );

// A full disk does not always answer false. fwrite() reports what it managed
// to write, so a stream with room for part of a row returns a short integer —
// which read as success leaves a truncated file the CLI deletes against.
//
// php://temp will not do this: past its memory limit it spills to a real temp
// file and the writes keep succeeding. A stream wrapper that accepts a fixed
// budget of bytes and then takes only part of the next line is the real
// condition, and the only way to reach it deterministically.
if ( ! class_exists( 'DC_Short_Write_Stream' ) ) {
	class DC_Short_Write_Stream {
		public $context;
		private $budget = 200;

		public function stream_open( $path, $mode, $options, &$opened_path ) {
			return true;
		}

		public function stream_write( $data ) {
			$take         = min( strlen( $data ), $this->budget );
			$this->budget -= $take;

			// Short on the line that runs out, zero on everything after it.
			return $take;
		}

		public function stream_eof() {
			return false;
		}

		public function stream_stat() {
			return array();
		}

		public function stream_close() {
		}
	}
}

if ( in_array( 'dcshort', stream_get_wrappers(), true ) ) {
	stream_wrapper_unregister( 'dcshort' );
}
stream_wrapper_register( 'dcshort', 'DC_Short_Write_Stream' );

$short = fopen( 'dcshort://sink', 'w' );

$threw = false;
try {
	$dc->stream_comments_csv( $short, array( 'delete_mode' => 'delete_spam' ) );
} catch ( Exception $e ) {
	$threw = true;
}
fclose( $short );
stream_wrapper_unregister( 'dcshort' );

dc_check( 'a partial write is treated as a failure, not a row', $threw );
dc_check( 'and nothing was deleted after one', 8 === dc_comment_count() );

// ---------------------------------------------------------------------------
echo "\n-- a delete after an export removes only what the export captured --\n";

// The export and the delete are two separate passes over the table. A comment
// arriving between the export's last read and the delete is not in the file,
// but the delete's WHERE clause still matches it — so the CLI would destroy a
// comment its "backup" never contained. comment_ID is AUTO_INCREMENT, so the
// highest id the export reached describes exactly what the file saw.
dc_seed_comments();

$exported = $dc->export_comments_csv( array( 'delete_mode' => 'delete_spam' ) );
$ceilings = $dc->get_last_export_ceilings();

dc_check( 'the export reports a ceiling', is_array( $ceilings ) && ! empty( $ceilings ) );

// This is the racing comment: inserted after the export finished reading.
$latecomer = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => 'arrived after the export',
		'comment_approved' => 'spam',
		'comment_author'   => 'DC Smoke',
	)
);

dc_check( 'the latecomer is not in the exported file', false === strpos( $exported, 'arrived after the export' ) );
dc_check( 'and it sorts above every ceiling the export recorded', $latecomer > max( (array) $ceilings[ (int) get_current_blog_id() ] ) );

dc_run_delete( $dc, array( 'delete_mode' => 'delete_spam' ), $ceilings );

// Asked through get_comment() this passes even when the row is gone: the
// delete runs as raw SQL, so WordPress keeps serving the comment it cached
// before. Ask the table.
dc_check( 'the delete spared the comment the backup never saw', 1 === dc_comment_count( 'comments.comment_ID = ' . (int) $latecomer ) );
dc_check( 'and it is the only spam left, so the rest was still removed', 1 === dc_spam_count() );

// Targets stream one after another, so a single blog-wide ceiling lets a later
// target raise an earlier one's cap: finish exporting posts, a new post comment
// arrives unexported, then a page comment with a higher id is exported and
// lifts the blog maximum past it — and the delete takes the unexported one.
//
// Built from two real single-target exports rather than by editing a ceiling
// map by hand, so the bounds are whatever the exporter actually recorded. The
// first draft of this check guessed which signature belonged to which target
// by picking the larger bound, and picked wrong: the seed's highest id is a
// post comment, so it raised the post target's own ceiling and the check
// passed for the wrong reason.
dc_seed_comments();

$post_args = array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post' ) );
$page_args = array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'page' ) );
$both_args = array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post', 'page' ) );

$dc->export_comments_csv( $post_args );
$ceilings = $dc->get_last_export_ceilings();

// Arrives after the post target finished reading: not in the file.
$unexported_post_comment = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => 'post comment after its target finished',
		'comment_approved' => 'spam',
		'comment_author'   => 'DC Smoke',
	)
);

// A page comment with a higher id still, which the later page target exports.
wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['page'],
		'comment_content'  => 'page comment exported later',
		'comment_approved' => 'spam',
		'comment_author'   => 'DC Smoke',
	)
);

$dc->export_comments_csv( $page_args );
$page_ceilings = $dc->get_last_export_ceilings();

$blog = (int) get_current_blog_id();
dc_check( 'each target gets its own ceiling entry', 1 === count( (array) $ceilings[ $blog ] ) && 1 === count( (array) $page_ceilings[ $blog ] ) );

$merged = $ceilings;
foreach ( (array) $page_ceilings[ $blog ] as $signature => $bound ) {
	$merged[ $blog ][ $signature ] = $bound;
}

dc_check( 'the page target ends up bounded above the unexported post comment', max( (array) $merged[ $blog ] ) > $unexported_post_comment );

dc_run_delete( $dc, $both_args, $merged );

dc_check(
	'a later target raising its own ceiling does not expose an earlier one',
	1 === dc_comment_count( 'comments.comment_ID = ' . (int) $unexported_post_comment )
);

dc_seed_comments();

// Zeroing comment_count assumes the delete emptied the target. A ceiling
// deliberately leaves rows behind, so the count has to be recalculated or the
// spared comment vanishes from every count-based display on the site.
dc_seed_comments();

$approved = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => 'approved, arrived after the export',
		'comment_approved' => '1',
		'comment_author'   => 'DC Smoke',
	)
);
$dc->export_comments_csv( array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post' ) ) );
$ceilings = $dc->get_last_export_ceilings();

$late_approved = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => 'later still',
		'comment_approved' => '1',
		'comment_author'   => 'DC Smoke',
	)
);

dc_run_delete( $dc, array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post' ) ), $ceilings );

global $wpdb;
$counted = (int) $wpdb->get_var( $wpdb->prepare( "SELECT comment_count FROM $wpdb->posts WHERE ID = %d", $ids['post'] ) );
$actual  = dc_comment_count( $wpdb->prepare( 'comments.comment_post_ID = %d AND comments.comment_approved = %s', $ids['post'], '1' ) );

dc_check( 'a comment that survived the ceiling is still there', $actual > 0 );
dc_check( 'and comment_count matches what actually survived', $counted === $actual );

// dc_seed_comments() truncates the table, so this resets the state for the
// uncapped check below.
dc_seed_comments();

// Without an export there is no backup to be consistent with, so an ordinary
// delete stays uncapped and removes everything it matches.
dc_seed_comments();
dc_run_delete( $dc, array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'an ordinary delete, with no export, is not capped', 0 === dc_spam_count() );

// ---------------------------------------------------------------------------
echo "\n-- a failed read never authorises a delete --\n";

// The block above deleted, so restore the known eight before asserting on
// counts again.
dc_seed_comments();

// wpdb answers a failed read exactly as it answers an empty table: an empty
// array from get_results(), null from get_var(). So "the replica went away"
// arrives looking like "there is nothing to export" — a header-only file the
// CLI reports as a complete backup, and a preview total of zero, which is the
// number the operator confirms against.
//
// Core applies the `query` filter to every statement before running it, so
// corrupting the matching statement there is a real failed query, error and
// all, rather than a mocked one.
function dc_break_query( $sql, $needle ) {
	return ( false !== strpos( $sql, $needle ) ) ? 'SELECT * FROM dc_no_such_table_' . md5( $needle ) : $sql;
}

global $wpdb;

foreach ( array(
	"FROM $wpdb->comments comments" => 'the comment read',
	"FROM $wpdb->commentmeta"       => 'the comment-metadata read',
) as $needle => $label ) {
	$breaker = function ( $sql ) use ( $needle ) {
		return dc_break_query( $sql, $needle );
	};
	add_filter( 'query', $breaker );

	$threw = false;
	try {
		$dc->export_comments_csv( array( 'delete_mode' => 'delete_spam' ) );
	} catch ( Exception $e ) {
		$threw = true;
	}

	remove_filter( 'query', $breaker );

	dc_check( "a failure in $label raises rather than reporting a complete export", $threw );
}

// The preview is the operator's basis for confirming, so a broken count must
// not read as "nothing will be deleted".
$count_needle = "SELECT COUNT(*) FROM $wpdb->comments";
$breaker      = function ( $sql ) use ( $count_needle ) {
	return dc_break_query( $sql, $count_needle );
};
add_filter( 'query', $breaker );

$threw = false;
try {
	$dc->count_comments_for_delete( array( 'delete_mode' => 'delete_spam' ) );
} catch ( Exception $e ) {
	$threw = true;
}

remove_filter( 'query', $breaker );

dc_check( 'a failure in the preview count raises rather than reporting zero', $threw );
dc_check( 'and nothing was deleted by any of it', 8 === dc_comment_count() );

// ---------------------------------------------------------------------------
echo "\n-- the spreadsheet guard can be undone --\n";

// The guard exists so Excel does not execute a comment body. But this file is
// also the backup, and a guard with no inverse corrupts what it protects: with
// a bare prefix, a body of "+1" and a body of "'+1" both leave as "'+1".
foreach ( array( '=cmd|calc', '+1', '-2', '@user', "'+1", "''already", 'ordinary text', '' ) as $original ) {
	$escaped = $dc->csv_escape( $original );
	dc_check(
		'csv_escape round-trips ' . ( '' === $original ? '(empty)' : $original ),
		$original === $dc->csv_unescape( $escaped )
	);
}

$dangerous = $dc->csv_escape( '=cmd|/C calc' );
dc_check( 'and a formula still cannot start a cell', "'" === substr( $dangerous, 0, 1 ) );

// ---------------------------------------------------------------------------
echo "\n-- a repeated post type is counted once, not twice --\n";

dc_seed_comments();

$once  = $dc->count_comments_for_delete( array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post' ) ) );
$twice = $dc->count_comments_for_delete( array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post', 'post' ) ) );

dc_check( 'a duplicated slug does not double the preview', $once['total'] === $twice['total'] );

$before  = dc_comment_count();
dc_run_delete( $dc, array( 'delete_mode' => 'selected_delete_types', 'delete_types' => array( 'post', 'post' ) ) );
$removed = $before - dc_comment_count();

dc_check( 'and the delete still matches what was previewed', $twice['total'] === $removed );

// The network screen takes extra post types as free text, and that list is
// merged in after the selected ones. Deduplicating only the selected ones
// leaves "book,book" building two identical targets, so the preview and the
// export double-count while the delete removes each comment once.
//
// The extra-types field is only read when the plugin is network-active, which
// this single-site sandbox is not; flip the flag for the length of the check
// and put it back. bin/smoke-test-multisite.sh covers the real network path.
dc_seed_comments();

$was                = $dc->networkactive;
$dc->networkactive  = true;

// The field exists for post types the network screen cannot see, and the
// merge drops anything already in the registered list — so the slug has to be
// one that is genuinely absent. Register it, give it a commented post, then
// unregister: the rows stay, and the type is now exactly the unknown-slug case
// the field is for.
register_post_type( 'dc_extra', array( 'public' => true, 'supports' => array( 'title', 'comments' ) ) );
$extra_post = dc_make_test_post( 'dc_extra' );
foreach ( array( 1, 2 ) as $ignored ) {
	wp_insert_comment(
		array(
			'comment_post_ID'  => $extra_post,
			'comment_content'  => 'dc smoke extra ' . uniqid(),
			'comment_approved' => '1',
			'comment_author'   => 'DC Smoke',
		)
	);
}
unregister_post_type( 'dc_extra' );

$plain = $dc->count_comments_for_delete(
	array(
		'delete_mode'             => 'selected_delete_types',
		'delete_types'            => array(),
		'delete_extra_post_types' => 'dc_extra',
	)
);
$dupe = $dc->count_comments_for_delete(
	array(
		'delete_mode'             => 'selected_delete_types',
		'delete_types'            => array(),
		'delete_extra_post_types' => 'dc_extra,dc_extra',
	)
);

$dc->networkactive = $was;

dc_check( 'a slug repeated in the extra-post-types field does not double the preview', $plain['total'] === $dupe['total'] );
dc_check( 'and that preview is not empty, so the check has something to count', $plain['total'] > 0 );

// ---------------------------------------------------------------------------
echo "\n-- --export is honoured for every filename it accepts --\n";

// A path is a string, and "0" is a legal one. Tested through empty() it reads
// as false, so --export=0 skipped the export in silence and ran the
// irreversible delete having been asked for a backup. Driven as a real
// subprocess because the bug is in the command's flag handling, which calling
// the plugin methods directly never reaches.
if ( ! class_exists( 'WP_CLI' ) ) {
	echo "  SKIP  not running under WP-CLI\n";
} else {
	dc_seed_comments();
	$spam_before = dc_spam_count();

	$dir = rtrim( get_temp_dir(), '/\\' ) . '/dc-export-' . uniqid();
	mkdir( $dir );
	$file = $dir . '/0';

	// The flag value has to be the bare string "0" for this to test anything —
	// a path merely ENDING in /0 is a non-empty string and empty() is happy
	// with it, which is how the first version of this check passed against the
	// broken code. So run from inside the directory and pass the filename
	// alone; the subprocess inherits this cwd.
	$cwd = getcwd();
	chdir( $dir );

	$run = WP_CLI::runcommand(
		'disable-comments delete --spam --export=0',
		array(
			'launch'     => true,
			'return'     => 'all',
			'exit_error' => false,
		)
	);

	chdir( $cwd );

	dc_check( 'the command succeeds', 0 === $run->return_code );
	dc_check( 'a file named 0 is actually written', file_exists( $file ) );
	dc_check(
		'and it holds a header plus every comment the delete removed',
		file_exists( $file ) && ( count( array_filter( explode( "\n", trim( (string) file_get_contents( $file ) ) ) ) ) === $spam_before + 1 )
	);

	if ( file_exists( $file ) ) {
		unlink( $file );
	}
	rmdir( $dir );

	// The other spellings of "no export". --no-export is WP-CLI's negation
	// syntax and supplies the flag as boolean false, which is neither null nor
	// '' — it reached fopen(false) and killed the command with a ValueError
	// before it deleted or previewed anything. Absent is the ordinary case and
	// is here so the guard can never be tightened into breaking it.
	foreach ( array( '--no-export', '' ) as $spelling ) {
		dc_seed_comments();
		$label = '' === $spelling ? 'with no --export at all' : $spelling;

		$run = WP_CLI::runcommand(
			trim( 'disable-comments delete --spam ' . $spelling ),
			array(
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			)
		);

		dc_check( "the command still runs $label", 0 === $run->return_code );
		dc_check( "and does not fault $label", false === strpos( $run->stderr . $run->stdout, 'Fatal error' ) );
		dc_check( "and still deletes $label", 0 === dc_spam_count() );
	}
}

// ---------------------------------------------------------------------------
echo "\n-- the preview shows what will be deleted, not just how many --\n";

// The button says "Preview what will be deleted" and used to answer with a
// number. A count cannot be reviewed: somebody who reads one and confirms has
// checked how many rows will go, not which, and this delete has no undo.
//
// Re-seeded, and the ids recaptured with it: every seed creates fresh posts,
// so the $ids from the top of this file point at posts these comments are not
// on any more.
$ids = dc_seed_comments();

$args = array(
	'delete_mode'  => 'selected_delete_types',
	'delete_types' => array( 'post' ),
);

// Nothing asked for, nothing queried: the WP-CLI dry run and the abilities'
// matched count read the total alone and must not pay for rows they discard.
$counted = $dc->count_comments_for_delete( $args );

dc_check( 'a plain count returns no sample', array() === $counted['sample'] );
dc_check( 'and still counts the 5 comments on the post', 5 === $counted['total'] );

$preview = $dc->count_comments_for_delete( $args, false, 10 );

dc_check( 'asking for a sample returns the comments themselves', 5 === count( $preview['sample'] ) );
dc_check( 'and does not change the count', $counted['total'] === $preview['total'] );
dc_check( 'and still deletes nothing', 8 === dc_comment_count() );

$row = $preview['sample'][0];

dc_check( 'each row names who wrote it', isset( $row['author'] ) && '' !== $row['author'] );
dc_check( 'and when', isset( $row['date'] ) && '' !== $row['date'] );
dc_check( 'and what it is on', isset( $row['post'] ) && '' !== $row['post'] );
dc_check( 'and enough of the text to recognise', isset( $row['excerpt'] ) && '' !== $row['excerpt'] );

// The excerpt is comment content, which is HTML somebody else wrote. It
// reaches the screen through .text(), but it should not be carrying markup
// into the payload either.
$html_id = wp_insert_comment(
	array(
		'comment_post_ID'  => $ids['post'],
		'comment_content'  => '<a href="https://example.com/">' . str_repeat( 'spam ', 60 ) . '</a>',
		'comment_approved' => '1',
		'comment_author'   => '',
	)
);

$preview = $dc->count_comments_for_delete( $args, false, 10 );
$excerpts = wp_list_pluck( $preview['sample'], 'excerpt' );
$authors  = wp_list_pluck( $preview['sample'], 'author' );

dc_check( 'the new comment is in the sample', 6 === count( $preview['sample'] ) );
dc_check( 'tags are stripped rather than counted as content', false === strpos( implode( ' ', $excerpts ), '<a href' ) );
dc_check( 'a long comment is truncated', 120 > max( array_map( 'strlen', $excerpts ) ) );
dc_check( 'an empty author name is not shown as blank', false === in_array( '', $authors, true ) );

wp_delete_comment( (int) $html_id, true );

// The cap is what keeps this a preview rather than the comments screen.
$preview = $dc->count_comments_for_delete( $args, false, 2 );

dc_check( 'the sample stops at the size asked for', 2 === count( $preview['sample'] ) );
dc_check( 'while the total still reports everything', 5 === $preview['total'] );

// Whatever the preview lists, the delete has to agree with it.
$preview = $dc->count_comments_for_delete( $args, false, 10 );
$before  = dc_comment_count();
dc_run_delete( $dc, $args );

dc_check( 'the delete removed exactly what the preview counted', $preview['total'] === ( $before - dc_comment_count() ) );

// Nothing matched means nothing listed — not a stale list from the last run.
$preview = $dc->count_comments_for_delete( $args, false, 10 );

dc_check( 'a preview that matches nothing lists nothing', 0 === $preview['total'] && array() === $preview['sample'] );

// ---------------------------------------------------------------------------
echo "\n-- the standing total says what it counts --\n";

// "Total Comments: 2" sat inches above "This will permanently delete 0
// comment(s)" whenever the selected mode was narrower than everything — most
// visibly with Spam on a site that has none. Both numbers were right; nothing
// on screen said they answer different questions.
//
// The count itself is deliberately untouched. This asserts what the screen
// says about it.
dc_seed_comments();

$dc_render_delete = Closure::bind(
	function () {
		ob_start();
		include DC_PLUGIN_VIEWS_PATH . 'partials/_delete.php';
		return ob_get_clean();
	},
	$dc,
	'Disable_Comments'
);

$html = $dc_render_delete();

dc_check( 'the figure names what it counts', false !== strpos( $html, 'Comments on this site:' ) );
dc_check( 'and no longer reads as a bare total', false === strpos( $html, 'Total Comments:' ) );
dc_check( 'it says every status is included', false !== strpos( $html, 'approved, pending, spam and trash' ) );
dc_check( 'and points at the preview for what a delete would take', false !== strpos( $html, 'the mode you picked would delete' ) );

// The number is the same one it always was: 8 seeded comments, no allowlist.
dc_check( 'the count is unchanged', 8 === (int) $dc->get_all_comments_number() );
dc_check( 'and is still rendered', false !== strpos( $html, '<span>8</span>' ) );

// The allowlist clause is only true when there is an allowlist, so it only
// appears when there is one.
dc_check( 'no allowlist, no allowlist sentence', false === strpos( $html, 'cannot be deleted from here' ) );

$dc->apply_settings(
	array(
		'mode'                  => 'selected_types',
		'disabled_types'        => array(),
		'allowed_comment_types' => array( 'note' ),
	),
	false,
	false
);

$html = $dc_render_delete();

dc_check( 'with an allowlist, the figure says those are left out', false !== strpos( $html, 'cannot be deleted from here' ) );
dc_check( 'and the count drops the allowlisted note', 7 === (int) $dc->get_all_comments_number() );

$dc->apply_settings(
	array(
		'mode'                  => 'selected_types',
		'disabled_types'        => array(),
		'allowed_comment_types' => array(),
	),
	false,
	false
);

dc_smoke_finish( 'delete-modes' );
