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
 * Debug function to log color integration process
 */
function gpbi_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[GP-Beaver Integration] ' . $message);
    }
}

/**
 * Get GeneratePress global colors in the format needed for Beaver Builder
 */
function gpbi_get_formatted_gp_colors() {
    if (!function_exists('generate_get_global_colors')) {
        gpbi_debug_log('GeneratePress global colors function not available');
        return [];
    }
    
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        gpbi_debug_log('No GeneratePress global colors found');
        return [];
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
            'label' => $label, // No GP: prefix here
            'color' => $color_value,
            'slug' => sanitize_title(strtolower($color['slug'])),
            'isGlobalColor' => true
        ];
    }
    
    gpbi_debug_log('Formatted ' . count($formatted_colors) . ' GP colors for BB');
    return $formatted_colors;
}

/**
 * Add GeneratePress colors to Beaver Builder's WordPress color palette
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
 * Filter WordPress theme colors in the Beaver Builder UI to prevent duplicates
 * 
 * This prevents duplicates in the UI but maintains the CSS variables
 */
function gpbi_remove_duplicate_theme_colors($colors) {
    // If we have no colors, return the original array
    if (empty($colors) || !is_array($colors)) {
        return $colors;
    }
    
    // Get GeneratePress color slugs for comparison
    $gp_color_slugs = [];
    if (function_exists('generate_get_global_colors')) {
        $gp_colors = generate_get_global_colors();
        foreach ($gp_colors as $color) {
            if (isset($color['slug'])) {
                $gp_color_slugs[] = sanitize_title(strtolower($color['slug']));
            }
        }
    }
    
    // If we have no GP colors, return the original array
    if (empty($gp_color_slugs)) {
        return $colors;
    }
    
    // Only filter if the theme colors preference is enabled
    $theme_colors_enabled = get_option('_fl_builder_theme_colors', '0');
    if ($theme_colors_enabled !== '1') {
        return $colors;
    }
    
    // Filter out duplicates for UI purposes only
    $filtered_colors = [];
    foreach ($colors as $color) {
        // Skip if this is a GP color we're handling elsewhere
        if (isset($color['slug']) && in_array($color['slug'], $gp_color_slugs)) {
            continue;
        }
        $filtered_colors[] = $color;
    }
    
    return $filtered_colors;
}
add_filter('fl_wp_core_global_colors', 'gpbi_remove_duplicate_theme_colors', 20);

/**
 * Add GeneratePress colors directly to BB's global colors registry
 * and remove the blank color at the top
 */
function gpbi_inject_colors_to_bb_globals() {
    if (!class_exists('FLBuilderGlobalStyles')) {
        gpbi_debug_log('FLBuilderGlobalStyles class not available');
        return;
    }
    
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
    
    $current_colors = $bb_settings->colors;
    $new_colors = [];
    $added_count = 0;
    
    // First, filter out empty/blank colors
    foreach ($current_colors as $bb_color) {
        if (!empty($bb_color['color']) && !empty($bb_color['label'])) {
            $new_colors[] = $bb_color;
        }
    }
    
    // Now add GeneratePress colors if they don't exist
    foreach ($gp_colors as $gp_color) {
        $exists = false;
        
        // Check if this color already exists in BB colors
        foreach ($new_colors as $key => $bb_color) {
            if (isset($bb_color['uid']) && $bb_color['uid'] === $gp_color['uid']) {
                // Update existing color
                $new_colors[$key] = $gp_color;
                $exists = true;
                break;
            }
        }
        
        // Add new color if it doesn't exist
        if (!$exists) {
            $new_colors[] = $gp_color;
            $added_count++;
        }
    }
    
    // Update BB settings with our cleaned and updated color list
    $bb_settings->colors = $new_colors;
    FLBuilderUtils::update_option('_fl_builder_styles', $bb_settings, true);
    FLBuilderModel::delete_asset_cache_for_all_posts();
    gpbi_debug_log('Updated BB global colors: removed blank colors and added/updated ' . $added_count . ' GP colors');
}

/**
 * Register GeneratePress colors with FLPageData for better integration
 */
function gpbi_register_gp_colors_with_flpagedata() {
    if (!class_exists('FLPageData')) {
        gpbi_debug_log('FLPageData class not available');
        return;
    }
    
    $gp_colors = gpbi_get_formatted_gp_colors();
    if (empty($gp_colors)) {
        return;
    }
    
    foreach ($gp_colors as $color) {
        // Register color with FLPageData - we use the prefix only in the UI label
        FLPageData::add_site_property('global_color_' . $color['uid'], array(
            'label'  => '<span class="prefix">' . __('GeneratePress -', 'gp-beaver-integration') . '</span>' . 
                      esc_html($color['label']) . 
                      '<span class="swatch" style="background-color:' . 
                      esc_attr($color['color']) . ';"></span>',
            'group'  => 'bb',
            'type'   => 'color',
            'getter' => function() use ($color) {
                return $color['color'];
            },
        ));
    }
    
    gpbi_debug_log('Registered ' . count($gp_colors) . ' GeneratePress colors with FLPageData');
}

/**
 * Modify Redux store data to include GeneratePress colors
 */
function gpbi_add_gp_colors_to_redux_store($response, $request) {
    if (!is_object($request) || !method_exists($request, 'get_route') || strpos($request->get_route(), '/fl-controls/v1/state') === false) {
        return $response;
    }
    
    $gp_colors = gpbi_get_formatted_gp_colors();
    if (empty($gp_colors)) {
        return $response;
    }
    
    // Ensure we have the proper structure
    if (!is_object($response)) {
        $response = new stdClass();
    }
    
    if (!isset($response->color)) {
        $response->color = new stdClass();
    }
    
    if (!isset($response->color->sets)) {
        $response->color->sets = new stdClass();
    }
    
    // IMPORTANT: Remove any existing GeneratePress theme colors that might be duplicated
    if (isset($response->color->sets->theme) && isset($response->color->sets->theme->colors)) {
        $gp_color_slugs = array_map(function($color) {
            return $color['slug'] ?? '';
        }, $gp_colors);
        
        $filtered_theme_colors = [];
        foreach ($response->color->sets->theme->colors as $color) {
            if (isset($color['label']) && !in_array(sanitize_title(strtolower($color['label'])), $gp_color_slugs)) {
                $filtered_theme_colors[] = $color;
            }
        }
        
        $response->color->sets->theme->colors = $filtered_theme_colors;
    }
    
    // Add GeneratePress colors as a set
    $response->color->sets->generatepress = array(
        'name' => 'GeneratePress',
        'colors' => []
    );
    
    foreach ($gp_colors as $color) {
        $response->color->sets->generatepress['colors'][] = array(
            'uid' => $color['uid'],
            'label' => $color['label'],
            'color' => $color['color'],
            'isGlobalColor' => true
        );
    }
    
    gpbi_debug_log('Added GeneratePress colors to Redux store state');
    return $response;
}

/**
 * Add color labels to the JS configuration
 */
function gpbi_add_gp_colors_to_js_config($config) {
    $gp_colors = gpbi_get_formatted_gp_colors();
    if (empty($gp_colors)) {
        return $config;
    }
    
    if (!isset($config['globalColorLabels'])) {
        $config['globalColorLabels'] = array();
    }
    
    foreach ($gp_colors as $color) {
        // Show the prefix only in UI, not in the variable name
        $config['globalColorLabels']['global_color_' . $color['uid']] = '<span class="prefix">GeneratePress -</span>' . 
            esc_html($color['label']) . 
            '<span class="swatch" style="background-color:' . 
            esc_attr($color['color']) . ';"></span>';
    }
    
    gpbi_debug_log('Added GeneratePress color labels to JS config');
    return $config;
}

/**
 * Run our integrations when the plugin is loaded
 */
function gpbi_run_color_integrations() {
    // Run immediately at plugin load to ensure BB has the colors
    gpbi_inject_colors_to_bb_globals();
    gpbi_register_gp_colors_with_flpagedata();
}

// Hook into appropriate actions to make this work
add_action('plugins_loaded', 'gpbi_run_color_integrations', 20);
add_filter('fl_builder_ui_js_config', 'gpbi_add_gp_colors_to_js_config', 10, 1);
add_filter('rest_pre_echo_response', 'gpbi_add_gp_colors_to_redux_store', 10, 2);

// Run when GeneratePress settings are updated
add_action('generate_settings_updated', function() {
    gpbi_inject_colors_to_bb_globals();
    FLBuilderModel::delete_asset_cache_for_all_posts();
    gpbi_debug_log('Re-synced GeneratePress colors after settings update');
});

// Also run on admin_init to catch updates
add_action('admin_init', 'gpbi_inject_colors_to_bb_globals', 20);

/**
 * Makes the Presets tab the default active tab in Beaver Builder's color picker
 * This improves UX by immediately showing available colors when a color picker opens
 */
function gpbi_activate_presets_tab() {
    // Only load when builder is active
    if (!class_exists('FLBuilderModel') || !FLBuilderModel::is_builder_active()) {
        return;
    }
    
    ?>
    <script>
    (function($) {
        // Function to click the presets tab in a color picker
        function clickPresetsTab(container) {
            if (!container) return;
            
            // Find the tabs at the bottom
            var $tabs = $(container).find('.fl-controls-picker-bottom-tabs');
            if (!$tabs.length) return;
            
            // The tabs are buttons - the last one should be presets
            var $buttons = $tabs.find('.fl-control');
            if (!$buttons.length) return;
            
            // Get the last button (presets tab)
            var $presetsTab = $buttons.last();
            
            // Only click if not already selected
            if (!$presetsTab.hasClass('is-selected')) {
                //console.log('GP Beaver Integration: Activating presets tab');
                $presetsTab.trigger('click');
            }
        }
        
        // Watch for color pickers opening
        // Method 1: Watch for dialog elements being added
        $(document).on('click', '.fl-controls-dialog-button', function() {
            // Give the dialog time to fully render
            setTimeout(function() {
                // Find the dialog
                $('.fl-controls-dialog').each(function() {
                    clickPresetsTab(this);
                });
            }, 50);
        });
        
        // Method 2: Direct MutationObserver approach
        $(function() {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes && mutation.addedNodes.length) {
                        for (var i = 0; i < mutation.addedNodes.length; i++) {
                            var node = mutation.addedNodes[i];
                            
                            // Check if this is a dialog
                            if ($(node).hasClass('fl-controls-dialog')) {
                                // Multiple timeouts to ensure we catch it
                                setTimeout(function() { clickPresetsTab(node); }, 10);
                                setTimeout(function() { clickPresetsTab(node); }, 50);
                                setTimeout(function() { clickPresetsTab(node); }, 150);
                            }
                        }
                    }
                });
            });
            
            // Start observing
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Also look for any existing color pickers
            $('.fl-controls-dialog').each(function() {
                clickPresetsTab(this);
            });
            
            // Handle iframe content too
            $(document).on('fl-builder.preview-rendered', function() {
                if ($('#fl-builder-iframe').length) {
                    var iframe = document.getElementById('fl-builder-iframe');
                    if (iframe && iframe.contentDocument) {
                        // Set up observer in iframe
                        var iframeObserver = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.addedNodes && mutation.addedNodes.length) {
                                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                                        var node = mutation.addedNodes[i];
                                        if ($(node).hasClass('fl-controls-dialog')) {
                                            setTimeout(function() { clickPresetsTab(node); }, 10);
                                            setTimeout(function() { clickPresetsTab(node); }, 50);
                                            setTimeout(function() { clickPresetsTab(node); }, 150);
                                        }
                                    }
                                }
                            });
                        });
                        
                        iframeObserver.observe(iframe.contentDocument.body, {
                            childList: true,
                            subtree: true
                        });
                    }
                }
            });
        });
        
    })(jQuery);
    </script>
    <?php
}

// Add to both footer locations
add_action('wp_footer', 'gpbi_activate_presets_tab', 999);
add_action('admin_footer', 'gpbi_activate_presets_tab', 999);
