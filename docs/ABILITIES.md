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
| `disabled_post_types` | string[] | Post type slugs with comments disabled |
| `available_post_types` | string[] | Post type slugs that support comments and can be toggled |
| `rest_api_comments_blocked` | bool | REST API comment endpoints are blocked — from the REST setting **or** from "disable everywhere". Not implied by the post type fields |
| `xmlrpc_comments_blocked` | bool | Posting new comments over XML-RPC is blocked. **Creation only** — reading and moderating still work, see below |
| `is_network_active` | bool | Plugin is activated network-wide on multisite |
| `is_network_wide_setting` | bool | A super admin applies one set of settings to every site. Always `false` when `is_network_active` is `false` |
| `role_exclusion_enabled` | bool | The site exempts one or more user roles from comment disabling |
| `excluded_for_current_user` | bool | The requesting user is exempt by role — comments stay open for them |
| `plugin_version` | string | Version of Disable Comments answering the request |

**Example response**

```json
{
  "status": "posts",
  "comments_disabled_everywhere": false,
  "allowed_comment_types": [],
  "disabled_post_types": ["post"],
  "available_post_types": ["post", "page", "attachment"],
  "is_network_active": false,
  "is_network_wide_setting": false,
  "rest_api_comments_blocked": false,
  "xmlrpc_comments_blocked": false,
  "role_exclusion_enabled": false,
  "excluded_for_current_user": false,
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
- Write abilities (changing settings, deleting comments) are intentionally not
  registered yet. They will ship separately with their own capability checks so
  that read access can be granted to agents without granting write access.
