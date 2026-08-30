# Page Cache Purging

Disabling comments changes the HTML of every page that renders a comment form
or a comment count. On a site with a full-page cache, those pages keep being
served from the cache after you save — comments really are off, but visitors on
a cached page still see the old form until the entry expires.

Since 2.8.0, Disable Comments purges page caches whenever it changes what the
front end renders:

- after settings are saved (settings screen, and `wp disable-comments settings`)
- after comments are deleted (Tools screen, and `wp disable-comments delete`)

## Bundled integrations

No configuration needed — these fire automatically when the plugin is active:

| Cache | How it is purged |
| ----- | ---------------- |
| WP Super Cache | `wp_cache_clear_cache()` |
| W3 Total Cache | `w3tc_flush_posts()` |
| WP Rocket | `rocket_clean_domain()` |
| SiteGround Optimizer | `sg_cachepress_purge_cache()` |
| LiteSpeed Cache | `litespeed_purge_all` action |
| Nginx Helper | `rt_nginx_helper_purge_all` action |
| Breeze | `breeze_clear_all_cache` action |
| Hummingbird | `wphb_clear_page_cache` action |

Every one is guarded, so a caching plugin that renames or drops its API cannot
turn "settings saved" into a fatal error.

## Hooking your own cache or CDN

If your host, CDN, or caching plugin isn't in the list above, hook the action.
It fires before the bundled integrations, so you can act first.

```php
add_action( 'disable_comments_purge_caches', function () {
    // Clear your own layer — edge cache, CDN zone, static HTML, etc.
    my_cdn_purge_everything();
} );
```

## Multisite

Several page caches only clear the site they are called from. So on a network
the purge runs **once per affected site, with that site switched in** —
`get_current_blog_id()` inside your handler is the site being purged.

- Deleting comments from the network admin purges each selected subsite.
- Saving settings from the network admin purges every site in the network,
  since network settings change what all of them render.
- Saving settings on a single subsite purges only that subsite.

On a very large network, purging every site can be more work than you want in
one request. If your cache layer already purges network-wide from a single
call, narrow the list:

```php
add_filter( 'disable_comments_purge_blog_ids', function ( $blog_ids ) {
    return array( get_main_site_id() ); // our CDN purge is network-wide anyway
} );
```

## What is *not* purged

The **object cache** (Redis, Memcached) is deliberately left alone. Settings
live in options that WordPress already invalidates on write, so flushing a
shared object cache on every save would stampede a busy site's origin without
fixing anything. The stale content is rendered HTML held in a *page* cache, and
that is what gets cleared.

## Verifying on a cached host

A page cache serves identical output regardless of what you changed, so testing
without busting the cache produces meaningless results. Check the `x-cache`
response header, and use a cache-busting query string to see the true state:

```bash
curl -sI 'https://example.com/hello-world/'            | grep -i x-cache
curl -s  'https://example.com/hello-world/?nocache=1'  | grep -c 'comment-form'
```
