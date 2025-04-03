<?php
/**
 * Plugin Name:       GP Beaver Integration
 * Plugin URI:        https://github.com/weavedigitalstudio/gp-beaver-integration
 * Description:       Integrates GeneratePress Global Colors and Font Library with Beaver Builder page builder for brand consistency.
 * Version:           1.0.2
 * Author:            Weave Digital Studio, Gareth Bissland
 * Author URI:        https://weave.co.nz
 * License:           GPL-2.0+
 */

// Prevent direct access to this file
if (!defined("ABSPATH")) {
    exit();
}

// Define plugin constants
define('GPBI_VERSION', '1.0.1');
define('GPBI_PLUGIN_FILE', __FILE__);
define('GPBI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GPBI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Define debug constant - set to true to enable verbose logging
// Set to false for production use
define('GPBI_DEBUG', false);

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
 * Include color grid shortcode functionality
 */
require_once plugin_dir_path(__FILE__) . "includes/color-grid.php";

/**
 * Include font integration functionality
 */
require_once plugin_dir_path(__FILE__) . "includes/font-integration.php";

/**
 * Include color palette restriction functionality
 */
require_once plugin_dir_path(__FILE__) . "includes/color-palette-restriction.php";

/**
 * Include color integration functionality
 * Handles both old and new versions of Beaver Builder
 */
function gpbi_include_color_integration() {
    // Check Beaver Builder version
    $bb_version = defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : '0';
    
    // For Beaver Builder 2.9+ use the new integration
    if (version_compare($bb_version, '2.9.0', '>=')) {
        require_once plugin_dir_path(__FILE__) . "includes/color-integration-bb29.php";
    } 
    // For older versions, use the legacy integration
    else {
        require_once plugin_dir_path(__FILE__) . "includes/color-integration.php";
    }
}
add_action('plugins_loaded', 'gpbi_include_color_integration');

/**
 * Configure Beaver Builder Color Settings
 * - Disable Theme Colors (not required for our Global Color Sync)
 * - Disable WordPress Core Colors (to keep color selection focused on theme colors)
 */
function gpbi_configure_bb_color_settings() {
    // Only proceed if Beaver Builder is active
    if (!class_exists('FLBuilder')) {
        return;
    }
    
    // Only update options if they've changed to prevent unnecessary option writes
    $theme_colors = get_option('_fl_builder_theme_colors', null);
    $core_colors = get_option('_fl_builder_core_colors', null);
    
    if ($theme_colors !== '0') {
        update_option('_fl_builder_theme_colors', '0');
    }
    
    if ($core_colors !== '0') {
        update_option('_fl_builder_core_colors', '0');
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
    // Only proceed if we need to (in admin and have colors)
    if (!is_admin() || !function_exists("generate_get_global_colors")) {
        return;
    }
    
    static $already_enqueued = false;
    
    // Only enqueue once
    if ($already_enqueued) {
        return;
    }
    
    $global_colors = generate_get_global_colors();
    
    // Only enqueue if we have colors to work with
    if (!empty($global_colors)) {
        // Check Beaver Builder version
        $bb_version = defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : '0';
        
        // For Beaver Builder 2.9+ use the new script
        if (version_compare($bb_version, '2.9.0', '>=')) {
            wp_enqueue_script(
                "gpbi-color-picker",
                plugin_dir_url(__FILE__) . "js/color-picker-bb29.js",
                ["wp-color-picker", "fl-builder-color-picker"],
                GPBI_VERSION,
                true
            );
        }
        
        // Convert colors array to simple palette array
        $palette = array_map(function ($color) {
            return isset($color["color"]) ? $color["color"] : "";
        }, $global_colors);
        
        wp_localize_script("gpbi-color-picker", "generatePressPalette", $palette);
        
        $already_enqueued = true;
    }
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
