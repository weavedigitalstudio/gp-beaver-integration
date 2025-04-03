<?php
/**
 * Color Integration
 *
 * This file provides functionality for integrating GeneratePress Global Colors with
 * Beaver Builder's color picker system.
 *
 * @package GP_Beaver_Integration
 * @since 1.0.2
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get GeneratePress global colors in the format needed for Beaver Builder
 * Uses caching to prevent repeated processing
 */
function gpbi_get_formatted_gp_colors() {
    // Check for cached result first
    static $cached_colors = null;
    
    // Return cached result if available
    if ($cached_colors !== null) {
        return $cached_colors;
    }
    
    // Check transient cache
    $transient_key = 'gpbi_formatted_colors';
    $cached_colors = get_transient($transient_key);
    if ($cached_colors !== false) {
        gpbi_debug_log('Using cached colors from transient');
        return $cached_colors;
    }
    
    // Check GeneratePress availability
    if (!function_exists('generate_get_global_colors')) {
        gpbi_debug_log('GeneratePress global colors function not available');
        $cached_colors = [];
        return $cached_colors;
    }
    
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        gpbi_debug_log('No GeneratePress global colors found');
        $cached_colors = [];
        return $cached_colors;
    }
    
    $formatted_colors = [];
    
    foreach ($global_colors as $color) {
        if (empty($color['slug']) || empty($color['color'])) {
            continue;
        }
        
        $uid = substr(md5($color['slug']), 0, 9);
        $label = isset($color['name']) ? $color['name'] : $color['slug'];
        $color_value = isset($color['color']) ? $color['color'] : '';
        
        // Skip empty colors
        if (empty($color_value)) {
            continue;
        }
        
        // Make sure color value is properly formatted
        if (!preg_match('/^#/', $color_value)) {
            $color_value = '#' . $color_value;
        }
        
        $formatted_colors[] = [
            'uid' => $uid,
            'label' => $label,
            'color' => $color_value,
            'slug' => sanitize_title(strtolower($color['slug'])),
            'isGlobalColor' => true
        ];
    }
    
    $count = count($formatted_colors);
    gpbi_debug_log("Formatted {$count} GP colors for BB");
    
    // Cache the result in a transient - 12 hour cache
    set_transient($transient_key, $formatted_colors, 12 * HOUR_IN_SECONDS);
    
    // Store in static variable for this page load
    $cached_colors = $formatted_colors;
    return $formatted_colors;
}

/**
 * Clear color cache when GeneratePress colors are updated
 */
function gpbi_clear_color_cache() {
    delete_transient('gpbi_formatted_colors');
    set_transient('gpbi_force_color_update', true, 5 * MINUTE_IN_SECONDS);
}
add_action('customize_save_after', 'gpbi_clear_color_cache');
add_action('update_option_generate_settings', 'gpbi_clear_color_cache');

/**
 * Update Beaver Builder's global styles when GeneratePress colors change
 */
function gpbi_update_bb_global_styles() {
    // Only proceed if we should update
    if (!get_transient('gpbi_force_color_update')) {
        return;
    }
    
    // Get the colors
    $gp_colors = gpbi_get_formatted_gp_colors();
    if (empty($gp_colors)) {
        return;
    }
    
    // Get existing BB global styles settings
    $bb_settings = FLBuilderGlobalStyles::get_settings(false);
    
    // Initialize or reset the colors array
    if (!isset($bb_settings->colors) || !is_array($bb_settings->colors)) {
        $bb_settings->colors = [];
    }
    
    // Update colors
    $bb_settings->colors = $gp_colors;
    
    // Save the updated settings
    FLBuilderGlobalStyles::save_settings($bb_settings);
    
    // Clear the force update flag
    delete_transient('gpbi_force_color_update');
    
    // Set a new sync timestamp
    set_transient('gpbi_colors_synced', true, 12 * HOUR_IN_SECONDS);
}
add_action('customize_save_after', 'gpbi_update_bb_global_styles');
add_action('update_option_generate_settings', 'gpbi_update_bb_global_styles'); 