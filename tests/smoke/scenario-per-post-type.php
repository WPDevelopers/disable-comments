<?php
/**
 * Smoke scenario: disable comments on selected post types only ("post").
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: false,
 *                                disabled_post_types: [post] }
 *
 * Run: bin/wp eval-file tests/smoke/scenario-per-post-type.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

$post_id = dc_make_test_post( 'post' );
$page_id = dc_make_test_post( 'page' );

dc_check( 'comments_open() forced false on disabled type (post)', ! comments_open( $post_id ) );
dc_check( 'comments_open() untouched on enabled type (page)', comments_open( $page_id ) );
dc_check( 'get_comments_number() zeroed on disabled type', 0 === (int) get_comments_number( $post_id ) );
dc_check( 'get_comments_number() untouched on enabled type', 0 <= (int) get_comments_number( $page_id ) );

dc_check( 'comment support removed from post type', ! post_type_supports( 'post', 'comments' ) );
dc_check( 'comment support kept on page type', post_type_supports( 'page', 'comments' ) );

$existing_post = apply_filters( 'comments_array', array( 'c1', 'c2' ), $post_id );
$existing_page = apply_filters( 'comments_array', array( 'c1', 'c2' ), $page_id );
dc_check( 'existing comments hidden on disabled type', array() === $existing_post );
dc_check( 'existing comments kept on enabled type', array( 'c1', 'c2' ) === $existing_page );

dc_smoke_finish( 'per-post-type' );
