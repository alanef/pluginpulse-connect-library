<?php
/**
 * Version Manager for PluginPulse Connect Library
 *
 * Handles version detection and ensures only the newest version loads
 * when multiple plugins include this library.
 *
 * @package PluginPulse\Library\Core
 * @since 1.0.0
 */

namespace PluginPulse\Library\Core;

/**
 * Class VersionManager
 *
 * Singleton class that manages library version detection and loading.
 * Ensures only the most recent version of the library is active.
 */
class VersionManager {

	/**
	 * The currently active library version
	 *
	 * @var string|null
	 */
	private static $active_version = null;

	/**
	 * The file path of the active library version
	 *
	 * @var string|null
	 */
	private static $active_path = null;

	/**
	 * Registry of all plugins that have attempted to load the library
	 *
	 * @var array Format: ['plugin-slug' => ['version' => '1.0.0', 'path' => '/path/to/library']]
	 */
	private static $registered_plugins = array();

	/**
	 * Register a plugin's library version and determine if it should load
	 *
	 * This method implements the "newest version wins" strategy. Each plugin
	 * calls this during its initialization. Only the plugin with the newest
	 * library version will receive a true response.
	 *
	 * @param string $plugin_slug    The plugin's slug/identifier.
	 * @param string $library_version The version of the library this plugin is using.
	 * @param string $library_path    The file path to this library instance.
	 *
	 * @return bool True if this version should load, false otherwise.
	 */
	public static function register( $plugin_slug, $library_version, $library_path ) {
		// Normalize the path for consistent comparison
		$library_path = wp_normalize_path( $library_path );

		// Store this plugin's registration
		self::$registered_plugins[ $plugin_slug ] = array(
			'version' => $library_version,
			'path'    => $library_path,
		);

		// If no version is active yet, this one becomes active
		if ( self::$active_version === null ) {
			self::$active_version = $library_version;
			self::$active_path    = $library_path;

			self::log_debug(
				sprintf(
					'PluginPulse Library: First registration by %s (v%s)',
					$plugin_slug,
					$library_version
				)
			);

			return true;
		}

		// Compare versions - if this one is newer, it becomes active
		if ( version_compare( $library_version, self::$active_version, '>' ) ) {
			self::log_debug(
				sprintf(
					'PluginPulse Library: Version upgrade detected. Switching from v%s (%s) to v%s (%s)',
					self::$active_version,
					self::get_plugin_by_path( self::$active_path ),
					$library_version,
					$plugin_slug
				)
			);

			self::$active_version = $library_version;
			self::$active_path    = $library_path;


			return true;
		}

		// This version is older or equal - don't load
		self::log_debug(
			sprintf(
				'PluginPulse Library: Plugin %s (v%s) registered but not loaded. Active version: v%s',
				$plugin_slug,
				$library_version,
				self::$active_version
			)
		);

		return false;
	}

	/**
	 * Get the currently active library version
	 *
	 * @return string|null The active version number, or null if none set.
	 */
	public static function get_active_version() {
		return self::$active_version;
	}

	/**
	 * Get the file path of the active library version
	 *
	 * @return string|null The active library path, or null if none set.
	 */
	public static function get_active_path() {
		return self::$active_path;
	}

	/**
	 * Get all registered plugins
	 *
	 * @return array Array of registered plugins with their versions and paths.
	 */
	public static function get_registered_plugins() {
		return self::$registered_plugins;
	}

	/**
	 * Check if multiple versions are registered
	 *
	 * @return bool True if more than one plugin has registered.
	 */
	public static function has_multiple_versions() {
		return count( self::$registered_plugins ) > 1;
	}

	/**
	 * Find plugin slug by library path
	 *
	 * @param string $path The library path to search for.
	 *
	 * @return string|null The plugin slug, or null if not found.
	 */
	private static function get_plugin_by_path( $path ) {
		foreach ( self::$registered_plugins as $slug => $data ) {
			if ( $data['path'] === $path ) {
				return $slug;
			}
		}
		return null;
	}

	/**
	 * Log debug messages when WP_DEBUG is enabled
	 *
	 * @param string $message The message to log.
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}
	}

	/**
	 * Reset the version manager (primarily for testing)
	 *
	 * @internal
	 */
	public static function reset() {
		self::$active_version      = null;
		self::$active_path         = null;
		self::$registered_plugins  = array();
	}
}