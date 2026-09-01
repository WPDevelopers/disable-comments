# Abilities API Support

Disable Comments registers with the [WordPress Abilities API](https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/) (WordPress 6.9+). This lets AI assistants, the command palette, and MCP clients ask a site how comments are configured through a documented, permission-checked contract instead of scraping the settings screen.

On WordPress older than 6.9 nothing is registered and plugin behaviour is unchanged.

## Registered ability

### `disable-comments/get-status`

Read-only. Reports where comments are currently disabled on the site.

| Property | Value |
| -------- | ----- |
| Category | `disable-comments` |
| Input | none |
| Permission | `manage_options` |
| Annotations | `readonly: true`, `destructive: false`, `idempotent: true` |
| REST | exposed (`show_in_rest: true`) |
| MCP | exposed (`mcp.public: true`, `mcp.type: tool`) |

**Output**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `status` | string | `all`, `none`, `posts`, `pages`, `posts,pages`, `multiple`, or a single custom post type slug |
| `comments_disabled_everywhere` | bool | True when no post type is left accepting comments, including non-public ones. Stricter than `status: "all"` — see below. Not absolute either, see `allowed_comment_types` |
| `allowed_comment_types` | string[] | Comment types that stay enabled anyway (e.g. `note` for WP 6.9+ editor notes) |
| `blocked_comment_types` | string[] | The mirror image: comment types closed **even where comments are open** (e.g. `review` on a store that closes product reviews but keeps other comments on products). Creating one is rejected with a 403 on both the comment form and REST. Never overlaps `allowed_comment_types` |
| `disabled_post_types` | string[] | Post type slugs with comments disabled |
| `available_post_types` | string[] | Post type slugs that support comments and can be toggled |
| `rest_api_comments_blocked` | bool | REST API comment endpoints are blocked — from the REST setting **or** from "disable everywhere". Not implied by the post type fields |
| `xmlrpc_comments_blocked` | bool | Posting new comments over XML-RPC is blocked. **Creation only** — reading and moderating still work, see below |
| `is_network_active` | bool | Plugin is activated network-wide on multisite |
| `is_network_wide_setting` | bool | A super admin applies one set of settings to every site. Always `false` when `is_network_active` is `false` |
| `role_exclusion_enabled` | bool | The site exempts one or more user roles from comment disabling |
| `excluded_for_current_user` | bool | The requesting user is exempt by role — comments stay open for them |
| `woocommerce_active` | bool | WooCommerce is running. The two fields below only mean anything while it is |
| `product_reviews_disabled` | bool | Customers cannot leave a product review. Reviews are usually just comments on the `product` post type, so this is mostly the answer for reviews specifically. It can also be `true` on its own — when `review` is in `blocked_comment_types`, or when WooCommerce's own review switch is off. Describes the site, not the caller: a role exemption does not change it |
| `product_reviews_disabled_by` | string | What is blocking reviews: `disable-comments`, `woocommerce`, or `""` when they are enabled |
| `plugin_version` | string | Version of Disable Comments answering the request |

**Example response**

```json
{
  "status": "posts",
  "comments_disabled_everywhere": false,
  "allowed_comment_types": [],
  "blocked_comment_types": [],
  "disabled_post_types": ["post"],
  "available_post_types": ["post", "page", "attachment"],
  "is_network_active": false,
  "is_network_wide_setting": false,
  "rest_api_comments_blocked": false,
  "xmlrpc_comments_blocked": false,
  "role_exclusion_enabled": false,
  "excluded_for_current_user": false,
  "woocommerce_active": false,
  "product_reviews_disabled": false,
  "product_reviews_disabled_by": "",
  "plugin_version": "2.8.0"
}
```

## Calling it

In PHP:

```php
$ability = wp_get_ability( 'disable-comments/get-status' );
if ( $ability ) {
    $result = $ability->execute();
    if ( ! is_wp_error( $result ) ) {
        // $result['status'] === 'posts'
    }
}
```

`execute()` checks permissions first and validates the response against the
output schema, so a non-`WP_Error` return is guaranteed to match the table
above. Users without `manage_options` receive an `ability_invalid_permissions`
error.

### From an MCP client

REST and MCP are **two separate switches**. `show_in_rest` opens the
`wp-abilities/v1` routes; MCP servers gate independently on `meta.mcp.public`
and skip anything without it — silently, with no warning and nothing logged.
This ability sets both, so an MCP client can discover and run it:

```
discover-abilities  -> includes disable-comments/get-status
execute-ability     -> the full payload above, isError: false
```

Note it is invoked *through* the adapter's `execute-ability` meta-tool. It does
not appear in `tools/list` under its own name — the WordPress MCP Adapter's
default server exposes a fixed set of meta-tools and auto-discovers only
resources and prompts. Registering it as a first-class tool would require the
`mcp_adapter_default_server_config` filter, i.e. a hard dependency on a
specific third-party plugin, which this plugin deliberately does not take.

## Notes for integrators

- **The payload describes the site's comment configuration, not necessarily
  what the requesting user sees.** If `excluded_for_current_user` is `true`,
  role-based exclusion applies and comments remain open for that user even
  when `status` says otherwise. Agents that act on behalf of a specific user
  should check this flag before concluding "comments are off for me".
- **Check the transport flags before acting over REST.** `status` and
  `disabled_post_types` describe post-type configuration only. A site can have
  comments open on every post type while `rest_api_comments_blocked` is `true`,
  in which case reading or creating comments over REST is rejected. If you are
  an agent working through the REST API or MCP, that flag matters more than
  `status`. It is `true` when the dedicated REST setting is on **or** when
  comments are disabled everywhere — global mode installs the same REST filters.
  Types in `allowed_comment_types` are still permitted through.
- **The two transport flags do not block the same amount.** They read alike but
  are not symmetric:
  - `rest_api_comments_blocked` is broad. The plugin removes comment routes,
    rejects dispatch with a 403, and filters comment queries — so **reading and
    writing** are both blocked.
  - `xmlrpc_comments_blocked` is narrow. Only `wp.newComment` is removed, so it
    blocks **creation only**. `wp.getComment`, `wp.getComments`,
    `wp.editComment`, `wp.deleteComment`, `wp.getCommentCount` and
    `wp.getCommentStatusList` all still work.

  If you are moderating over XML-RPC, do not skip the operation because this
  flag is `true` — fetching, editing and deleting comments remain available.
- **`comments_disabled_everywhere` is not absolute.** Any type in
  `allowed_comment_types` is still preserved in comment queries, counted, and
  permitted through the REST API. Check that list before concluding a comment
  type is unavailable — WordPress 6.9+ editor notes are the common case.
- **"Comments are open" is not absolute either — check `blocked_comment_types`.**
  It is the only field that discloses a comment type closed on its own. Nothing
  else in the payload will: `status`, `disabled_post_types` and
  `comments_disabled_everywhere` can all say a post type accepts comments while
  one comment type on it is refused with a 403. The motivating case is a
  WooCommerce store closing `review` while other comments on products stay open,
  which `comments_open()` cannot express because it is one boolean per post.
  Existing comments of a blocked type are untouched and still readable — only
  creating one is refused.
- **When `comments_disabled_everywhere` is `true`, `disabled_post_types` lists
  every comment-capable post type**, not just the ones ticked in settings. The
  global setting also covers post types registered after the settings were
  saved, so reporting the stored selection alone would understate reality.
- **`status: "all"` does not mean the site is closed — `comments_disabled_everywhere` does.**
  The settings screen only lists `public` post types, so `status` reads `all`
  once all of *those* are switched off. A non-public post type that supports
  comments stays open in that case, and the payload will correctly report
  `comments_disabled_everywhere: false` beside `status: "all"`. That pairing is
  not a contradiction: `status` describes the settings screen, the flag
  describes the site. Only the global "disable everywhere" setting closes
  non-public and later-registered types.
- **Known limitation:** both post type lists come from the plugin's
  settings-screen list, which only includes `public` post types. A *non-public*
  post type that supports comments is still closed by the global setting but is
  not named in `disabled_post_types`. Treat `comments_disabled_everywhere` as
  authoritative for such types rather than the list.
- `status` matches
  `Disable_Comments::get_instance()->get_current_comment_status()` (see
  [README_COMMENT_STATUS.md](README_COMMENT_STATUS.md)) for every caller except
  a role-exempt one. That method resolves its `all` branch per-user, so for an
  exempt caller it would under-report the site's global setting; the ability
  reads the global setting role-independently instead and reports `all`.

## Write abilities

Two abilities change the site. Both are gated on `manage_options`, and on a
network-activated install with sitewide settings on, both require
`manage_network_plugins` — a call that rewrites configuration for every site in
a network needs the network capability, not just `manage_options` on whichever
site the request landed on.

### `disable-comments/set-status`

Changes where comments are disabled.

| Input | |
| --- | --- |
| `mode` | `everywhere`, `post_types`, or `off`. Required. |
| `post_types` | Slugs, when `mode` is `post_types`. |
| `confirm` | Must be `true`. Required. |

Returns `changed`, `previous_status`, `previous_mode`,
`previous_disabled_post_types`, `status`, `disabled_post_types` and
`unknown_post_types`.

**To undo a change, send back `previous_mode` and
`previous_disabled_post_types`** — not `previous_status`. That field is a
human-readable summary in the same vocabulary as `get-status`, and values like
`pages` or `multiple` are rejected by the input schema; `multiple` does not
carry the type list at all. The two `previous_*` fields together are everything
needed to restore the earlier state, which is why the ability is annotated
`destructive: false` despite being a write.

**`previous_status` and `status` describe the site, not the caller.** Both read
the global "disable everywhere" setting role-independently, exactly as
`get-status` does, so a caller in an excluded role gets the same summary as
anyone else. Use `excluded_for_current_user` from `get-status` to find out
whether comments are actually open for the user making the call.

Slugs the site does not register are reported in `unknown_post_types` and
ignored, rather than being stored where they would sit in the options claiming
to disable something that does not exist.

### `disable-comments/delete-comments`

Permanently deletes comments. There is no trash and no undo.

| Input | |
| --- | --- |
| `mode` | `everywhere`, `post_types`, `comment_types`, or `spam`. Required. |
| `post_types` / `comment_types` | Slugs, for the matching mode. |
| `dry_run` | Count only. **Defaults to `true`.** |
| `confirm` | Must be `true` for a real deletion. |

Returns `dry_run`, `deleted` and `matched`.

On a multisite network this only ever affects the site the request is made
against. It does not fan out across the network, even when the plugin is
network-activated.

**`dry_run` defaults to true when the field is omitted.** A missing field is
the most likely thing a caller gets wrong, and the safe reading of an
ambiguous delete request is to count rather than to delete. A real deletion
needs `dry_run: false` *and* `confirm: true` — two separate deliberate acts.

Comment types on the site's allowlist (WP 6.9+ notes, and anything a plugin
adds) cannot be deleted through this ability, and are excluded from `matched`.

### Why `confirm` exists

An agent must say so in the call. Without it, a mis-parsed instruction or a
hallucinated tool call rewrites a site's configuration, or empties its comments
table, with nothing standing in the way. Hosts that surface the `destructive`
annotation should require a human to confirm the delete ability on top of this.
