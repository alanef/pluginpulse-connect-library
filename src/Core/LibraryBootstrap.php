<?php
/**
 * Library Bootstrap for PluginPulse Connect Library
 *
 * Main entry point for all plugins using the library. Handles version detection,
 * component initialization, and plugin registration.
 *
 * @package PluginPulse\Library\Core
 * @since 1.0.0
 */

namespace PluginPulse\Library\Core;

use PluginPulse\Library\Data\DiagnosticData;
use PluginPulse\Library\Data\PluginDiscovery;
use PluginPulse\Library\REST\DiagnosticsEndpoint;
use PluginPulse\Library\Admin\AdminPage;

/**
 * Class LibraryBootstrap
 *
 * Provides simple initialization API for third-party plugins.
 * Handles version detection, component wiring, and multi-plugin coordination.
 */
class LibraryBootstrap {

	/**
	 * Library version
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Initialization status per plugin
	 *
	 * @var array Format: ['plugin-slug' => true/false]
	 */
	private static $initialized_plugins = array();

	/**
	 * Active components per plugin
	 *
	 * @var array Format: ['plugin-slug' => ['discovery' => obj, 'endpoint' => obj, 'admin' => obj]]
	 */
	private static $plugin_components = array();

	/**
	 * Initialize the library for a host plugin
	 *
	 * This is the main entry point. Each plugin calls this method with its configuration
	 * to initialize the library components.
	 *
	 * @param array $config Configuration array with the following keys:
	 *                      - plugin_slug (string, required): Plugin identifier
	 *                      - plugin_name (string, required): Display name
	 *                      - plugin_version (string, required): Plugin version
	 *                      - option_name (string, required): WordPress option name for settings
	 *                      - plugin_url (string, required): Plugin URL for loading assets
	 *                      - library_path (string, optional): Path to library directory
	 *                      - enable_rest_api (bool, optional): Enable REST endpoint (default: true)
	 *                      - enable_admin_ui (bool, optional): Enable admin interface (default: true)
	 *                      - diagnostics_callback (callable, optional): Function to generate diagnostics
	 *
	 * @return bool True if this version loaded and initialized, false if using existing version.
	 */
	public static function init( $config ) {
		// Validate required configuration
		$defaults = array(
			'plugin_slug'          => '',
			'plugin_name'          => '',
			'plugin_version'       => '',
			'option_name'          => '',
			'plugin_url'           => '',
			'library_path'         => '',
			'enable_rest_api'      => true,
			'enable_admin_ui'      => true,
			'diagnostics_callback' => null,
		);

		$config = array_merge( $defaults, $config );

		// Validate required fields
		$required = array( 'plugin_slug', 'plugin_name', 'plugin_version', 'option_name' );
		foreach ( $required as $field ) {
			if ( empty( $config[ $field ] ) ) {
				self::log_error(
					sprintf(
						'Library initialization failed: Missing required field "%s"',
						$field
					)
				);
				return false;
			}
		}

		$plugin_slug = sanitize_key( $config['plugin_slug'] );

		// Check if this plugin already initialized
		if ( isset( self::$initialized_plugins[ $plugin_slug ] ) ) {
			self::log_debug(
				sprintf(
					'Plugin %s already initialized. Skipping duplicate initialization.',
					$plugin_slug
				)
			);
			return false;
		}

		// Determine library path
		$library_path = ! empty( $config['library_path'] )
			? $config['library_path']
			: dirname( dirname( __DIR__ ) ); // Default to library root

		// Register with VersionManager to determine if this version should load
		$should_load = VersionManager::register(
			$plugin_slug,
			self::VERSION,
			$library_path
		);

		// Always register plugin with ServiceLocator (even if not loading)
		self::register_with_service_locator( $config );

		// If this version should not load, skip component initialization
		if ( ! $should_load ) {
			self::log_debug(
				sprintf(
					'Plugin %s: Using existing library v%s (this plugin has v%s)',
					$plugin_slug,
					VersionManager::get_active_version(),
					self::VERSION
				)
			);

			self::$initialized_plugins[ $plugin_slug ] = false;
			return false;
		}

		// This version should load - initialize all components
		self::log_debug(
			sprintf(
				'Plugin %s: Initializing library v%s',
				$plugin_slug,
				self::VERSION
			)
		);

		self::initialize_components( $config );

		self::$initialized_plugins[ $plugin_slug ] = true;

		return true;
	}

	/**
	 * Initialize library components for a plugin
	 *
	 * @param array $config Plugin configuration.
	 */
	private static function initialize_components( $config ) {
		$plugin_slug = sanitize_key( $config['plugin_slug'] );
		$option_name = $config['option_name'];

		// Get plugin settings from WordPress options
		$settings = get_option( $option_name, array() );

		// Initialize default settings if needed
		$default_settings = array(
			'access_key'               => '',
			'enable_rest_endpoint'     => true,
			'rest_endpoint_key'        => '',
			'manage_debug_constants'   => false,
			'debug_constants'          => array(),
			'manual_plugin_options'    => array(),
			'scan_shortcodes'          => array(),
			'freemius_modules'         => array(),
			'loaded_freemius_instances' => array(),
		);

		$settings = wp_parse_args( $settings, $default_settings );

		// Generate access keys if empty
		if ( empty( $settings['access_key'] ) ) {
			$settings['access_key'] = self::generate_random_key( 12 );
			update_option( $option_name, $settings );
		}

		if ( empty( $settings['rest_endpoint_key'] ) ) {
			$settings['rest_endpoint_key'] = self::generate_random_key( 32 );
			update_option( $option_name, $settings );
		}

		// Initialize PluginDiscovery
		$plugin_discovery = new PluginDiscovery(
			$plugin_slug,
			$option_name,
			$settings,
			array() // Known debug constants will be populated by discovery
		);

		$discovered_plugins    = $plugin_discovery->discover_compatible_plugins();
		$settings              = $plugin_discovery->get_settings();
		$known_debug_constants = $plugin_discovery->get_known_debug_constants();

		// Store components for later access
		self::$plugin_components[ $plugin_slug ] = array(
			'discovery'             => $plugin_discovery,
			'discovered_plugins'    => $discovered_plugins,
			'settings'              => $settings,
			'known_debug_constants' => $known_debug_constants,
		);

		// Initialize REST API if enabled
		if ( $config['enable_rest_api'] && ! empty( $settings['enable_rest_endpoint'] ) ) {
			$rest_endpoint = new DiagnosticsEndpoint(
				$plugin_slug,
				$option_name,
				$settings,
				$discovered_plugins,
				$known_debug_constants
			);

			add_action( 'rest_api_init', array( $rest_endpoint, 'register_rest_route' ) );

			// Register AJAX handlers with plugin-specific action names
			add_action(
				'wp_ajax_pp_' . $plugin_slug . '_generate_diagnostic_data',
				array( $rest_endpoint, 'ajax_generate_diagnostic_data' )
			);
			add_action(
				'wp_ajax_pp_' . $plugin_slug . '_regenerate_keys',
				array( $rest_endpoint, 'ajax_regenerate_keys' )
			);

			self::$plugin_components[ $plugin_slug ]['endpoint'] = $rest_endpoint;

			self::log_debug(
				sprintf(
					'Plugin %s: REST API initialized with namespace pluginpulse/%s/v1',
					$plugin_slug,
					$plugin_slug
				)
			);
		}

		// Initialize Admin UI if enabled
		if ( $config['enable_admin_ui'] && is_admin() ) {
			$admin_page = new AdminPage(
				$plugin_slug,
				$option_name,
				$settings,
				$discovered_plugins,
				$known_debug_constants,
				$config['plugin_url']
			);

			add_action( 'admin_menu', array( $admin_page, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $admin_page, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $admin_page, 'enqueue_admin_scripts' ) );

			self::$plugin_components[ $plugin_slug ]['admin'] = $admin_page;

			self::log_debug(
				sprintf(
					'Plugin %s: Admin UI initialized',
					$plugin_slug
				)
			);
		}

		/**
		 * Fires after a plugin's library components are initialized
		 *
		 * @param string $plugin_slug The plugin slug.
		 * @param array  $config      The plugin configuration.
		 */
		do_action( 'pluginpulse_library_components_initialized', $plugin_slug, $config );
	}

	/**
	 * Register plugin with ServiceLocator
	 *
	 * @param array $config Plugin configuration.
	 */
	private static function register_with_service_locator( $config ) {
		$plugin_slug = sanitize_key( $config['plugin_slug'] );

		// Build registration config for ServiceLocator
		$registration_config = array(
			'slug'            => $plugin_slug,
			'name'            => $config['plugin_name'],
			'version'         => $config['plugin_version'],
			'library_version' => self::VERSION,
		);

		// Add diagnostics callback if provided
		if ( isset( $config['diagnostics_callback'] ) && is_callable( $config['diagnostics_callback'] ) ) {
			$registration_config['diagnostics_callback'] = $config['diagnostics_callback'];
		}

		ServiceLocator::register_plugin( $registration_config );

		self::log_debug(
			sprintf(
				'Plugin %s registered with ServiceLocator',
				$plugin_slug
			)
		);
	}

	/**
	 * Get library version
	 *
	 * @return string Library version number.
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Check if a specific plugin has initialized
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return bool True if initialized, false otherwise.
	 */
	public static function is_plugin_initialized( $plugin_slug ) {
		$plugin_slug = sanitize_key( $plugin_slug );
		return isset( self::$initialized_plugins[ $plugin_slug ] ) && self::$initialized_plugins[ $plugin_slug ];
	}

	/**
	 * Get components for a specific plugin
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|null Array of components, or null if not found.
	 */
	public static function get_plugin_components( $plugin_slug ) {
		$plugin_slug = sanitize_key( $plugin_slug );
		return isset( self::$plugin_components[ $plugin_slug ] ) ? self::$plugin_components[ $plugin_slug ] : null;
	}

	/**
	 * Generate a random key
	 *
	 * @param int $length Key length (default: 12).
	 *
	 * @return string Random key.
	 */
	private static function generate_random_key( $length = 12 ) {
		return substr( str_shuffle( md5( microtime() ) ), 0, $length );
	}

	/**
	 * Log debug messages when WP_DEBUG is enabled
	 *
	 * @param string $message The message to log.
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'PluginPulse Bootstrap: ' . $message );
		}
	}

	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log.
	 */
	private static function log_error( $message ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'PluginPulse Bootstrap ERROR: ' . $message );
	}

	/**
	 * Reset the bootstrap (primarily for testing)
	 *
	 * @internal
	 */
	public static function reset() {
		self::$initialized_plugins = array();
		self::$plugin_components   = array();
	}
}