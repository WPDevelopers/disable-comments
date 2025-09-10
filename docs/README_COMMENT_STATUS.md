# Comment Status Functions

The Disable Comments plugin now includes two methods to programmatically check the current comment disable configuration.

## Available Methods

### `get_current_comment_status()`

Returns a simple string describing the current comment status.

**Usage:**
```php
$instance = Disable_Comments::get_instance();
$status = $instance->get_current_comment_status();

// Possible return values:
// 'all' - Comments disabled site-wide
// 'posts' - Comments disabled only on posts
// 'pages' - Comments disabled only on pages
// 'posts,pages' - Comments disabled on both posts and pages
// 'custom_type_name' - Comments disabled on specific custom post type
// 'multiple' - Comments disabled on multiple specific types
// 'none' - Comments enabled everywhere
```

### `get_detailed_comment_status()`

Returns comprehensive status information as an associative array.

**Usage:**
```php
$instance = Disable_Comments::get_instance();
$details = $instance->get_detailed_comment_status();

// Returns array with keys:
// 'status' - Main status string
// 'disabled_post_types' - Array of disabled post type slugs
// 'disabled_post_type_labels' - Array of human-readable labels
// 'remove_everywhere' - Boolean for global disable
// 'xmlrpc_disabled' - Boolean for XML-RPC restriction
// 'rest_api_disabled' - Boolean for REST API restriction
// 'total_post_types' - Total available post types
// 'is_configured' - Boolean for plugin configuration status
// 'total_comments' - Total number of comments in database
// 'network_active' - Boolean for network activation status
// 'sitewide_settings' - Site-wide settings status ('enabled'/'disabled'/'not_applicable')
// 'role_exclusion_enabled' - Boolean for role-based exclusions
// 'excluded_roles' - Array of excluded role slugs
// 'excluded_role_labels' - Array of human-readable excluded role names
```

## Site Health Integration

The detailed comment status is automatically displayed in WordPress's Site Health Info panel:

1. Go to **Tools > Site Health**
2. Click the **Info** tab
3. Look for the **Disable Comments** section

This provides administrators with a complete overview of all plugin settings including:

- **Comment Status**: Overall status description
- **Plugin Configured**: Whether the plugin has been set up
- **Total Comments**: Current number of comments in the database
- **Global Disable Active**: Yes/No for site-wide comment disable
- **Disabled Post Types Count**: Number of disabled types (e.g., "2 of 5")
- **Disabled Post Types**: List of specific post types with disabled comments
- **XML-RPC Comments**: Enabled/Disabled status
- **REST API Comments**: Enabled/Disabled status
- **Network Active**: Whether plugin is network activated
- **Site-wide Settings**: Network site-wide settings status
- **Role-based Exclusions**: Whether role exclusions are enabled
- **Excluded Roles**: List of roles excluded from comment restrictions

All fields are always displayed regardless of their current state, providing a complete configuration overview.

## Example Usage

```php
// Basic status check
$instance = Disable_Comments::get_instance();
$status = $instance->get_current_comment_status();

if ($status === 'all') {
    echo "Comments are disabled everywhere";
} elseif ($status === 'none') {
    echo "Comments are enabled";
} else {
    echo "Comments are partially disabled: " . $status;
}

// Detailed information
$details = $instance->get_detailed_comment_status();
if ($details['is_configured'] && $details['remove_everywhere']) {
    echo "Plugin is configured with global comment disable";
}
```
