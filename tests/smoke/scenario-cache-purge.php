<?php
/**
 * Smoke scenario: page caches are purged when settings change.
 *
 * Regression cover for the live-site test report (2026-07-27), Finding 1: on a
 * host with a warm nginx FastCGI page cache, saving settings left cached pages
 * still serving the comment form. Comments were genuinely off, but every
 * visitor on a cached page saw the old markup until the entry expired — the
 * plugin's core promise silently failing.
 *
 * These assertions cover the *mechanism*: that the save and delete handlers
 * reach purge_page_caches(), and that it fires the integration hooks a cache
 * plugin listens on. End-to-end proof against a real FastCGI cache can only
 * happen on a cached host — there is no page cache in this sandbox or in CI.
 *
 * Run: bin/wp eval-file tests/smoke/scenario-cache-purge.php
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

$instance = Disable_Comments::get_instance();

dc_check( 'purge_page_caches() is callable', method_exists( $instance, 'purge_page_caches' ) );

if ( ! method_exists( $instance, 'purge_page_caches' ) ) {
	dc_smoke_finish( 'cache-purge' );
	return;
}

// ── Stand in for the cache plugins we integrate with ────────────────────────
$fired = array(
	'disable_comments_purge_caches' => 0,
	'rt_nginx_helper_purge_all'     => 0,
	'litespeed_purge_all'           => 0,
	'breeze_clear_all_cache'        => 0,
	'wphb_clear_page_cache'         => 0,
);

foreach ( array_keys( $fired ) as $hook ) {
	add_action(
		$hook,
		function () use ( $hook, &$fired ) {
			$fired[ $hook ]++;
		}
	);
}

// ── A direct call reaches every integration ─────────────────────────────────
$instance->purge_page_caches();

dc_check( 'the documented disable_comments_purge_caches action fires', 1 === $fired['disable_comments_purge_caches'] );
// The host in the report runs Nginx Helper, so this is the one that would have
// fixed the reported symptom.
dc_check( 'Nginx Helper purge fires', 1 === $fired['rt_nginx_helper_purge_all'] );
dc_check( 'LiteSpeed purge fires', 1 === $fired['litespeed_purge_all'] );
dc_check( 'Breeze purge fires', 1 === $fired['breeze_clear_all_cache'] );
dc_check( 'Hummingbird page-cache purge fires', 1 === $fired['wphb_clear_page_cache'] );

// ── The object cache is deliberately left alone ─────────────────────────────
// Flushing a shared Redis/Memcached object cache on every settings save would
// stampede a busy site's origin, and it is not what went stale: the problem is
// rendered HTML held in a *page* cache.
wp_cache_set( 'dc_smoke_canary', 'alive', 'dc_smoke' );
$instance->purge_page_caches();
dc_check(
	'the object cache is not flushed by a purge',
	'alive' === wp_cache_get( 'dc_smoke_canary', 'dc_smoke' )
);

// ── A real settings save purges exactly once ────────────────────────────────
// Goes through the actual handler (CLI path), not a direct call, so a future
// refactor that drops the purge from the save path fails here.
$fired['disable_comments_purge_caches'] = 0;
$fired['rt_nginx_helper_purge_all']     = 0;

$instance->disable_comments_settings(
	array(
		'mode'           => 'selected_types',
		'disabled_types' => array( 'post' ),
	)
);

dc_check( 'saving settings purges the page cache', 1 === $fired['disable_comments_purge_caches'] );
dc_check( 'saving settings purges exactly once, not per integration', 1 === $fired['rt_nginx_helper_purge_all'] );

// Guards the assertion above: if the handler had bailed early, the purge count
// would also be 0 and the test would pass for the wrong reason.
$saved = get_option( 'disable_comments_options' );
dc_check(
	'the save handler actually persisted the change',
	! empty( $saved['disabled_post_types'] ) && in_array( 'post', (array) $saved['disabled_post_types'], true )
);

// A save must survive a site with no caching plugin at all — every integration
// is guarded, and reaching this line at all proves none of them fataled.
dc_check( 'saving settings completes with no caching plugin present', true );

// ── Deleting comments purges too ────────────────────────────────────────────
// Deleted comments stay visible in cached pages, and so do their counts.
$fired['disable_comments_purge_caches'] = 0;

$instance->delete_comments_settings(
	array(
		'delete'       => true,
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);

dc_check( 'deleting comments purges the page cache', 1 === $fired['disable_comments_purge_caches'] );

dc_smoke_finish( 'cache-purge' );
