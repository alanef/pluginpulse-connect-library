<?php
/**
 * Manual autoloader for PluginPulse Connect Library
 *
 * This file provides PSR-4 autoloading for non-Composer environments.
 * WordPress plugins that cannot use Composer can include this file directly.
 *
 * Usage in host plugin:
 *     require_once __DIR__ . '/path/to/pluginpulse-connect-library/src/autoload.php';
 *
 * @package PluginPulse\Library
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only register autoloader if LibraryBootstrap is not already loaded
if ( ! class_exists( 'PluginPulse\\Library\\Core\\LibraryBootstrap' ) ) {
	spl_autoload_register(
		function ( $class ) {
			// PSR-4 namespace prefix
			$prefix = 'PluginPulse\\Library\\';

			// Base directory for the namespace prefix
			$base_dir = __DIR__ . '/';

			// Does the class use the namespace prefix?
			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				// No, move to the next registered autoloader
				return;
			}

			// Get the relative class name
			$relative_class = substr( $class, $len );

			// Replace namespace separators with directory separators in the relative class name,
			// append with .php
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			// If the file exists, require it
			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	);
}