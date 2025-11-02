<?php
/**
 * Example: Integrating PluginPulse Connect Library via Composer
 *
 * This example shows how to initialize the library when it's installed via Composer.
 * The library will be autoloaded by Composer's autoloader automatically.
 *
 * @package PluginPulse\Library
 * @since 1.0.0
 */

// Assuming Composer autoload is already loaded in your plugin
// If not, you would include it like this:
// require_once __DIR__ . '/vendor/autoload.php';

use PluginPulse\Library\Core\LibraryBootstrap;

/**
 * Initialize the library in your plugin's main file or initialization hook
 */
add_action(
	'plugins_loaded',
	function () {
		// Initialize library with minimal configuration
		LibraryBootstrap::init(
			array(
				'plugin_slug'    => 'my-awesome-plugin',           // Required: Your plugin's unique slug
				'plugin_name'    => 'My Awesome Plugin',           // Required: Display name
				'plugin_version' => '1.0.0',                       // Required: Your plugin version
				'option_name'    => 'my_plugin_settings',          // Required: WordPress option name for settings
			)
		);
	},
	5 // Priority 5 ensures library loads early
);

/**
 * Example with all available options
 */
add_action(
	'plugins_loaded',
	function () {
		LibraryBootstrap::init(
			array(
				// Required fields
				'plugin_slug'    => 'my-awesome-plugin',
				'plugin_name'    => 'My Awesome Plugin',
				'plugin_version' => '1.0.0',
				'option_name'    => 'my_plugin_settings',

				// Optional fields
				'library_path'    => __DIR__ . '/vendor/pluginpulse/connect-library', // Auto-detected if not provided
				'enable_rest_api' => true,  // Enable REST endpoint for diagnostics (default: true)
				'enable_admin_ui' => true,  // Enable admin interface (default: true)

				// Optional: Provide custom diagnostics callback
				'diagnostics_callback' => function () {
					// Return array of diagnostic data specific to your plugin
					return array(
						'custom_setting'   => get_option( 'my_custom_setting' ),
						'feature_flags'    => array(
							'feature_a' => true,
							'feature_b' => false,
						),
						'cache_status'     => wp_cache_get( 'my_cache_key' ) ? 'active' : 'inactive',
					);
				},
			)
		);
	},
	5
);