
<?php
/**
 * Service Locator for PluginPulse Connect Library
 *
 * Central registry for all plugins using the library. Manages plugin
 * registration and provides unified access to diagnostic data.
 *
 * @package PluginPulse\Library\Core
 * @since 1.0.0
 */

namespace PluginPulse\Library\Core;

/**
 * Class ServiceLocator
 *
 * Singleton registry pattern for managing multiple plugins using the library.
 * Provides centralized access to plugin configurations and diagnostic data.
 */
class ServiceLocator {

	/**
	 * Registry of all plugins using the library
	 *
	 * @var array Format: ['plugin-slug' => ['name' => '', 'version' => '', 'callback' => callable, ...]]
	 */
	private static $plugins = array();

	/**
	 * Whether the unified admin menu has been registered
	 *
	 * @var bool
	 */
	private static $menu_registered = false;

	/**
	 * Register a plugin with the library
	 *
	 * Each plugin should call this method during initialization to register
	 * itself with the library's service locator.
	 *
	 * @param array $config Plugin configuration array with the following keys:
	 *                      - slug (string, required): Plugin identifier
	 *                      - name (string, required): Display name
	 *                      - version (string, required): Plugin version
	 *                      - library_version (string, required): Library version being used
	 *                      - diagnostics_callback (callable, optional): Function returning diagnostic data
	 *                      - settings_callback (callable, optional): Function for settings UI
	 *                      - icon (string, optional): Dashicon class or image URL
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function register_plugin( $config ) {
		// Validate required fields
		$required = array( 'slug', 'name', 'version', 'library_version' );
		foreach ( $required as $field ) {
			if ( empty( $config[ $field ] ) ) {
				self::log_error(
					sprintf(
						'Plugin registration failed: Missing required field "%s"',
						$field
					)
				);
				return false;
			}
		}

		$slug = sanitize_key( $config['slug'] );

		// Check if already registered
		if ( isset( self::$plugins[ $slug ] ) ) {
			self::log_debug(
				sprintf(
					'Plugin "%s" is already registered. Skipping duplicate registration.',
					$slug
				)
			);
			return false;
		}

		// Store sanitized configuration
		self::$plugins[ $slug ] = array(
			'slug'                 => $slug,
			'name'                 => sanitize_text_field( $config['name'] ),
			'version'              => sanitize_text_field( $config['version'] ),
			'library_version'      => sanitize_text_field( $config['library_version'] ),
			'diagnostics_callback' => isset( $config['diagnostics_callback'] ) && is_callable( $config['diagnostics_callback'] )
				? $config['diagnostics_callback']
				: null,
			'settings_callback'    => isset( $config['settings_callback'] ) && is_callable( $config['settings_callback'] )
				? $config['settings_callback']
				: null,
			'icon'                 => isset( $config['icon'] ) ? sanitize_text_field( $config['icon'] ) : 'dashicons-admin-plugins',
			'registered_at'        => current_time( 'mysql' ),
		);

		self::log_debug(
			sprintf(
				'Plugin registered: %s (v%s) using library v%s',
				$config['name'],
				$config['version'],
				$config['library_version']
			)
		);

		/**
		 * Fires after a plugin is registered with the service locator
		 *
		 * @param string $slug   The plugin slug.
		 * @param array  $config The plugin configuration.
		 */
		do_action( 'pluginpulse_library_plugin_registered', $slug, self::$plugins[ $slug ] );

		return true;
	}

	/**
	 * Get all registered plugins
	 *
	 * @return array Array of plugin configurations indexed by slug.
	 */
	public static function get_all_plugins() {
		return self::$plugins;
	}

	/**
	 * Get a specific plugin's configuration
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return array|null Plugin configuration array, or null if not found.
	 */
	public static function get_plugin( $slug ) {
		$slug = sanitize_key( $slug );
		return isset( self::$plugins[ $slug ] ) ? self::$plugins[ $slug ] : null;
	}

	/**
	 * Check if a plugin is registered
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return bool True if registered, false otherwise.
	 */
	public static function is_plugin_registered( $slug ) {
		$slug = sanitize_key( $slug );
		return isset( self::$plugins[ $slug ] );
	}

	/**
	 * Get the number of registered plugins
	 *
	 * @return int Count of registered plugins.
	 */
	public static function get_plugin_count() {
		return count( self::$plugins );
	}

	/**
	 * Check if the unified admin menu has been registered
	 *
	 * @return bool True if menu is registered, false otherwise.
	 */
	public static function is_menu_registered() {
		return self::$menu_registered;
	}

	/**
	 * Mark the unified admin menu as registered
	 *
	 * The Admin UI component calls this to prevent duplicate menu registration.
	 *
	 * @return void
	 */
	public static function set_menu_registered() {
		self::$menu_registered = true;
	}

	/**
	 * Collect diagnostic data from all registered plugins
	 *
	 * Calls each plugin's diagnostics callback and aggregates the results.
	 *
	 * @return array Aggregated diagnostic data indexed by plugin slug.
	 */
	public static function get_all_diagnostics() {
		$diagnostics = array();

		foreach ( self::$plugins as $slug => $config ) {
			if ( isset( $config['diagnostics_callback'] ) && is_callable( $config['diagnostics_callback'] ) ) {
				try {
					$plugin_diagnostics = call_user_func( $config['diagnostics_callback'] );

					if ( is_array( $plugin_diagnostics ) ) {
						$diagnostics[ $slug ] = array(
							'plugin_name'  => $config['name'],
							'plugin_version' => $config['version'],
							'library_version' => $config['library_version'],
							'data'         => $plugin_diagnostics,
							'collected_at' => current_time( 'mysql' ),
						);
					}
				} catch ( \Exception $e ) {
					self::log_error(
						sprintf(
							'Error collecting diagnostics from %s: %s',
							$slug,
							$e->getMessage()
						)
					);

					$diagnostics[ $slug ] = array(
						'plugin_name'  => $config['name'],
						'error'        => $e->getMessage(),
						'collected_at' => current_time( 'mysql' ),
					);
				}
			}
		}

		/**
		 * Filters the aggregated diagnostic data
		 *
		 * @param array $diagnostics Diagnostic data from all plugins.
		 */
		return apply_filters( 'pluginpulse_library_diagnostics', $diagnostics );
	}

	/**
	 * Get diagnostic data for a specific plugin
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return array|null Diagnostic data array, or null if plugin not found or no callback set.
	 */
	public static function get_plugin_diagnostics( $slug ) {
		$slug = sanitize_key( $slug );
		$config = self::get_plugin( $slug );

		if ( ! $config || ! isset( $config['diagnostics_callback'] ) || ! is_callable( $config['diagnostics_callback'] ) ) {
			return null;
		}

		try {
			$diagnostics = call_user_func( $config['diagnostics_callback'] );
			return is_array( $diagnostics ) ? $diagnostics : null;
		} catch ( \Exception $e ) {
			self::log_error(
				sprintf(
					'Error collecting diagnostics from %s: %s',
					$slug,
					$e->getMessage()
				)
			);
			return null;
		}
	}

	/**
	 * Unregister a plugin (primarily for testing)
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return bool True if unregistered, false if not found.
	 */
	public static function unregister_plugin( $slug ) {
		$slug = sanitize_key( $slug );

		if ( isset( self::$plugins[ $slug ] ) ) {
			unset( self::$plugins[ $slug ] );
			self::log_debug( sprintf( 'Plugin unregistered: %s', $slug ) );
			return true;
		}

		return false;
	}

	/**
	 * Reset the service locator (primarily for testing)
	 *
	 * @internal
	 */
	public static function reset() {
		self::$plugins          = array();
		self::$menu_registered  = false;
	}

	/**
	 * Log debug messages when WP_DEBUG is enabled
	 *
	 * @param string $message The message to log.
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'PluginPulse ServiceLocator: ' . $message );
		}
	}

	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log.
	 */
	private static function log_error( $message ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'PluginPulse ServiceLocator ERROR: ' . $message );
	}
}