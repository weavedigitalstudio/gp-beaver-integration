<?php
/**
 * Color Integration for Beaver Builder 2.9+
 *
 * This file provides functionality for integrating GeneratePress Global Colors with
 * Beaver Builder 2.9+'s new React-based color picker system.
 *
 * @package GP_Beaver_Integration
 * @since 0.7.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add GeneratePress Global Colors to Beaver Builder's WordPress color palette
 *
 * This uses the fl_wp_core_global_colors filter provided by Beaver Builder
 * to add GeneratePress colors to the WordPress colors section.
 *
 * @param array $colors The existing WordPress core colors
 * @return array Modified array including GeneratePress colors
 */
function gpbi_add_gp_colors_to_bb_palette($colors) {
    if (!function_exists('generate_get_global_colors')) {
        return $colors;
    }
    
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return $colors;
    }
    
    foreach ($global_colors as $color) {
        if (!isset($color['slug']) || !isset($color['color'])) {
            continue;
        }
        
        // Ensure we have valid, sanitized data
        $slug = sanitize_title(strtolower($color['slug']));
        $label = isset($color['name']) ? esc_html($color['name']) : esc_html($color['slug']);
        $color_value = isset($color['color']) ? $color['color'] : '';
        
        // Skip empty colors
        if (empty($color_value)) {
            continue;
        }
        
        // Add color to Beaver Builder's color system
        $colors[] = array(
            'slug'  => $slug,
            'color' => class_exists('FLBuilderColor') ? FLBuilderColor::hex_or_rgb($color_value) : $color_value,
            'name'  => $label
        );
    }
    
    return $colors;
}
add_filter('fl_wp_core_global_colors', 'gpbi_add_gp_colors_to_bb_palette');

/**
 * Register GeneratePress Global Colors with FLPageData for better integration
 * 
 * This makes GeneratePress colors available as "Global Colors" in BB's color picker,
 * enabling a richer experience including field connections.
 */
function gpbi_register_gp_colors_in_bb() {
    if (!function_exists('generate_get_global_colors') || !class_exists('FLPageData')) {
        return;
    }
    
    // Get GeneratePress colors
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return;
    }
    
    // Set the CSS variable prefix from GeneratePress settings if available
    $gp_settings = get_option('generate_settings');
    $prefix = isset($gp_settings['prefix']) && !empty($gp_settings['prefix']) 
        ? sanitize_title(strtolower($gp_settings['prefix'])) 
        : 'fl-global';
    
    // Register each color with FLPageData
    foreach ($global_colors as $color) {
        if (empty($color['slug']) || empty($color['color'])) {
            continue;
        }
        
        // Generate a unique ID for each color
        $uid = substr(md5($color['slug']), 0, 9);
        $slug = sanitize_title(strtolower($color['slug']));
        $label = isset($color['name']) ? esc_html($color['name']) : esc_html($color['slug']);
        $color_value = isset($color['color']) ? esc_attr($color['color']) : '';
        
        // Skip empty colors
        if (empty($color_value)) {
            continue;
        }
        
        // Register color with FLPageData
        FLPageData::add_site_property('global_color_' . $uid, array(
            'label'  => '<span class="prefix">' . __('GeneratePress -', 'gp-beaver-integration') . '</span>' . 
                      $label . 
                      '<span class="swatch" style="background-color:' . 
                      (class_exists('FLBuilderColor') ? FLBuilderColor::hex_or_rgb($color_value) : $color_value) . 
                      ';"></span>',
            'group'  => 'bb', // Makes it appear in the BB Global Colors section
            'type'   => 'color',
            'getter' => function() use ($prefix, $slug) {
                return 'var(--wp--preset--color--' . $slug . ')';
            },
        ));
        
        // Add to the global color labels in JS config
        add_filter('fl_builder_ui_js_config', function($config) use ($uid, $label, $color_value) {
            if (!isset($config['globalColorLabels'])) {
                $config['globalColorLabels'] = array();
            }
            
            $config['globalColorLabels']['global_color_' . $uid] = '<span class="prefix">' . 
                __('GeneratePress -', 'gp-beaver-integration') . '</span>' . 
                $label . 
                '<span class="swatch" style="background-color:' . 
                (class_exists('FLBuilderColor') ? FLBuilderColor::hex_or_rgb($color_value) : $color_value) . 
                ';"></span>';
            
            return $config;
        });
    }
}

// Register GeneratePress colors with BB's FLPageData system if it exists
if (class_exists('FLPageData')) {
    add_action('init', 'gpbi_register_gp_colors_in_bb', 20);
}

/**
 * Add GeneratePress colors to BB's Redux store via state endpoint
 * 
 * This ensures that our colors are available in the Redux store that
 * powers the React color picker UI.
 */
function gpbi_add_gp_colors_to_bb_state($response, $request) {
    // Only process our specific endpoint
    if (empty($request) || !is_object($request) || !isset($request->get_route)) {
        return $response;
    }
    
    // Check if we're on the state API endpoint
    $route = $request->get_route();
    if (strpos($route, '/fl-controls/v1/state') === false) {
        return $response;
    }
    
    if (!function_exists('generate_get_global_colors')) {
        return $response;
    }
    
    // Get GeneratePress colors
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return $response;
    }
    
    // Ensure response object is properly set up
    if (!is_object($response)) {
        $response = new stdClass();
    }
    
    if (!isset($response->color)) {
        $response->color = new stdClass();
    }
    
    if (!isset($response->color->sets)) {
        $response->color->sets = new stdClass();
    }
    
    // Add a GeneratePress color set
    $response->color->sets->generatepress = array(
        'name' => 'GeneratePress',
        'colors' => array()
    );
    
    // Add each color to the set
    foreach ($global_colors as $color) {
        if (!isset($color['slug']) || !isset($color['color'])) {
            continue;
        }
        
        $uid = substr(md5($color['slug']), 0, 9);
        $label = isset($color['name']) ? esc_html($color['name']) : esc_html($color['slug']);
        $color_value = isset($color['color']) ? esc_attr($color['color']) : '';
        
        // Skip empty colors
        if (empty($color_value)) {
            continue;
        }
        
        $response->color->sets->generatepress['colors'][] = array(
            'uid' => $uid,
            'label' => $label,
            'color' => class_exists('FLBuilderColor') ? FLBuilderColor::hex_or_rgb($color_value) : $color_value,
            'isGlobalColor' => true
        );
    }
    
    return $response;
}
// Add to the REST API response for state
add_filter('rest_pre_echo_response', 'gpbi_add_gp_colors_to_bb_state', 10, 2);
