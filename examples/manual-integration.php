<?php
/**
 * Example: Integrating PluginPulse Connect Library Manually (without Composer)
 *
 * This example shows how to initialize the library when you've included it
 * directly in your plugin without using Composer.
 *
 * @package PluginPulse\Library
 * @since 1.0.0
 */

/**
 * Step 1: Include the library
 *
 * Copy the pluginpulse-connect-library directory into your plugin.
 * A common structure would be:
 *
 * my-plugin/
 * ├── my-plugin.php (main plugin file)
 * ├── includes/
 * └── lib/
 *     └── pluginpulse-connect-library/
 *         ├── src/
 *         │   ├── autoload.php  <-- Include this file
 *         │   ├── Core/
 *         │   ├── Admin/
 *         │   ├── Data/
 *         │   └── REST/
 *         └── README.md
 */

// Load the library's autoloader
require_once __DIR__ . '/lib/pluginpulse-connect-library/src/autoload.php';

/**
 * Step 2: Initialize the library
 *
 * Call LibraryBootstrap::init() during your plugin's initialization.
 * It's recommended to use the plugins_loaded hook with priority 5.
 */
add_action(
	'plugins_loaded',
	function () {
		// Initialize library with minimal configuration
		\PluginPulse\Library\Core\LibraryBootstrap::init(
			array(
				'plugin_slug'    => 'my-plugin',              // Required: Your plugin's unique slug
				'plugin_name'    => 'My Plugin',              // Required: Display name
				'plugin_version' => '1.0.0',                  // Required: Your plugin version
				'option_name'    => 'my_plugin_settings',     // Required: WordPress option name
				'library_path'   => __DIR__ . '/lib/pluginpulse-connect-library', // Recommended for manual loading
			)
		);
	},
	5
);

/**
 * Example with all options for manual integration
 */
add_action(
	'plugins_loaded',
	function () {
		$library_loaded = \PluginPulse\Library\Core\LibraryBootstrap::init(
			array(
				// Required fields
				'plugin_slug'    => 'my-plugin',
				'plugin_name'    => 'My Plugin',
				'plugin_version' => '1.0.0',
				'option_name'    => 'my_plugin_settings',

				// Recommended for manual loading (helps with version detection)
				'library_path'   => __DIR__ . '/lib/pluginpulse-connect-library',

				// Optional: Control which features to enable
				'enable_rest_api' => true,  // Enable REST endpoint
				'enable_admin_ui' => true,  // Enable admin interface

				// Optional: Provide custom diagnostics
				'diagnostics_callback' => function () {
					return array(
						'plugin_status' => 'active',
						'custom_data'   => get_option( 'my_custom_data' ),
					);
				},
			)
		);

		// $library_loaded will be true if this version initialized,
		// false if an existing version is being used
		if ( $library_loaded ) {
			// Your version of the library is active
			error_log( 'My Plugin: Library v' . \PluginPulse\Library\Core\LibraryBootstrap::get_version() . ' loaded' );
		} else {
			// Another plugin's version is active (newer or first loaded)
			error_log( 'My Plugin: Using existing library' );
		}
	},
	5
);

/**
 * Step 3 (Optional): Create a support-config.json file
 *
 * If you want your plugin to be auto-discovered by other PluginPulse-enabled plugins,
 * create a support-config.json file in your plugin's root directory.
 *
 * Example support-config.json:
 *
 * {
 *   "plugin_info": {
 *     "name": "My Plugin",
 *     "slug": "my-plugin",
 *     "version": "1.0.0"
 *   },
 *   "shortcodes": ["my_shortcode", "another_shortcode"],
 *   "debug_constants": {
 *     "MY_PLUGIN_DEBUG": "My Plugin Debug Mode"
 *   },
 *   "freemius": {
 *     "global_variable": "my_plugin_fs"
 *   }
 * }
 */