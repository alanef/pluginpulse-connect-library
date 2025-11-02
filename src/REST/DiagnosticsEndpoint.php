<?php

namespace PluginPulse\Library\REST;

use PluginPulse\Library\Data\DiagnosticData;
use PluginPulse\Library\Core\ServiceLocator;

/**
 * Diagnostics REST Endpoint
 *
 * Provides REST API endpoints for diagnostic data access.
 * Context-aware to support multiple plugins using the library.
 *
 * @package PluginPulse\Library\REST
 * @since 1.0.0
 */
class DiagnosticsEndpoint {

    /**
     * Plugin context slug
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * WordPress option name for settings storage
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
     * Discovered plugins array
     *
     * @var array
     */
    private $discovered_plugins;

    /**
     * Known debug constants array
     *
     * @var array
     */
    private $known_debug_constants;

    /**
     * Constructor
     *
     * @param string $plugin_slug          Plugin identifier.
     * @param string $option_name          WordPress option name for settings.
     * @param array  $settings             Plugin settings array.
     * @param array  $discovered_plugins   Discovered plugins with support-config.json.
     * @param array  $known_debug_constants Known debug constants.
     */
    public function __construct( $plugin_slug, $option_name, $settings, $discovered_plugins, $known_debug_constants ) {
        $this->plugin_slug           = sanitize_key( $plugin_slug );
        $this->option_name           = $option_name;
        $this->settings              = $settings;
        $this->discovered_plugins    = $discovered_plugins;
        $this->known_debug_constants = $known_debug_constants;
    }

    /**
     * Register REST API route
     *
     * Uses plugin-specific namespace to avoid conflicts when multiple plugins
     * use the library. Format: pluginpulse/{plugin-slug}/v1/diagnostics
     *
     * Epic integration: Enables PAG-72 (Admin UI) to call REST endpoint
     */
    public function register_rest_route() {
        if ( empty( $this->settings['enable_rest_endpoint'] ) ) {
            return;
        }

        // Use plugin-specific namespace to avoid conflicts (epic multi-plugin architecture)
        $namespace = 'pluginpulse/' . $this->plugin_slug . '/v1';

        register_rest_route(
            $namespace,
            '/diagnostics',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_api_callback' ),
                'permission_callback' => array( $this, 'rest_api_permission_check' ),
                'args'                => array(
                    'mode' => array(
                        'description' => 'Response mode: "single" for this plugin only, "all" for aggregated data from all plugins',
                        'type'        => 'string',
                        'enum'        => array( 'single', 'all' ),
                        'default'     => 'single',
                    ),
                ),
            )
        );
    }

    /**
     * REST API permission check
     *
     * Verifies access via either transient key (temporary access) or access keys.
     *
     * @param \WP_REST_Request $request REST request object.
     * @return bool True if access is allowed.
     */
    public function rest_api_permission_check( $request ) {
        // Get request parameters
        $access_key    = $request->get_param( 'access_key' );
        $endpoint_key  = $request->get_param( 'endpoint_key' );
        $transient_key = sanitize_key( $request->get_param( 'transient_key' ) ?? '' );

        // Check if there's a valid transient with the diagnostic data (plugin-specific)
        if ( ! empty( $transient_key ) ) {
            $transient_name = 'pp_' . $this->plugin_slug . '_' . $transient_key;
            $has_transient  = false !== get_transient( $transient_name );
            if ( $has_transient ) {
                return true;
            }
        }

        // Check if both keys match
        if ( ! empty( $access_key ) && ! empty( $endpoint_key ) &&
             $access_key === $this->settings['access_key'] &&
             $endpoint_key === $this->settings['rest_endpoint_key'] ) {
            return true;
        }

        return false;
    }

    /**
     * REST API callback
     *
     * Supports two modes:
     * - "single": Returns diagnostics for this plugin only
     * - "all": Returns aggregated diagnostics from all plugins using library (via ServiceLocator)
     *
     * Epic integration: Uses ServiceLocator (PAG-69) for aggregated diagnostics
     *
     * @param \WP_REST_Request $request REST request object.
     * @return \WP_REST_Response
     */
    public function rest_api_callback( $request ) {
        // Check if using transient key (plugin-specific transient name)
        $transient_key = sanitize_key( $request->get_param( 'transient_key' ) ?? '' );
        if ( ! empty( $transient_key ) ) {
            // Use plugin-specific transient name to avoid conflicts
            $transient_name = 'pp_' . $this->plugin_slug . '_' . $transient_key;
            $data           = get_transient( $transient_name );

            if ( false !== $data ) {
                return rest_ensure_response( $data );
            }
        }

        // Check mode parameter
        $mode = $request->get_param( 'mode' );

        if ( 'all' === $mode ) {
            // Return aggregated diagnostics from all plugins using library
            // Epic integration: Uses ServiceLocator from PAG-69
            $diagnostic_data = ServiceLocator::get_all_diagnostics();
        } else {
            // Return diagnostics for this plugin only
            $diagnostic_data = $this->generate_diagnostic_data();
        }

        return rest_ensure_response( $diagnostic_data );
    }

    /**
     * AJAX: Generate diagnostic data
     *
     * Epic integration: Called by PAG-72 (Admin UI) to generate diagnostic data
     *
     * NOTE: Nonce should be passed by the calling plugin, validated here
     */
    public function ajax_generate_diagnostic_data() {
        // Nonce check - uses plugin-specific nonce action
        check_ajax_referer( 'pp_' . $this->plugin_slug . '_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $diagnostic_data = $this->generate_diagnostic_data();

        // Create a plugin-specific transient for direct access
        $transient_key  = $this->generate_random_key( 16 );
        $transient_name = 'pp_' . $this->plugin_slug . '_' . $transient_key;
        set_transient( $transient_name, $diagnostic_data, DAY_IN_SECONDS );

        // Build plugin-specific REST URL
        $rest_url = rest_url( 'pluginpulse/' . $this->plugin_slug . '/v1/diagnostics' );
        $direct_access_url = add_query_arg(
            array( 'transient_key' => $transient_key ),
            $rest_url
        );

        wp_send_json_success(
            array(
                'data'              => $diagnostic_data,
                'direct_access_url' => $direct_access_url,
            )
        );
    }

    /**
     * AJAX: Regenerate keys
     *
     * Epic integration: Called by PAG-72 (Admin UI) to regenerate access keys
     */
    public function ajax_regenerate_keys() {
        // Nonce check - uses plugin-specific nonce action
        check_ajax_referer( 'pp_' . $this->plugin_slug . '_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        // Generate new keys (library doesn't depend on Main class)
        $this->settings['access_key']        = $this->generate_random_key( 12 );
        $this->settings['rest_endpoint_key'] = $this->generate_random_key( 32 );

        // Save updated settings using context option_name
        update_option( $this->option_name, $this->settings );

        wp_send_json_success(
            array(
                'message'          => 'Keys regenerated successfully.',
                'access_key'       => $this->settings['access_key'],
                'rest_endpoint_key' => $this->settings['rest_endpoint_key'],
            )
        );
    }

    /**
     * Generate diagnostic data
     */
    private function generate_diagnostic_data() {
        $diagnosticData = new DiagnosticData(
            $this->settings,
            $this->discovered_plugins,
            $this->known_debug_constants
        );

        return $diagnosticData->generate_diagnostic_data();
    }

    /**
     * Generate a random key
     */
    private function generate_random_key($length = 12) {
        return substr(str_shuffle(MD5(microtime())), 0, $length);
    }
}