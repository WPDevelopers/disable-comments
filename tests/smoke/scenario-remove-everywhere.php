<?php
/**
 * Smoke scenario: "remove everywhere" mode.
 *
 * Pre-condition (set by bin/smoke-test.sh before this file runs):
 *   disable_comments_options = { remove_everywhere: true,
 *                                disabled_post_types: [post, page, attachment] }
 *
 * Run: bin/wp eval-file tests/smoke/scenario-remove-everywhere.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

dc_check( 'Disable_Comments class exists', class_exists( 'Disable_Comments' ) );
dc_check( 'plugin is network/site active', is_plugin_active( 'disable-comments/disable-comments.php' ) );

$post_id = dc_make_test_post( 'post' );
$page_id = dc_make_test_post( 'page' );

dc_check( 'comments_open() forced false on post', ! comments_open( $post_id ) );
dc_check( 'comments_open() forced false on page', ! comments_open( $page_id ) );
dc_check( 'pings_open() forced false on post', ! pings_open( $post_id ) );
dc_check( 'get_comments_number() forced to 0', 0 === (int) get_comments_number( $post_id ) );

dc_check( 'comment support removed from post type', ! post_type_supports( 'post', 'comments' ) );
dc_check( 'comment support removed from page type', ! post_type_supports( 'page', 'comments' ) );
dc_check( 'trackback support removed from post type', ! post_type_supports( 'post', 'trackbacks' ) );

$headers = apply_filters( 'wp_headers', array( 'X-Pingback' => 'http://example.test/xmlrpc.php' ) );
dc_check( 'X-Pingback header stripped', ! isset( $headers['X-Pingback'] ) );

$existing = apply_filters( 'comments_array', array( 'c1', 'c2' ), $post_id );
dc_check( 'existing comments hidden via comments_array', array() === $existing );

dc_smoke_finish( 'remove-everywhere' );
