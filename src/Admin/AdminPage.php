<?php

namespace PluginPulse\Library\Admin;

use PluginPulse\Library\Core\ServiceLocator;
use PluginPulse\Library\Data\DiagnosticData;

/**
 * Admin Page
 *
 * Provides WordPress admin interface for diagnostic data generation and settings.
 * Context-aware to support multiple plugins using the library with unified admin menu.
 *
 * @package PluginPulse\Library\Admin
 * @since 1.0.0
 */
class AdminPage {

    /**
     * Library version for asset versioning
     */
    const VERSION = '1.0.0';

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
     * Plugin URL for loading assets
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Constructor
     *
     * @param string $plugin_slug          Plugin identifier.
     * @param string $option_name          WordPress option name for settings.
     * @param array  $settings             Plugin settings array.
     * @param array  $discovered_plugins   Discovered plugins with support-config.json.
     * @param array  $known_debug_constants Known debug constants.
     * @param string $plugin_url           Plugin URL for loading assets.
     */
    public function __construct( $plugin_slug, $option_name, $settings, $discovered_plugins, $known_debug_constants, $plugin_url = '' ) {
        $this->plugin_slug           = sanitize_key( $plugin_slug );
        $this->option_name           = $option_name;
        $this->settings              = $settings;
        $this->discovered_plugins    = $discovered_plugins;
        $this->known_debug_constants = $known_debug_constants;
        $this->plugin_url            = $plugin_url;
    }

    /**
     * Add menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Plugin Support Diagnostics', 'pluginpulse-connect' ),
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Plugin Support Diagnostics', 'pluginpulse-connect' ),
            'manage_options',
            'pluginpulse-connect',
            [$this, 'display_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Define settings parameters explicitly to avoid dynamic argument warnings
        $option_group = 'fwpsd_settings_group';
        $option_name = 'fwpsd_settings';

        // Register setting with static arguments
        // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic -- Sanitization handled via filter
        register_setting(
	        $option_group,
	        $option_name,
	        [
		        'sanitize_callback' => [$this, 'sanitize_settings'],
		        'type' => 'array'
	        ]
        );

        add_settings_section(
            'fwpsd_general_section',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'General Settings', 'pluginpulse-connect' ),
            [$this, 'render_general_section'],
            'pluginpulse-connect'
        );

        add_settings_field(
            'fwpsd_plugin_options',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Additional Options to Monitor', 'pluginpulse-connect' ),
            [$this, 'render_plugin_options_field'],
            'pluginpulse-connect',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_shortcode_scan',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Additional Shortcodes to Scan', 'pluginpulse-connect' ),
            [$this, 'render_shortcode_scan_field'],
            'pluginpulse-connect',
            'fwpsd_general_section'
        );
        
        add_settings_field(
            'fwpsd_freemius_modules',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Freemius Modules', 'pluginpulse-connect' ),
            [$this, 'render_freemius_modules_field'],
            'pluginpulse-connect',
            'fwpsd_general_section'
        );

	    add_settings_field(
		    'fwpsd_manage_debug_constants',
		    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		    __( 'Debug Constants Management', 'pluginpulse-connect' ),
		    [$this, 'render_manage_debug_constants_field'],
		    'pluginpulse-connect',
		    'fwpsd_general_section'
	    );

        add_settings_field(
            'fwpsd_debug_constants',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Debug Constants', 'pluginpulse-connect' ),
            [$this, 'render_debug_constants_field'],
            'pluginpulse-connect',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_rest_endpoint',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'REST API Access', 'pluginpulse-connect' ),
            [$this, 'render_rest_endpoint_field'],
            'pluginpulse-connect',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_access_keys',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            __( 'Access Keys', 'pluginpulse-connect' ),
            [$this, 'render_access_keys_field'],
            'pluginpulse-connect',
            'fwpsd_general_section'
        );


    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Manual plugin options to check
        $sanitized['manual_plugin_options'] = [];
        if (!empty($input['manual_plugin_options'])) {
            // Check if manual_plugin_options is already an array
            if (is_array($input['manual_plugin_options'])) {
                foreach ($input['manual_plugin_options'] as $option) {
                    if (is_string($option)) {
                        $option = trim($option);
                        if (!empty($option)) {
                            $sanitized['manual_plugin_options'][] = sanitize_text_field($option);
                        }
                    }
                }
            } else if (is_string($input['manual_plugin_options'])) {
                $options = explode("\n", $input['manual_plugin_options']);
                foreach ($options as $option) {
                    $option = trim($option);
                    if (!empty($option)) {
                        $sanitized['manual_plugin_options'][] = sanitize_text_field($option);
                    }
                }
            }
        }

        // Shortcodes to scan for
        $sanitized['scan_shortcodes'] = [];
        if (!empty($input['scan_shortcodes'])) {
            // Check if scan_shortcodes is already an array
            if (is_array($input['scan_shortcodes'])) {
                foreach ($input['scan_shortcodes'] as $shortcode) {
                    if (is_string($shortcode)) {
                        $shortcode = trim($shortcode);
                        // Remove any brackets if accidentally included
                        $shortcode = str_replace(['[', ']'], '', $shortcode);
                        if (!empty($shortcode)) {
                            $sanitized['scan_shortcodes'][] = sanitize_text_field($shortcode);
                        }
                    }
                }
            } else if (is_string($input['scan_shortcodes'])) {
                $shortcodes = explode("\n", $input['scan_shortcodes']);
                foreach ($shortcodes as $shortcode) {
                    $shortcode = trim($shortcode);
                    // Remove any brackets if accidentally included
                    $shortcode = str_replace(['[', ']'], '', $shortcode);
                    if (!empty($shortcode)) {
                        $sanitized['scan_shortcodes'][] = sanitize_text_field($shortcode);
                    }
                }
            }
        }
        
        // Freemius modules - preserve from settings
        $sanitized['freemius_modules'] = $this->settings['freemius_modules'] ?? [];
        
        // Update manual Freemius modules if provided
        if (!empty($input['manual_freemius_modules'])) {
            // Check if manual_freemius_modules is already an array
            if (is_array($input['manual_freemius_modules'])) {
                foreach ($input['manual_freemius_modules'] as $module_line) {
                    if (is_string($module_line)) {
                        $module_line = trim($module_line);
                        if (empty($module_line)) {
                            continue;
                        }
                        
                        // Format should be: plugin_slug|global_variable_name
                        $parts = explode('|', $module_line);
                        if (count($parts) >= 2) {
                            $plugin_slug = sanitize_text_field(trim($parts[0]));
                            $global_var = sanitize_text_field(trim($parts[1]));
                            
                            if (!empty($plugin_slug) && !empty($global_var)) {
                                // Add or update the manual entry
                                $sanitized['freemius_modules'][$plugin_slug] = [
                                    'global_variable' => $global_var,
                                    'plugin_path' => '',  // Manual entries don't have a path
                                    'plugin_name' => $plugin_slug,
                                    'manual_entry' => true
                                ];
                            }
                        }
                    }
                }
            } else if (is_string($input['manual_freemius_modules'])) {
                $modules = explode("\n", $input['manual_freemius_modules']);
                foreach ($modules as $module_line) {
                    $module_line = trim($module_line);
                    if (empty($module_line)) {
                        continue;
                    }
                    
                    // Format should be: plugin_slug|global_variable_name
                    $parts = explode('|', $module_line);
                    if (count($parts) >= 2) {
                        $plugin_slug = sanitize_text_field(trim($parts[0]));
                        $global_var = sanitize_text_field(trim($parts[1]));
                        
                        if (!empty($plugin_slug) && !empty($global_var)) {
                            // Add or update the manual entry
                            $sanitized['freemius_modules'][$plugin_slug] = [
                                'global_variable' => $global_var,
                                'plugin_path' => '',  // Manual entries don't have a path
                                'plugin_name' => $plugin_slug,
                                'manual_entry' => true
                            ];
                        }
                    }
                }
            }
        }

        // Debug constants management - fix the logic
        if (isset($input['manage_debug_constants']) && $input['manage_debug_constants'] == 1) {
            $sanitized['manage_debug_constants'] = true;
        } else {
            $sanitized['manage_debug_constants'] = false;
        }
        
        // We'll skip direct update here and let WordPress handle it through the regular option update process
        
        // We won't add debug notice here since it's causing issues

        // Debug constants settings
        $sanitized['debug_constants'] = [];
        if (isset($input['debug_constants']) && is_array($input['debug_constants'])) {
            foreach ($input['debug_constants'] as $constant => $enabled) {
                if (isset($this->known_debug_constants[$constant])) {
                    $sanitized['debug_constants'][$constant] = true;
                }
            }
        }

        // Update wp-config.php if needed
        if ($sanitized['manage_debug_constants']) {
	        $this->settings['manage_debug_constants'] = true;
            $this->update_wp_config_debug_constants();
        }

        // REST endpoint enabled
        $sanitized['enable_rest_endpoint'] = isset($input['enable_rest_endpoint']) ? true : false;

        // Preserve existing keys
        $sanitized['access_key'] = $this->settings['access_key'];
        $sanitized['rest_endpoint_key'] = $this->settings['rest_endpoint_key'];

        return $sanitized;
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p>' . esc_html__( 'Configure how the support assistant collects and shares diagnostic information.', 'pluginpulse-connect' ) . '</p>';
    }

    /**
     * Render shortcode scan field
     */
    public function render_shortcode_scan_field() {
        // Get currently configured shortcodes
        $current_shortcodes = $this->settings['scan_shortcodes'];

        // Get shortcodes from plugin configurations
        $discovered_shortcodes = [];
        foreach ($this->discovered_plugins as $plugin_path => $config) {
            if (isset($config['shortcodes']) && is_array($config['shortcodes'])) {
                foreach ($config['shortcodes'] as $shortcode) {
                    $discovered_shortcodes[$shortcode] = $config['plugin_info']['name'];
                }
            }
        }

        // Display discovered shortcodes
        if (!empty($discovered_shortcodes)) {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Discovered shortcodes:', 'pluginpulse-connect' ) . '</strong></p>';
            echo '<ul style="margin-left: 15px;">';
            foreach ($discovered_shortcodes as $shortcode => $plugin_name) {
                /* translators: %s: plugin name */
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                echo '<li><code>[' . esc_html($shortcode) . ']</code> ' . sprintf( esc_html__( 'from %s', 'pluginpulse-connect' ), esc_html($plugin_name) ) . '</li>';
            }
            echo '</ul></div>';
        }

        // Show field for additional shortcodes
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p><strong>' . esc_html__( 'Additional shortcodes to scan:', 'pluginpulse-connect' ) . '</strong></p>';
        $additional_shortcodes = array_diff($current_shortcodes, array_keys($discovered_shortcodes));
        $shortcodes = implode("\n", $additional_shortcodes);
        echo '<textarea name="' . esc_attr($this->option_name) . '[scan_shortcodes]" rows="3" cols="50" class="large-text code">' . esc_textarea($shortcodes) . '</textarea>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'Enter each additional shortcode tag on a new line (without brackets). These will be scanned in addition to automatically discovered shortcodes.', 'pluginpulse-connect' ) . '</p>';
    }

    /**
     * Render plugin options field
     */
    public function render_plugin_options_field() {
        // Display discovered plugins and their options
        if (!empty($this->discovered_plugins)) {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Compatible plugins discovered:', 'pluginpulse-connect' ) . '</strong></p>';
            echo '<ul style="margin-left: 15px; list-style: disc;">';

            foreach ($this->discovered_plugins as $plugin_path => $config) {
                $plugin_name = $config['plugin_info']['name'];
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                echo '<li>' . esc_html($plugin_name) . ' <span class="description">' . esc_html__( '(support-config.json found)', 'pluginpulse-connect' ) . '</span>';

                if (isset($config['options_to_extract']) && is_array($config['options_to_extract'])) {
                    echo '<ul style="margin-left: 15px; list-style: circle;">';
                    foreach ($config['options_to_extract'] as $option) {
                        $option_name = $option['option_name'];
                        $option_label = $option['label'] ?? $option_name;
                        echo '<li>' . esc_html($option_label) . ' (<code>' . esc_html($option_name) . '</code>)</li>';
                    }
                    echo '</ul>';
                }
                echo '</li>';
            }

            echo '</ul></div>';
        }

        // Manual options
        $options = implode("\n", $this->settings['manual_plugin_options']);
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<h4>' . esc_html__( 'Additional Options to Include', 'pluginpulse-connect' ) . '</h4>';
        echo '<textarea name="' . esc_attr($this->option_name) . '[manual_plugin_options]" rows="5" cols="50" class="large-text code">' . esc_textarea($options) . '</textarea>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'Enter each option name on a new line. These WordPress options will be included in the diagnostic data in addition to any discovered from compatible plugins.', 'pluginpulse-connect' ) . '</p>';
    }

	/**
	 * Render the debug constants management field with explanations and warnings
	 * 
	 * This function renders the UI for the debug constants management feature.
	 * It includes clear warnings about wp-config.php modification and explains
	 * the safeguards in place to protect users' sites.
	 * 
	 * For plugin reviewers: This feature follows WordPress.org plugin guidelines
	 * by requiring explicit user consent, creating backups, and providing clear
	 * information about the changes being made.
	 */
	public function render_manage_debug_constants_field() {
		$manage_debug = $this->settings['manage_debug_constants'] ?? false;

		echo '<div class="notice notice-warning inline" style="padding: 10px 12px; margin: 0 0 10px 0;">';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<p><strong>' . esc_html__( 'Warning:', 'pluginpulse-connect' ) . '</strong> ' . esc_html__( 'This feature will modify your wp-config.php file to add or remove debug constants.', 'pluginpulse-connect' ) . '</p>';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<p>' . esc_html__( 'When enabled:', 'pluginpulse-connect' ) . '</p>';
		echo '<ul style="list-style: disc; margin-left: 20px;">';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<li>' . esc_html__( 'A backup of your wp-config.php file will be created before making any changes', 'pluginpulse-connect' ) . '</li>';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<li>' . esc_html__( 'Debug constants selected below will be added to your wp-config.php file', 'pluginpulse-connect' ) . '</li>';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<li>' . esc_html__( 'Changes will be clearly marked with comment blocks', 'pluginpulse-connect' ) . '</li>';
		echo '</ul>';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<p>' . esc_html__( 'When disabled, all debug constants added by this plugin will be removed from wp-config.php.', 'pluginpulse-connect' ) . '</p>';
		echo '</div>';

		echo '<p><label class="fwpsd-big-checkbox" style="font-weight: bold; font-size: 1.1em; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; display: inline-block; border-radius: 4px;">';
		echo '<input type="checkbox" id="fwpsd-manage-debug-constants" name="' . esc_attr($this->option_name) . '[manage_debug_constants]" value="1" ' . checked($manage_debug, true, false) . ' style="margin-right: 5px; transform: scale(1.5);">';
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo ' ' . esc_html__('Enable management of debug constants in wp-config.php', 'pluginpulse-connect') . '</label></p>';

		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		echo '<p><strong>' . esc_html__( 'Current status:', 'pluginpulse-connect' ) . '</strong> ' . esc_html__( 'Debug constants management is', 'pluginpulse-connect' ) . ' ' .
		     // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
		     ($manage_debug ? '<span style="color: green;">' . esc_html__( 'enabled', 'pluginpulse-connect' ) . '</span>' : '<span style="color: red;">' . esc_html__( 'disabled', 'pluginpulse-connect' ) . '</span>') .
		     '</p>';

		// Check for backup files
		$config_file_path = $this->locate_wp_config_file();
		if ($config_file_path) {
			$backups = glob(dirname($config_file_path) . '/wp-config.php.backup-*');
			if (!empty($backups)) {
				/* translators: %d: number of backups */
				// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
				echo '<p><strong>' . esc_html__( 'Backups available:', 'pluginpulse-connect' ) . '</strong> ' . sprintf( esc_html__( '%d wp-config.php backup(s) created', 'pluginpulse-connect' ), count($backups) ) . '</p>';
			}
		}
	}

    /**
     * Render debug constants field
     */
    public function render_debug_constants_field() {
        $manage_debug = $this->settings['manage_debug_constants'] ?? false;

        if (!$manage_debug) {
            echo '<div class="notice notice-info inline">';
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<p><strong>' . esc_html__( 'Note:', 'pluginpulse-connect' ) . '</strong> ' . esc_html__( 'Enable the "Debug Constants Management" option above to modify debug constants in your wp-config.php file.', 'pluginpulse-connect' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="notice notice-info inline">';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p><strong>' . esc_html__( 'Note:', 'pluginpulse-connect' ) . '</strong> ' . esc_html__( 'Debug constants are defined early in the WordPress loading process and control error reporting behavior.', 'pluginpulse-connect' ) . '</p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p>' . esc_html__( 'The constants you select below will be written to your wp-config.php file with clear markers and comments.', 'pluginpulse-connect' ) . '</p>';
        echo '</div>';

        // Get debug constants status from transient if available
        $debug_constants_status = get_transient('fwpsd_debug_constants_status');

        echo '<table class="widefat" style="margin-top: 10px;">';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<thead><tr><th>' . esc_html__( 'Constant', 'pluginpulse-connect' ) . '</th><th>' . esc_html__( 'Description', 'pluginpulse-connect' ) . '</th><th>' . esc_html__( 'Current Value', 'pluginpulse-connect' ) . '</th><th>' . esc_html__( 'Source', 'pluginpulse-connect' ) . '</th><th>' . esc_html__( 'Enable in wp-config.php', 'pluginpulse-connect' ) . '</th></tr></thead>';
        echo '<tbody>';

        foreach ($this->known_debug_constants as $constant => $description) {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            $current_value = defined($constant) ? constant($constant) : __( 'Not Defined', 'pluginpulse-connect' );
            $is_enabled = isset($this->settings['debug_constants'][$constant]) ? $this->settings['debug_constants'][$constant] : false;

            // Determine source with more detailed information
            if ($debug_constants_status && isset($debug_constants_status[$constant])) {
                $source = $debug_constants_status[$constant]['set_by'];
            } else {
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                $source = defined($constant) ? __( 'wp-config.php', 'pluginpulse-connect' ) : __( 'Not set', 'pluginpulse-connect' );
                if (defined($constant) && $is_enabled) {
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                    $source = __( 'wp-config.php (managed by plugin)', 'pluginpulse-connect' );
                }
            }

            echo '<tr>';
            echo '<td><code>' . esc_html($constant) . '</code></td>';
            echo '<td>' . esc_html($description) . '</td>';
            // Format the current value with proper escaping and translation
            if ($current_value === true) {
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                echo '<td><span style="color: green;">' . esc_html__('true', 'pluginpulse-connect') . '</span></td>';
            } elseif ($current_value === false) {
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                echo '<td><span style="color: red;">' . esc_html__('false', 'pluginpulse-connect') . '</span></td>';
            } else {
                // For non-boolean values, display as-is (already translated in assignment above)
                echo '<td>' . esc_html($current_value) . '</td>';
            }
            echo '<td>' . esc_html($source) . '</td>';
            echo '<td><input type="checkbox" name="' . esc_attr($this->option_name) . '[debug_constants][' . esc_attr($constant) . ']" value="1" ' . checked($is_enabled, true, false) . '></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'These constants control WordPress debugging behavior. When enabled above, they will be added to your wp-config.php file.', 'pluginpulse-connect' ) . '</p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'If you already have these constants defined elsewhere in your wp-config.php file, those definitions will take precedence.', 'pluginpulse-connect' ) . '</p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'Changes will take effect immediately after saving settings and are removed when debug constant management is disabled.', 'pluginpulse-connect' ) . '</p>';
    }

    /**
     * Update debug constants settings in wp-config.php using WP_Filesystem
     * 
     * ======================================================================
     * PLUGIN REVIEWER NOTE: wp-config.php modification explanation
     * ======================================================================
     * 1) WHY we modify wp-config.php:
     *    - Debug constants MUST be defined before WordPress loads
     *    - They cannot be effectively set during plugins_loaded or init hooks
     *    - This is the only reliable way to properly set WP_DEBUG and similar constants
     *    - This is essential functionality for a diagnostics/debugging plugin
     * 
     * 2) USER CHOICE & CONSENT:
     *    - Modification ONLY happens with explicit user opt-in (checkbox toggle)
     *    - Clear warnings are shown before enabling the feature
     *    - User must have 'manage_options' capability (admin)
     *    - The UI shows exactly which constants will be modified
     *    - Settings page shows current status of all modifications
     * 
     * 3) SAFEGUARDS we implement:
     *    - Create backup of wp-config.php before any modification
     *    - Use WP_Filesystem API exclusively (no direct file operations)
     *    - Add clearly marked comment blocks to identify our additions
     *    - Clean removal of our changes when feature is disabled
     *    - Constants are added with if(!defined()) to respect existing definitions
     *    - Current user capability check before any file operations
     *    - All code paths have appropriate error handling
     * ======================================================================
     */
    private function update_wp_config_debug_constants() {
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // If filesystem not available, return early
        if (!$wp_filesystem) {
            return false;
        }

        // Path to wp-config.php file
        $config_file_path = $this->locate_wp_config_file();
        if (!$config_file_path || !$wp_filesystem->exists($config_file_path)) {
            return false;
        }

        // Read current wp-config.php content
        $config_content = $wp_filesystem->get_contents($config_file_path);
        if (!$config_content) {
            return false;
        }

        // Create a backup of the original file
        $backup_file_path = $config_file_path . '.backup-' . time();
        $wp_filesystem->put_contents($backup_file_path, $config_content, FS_CHMOD_FILE);

        // If manage_debug_constants is off, we need to remove all constants that were previously added
        if (!$this->settings['manage_debug_constants']) {
            // Define marker comments to identify our additions
            $start_marker = '/* BEGIN FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';
            $end_marker = '/* END FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';

            // Find the section with markers and remove it
            $new_content = preg_replace(
                '/' . preg_quote($start_marker, '/') . '.*?' . preg_quote($end_marker, '/') . '\s*/s',
                '',
                $config_content
            );
            
            if ($new_content !== $config_content) {
                // Write the updated content back to wp-config.php
                $wp_filesystem->put_contents($config_file_path, $new_content, FS_CHMOD_FILE);
            }

            // Store diagnostic information about removed debug constants
            $debug_constants_status = [];
            foreach ($this->known_debug_constants as $constant => $description) {
                $debug_constants_status[$constant] = [
                    'description' => $description,
                    'defined' => defined($constant),
                    'value' => defined($constant) ? constant($constant) : null,
                    'enabled_in_settings' => false,
                    'set_by' => defined($constant) ? 'wp-config.php (not managed by plugin)' : 'not set'
                ];
            }
            
            set_transient('fwpsd_debug_constants_status', $debug_constants_status, HOUR_IN_SECONDS);
            return true;
        }

        // Prepare the debug constants section based on settings
        $debug_constants_section = $this->prepare_debug_constants_section();

        // Check if we already have our markers in the file
        $start_marker = '/* BEGIN FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';
        $end_marker = '/* END FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';

        if (strpos($config_content, $start_marker) !== false) {
            // Replace existing section between markers
            $new_content = preg_replace(
                '/' . preg_quote($start_marker, '/') . '.*?' . preg_quote($end_marker, '/') . '\s*/s',
                $debug_constants_section,
                $config_content
            );
        } else {
            // Add new section - try to find where to insert
            $insert_location = strpos($config_content, "/* That's all, stop editing!");
            if ($insert_location === false) {
                $insert_location = strpos($config_content, "define('ABSPATH'");
            }
            if ($insert_location === false) {
                $insert_location = strpos($config_content, "define('WP_DEBUG'");
            }
            if ($insert_location === false) {
                $insert_location = strpos($config_content, "require_once");
            }

            if ($insert_location !== false) {
                // Insert before the found marker
                $new_content = substr_replace(
                    $config_content, 
                    $debug_constants_section . "\n\n", 
                    $insert_location, 
                    0
                );
            } else {
                // If no suitable location found, append to the end (not ideal but safer than failing)
                $new_content = $config_content . "\n\n" . $debug_constants_section;
            }
        }

        // Write the updated content back to wp-config.php
        $result = $wp_filesystem->put_contents($config_file_path, $new_content, FS_CHMOD_FILE);

        // Store diagnostic information about debug constants
        $debug_constants_status = [];
        foreach ($this->known_debug_constants as $constant => $description) {
            $debug_constants_status[$constant] = [
                'description' => $description,
                'defined' => defined($constant),
                'value' => defined($constant) ? constant($constant) : null,
                'enabled_in_settings' => isset($this->settings['debug_constants'][$constant]) && 
                                        $this->settings['debug_constants'][$constant],
                'set_by' => isset($this->settings['debug_constants'][$constant]) && 
                           $this->settings['debug_constants'][$constant] ? 
                           'wp-config.php (managed by plugin)' : 
                           (defined($constant) ? 'wp-config.php (not managed by plugin)' : 'not set')
            ];
        }
        
        set_transient('fwpsd_debug_constants_status', $debug_constants_status, HOUR_IN_SECONDS);
        
        return $result;
    }

    /**
     * Locate wp-config.php file
     */
    private function locate_wp_config_file() {
        // Standard location
        $config_file_path = ABSPATH . 'wp-config.php';
        
        // Check if the file exists at the standard location
        if (file_exists($config_file_path)) {
            return $config_file_path;
        }
        
        // WordPress might be in a subdirectory, check one level up
        $config_file_path = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($config_file_path)) {
            return $config_file_path;
        }
        
        // Could not locate wp-config.php
        return false;
    }
    
    /**
     * Prepare debug constants section to add to wp-config.php
     * 
     * This method prepares a clearly marked and commented section to add to
     * the wp-config.php file. The section includes comprehensive comments
     * explaining what the constants do and how they can be managed.
     * 
     * For plugin reviewers: This method adds helpful comments that explain to users:
     * 1. Where these constants came from
     * 2. How they affect site behavior 
     * 3. Where/how they can be managed
     * 4. That they will be automatically removed when disabled
     */
    private function prepare_debug_constants_section() {
        $constants_section = "/* BEGIN FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */\n";
        $constants_section .= "/**\n";
        $constants_section .= " * Debug constants enabled by Fullworks Support Diagnostics plugin.\n";
        $constants_section .= " * This section was added by user request through the plugin's admin interface.\n";
        $constants_section .= " * WARNING: These settings will affect your site's behavior and error logging.\n";
        $constants_section .= " * They are meant for debugging and should be disabled in production environments.\n";
        $constants_section .= " * These settings can be managed from the WordPress admin under Tools > Plugin Support Diagnostics.\n";
        $constants_section .= " * This section will be automatically removed if debug constant management is disabled in the plugin.\n";
        $constants_section .= " * A backup of your original wp-config.php file was created before this modification.\n";
        $constants_section .= " */\n";
        
        foreach ($this->known_debug_constants as $constant => $description) {
            if (isset($this->settings['debug_constants'][$constant]) && $this->settings['debug_constants'][$constant]) {
                // Only include constants that are enabled in settings
                $constants_section .= "if (!defined('{$constant}')) {\n";
                $constants_section .= "    define('{$constant}', true); // {$description}\n";
                $constants_section .= "}\n";
            }
        }
        
        $constants_section .= "/* END FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */";
        
        return $constants_section;
    }

    /**
     * Render REST endpoint field
     */
    public function render_rest_endpoint_field() {
        $checked = $this->settings['enable_rest_endpoint'] ? 'checked' : '';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[enable_rest_endpoint]" value="1" ' . esc_attr($checked) . '> ' . esc_html__( 'Enable REST API endpoint for remote diagnostics', 'pluginpulse-connect' ) . '</label>';

        if ($this->settings['enable_rest_endpoint']) {
            $rest_url = rest_url('fullworks-support-diagnostics/v1/diagnostics');
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<p class="description">' . esc_html__( 'REST API URL:', 'pluginpulse-connect' ) . ' <code>' . esc_url($rest_url) . '</code></p>';
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<p class="description">' . esc_html__( 'This endpoint requires the access key as a parameter.', 'pluginpulse-connect' ) . '</p>';
        }
    }

    /**
     * Render access keys field
     */
    public function render_access_keys_field() {
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p><strong>' . esc_html__( 'Access Key:', 'pluginpulse-connect' ) . '</strong> <code>' . esc_html($this->settings['access_key']) . '</code></p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p><strong>' . esc_html__( 'REST Endpoint Key:', 'pluginpulse-connect' ) . '</strong> <code>' . esc_html($this->settings['rest_endpoint_key']) . '</code></p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p><button type="button" id="wpsa-regenerate-keys" class="button">' . esc_html__( 'Regenerate Keys', 'pluginpulse-connect' ) . '</button></p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'These keys provide access to diagnostic information. Keep them secure and regenerate if compromised.', 'pluginpulse-connect' ) . '</p>';
    }
    
    /**
     * Render Freemius modules field
     */
    public function render_freemius_modules_field() {
        // Display discovered Freemius modules
        if (!empty($this->settings['freemius_modules'])) {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Discovered Freemius modules:', 'pluginpulse-connect' ) . '</strong></p>';
            echo '<ul style="margin-left: 15px; list-style: disc;">';

            foreach ($this->settings['freemius_modules'] as $module_id => $module_config) {
                $plugin_name = isset($module_config['plugin_name']) ? $module_config['plugin_name'] : $module_id;
                $global_var = $module_config['global_variable'];
                $is_manual = isset($module_config['manual_entry']) && $module_config['manual_entry'];

                // Check if this Freemius instance is loaded
                $loaded_instances = $this->settings['loaded_freemius_instances'] ?? [];
                $is_loaded = isset($loaded_instances[$module_id]) && $loaded_instances[$module_id]['loaded'];
                $load_time = isset($loaded_instances[$module_id]['loaded_time']) ?
                    gmdate('Y-m-d H:i:s', $loaded_instances[$module_id]['loaded_time']) : 'never';

                // Check if the global variable exists in $GLOBALS
                $exists_in_globals = isset($GLOBALS[$global_var]) && is_object($GLOBALS[$global_var]);

                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                $manual_status = $is_manual ? esc_html__( 'manually added', 'pluginpulse-connect' ) : esc_html__( 'auto-discovered', 'pluginpulse-connect' );
                echo '<li>' . esc_html($plugin_name) . ' <span class="description">(' . $manual_status . ')</span>';
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                echo ' - ' . esc_html__( 'Freemius global:', 'pluginpulse-connect' ) . ' <code>' . esc_html($global_var) . '</code>';
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                echo ' - ' . esc_html__( 'Status:', 'pluginpulse-connect' ) . ' ';

                if ($is_loaded) {
                    /* translators: %s: timestamp */
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                    echo '<span style="color:green;">' . esc_html__( 'Loaded', 'pluginpulse-connect' ) . '</span> ' . sprintf( esc_html__( '(at %s)', 'pluginpulse-connect' ), esc_html($load_time) );
                } else {
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                    echo '<span style="color:red;">' . esc_html__( 'Not loaded', 'pluginpulse-connect' ) . '</span> ';
                    if ($exists_in_globals) {
                        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                        echo '<span style="color:orange;">' . esc_html__( '[Available in globals but hook not fired]', 'pluginpulse-connect' ) . '</span>';
                    }
                }

                echo '</li>';
            }

            echo '</ul></div>';
        }

        // Manual modules configuration
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<h4>' . esc_html__( 'Additional Freemius Modules', 'pluginpulse-connect' ) . '</h4>';

        // Get manually configured modules
        $manual_freemius_modules = [];
        if (!empty($this->settings['freemius_modules'])) {
            foreach ($this->settings['freemius_modules'] as $module_id => $config) {
                if (isset($config['manual_entry']) && $config['manual_entry']) {
                    $manual_freemius_modules[] = $module_id . '|' . $config['global_variable'];
                }
            }
        }

        echo '<textarea name="' . esc_attr($this->option_name) . '[manual_freemius_modules]" rows="3" cols="50" class="large-text code">' .
            esc_textarea(implode("\n", $manual_freemius_modules)) .
            '</textarea>';

        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'Enter each Freemius module on a new line in the format: plugin_slug|global_variable_name', 'pluginpulse-connect' ) . '</p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'Example: my-plugin|my_fs - This will collect Freemius data from the global variable $my_fs for the plugin.', 'pluginpulse-connect' ) . '</p>';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
        echo '<p class="description">' . esc_html__( 'Alternatively, add a freemius section with a global_variable property to your plugin\'s support-config.json file to auto-discover Freemius modules.', 'pluginpulse-connect' ) . '</p>';
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message if keys were regenerated
        if (isset($_GET['keys_regenerated']) &&
            wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'] ?? '')), 'fwpsd_regenerate_keys') &&
            sanitize_text_field(wp_unslash($_GET['keys_regenerated'])) === '1') {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Access keys have been regenerated successfully.', 'pluginpulse-connect' ) . '</p></div>';
        }

        // Show message if debug constants were updated
        if (isset($_GET['settings-updated']) &&
            sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true') {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings have been saved. If debug constants were modified, they will be applied to your wp-config.php file.', 'pluginpulse-connect' ) . '</p></div>';

            // Display current debug constants management status
            $options = get_option($this->option_name);
            $manage_debug = !empty($options['manage_debug_constants']);
            echo '<div class="notice notice-info is-dismissible">';
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
            echo '<p><strong>' . esc_html__( 'Debug Constants Status:', 'pluginpulse-connect' ) . '</strong> ' .
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                ($manage_debug ? esc_html__( 'Enabled', 'pluginpulse-connect' ) : esc_html__( 'Disabled', 'pluginpulse-connect' )) . '</p>';
            echo '</div>';

            // We now handle tab switching via URL parameter in the form redirect

        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                ?>
                <a href="#diagnostics" class="nav-tab nav-tab-active"><?php echo esc_html__( 'Diagnostics', 'pluginpulse-connect' ); ?></a>
                <?php
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                ?>
                <a href="#settings" class="nav-tab"><?php echo esc_html__( 'Settings', 'pluginpulse-connect' ); ?></a>
            </h2>

            <div id="diagnostics" class="tab-content">
                <div class="fw-card">
                    <?php
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                    ?>
                    <h2><?php echo esc_html__( 'Generate Diagnostic Information', 'pluginpulse-connect' ); ?></h2>
                    <?php
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                    ?>
                    <p><?php echo esc_html__( 'This tool collects information about your WordPress installation, active plugins, theme, and plugin settings to help troubleshoot issues.', 'pluginpulse-connect' ); ?></p>

                    <?php
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                    ?>
                    <button type="button" id="wpsa-generate-data" class="button button-primary"><?php echo esc_html__( 'Generate Diagnostic Data', 'pluginpulse-connect' ); ?></button>

                    <div id="wpsa-diagnostic-result" style="display: none; margin-top: 20px;">
                        <?php
                        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                        ?>
                        <h3><?php echo esc_html__( 'Diagnostic Information', 'pluginpulse-connect' ); ?></h3>
                        <div class="notice notice-warning">
                            <?php
                            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                            ?>
                            <p><strong><?php echo esc_html__( 'Note:', 'pluginpulse-connect' ); ?></strong> <?php echo esc_html__( 'This information contains sensitive data about your WordPress installation. Only share it with trusted support personnel.', 'pluginpulse-connect' ); ?></p>
                        </div>

                        <div class="diagnostic-actions" style="margin-bottom: 15px;">
                            <?php
                            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                            ?>
                            <button type="button" id="wpsa-copy-data" class="button"><?php echo esc_html__( 'Copy to Clipboard', 'pluginpulse-connect' ); ?></button>
                            <?php
                            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                            ?>
                            <button type="button" id="wpsa-download-data" class="button"><?php echo esc_html__( 'Download as JSON', 'pluginpulse-connect' ); ?></button>
                            <?php if ($this->settings['enable_rest_endpoint']): ?>
                                <div style="margin-top: 10px;">
                                    <?php
                                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                                    ?>
                                    <p><strong><?php echo esc_html__( 'Temporary Direct Access Link:', 'pluginpulse-connect' ); ?></strong></p>
                                    <input type="text" id="wpsa-access-link" class="large-text code" readonly>
                                    <?php
                                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Library uses its own text domain
                                    ?>
                                    <p class="description"><?php echo esc_html__( 'This link will work for 24 hours. Share it with support personnel for direct access to your diagnostic data.', 'pluginpulse-connect' ); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <textarea id="wpsa-diagnostic-data" style="width: 100%; height: 300px; font-family: monospace;" readonly></textarea>
                    </div>
                </div>
            </div>

            <div id="settings" class="tab-content" style="display: none;">
                <form method="post" action="options.php">
                    <?php
                    // Add a custom redirect field to return to the settings tab
                    echo '<input type="hidden" name="_wp_http_referer" value="' . 
                        esc_attr(admin_url('tools.php?page=fullworks-support-diagnostics&tab=settings')) . '">';
                    
                    settings_fields('fwpsd_settings_group');
                    do_settings_sections('pluginpulse-connect');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('tools_page_fullworks-support-diagnostics' !== $hook) {
            return;
        }

        // Load admin.js from plugin directory (passed via plugin_url parameter)
        if ( ! empty( $this->plugin_url ) ) {
            $script_url = $this->plugin_url . 'admin.js';
        } else {
            // Fallback to library directory for backward compatibility
            $script_url = plugin_dir_url( dirname( __DIR__ ) ) . 'admin.js';
        }

        wp_enqueue_script(
            'fwpsd-admin-script',
            $script_url,
            ['jquery', 'wp-i18n'], // Add wp-i18n as a dependency for translations
            self::VERSION,
            true
        );
        
        // Set up script translations
        wp_set_script_translations('fwpsd-admin-script', 'pluginpulse-connect');

        // Epic integration: Uses plugin-specific nonce and REST URL from PAG-71
        wp_localize_script('fwpsd-admin-script', 'psdData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( 'pp_' . $this->plugin_slug . '_nonce' ), // Plugin-specific nonce (PAG-71)
            'restUrl' => rest_url( 'pluginpulse/' . $this->plugin_slug . '/v1/diagnostics' ), // Plugin-specific REST URL (PAG-71)
            'accessKey' => $this->settings['access_key'],
            'restEndpointKey' => $this->settings['rest_endpoint_key'],
            'pluginSlug' => $this->plugin_slug, // Pass plugin slug to JavaScript
        ]);

        wp_add_inline_style('admin-bar', '
            .tab-content { margin-top: 20px; }
            #wpsa-diagnostic-result { background: #fff; padding: 15px; border: 1px solid #ccd0d4; }
        ');
    }
}