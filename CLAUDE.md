# Disable Comments — Plugin Development Guide

WordPress plugin by WPDeveloper. Allows administrators to globally disable comments by post type, with multisite network support.

- **WordPress.org:** <https://wordpress.org/plugins/disable-comments/>
- **Current version:** 2.6.2
- **Main class:** `Disable_Comments` (singleton) in `disable-comments.php`

---

## Project Structure

```text
disable-comments.php          Main plugin file (~2000 lines), single class
includes/
  cli.php                     WP-CLI command definitions
  class-plugin-usage-tracker.php
views/
  settings.php                Main settings page shell
  comments.php                Tools/delete page shell
  partials/
    _disable.php              Disable-comments form (main settings form)
    _delete.php               Delete comments form
    _sites.php                Multisite sub-site list
    _menu.php / _footer.php / _sidebar.php
assets/
  js/disable-comments-settings-scripts.js   Settings page JS (role exclusion UI, AJAX calls)
  js/disable-comments.js
  css/ scss/
tests/
  test-plugin.php             PHPUnit tests (Brain/Monkey mocking)
  bootstrap.php
```

---

## Key AJAX Handlers

All three AJAX handlers are registered in `__construct()` (~line 49):

| Action | Handler | Line |
| ------ | ------- | ---- |
| `disable_comments_save_settings` | `disable_comments_settings()` | ~1217 |
| `disable_comments_delete_comments` | `delete_comments_settings()` | ~1324 |
| `get_sub_sites` | `get_sub_sites()` | ~1157 |

**Nonce:** All handlers verify nonce `disable_comments_save_settings`. The nonce is created in `admin_enqueue_scripts()` (~line 799) and exposed to JS as `disableCommentsObj._nonce`.

**POST data parsing:** `get_form_array_escaped()` (~line 1202) reads `$_POST['data']` as a URL-encoded string, parses with `wp_parse_args()`, and sanitizes all values with `map_deep(..., 'sanitize_text_field')`.

**Network admin flag:** `$formArray['is_network_admin']` comes from POST data and controls network-wide operations — always verify server-side capability before acting on it.

---

## Development

```bash
npm install        # Install JS build deps
npm run build      # Compile JS/CSS via Grunt + Babel
npm run release    # Build + generate .pot + package release
```

```bash
composer install              # Install PHP dev deps (Brain/Monkey for tests)
./vendor/bin/phpunit          # Run tests
```

**Linting:** `phpcs.ruleset.xml` is configured for WordPress Coding Standards.

---

## Architecture Notes

- **Singleton pattern:** Always access via `Disable_Comments::get_instance()`.
- **CLI support:** `includes/cli.php` calls the same handler methods with `$_args` to bypass nonce (expected for WP-CLI context; nonce bypass is gated on `$this->is_CLI`).
- **Multisite vs single-site:** Plugin behaviour branches heavily on `$this->networkactive` (set in constructor) and `$this->sitewide_settings`.
- **Database queries:** Use `$wpdb->prepare()` throughout `delete_comments()`. Safe against SQL injection.
- **Input sanitization:** `get_form_array_escaped()` uses `wp_parse_args()` + `map_deep(sanitize_text_field)`.
