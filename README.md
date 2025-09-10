# Disable Comments for WordPress

<!-- [![Build Status](https://travis-ci.org/solarissmoke/disable-comments.svg?branch=master)](https://travis-ci.org/solarissmoke/disable-comments) -->

This is the development respository for the [Disable Comments](https://wordpress.org/plugins/disable-comments/) WordPress plugin. Send pull requests here, download the latest stable version there!

Version and compatibility information can be found in the plugin [readme](https://github.com/WPDevelopers/disable-comments/blob/master/readme.txt) file.

License: [GPLv3+](https://www.gnu.org/licenses/gpl-3.0.html)

## Comment Status Functions

The plugin provides two methods to programmatically check the current comment disable configuration:

### `get_current_comment_status()`

Returns a simple string indicating the current comment disable status.

**Return Values:**

- `'all'` - Comments disabled site-wide for all content types
- `'posts'` - Comments disabled only for blog posts
- `'pages'` - Comments disabled only for pages
- `'posts,pages'` - Comments disabled for both posts and pages
- `'multiple'` - Comments disabled for multiple specific content types
- `'none'` - Comments enabled everywhere
- Custom post type name (e.g., `'product'`) - Comments disabled for that specific post type

### `get_detailed_comment_status()`

Returns a comprehensive array with complete plugin configuration details including disabled post types, API restrictions, network settings, role exclusions, and comment counts.

**Key Array Elements:**

- `status` - Main status string (same as above method)
- `disabled_post_types` - Array of disabled post type slugs
- `total_comments` - Total number of comments in database
- `network_active` - Whether plugin is network activated
- `xmlrpc_disabled` / `rest_api_disabled` - API restriction status
- `role_exclusion_enabled` - Whether role-based exclusions are active

## Usage Examples

```php
// Get simple status
$instance = Disable_Comments::get_instance();
$status = $instance->get_current_comment_status();

if ($status === 'all') {
    echo 'Comments are disabled everywhere';
} elseif ($status === 'none') {
    echo 'Comments are enabled';
}

// Get detailed information
$details = $instance->get_detailed_comment_status();
echo 'Total comments: ' . $details['total_comments'];
echo 'Plugin configured: ' . ($details['is_configured'] ? 'Yes' : 'No');
```

**Note:** Call these methods on or after the `init` hook to ensure proper plugin initialization.

## Site Health Integration

Plugin status is automatically displayed in **Tools > Site Health > Info > Disable Comments** section, providing administrators with a complete overview of all plugin settings including comment counts, disabled post types, API restrictions, and network configuration. No additional setup required.

## Must-Use version

A [must-use version](https://github.com/WPDevelopers/disable-comments-mu) of the plugin is also available.

### This plugin is maintained by the [WPDeveloper](https://wpdeveloper.com/)
