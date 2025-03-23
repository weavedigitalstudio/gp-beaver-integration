<?php
/**
 * Plugin Name:       GP Beaver Integration
 * Plugin URI:        https://github.com/weavedigitalstudio/gp-beaver-integration
 * Description:       Integrates GeneratePress Global Colors and Font Library with Beaver Builder page builder for brand consistency.
 * Version:           1.0.1
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
 * Include color integration for Beaver Builder 2.9+
 */
require_once plugin_dir_path(__FILE__) . "includes/color-integration-bb29.php";

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
function gpbi_enqueue_admin_scripts()
{
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
        wp_enqueue_script(
            "gpbi-color-picker",
            plugin_dir_url(__FILE__) . "js/color-picker.js",
            ["wp-color-picker"],
            GPBI_VERSION,
            true
        );
        
        // Convert colors array to simple palette array
        $palette = array_map(function ($color) {
            return isset($color["color"]) ? $color["color"] : "";
        }, $global_colors);
        
        wp_localize_script("gpbi-color-picker", "generatePressPalette", $palette);
        
        $already_enqueued = true;
    }
}
add_action("admin_enqueue_scripts", "gpbi_enqueue_admin_scripts");
