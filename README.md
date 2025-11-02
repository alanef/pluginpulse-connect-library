# PluginPulse Connect Library

Embeddable diagnostic and monitoring library for WordPress plugins.

## Overview

PluginPulse Connect Library enables WordPress plugin developers to add comprehensive diagnostic and monitoring capabilities to their plugins without requiring users to install a separate plugin. The library handles version conflicts automatically and supports multiple plugins using it simultaneously.

## Features

- 🔍 **Automatic Diagnostic Data Collection** - Gathers WordPress environment, plugin settings, and debug information
- 📊 **Multi-Plugin Aggregation** - Works seamlessly when multiple plugins embed the library
- 🔐 **Secure REST API Endpoints** - Generate temporary access links for remote diagnostics
- ⚙️ **Configurable Data Collection** - Define exactly what data to collect via support-config.json
- 🔄 **Automatic Version Conflict Resolution** - Always uses the newest library version
- 💻 **Composer or Manual Integration** - Works with or without Composer

## Requirements

- PHP 7.4+
- WordPress 5.8+
- (Optional) Composer for dependency management

## Installation

### Method 1: Via Composer (Recommended)

```bash
composer require pluginpulse/connect-library
```

### Method 2: Manual Installation

1. Download the latest release from [GitHub Releases](https://github.com/pluginpulse/pluginpulse-connect-library/releases)
2. Copy the `pluginpulse-connect-library` folder to your plugin's `library/` directory
3. Include the autoloader in your plugin

## Quick Start

### Composer Integration

```php
<?php
// In your main plugin file, after loading Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Initialize the library
\PluginPulse\Library\Core\LibraryBootstrap::init([
    'plugin_slug'    => 'my-plugin',
    'plugin_name'    => 'My Plugin Name',
    'plugin_version' => '1.0.0',
]);
```

### Manual Integration

```php
<?php
// In your main plugin file
require_once __DIR__ . '/library/pluginpulse-connect/src/autoload.php';

// Initialize the library
\PluginPulse\Library\Core\LibraryBootstrap::init([
    'plugin_slug'    => 'my-plugin',
    'plugin_name'    => 'My Plugin Name',
    'plugin_version' => '1.0.0',
    'library_path'   => __DIR__ . '/library/pluginpulse-connect',
]);
```

## Configuration Options

Full initialization configuration:

```php
\PluginPulse\Library\Core\LibraryBootstrap::init([
    // Required
    'plugin_slug'        => 'my-plugin',      // Unique plugin identifier
    'plugin_name'        => 'My Plugin',      // Display name
    'plugin_version'     => '1.0.0',         // Plugin version

    // Optional
    'library_path'       => '',              // Path to library (auto-detected if empty)
    'admin_menu_mode'    => 'unified',       // 'unified' or 'separate'
    'enable_rest_api'    => true,            // Enable REST API endpoints
]);
```

## How It Works

### Version Management

When multiple plugins embed the library, it automatically handles version conflicts:

1. **First plugin loads** → Sets the active library version
2. **Newer version detected** → Library switches to the newer version
3. **Older version detected** → Continues using the currently active version

**Example:**
- Plugin A (library v1.2.0) loads → Active: v1.2.0
- Plugin B (library v1.1.0) loads → Active: v1.2.0 (newer already loaded)
- Plugin C (library v1.3.0) loads → Active: v1.3.0 (switches to newest)

All plugins now use v1.3.0 automatically.

### Multi-Plugin Admin UI

When multiple plugins use the library, a unified admin interface is created:

**WordPress Admin → Tools → PluginPulse Diagnostics**
- Tab: Plugin A Diagnostics
- Tab: Plugin B Diagnostics
- Tab: Plugin C Diagnostics
- Tab: Settings (library version info)

Each tab allows generating diagnostics specific to that plugin.

## Documentation

- [Integration Guide](INTEGRATION.md) - Step-by-step integration instructions
- [API Reference](API.md) - REST API endpoint documentation
- [Examples](examples/) - Working code examples
- [Testing Guide](TESTING.md) - How to test library integration

## Examples

See the `examples/` directory for:
- Composer integration example
- Manual integration example
- Advanced configuration
- Custom diagnostic data

## Support

- **Documentation**: https://pluginpulse.io/docs
- **Issues**: https://github.com/pluginpulse/pluginpulse-connect-library/issues
- **Email**: support@pluginpulse.io

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

GPL-2.0-or-later - See [LICENSE](LICENSE) file for details.

## Credits

Developed by [PluginPulse](https://pluginpulse.io) for the WordPress plugin development community.