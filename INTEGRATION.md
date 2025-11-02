# PluginPulse Connect Library - Integration Guide

Complete step-by-step guide for integrating PluginPulse Connect Library into your WordPress plugin.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation Methods](#installation-methods)
3. [Basic Integration](#basic-integration)
4. [Configuration](#configuration)
5. [support-config.json Setup](#support-configjson-setup)
6. [Advanced Usage](#advanced-usage)
7. [Multi-Plugin Scenarios](#multi-plugin-scenarios)
8. [Troubleshooting](#troubleshooting)

## Prerequisites

Before integrating the library, ensure your plugin meets these requirements:

- **PHP Version**: 7.4 or higher
- **WordPress Version**: 5.8 or higher
- **Plugin Structure**: Standard WordPress plugin structure
- **Namespace Support**: Plugin uses PHP namespaces (recommended but not required)

## Installation Methods

### Method 1: Composer Installation (Recommended)

Best for plugins that already use Composer for dependency management.

**Step 1: Add to composer.json**

```json
{
    "require": {
        "pluginpulse/connect-library": "^1.0"
    }
}
```

**Step 2: Install the library**

```bash
composer require pluginpulse/connect-library
```

**Step 3: Composer will create/update:**
- `vendor/` directory with the library
- `vendor/autoload.php` autoloader file
- `composer.lock` with exact versions

### Method 2: Manual Installation

Best for plugins that don't use Composer or need to bundle the library directly.

**Step 1: Download the library**

1. Go to [GitHub Releases](https://github.com/pluginpulse/pluginpulse-connect-library/releases)
2. Download the latest `pluginpulse-connect-library.zip`
3. Extract the ZIP file

**Step 2: Copy to your plugin**

```
your-plugin/
├── library/                          ← Create this directory
│   └── pluginpulse-connect/         ← Copy library here
│       ├── src/
│       ├── composer.json
│       └── README.md
├── your-plugin.php
└── ...
```

**Step 3: The library includes its own autoloader**

Located at: `library/pluginpulse-connect/src/autoload.php`

## Basic Integration

### Composer-Based Integration

**File**: `your-plugin.php` (main plugin file)

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Version: 1.0.0
 * Description: Does awesome things
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Define plugin constants
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Initialize PluginPulse Connect Library
add_action('plugins_loaded', function() {
    \PluginPulse\Library\Core\LibraryBootstrap::init([
        'plugin_slug'    => 'my-awesome-plugin',
        'plugin_name'    => 'My Awesome Plugin',
        'plugin_version' => MY_PLUGIN_VERSION,
        'option_name'    => 'my_awesome_plugin_settings',
        'plugin_url'     => MY_PLUGIN_URL,
    ]);
}, 10);

// Your plugin code continues here...
```

### Manual Integration

**File**: `your-plugin.php` (main plugin file)

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Version: 1.0.0
 * Description: Does awesome things
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load library autoloader
require_once __DIR__ . '/library/pluginpulse-connect/src/autoload.php';

// Define plugin constants
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Initialize PluginPulse Connect Library
add_action('plugins_loaded', function() {
    \PluginPulse\Library\Core\LibraryBootstrap::init([
        'plugin_slug'    => 'my-awesome-plugin',
        'plugin_name'    => 'My Awesome Plugin',
        'plugin_version' => MY_PLUGIN_VERSION,
        'option_name'    => 'my_awesome_plugin_settings',
        'plugin_url'     => MY_PLUGIN_URL,
        'library_path'   => __DIR__ . '/library/pluginpulse-connect',
    ]);
}, 10);

// Your plugin code continues here...
```

### Important Notes

1. **Use `plugins_loaded` hook**: Always initialize the library on the `plugins_loaded` action hook with priority 10 or lower
2. **Unique plugin_slug**: Must be unique across all plugins on the site
3. **Match plugin version**: `plugin_version` should match your actual plugin version
4. **Library path**: Only required for manual integration; auto-detected for Composer

## Configuration

### Full Configuration Options

```php
\PluginPulse\Library\Core\LibraryBootstrap::init([
    // ===== REQUIRED PARAMETERS =====

    'plugin_slug'    => 'my-plugin',
    // Unique identifier for your plugin (use the same as your plugin directory name)

    'plugin_name'    => 'My Plugin',
    // Human-readable plugin name (shown in admin UI)

    'plugin_version' => '1.0.0',
    // Your plugin version (semantic versioning recommended)

    'option_name'    => 'my_plugin_settings',
    // WordPress option name for storing plugin settings

    'plugin_url'     => plugin_dir_url( __FILE__ ),
    // Plugin URL for loading assets (JavaScript, CSS)
    // Use plugin_dir_url( __FILE__ ) in your main plugin file

    // ===== OPTIONAL PARAMETERS =====

    'library_path'   => '',
    // Path to library directory (auto-detected for Composer)
    // Required for manual integration: __DIR__ . '/library/pluginpulse-connect'

    'enable_rest_api' => true,
    // Enable REST API endpoints for remote diagnostics
    // Set to false to disable API endpoints entirely

    'enable_admin_ui' => true,
    // Enable admin interface
    // Set to false to disable admin UI entirely

    'diagnostics_callback' => null,
    // Optional callback function to generate additional diagnostic data
]);
```

### Configuration Examples

**Minimal Configuration (Composer):**
```php
\PluginPulse\Library\Core\LibraryBootstrap::init([
    'plugin_slug'    => 'my-plugin',
    'plugin_name'    => 'My Plugin',
    'plugin_version' => '1.0.0',
    'option_name'    => 'my_plugin_settings',
    'plugin_url'     => plugin_dir_url( __FILE__ ),
]);
```

**Full Configuration (Manual Integration):**
```php
\PluginPulse\Library\Core\LibraryBootstrap::init([
    'plugin_slug'      => 'my-ecommerce-plugin',
    'plugin_name'      => 'My eCommerce Plugin',
    'plugin_version'   => '2.5.3',
    'library_path'     => __DIR__ . '/library/pluginpulse-connect',
    'admin_menu_mode'  => 'unified',
    'enable_rest_api'  => true,
]);
```

## support-config.json Setup

The `support-config.json` file defines what diagnostic data your plugin collects.

### File Location

Create this file in your plugin's root directory:

```
your-plugin/
├── support-config.json    ← Create here
├── your-plugin.php
└── ...
```

### Basic Configuration

**File**: `support-config.json`

```json
{
  "plugin_name": "My Awesome Plugin",
  "plugin_slug": "my-awesome-plugin",
  "plugin_version": "1.0.0",
  "data_collection": {
    "wordpress_environment": true,
    "plugin_settings": true,
    "debug_log": false,
    "system_info": true
  },
  "settings_to_collect": [
    "my_plugin_api_key",
    "my_plugin_enable_feature",
    "my_plugin_cache_duration"
  ],
  "excluded_settings": [
    "my_plugin_secret_key",
    "my_plugin_password"
  ]
}
```

### Configuration Options

#### Top-Level Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `plugin_name` | string | Yes | Your plugin name |
| `plugin_slug` | string | Yes | Your plugin slug (must match init config) |
| `plugin_version` | string | Yes | Your plugin version |
| `data_collection` | object | Yes | What data to collect |
| `settings_to_collect` | array | No | Specific settings to include |
| `excluded_settings` | array | No | Settings to exclude (sensitive data) |

#### data_collection Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `wordpress_environment` | boolean | true | WP version, PHP version, server info |
| `plugin_settings` | boolean | true | Your plugin's settings/options |
| `debug_log` | boolean | false | WordPress debug log (if enabled) |
| `system_info` | boolean | true | Server specs, memory limits |

### Advanced Configuration

**Include Custom Data:**

```json
{
  "plugin_name": "My Plugin",
  "plugin_slug": "my-plugin",
  "plugin_version": "1.0.0",
  "data_collection": {
    "wordpress_environment": true,
    "plugin_settings": true,
    "debug_log": true,
    "system_info": true,
    "custom_data": true
  },
  "custom_data_callbacks": [
    "MyPlugin\\Diagnostics::get_api_status",
    "MyPlugin\\Diagnostics::get_cache_info"
  ],
  "settings_to_collect": [
    "my_plugin_*"
  ],
  "excluded_settings": [
    "*_password",
    "*_secret",
    "*_api_key"
  ]
}
```

### Security Best Practices

**Always exclude sensitive data:**

```json
{
  "excluded_settings": [
    "*_password",
    "*_secret",
    "*_api_key",
    "*_token",
    "*_private_key",
    "my_plugin_stripe_key",
    "my_plugin_paypal_secret"
  ]
}
```

**Use wildcards for pattern matching:**
- `my_plugin_*`: All settings starting with `my_plugin_`
- `*_password`: All settings ending with `_password`

## Advanced Usage

### Custom Diagnostic Data

Add plugin-specific diagnostic data to the collection:

```php
add_filter('pluginpulse_custom_diagnostics_my-plugin', function($data) {
    $data['custom_info'] = [
        'api_status' => my_plugin_check_api(),
        'cache_size' => my_plugin_get_cache_size(),
        'last_sync'  => get_option('my_plugin_last_sync'),
    ];

    return $data;
});
```

### Conditional Library Loading

Only load the library in certain environments:

```php
add_action('plugins_loaded', function() {
    // Only initialize if admin or WP-CLI
    if (!is_admin() && !defined('WP_CLI')) {
        return;
    }

    \PluginPulse\Library\Core\LibraryBootstrap::init([
        'plugin_slug'    => 'my-plugin',
        'plugin_name'    => 'My Plugin',
        'plugin_version' => '1.0.0',
    ]);
}, 10);
```

### Programmatic Diagnostic Generation

Generate diagnostics programmatically (requires REST API enabled):

```php
// Get diagnostic data for your plugin
$diagnostics = apply_filters('pluginpulse_get_diagnostics_my-plugin', []);

// Generate temporary access link
$link = apply_filters('pluginpulse_generate_link_my-plugin', null, [
    'expiration' => 3600, // 1 hour
]);
```

## Multi-Plugin Scenarios

### How It Works

When multiple plugins embed the library:

1. **First plugin loads** → Library initializes with that version
2. **Second plugin loads** → Library checks versions
   - If newer: Switches to newer version
   - If older: Continues with current version
3. **All plugins** → Use the same (newest) library version

### Version Detection Example

```
Site has 3 plugins with PluginPulse Connect Library:

Plugin A: library v1.2.0 (loads first)
  → Active version: 1.2.0

Plugin B: library v1.1.0 (loads second)
  → Active version: 1.2.0 (keeps existing, newer)

Plugin C: library v1.3.0 (loads third)
  → Active version: 1.3.0 (switches to newest)

Result: All 3 plugins now use library v1.3.0
```

### Unified Admin UI

**WordPress Admin → Tools → PluginPulse Diagnostics**

Shows tabs for each plugin:
- **Plugin A** tab
- **Plugin B** tab
- **Plugin C** tab
- **Settings** tab (shows active library version)

Each plugin can generate its own diagnostics independently.

### Separate Admin UI Mode

If you prefer separate menu items:

```php
\PluginPulse\Library\Core\LibraryBootstrap::init([
    'plugin_slug'     => 'my-plugin',
    'plugin_name'     => 'My Plugin',
    'plugin_version'  => '1.0.0',
    'admin_menu_mode' => 'separate',  // Creates separate menu item
]);
```

Result:
- **Tools → My Plugin Diagnostics** (separate menu)
- Other plugins get their own menu items too

## Troubleshooting

### Library Not Loading

**Symptom**: No admin menu appears, REST endpoints don't work

**Solutions**:

1. **Check autoloader is loaded:**
   ```php
   // Composer
   if (!class_exists('\PluginPulse\Library\Core\LibraryBootstrap')) {
       error_log('PluginPulse library not loaded - check composer install');
   }

   // Manual
   if (!file_exists(__DIR__ . '/library/pluginpulse-connect/src/autoload.php')) {
       error_log('PluginPulse library not found in library directory');
   }
   ```

2. **Verify initialization hook:**
   ```php
   // Must be on plugins_loaded or later
   add_action('plugins_loaded', function() {
       \PluginPulse\Library\Core\LibraryBootstrap::init([...]);
   }, 10);
   ```

3. **Check PHP version:**
   ```php
   if (version_compare(PHP_VERSION, '7.4', '<')) {
       // Library requires PHP 7.4+
   }
   ```

### Version Conflicts

**Symptom**: Admin notices about version mismatches

**Solutions**:

1. **Check active version:**
   ```php
   $version = \PluginPulse\Library\Core\VersionManager::get_active_version();
   error_log("Active library version: $version");
   ```

2. **List all registered plugins:**
   ```php
   $plugins = \PluginPulse\Library\Core\ServiceLocator::get_all_plugins();
   foreach ($plugins as $slug => $info) {
       error_log("Plugin: $slug, Library: {$info['library_version']}");
   }
   ```

3. **Update to latest library version** in your plugin

### Missing Admin Menu

**Symptom**: Other plugins show tabs, but not yours

**Solutions**:

1. **Verify plugin_slug is unique:**
   ```php
   // Must not conflict with other plugins
   'plugin_slug' => 'my-unique-plugin-slug',
   ```

2. **Check user capabilities:**
   ```php
   // Must be logged in as admin
   if (!current_user_can('manage_options')) {
       // Admin menu only visible to admins
   }
   ```

3. **Check initialization succeeded:**
   ```php
   add_action('admin_notices', function() {
       $registered = \PluginPulse\Library\Core\ServiceLocator::is_registered('my-plugin');
       if (!$registered) {
           echo '<div class="notice notice-error"><p>PluginPulse library not initialized</p></div>';
       }
   });
   ```

### REST API Endpoints Not Working

**Symptom**: `/wp-json/pluginpulse/my-plugin/v1/diagnostics` returns 404

**Solutions**:

1. **Verify REST API is enabled:**
   ```php
   \PluginPulse\Library\Core\LibraryBootstrap::init([
       'plugin_slug'    => 'my-plugin',
       'plugin_name'    => 'My Plugin',
       'plugin_version' => '1.0.0',
       'enable_rest_api' => true,  // Must be true
   ]);
   ```

2. **Flush rewrite rules:**
   ```php
   // In plugin activation
   register_activation_hook(__FILE__, function() {
       flush_rewrite_rules();
   });
   ```

3. **Check endpoint registration:**
   ```php
   add_action('rest_api_init', function() {
       $routes = rest_get_server()->get_routes();
       if (isset($routes['/pluginpulse/my-plugin/v1/diagnostics'])) {
           error_log('Diagnostic endpoint registered');
       }
   });
   ```

### support-config.json Not Found

**Symptom**: Settings not being collected

**Solutions**:

1. **Verify file location:**
   ```php
   $config_path = plugin_dir_path(__FILE__) . 'support-config.json';
   if (!file_exists($config_path)) {
       error_log("support-config.json not found at: $config_path");
   }
   ```

2. **Validate JSON syntax:**
   ```bash
   # Use JSON validator
   cat support-config.json | python -m json.tool
   ```

3. **Check file permissions:**
   ```bash
   # Must be readable by web server
   chmod 644 support-config.json
   ```

### Debugging Tips

**Enable WordPress debug mode:**

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Add library debug logging:**

```php
add_filter('pluginpulse_debug', '__return_true');
```

**Check error log:**

```bash
# Default location
tail -f wp-content/debug.log
```

## Getting Help

If you encounter issues not covered here:

1. **Check the documentation**: [https://pluginpulse.io/docs](https://pluginpulse.io/docs)
2. **Search existing issues**: [GitHub Issues](https://github.com/pluginpulse/pluginpulse-connect-library/issues)
3. **Create a new issue**: Include:
   - PHP version
   - WordPress version
   - Integration method (Composer/Manual)
   - Error messages from debug log
   - support-config.json (with sensitive data removed)
4. **Contact support**: support@pluginpulse.io

## Next Steps

- Review [API.md](API.md) for REST endpoint documentation
- Check [examples/](examples/) for complete integration examples
- Read [TESTING.md](TESTING.md) for testing your integration
- See [CONTRIBUTING.md](CONTRIBUTING.md) to contribute to the library