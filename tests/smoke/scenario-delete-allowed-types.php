<?php
/**
 * Allowed comment types survive every delete mode.
 *
 * WP 6.9+ Notes are opt-in-preserved through the "Enable Certain Comment
 * Types" setting. If a delete mode ignores that allowlist, a user who turned
 * Notes back on and then cleared spam loses their Notes with no warning and no
 * way back. The preview has to exclude them too, or it promises a number the
 * delete will not honour.
 *
 * The runner seeds allowed_comment_types before this file runs — the option is
 * read when the plugin constructs, so it cannot be changed mid-process.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/delete-seed.php';

$dc = Disable_Comments::get_instance();

dc_check(
	'the runner configured note as an allowed comment type',
	in_array( 'note', (array) $dc->get_allowed_comment_types_list(), true )
);

// ---------------------------------------------------------------------------
echo "\n-- delete_everywhere --\n";
dc_seed_comments();

$preview = $dc->count_comments_for_delete( array( 'delete_mode' => 'delete_everywhere' ) );
dc_check( 'preview excludes the protected note', 7 === $preview['total'] );

$before  = dc_comment_count();
dc_run_delete( $dc, array( 'delete_mode' => 'delete_everywhere' ) );
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'the note survived a delete-everywhere', 1 === dc_comment_count( "comments.comment_type = 'note'" ) );

// ---------------------------------------------------------------------------
echo "\n-- selected_delete_types --\n";
dc_seed_comments();

$preview = $dc->count_comments_for_delete(
	array(
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);
// Five comments hang off the post, one of which is the protected note.
dc_check( 'preview excludes the note from the post-type target', 4 === $preview['total'] );

$before  = dc_comment_count();
dc_run_delete(
	$dc,
	array(
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'the note survived a post-type delete', 1 === dc_comment_count( "comments.comment_type = 'note'" ) );

// ---------------------------------------------------------------------------
echo "\n-- delete_spam --\n";
dc_seed_comments();

$preview = $dc->count_comments_for_delete( array( 'delete_mode' => 'delete_spam' ) );
dc_check( 'preview counts only the spam', 2 === $preview['total'] );

$before  = dc_comment_count();
dc_run_delete( $dc, array( 'delete_mode' => 'delete_spam' ) );
$removed = $before - dc_comment_count();

dc_check( 'delete removed exactly what the preview counted', $preview['total'] === $removed );
dc_check( 'the note survived a spam purge', 1 === dc_comment_count( "comments.comment_type = 'note'" ) );

// ---------------------------------------------------------------------------
echo "\n-- the allowlisted type cannot be targeted directly --\n";
dc_seed_comments();

// get_all_comment_types() drops allowed types, so selecting `note` explicitly
// must resolve to no target rather than deleting it.
$preview = $dc->count_comments_for_delete(
	array(
		'delete_mode'          => 'selected_delete_comment_types',
		'delete_comment_types' => array( 'note' ),
	)
);
dc_check( 'preview refuses to count an allowlisted type', 0 === $preview['total'] );

$before  = dc_comment_count();
dc_run_delete(
	$dc,
	array(
		'delete_mode'          => 'selected_delete_comment_types',
		'delete_comment_types' => array( 'note' ),
	)
);
dc_check( 'nothing was deleted', $before === dc_comment_count() );
dc_check( 'the note is still there', 1 === dc_comment_count( "comments.comment_type = 'note'" ) );

dc_smoke_finish( 'delete-allowed-types' );
