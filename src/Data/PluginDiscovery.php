<?php

namespace PluginPulse\Library\Data;

/**
 * Plugin Discovery
 *
 * Discovers WordPress plugins that have support-config.json files and can
 * integrate with the PluginPulse Connect library.
 *
 * @package PluginPulse\Library\Data
 * @since 1.0.0
 */
class PluginDiscovery {

    /**
     * Plugin context (slug of the plugin using this library)
     *
     * @var string
     */
    private $context_plugin_slug;

    /**
     * Option name where plugin settings are stored
     *
     * @var string
     */
    private $option_name;

    /**
     * Plugin settings array
     *
     * @var array
     */
    private $settings;

    /**
     * Known debug constants
     *
     * @var array
     */
    private $known_debug_constants;

    /**
     * Discovered plugins with support-config.json
     *
     * @var array
     */
    private $discovered_plugins = [];

    /**
     * Constructor
     *
     * @param string $context_plugin_slug  Slug of the plugin using this library.
     * @param string $option_name          WordPress option name for storing settings.
     * @param array  $settings             Plugin settings array.
     * @param array  $known_debug_constants Known WordPress debug constants.
     */
    public function __construct( $context_plugin_slug, $option_name, $settings, $known_debug_constants ) {
        $this->context_plugin_slug  = $context_plugin_slug;
        $this->option_name          = $option_name;
        $this->settings             = $settings;
        $this->known_debug_constants = $known_debug_constants;
    }

    /**
     * Discover compatible plugins
     */
    public function discover_compatible_plugins() {
        $this->discovered_plugins = [];

        // Get all active plugins
        $active_plugins = get_option('active_plugins', []);

        foreach ($active_plugins as $plugin_path) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_path);

            // Check for support-config.json file
            $config_file = $plugin_dir . '/support-config.json';

            if (file_exists($config_file)) {
                $config_json = file_get_contents($config_file);
                $config = json_decode($config_json, true);

                // Validate the configuration
                if (is_array($config) && isset($config['plugin_info']['name'])) {
                    $this->discovered_plugins[$plugin_path] = $config;

                    // Add shortcodes to be scanned if defined in plugin config
                    if (isset($config['shortcodes']) && is_array($config['shortcodes'])) {
                        foreach ($config['shortcodes'] as $shortcode) {
                            if (!in_array($shortcode, $this->settings['scan_shortcodes'])) {
                                $this->settings['scan_shortcodes'][] = $shortcode;
                            }
                        }
                    }

                    // Add debug constants from plugin config
                    if (isset($config['debug_constants']) && is_array($config['debug_constants'])) {
                        foreach ($config['debug_constants'] as $constant => $description) {
                            if (!isset($this->known_debug_constants[$constant])) {
                                $this->known_debug_constants[$constant] = $description;
                            }
                        }
                    }
                    
                    // Add Freemius configuration if specified
                    if (isset($config['freemius']) && !empty($config['freemius']['global_variable'])) {
                        if (!isset($this->settings['freemius_modules'])) {
                            $this->settings['freemius_modules'] = [];
                        }
                        
                        $plugin_slug = dirname($plugin_path);
                        $this->settings['freemius_modules'][$plugin_slug] = [
                            'global_variable' => $config['freemius']['global_variable'],
                            'plugin_path' => $plugin_path,
                            'plugin_name' => $config['plugin_info']['name']
                        ];
                    }

                    // Save the updated settings to include discovered shortcodes and Freemius config
                    // Uses the option_name provided by the calling plugin (context-aware)
                    update_option( $this->option_name, $this->settings );
                }
            }
        }

        return $this->discovered_plugins;
    }

    /**
     * Get updated settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get known debug constants
     */
    public function get_known_debug_constants() {
        return $this->known_debug_constants;
    }
}