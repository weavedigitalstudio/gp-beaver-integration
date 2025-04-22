<?php
/**
 * Plugin Name:       GP Beaver Integration
 * Plugin URI:        https://github.com/weavedigitalstudio/gp-beaver-integration
 * Description:       Integrates GeneratePress Global Colors and Font Library with Beaver Builder page builder for brand consistency.
 * Version:           1.0.6
 * Author:            Weave Digital Studio, Gareth Bissland
 * Author URI:        https://weave.co.nz
 * License:           GPL-2.0+
 */

// Prevent direct access to this file
if (!defined("ABSPATH")) {
    exit();
}

// Define plugin constants
define('GPBI_VERSION', '1.0.6');
define('GPBI_PLUGIN_FILE', __FILE__);
define('GPBI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GPBI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Define debug constant - set to true to enable verbose logging
// Set to false for production use
if (!defined('GPBI_DEBUG')) {
    define('GPBI_DEBUG', false);
}

// Define customizer error tracking constant
if (!defined('GPBI_TRACK_CUSTOMIZER_ERRORS')) {
    define('GPBI_TRACK_CUSTOMIZER_ERRORS', true);
}

/**
 * Debug function to log integration process
 * Only logs if both WP_DEBUG and GPBI_DEBUG are true
 */
if (!function_exists('gpbi_debug_log')) {
    function gpbi_debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && (defined('GPBI_DEBUG') && GPBI_DEBUG)) {
            error_log('[GP-Beaver Integration] ' . $message);
        }
    }
}

/**
 * Central function to ensure colors are synced properly
 * This prevents duplicate colors by centralizing the sync process
 */
function gpbi_sync_colors_to_bb() {
    // Only continue if we have the proper function
    if (!function_exists('gpbi_update_bb_global_styles')) {
        return;
    }
    
    // Check if force update is needed, or run if it's not set
    if (get_transient('gpbi_force_color_update') || !get_transient('gpbi_colors_synced')) {
        gpbi_debug_log('Running color sync via central function');
        gpbi_update_bb_global_styles();
    }
}

// Add central hooks for color syncing to avoid duplicates
add_action('customize_save_after', 'gpbi_sync_colors_to_bb', 30);
add_action('update_option_generate_settings', 'gpbi_sync_colors_to_bb', 30);

/**
 * Handles plugin activation requirements
 * Makes sure GeneratePress and Beaver Builder are active before allowing activation
 */
function gpbi_activate_plugin()
{
    $theme = wp_get_theme();
    $is_generatepress =
        "GeneratePress" === $theme->get("Name") ||
        "generatepress" === $theme->get_template();
    if (!$is_generatepress || !class_exists("FLBuilder")) {
        deactivate_plugins(plugin_basename(__FILE__));
        $missing = !$is_generatepress ? "GeneratePress theme" : "Beaver Builder";
        wp_die(
            "This plugin requires both GeneratePress theme and Beaver Builder to be installed and active. Missing: {$missing}",
            "Plugin Activation Error",
            ["back_link" => true]
        );
    }
    
    // Force a color update on activation
    set_transient('gpbi_force_color_update', true, 5 * MINUTE_IN_SECONDS);
}
register_activation_hook(__FILE__, "gpbi_activate_plugin");

/**
 * Initialize GitHub updater if we're in admin
 */
function gpbi_init_github_updater() {
    if (is_admin() && file_exists(GPBI_PLUGIN_DIR . 'includes/github-updater.php')) {
        require_once GPBI_PLUGIN_DIR . 'includes/github-updater.php';
        GPBI_GitHub_Updater::init(GPBI_PLUGIN_FILE);
    }
}
add_action('init', 'gpbi_init_github_updater');

/**
 * Include necessary files
 */
require_once GPBI_PLUGIN_DIR . 'includes/github-updater.php';

/**
 * Include color grid shortcode functionality
 */
require_once GPBI_PLUGIN_DIR . 'includes/color-grid.php';

/**
 * Include font integration functionality
 */
require_once GPBI_PLUGIN_DIR . 'includes/font-integration.php';

/**
 * Include color palette restriction functionality
 */
require_once GPBI_PLUGIN_DIR . 'includes/color-palette-restriction.php';

/**
 * Detect Beaver Builder version to determine which scripts to load
 * 
 * @return bool True if BB version is 2.9 or higher
 */
function gpbi_is_new_bb_version() {
    if (!defined('FL_BUILDER_VERSION')) {
        return false;
    }
    
    return version_compare(FL_BUILDER_VERSION, '2.9', '>=');
}

/**
 * Include the appropriate color swatch fixers based on BB version
 */
if (gpbi_is_new_bb_version()) {
    // Modern BB 2.9+ React-based color picker fixes
    require_once GPBI_PLUGIN_DIR . 'includes/color-swatch-fixer.php';
} else {
    // Classic BB 2.8 and earlier fixes
    require_once GPBI_PLUGIN_DIR . 'includes/classic-color-swatch-fixer.php';
    require_once GPBI_PLUGIN_DIR . 'includes/classic-preset-activator.php';
    // Add the ultra-direct BB 2.8 fix
    require_once GPBI_PLUGIN_DIR . 'includes/bb28-global-colors-fix.php';
}

/**
 * Include color integration functionality
 * Handles both old and new versions of Beaver Builder
 */
function gpbi_include_color_integration() {
    // Check Beaver Builder version
    $bb_version = defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : '0';
    
    // For Beaver Builder 2.9+ use the new integration
    if (version_compare($bb_version, '2.9.0', '>=')) {
        require_once GPBI_PLUGIN_DIR . 'includes/color-integration-bb29.php';
    } 
    // For older versions, use the legacy integration
    else {
        require_once GPBI_PLUGIN_DIR . 'includes/color-integration.php';
    }
}
add_action('plugins_loaded', 'gpbi_include_color_integration');

/**
 * Configure Beaver Builder Color Settings
 * - Disable Theme Colors (not required for our Global Color Sync)
 * - Disable WordPress Core Colors (to keep color selection focused on theme colors)
 */
function gpbi_configure_bb_color_settings() {
    // Check if Beaver Builder is active
    if (!class_exists('FLBuilder')) {
        return;
    }
    
    // Check if FLBuilderModel exists
    if (!class_exists('FLBuilderModel')) {
        return;
    }
    
    $options = get_option('gpbi_settings');
    $restrict_colors = isset($options['restrict_colors']) && $options['restrict_colors'];
    
    $global_settings = FLBuilderModel::get_global_settings();
    
    // Add check: Ensure global_settings is an object before proceeding
    if (!is_object($global_settings)) {
        return; // Cannot proceed if settings aren't loaded
    }
    
    // Determine the correct setting key based on BB version
    $setting_key = 'show_wordpress_colors';
    if (isset($global_settings->use_wp_palette)) {
        $setting_key = 'use_wp_palette';
    }
    
    $setting_changed = false;
    
    // If restriction is enabled, ensure the setting is false
    if ($restrict_colors) {
        // Check if the property exists before accessing
        if (property_exists($global_settings, $setting_key)) {
            if ($global_settings->$setting_key !== false) {
                $global_settings->$setting_key = false;
                $setting_changed = true;
            }
        }
    } 
    // If restriction is disabled, ensure the setting is true
    else {
        // Check if the property exists before accessing
        if (property_exists($global_settings, $setting_key)) {
            if ($global_settings->$setting_key !== true) {
                $global_settings->$setting_key = true;
                $setting_changed = true;
            }
        }
    }
    
    // BUGFIX: FLBuilderModel::update_global_settings() doesn't exist
    // This call was causing the 500 error in the customizer
    if ($setting_changed) {
        // Instead of FLBuilderModel::update_global_settings(), we'll use the proper method
        // or just log that the setting would be changed
        gpbi_debug_log('Would update BB setting: ' . $setting_key . ' to ' . ($restrict_colors ? 'false' : 'true'));
    }
}
add_action('init', 'gpbi_configure_bb_color_settings', 20);

/**
 * Generates CSS for global color variables
 * This makes GeneratePress colors available to Beaver Builder
 */
function gpbi_generate_global_colors_css($global_colors)
{
    static $generated_css = null;
    
    // Return cached result if available
    if ($generated_css !== null) {
        return $generated_css;
    }
    
    if (empty($global_colors)) {
        $generated_css = "";
        return $generated_css;
    }
    
    $css = ":root{";
    foreach ($global_colors as $data) {
        if (!isset($data["slug"]) || !isset($data["color"])) {
            continue;
        }
        $css .= sprintf(
            "--wp--preset--color--%s:%s;",
            esc_attr($data["slug"]),
            esc_attr($data["color"])
        );
    }
    $css .= "}";
    
    $generated_css = $css;
    return $css;
}

/**
 * Enqueues the color variables as inline CSS
 * This adds them to the GeneratePress stylesheet for efficiency
 */
function gpbi_enqueue_inline_styles()
{
    if (!function_exists("generate_get_global_colors")) {
        return;
    }
    
    $global_colors = generate_get_global_colors();
    $css = gpbi_generate_global_colors_css($global_colors);
    
    if (!empty($css) && wp_style_is("generate-style", "enqueued")) {
        wp_add_inline_style("generate-style", $css);
    }
}
add_action("wp_enqueue_scripts", "gpbi_enqueue_inline_styles", 20);

/**
 * Enqueues the color picker enhancement script
 * Makes the colors available in Beaver Builder's color picker
 */
function gpbi_enqueue_admin_scripts() {
    // Define script paths
    $bb29_script_rel_path = "js/color-picker-bb29.js";
    $bb29_script_abs_path = plugin_dir_path(__FILE__) . $bb29_script_rel_path;
    $customizer_script_rel_path = "js/customizer-handler.js";
    $customizer_script_abs_path = plugin_dir_path(__FILE__) . $customizer_script_rel_path;
    $admin_script_rel_path = "js/iris-color-picker.js";
    $admin_script_abs_path = plugin_dir_path(__FILE__) . $admin_script_rel_path;

    // Check if we are in the BB editor or Customizer preview
    $is_bb_active = class_exists('FLBuilderModel') && FLBuilderModel::is_builder_active();
    $is_customizer = is_customize_preview();
    $is_admin = is_admin();

    // Only proceed if we are in admin and GeneratePress functions exist
    if (!$is_admin || !function_exists("generate_get_global_colors")) {
        return;
    }

    static $already_enqueued = false;
    
    // Only enqueue once per request
    if ($already_enqueued) {
        return;
    }

    // Fetch global colors only when needed
    $global_colors = generate_get_global_colors();
    
    // Only proceed if we have colors to work with
    if (empty($global_colors)) {
        return; 
    }

    // Convert colors array to simple palette array for localization
    $palette = array_map(function ($color) {
        return isset($color["color"]) ? $color["color"] : "";
    }, $global_colors);

    // Enqueue scripts based on context
    if ($is_bb_active) {
        // Check Beaver Builder version
        $bb_version = defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : '0';
        
        // Enqueue BB 2.9+ script if version matches, dependencies are met, and file exists
        if (version_compare($bb_version, '2.9.0', '>=') &&
            wp_script_is('wp-color-picker', 'registered') && 
            wp_script_is('fl-builder-color-picker', 'registered') &&
            file_exists($bb29_script_abs_path)) { 

            wp_enqueue_script(
                "gpbi-color-picker", 
                plugin_dir_url(__FILE__) . $bb29_script_rel_path,
                ["wp-color-picker", "fl-builder-color-picker"],
                GPBI_VERSION,
                true 
            );
            
            // Localize only if the script was successfully enqueued
            wp_localize_script("gpbi-color-picker", "generatePressPalette", $palette);
        }
        // TODO: Consider adding logic for BB versions older than 2.9 if needed
        
    } elseif ($is_customizer) {
        // Enqueue customizer script if the file exists
        if (file_exists($customizer_script_abs_path)) {
            wp_enqueue_script(
                "gpbi-customizer", 
                plugin_dir_url(__FILE__) . $customizer_script_rel_path,
                ["jquery"],
                GPBI_VERSION,
                true 
            );
            
            // Pass debug flag to script
            wp_localize_script("gpbi-customizer", "gpbiCustomizer", [
                "debug" => defined('GPBI_DEBUG') && GPBI_DEBUG
            ]);
        }
    }
    
    // For all admin screens (including BB editor), enhance the WP Admin iris color picker
    if ($is_admin && file_exists($admin_script_abs_path)) {
        // First, make sure the WP color picker is enqueued
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue the standard iris color picker script
        wp_enqueue_script(
            "gpbi-iris-color-picker", 
            plugin_dir_url(__FILE__) . $admin_script_rel_path,
            ["wp-color-picker", "jquery"],
            GPBI_VERSION,
            true 
        );
        
        // Localize palette for the iris color picker
        wp_localize_script("gpbi-iris-color-picker", "generatePressPalette", $palette);
    }
    
    $already_enqueued = true; 
}
add_action("admin_enqueue_scripts", "gpbi_enqueue_admin_scripts");

/**
 * Add settings page to WordPress admin menu
 */
function gpbi_add_settings_page() {
    add_options_page(
        'GP Beaver Integration Settings',
        'GP Beaver Integration',
        'manage_options',
        'gp-beaver-integration',
        'gpbi_render_settings_page'
    );
}
add_action('admin_menu', 'gpbi_add_settings_page');

/**
 * Add settings link to plugins page
 */
function gpbi_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=gp-beaver-integration') . '">' . __('Settings', 'gp-beaver-integration') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gpbi_add_settings_link');

/**
 * Register plugin settings
 */
function gpbi_register_settings() {
    // Register the setting
    register_setting(
        'gpbi_settings',           // Option group
        'gpbi_settings',           // Option name
        'gpbi_sanitize_settings'   // Sanitize callback
    );
}
add_action('admin_init', 'gpbi_register_settings');

/**
 * Sanitize settings before saving
 */
function gpbi_sanitize_settings($input) {
    $sanitized = array();
    
    // Sanitize restrict_colors - ensure it's either 1 or 0
    $sanitized['restrict_colors'] = isset($input['restrict_colors']) ? 1 : 0;
    
    return $sanitized;
}

/**
 * Render the settings page
 */
function gpbi_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get settings with proper defaults
    $settings = get_option('gpbi_settings', array('restrict_colors' => 1));
    $restrict_colors = isset($settings['restrict_colors']) ? $settings['restrict_colors'] : 1;
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('gpbi_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Color Restrictions</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gpbi_settings[restrict_colors]" value="1" <?php checked($restrict_colors, 1); ?>>
                            Restrict color picker to Global Colors only
                        </label>
                        <p class="description">
                            When enabled, users can only select from your GeneratePress Global Colors in the Beaver Builder color picker.<br>
                            When disabled, users can add custom colors to the color picker.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>

        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>Using the Color Grid Shortcode</h2>
            <p>Display your color palette anywhere on your site using the shortcode:</p>
            <pre><code>[gp_global_color_grid]</code></pre>

            <h3>Optional Parameters</h3>
            <ul>
                <li><code>size</code>: Square size in pixels (default: 190)</li>
                <li><code>columns</code>: Number of columns (default: 4)</li>
                <li><code>names</code>: Show color names (default: true)</li>
                <li><code>values</code>: Show color hex values (default: true)</li>
            </ul>

            <h3>Example</h3>
            <pre><code>[gp_global_color_grid size="150" columns="3" names="true" values="false"]</code></pre>

            <p>This creates a responsive grid showing all your GeneratePress Global Colors with their names, CSS variables, and hex values.</p>
            
            <div style="margin-top: 20px;">
                <img src="https://weave-hk-github.b-cdn.net/screens/global-color-grid.png" alt="Global Color Grid Example" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler for tracking errors from JavaScript
 */
function gpbi_ajax_track_error() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save-customize_' . get_stylesheet())) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('edit_theme_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Get error data
    $error_data = isset($_POST['error']) ? json_decode(stripslashes($_POST['error']), true) : null;
    
    if (!$error_data) {
        wp_send_json_error('Invalid error data');
        return;
    }
    
    // Add browser info
    $error_data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    $error_data['timestamp'] = current_time('mysql');
    
    // Track the error
    if (function_exists('gpbi_track_customizer_error')) {
        gpbi_track_customizer_error($error_data);
    }
    
    wp_send_json_success('Error tracked');
}
add_action('wp_ajax_gpbi_track_error', 'gpbi_ajax_track_error');
