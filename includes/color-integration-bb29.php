<?php
/**
 * Color Integration for Beaver Builder 2.9+
 *
 * This file provides functionality for integrating GeneratePress Global Colors with
 * Beaver Builder 2.9+'s new React-based color picker system.
 *
 * @package GP_Beaver_Integration
 * @since 1.0.0
 * @Version 1.0.1
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Track customizer errors for debugging
 */
function gpbi_track_customizer_error($error) {
    if (!defined('GPBI_TRACK_CUSTOMIZER_ERRORS') || !GPBI_TRACK_CUSTOMIZER_ERRORS) {
        return;
    }
    
    // Get existing errors
    $errors = get_transient('gpbi_customizer_errors');
    if (!is_array($errors)) {
        $errors = [];
    }
    
    // Add timestamp
    $error['timestamp'] = current_time('mysql');
    
    // Add to errors array
    $errors[] = $error;
    
    // Keep only the last 10 errors
    if (count($errors) > 10) {
        $errors = array_slice($errors, -10);
    }
    
    // Save errors
    set_transient('gpbi_customizer_errors', $errors, 24 * HOUR_IN_SECONDS);
    
    // Log if debug is enabled
    if (defined('GPBI_DEBUG') && GPBI_DEBUG) {
        gpbi_debug_log('Customizer error tracked: ' . print_r($error, true));
    }
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
        return $cached_colors;
    }
    
    // Check GeneratePress availability
    if (!function_exists('generate_get_global_colors')) {
        $cached_colors = [];
        return $cached_colors;
    }
    
    $global_colors = generate_get_global_colors();
    
    if (empty($global_colors)) {
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
    
    // Cache the result in a transient - 12 hour cache
    set_transient($transient_key, $formatted_colors, 12 * HOUR_IN_SECONDS);
    
    // Store in static variable for this page load
    $cached_colors = $formatted_colors;
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
 * Update Beaver Builder global styles with GeneratePress colors
 * This is a one-way sync from GP to BB
 */
function gpbi_update_bb_global_styles() {
    // Check GeneratePress availability
    if (!function_exists('generate_get_global_colors')) {
        return;
    }
    
    // Check BB availability
    if (!class_exists('FLBuilderGlobalStyles')) {
        return;
    }
    
    // Get GeneratePress colors
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return;
    }
    
    // Get current BB settings
    $bb_settings = FLBuilderGlobalStyles::get_settings(false);
    
    // Initialize colors array if it doesn't exist
    if (!isset($bb_settings->colors) || !is_array($bb_settings->colors)) {
        $bb_settings->colors = [];
    }
    
    // First, identify and remove any existing GP colors
    $existing_bb_colors = [];
    foreach ($bb_settings->colors as $bb_color) {
        $is_gp_color = false;
        foreach ($global_colors as $gp_color) {
            if (isset($bb_color['slug']) && 
                sanitize_title(strtolower($bb_color['slug'])) === sanitize_title(strtolower($gp_color['slug']))) {
                $is_gp_color = true;
                break;
            }
        }
        
        if (!$is_gp_color) {
            $existing_bb_colors[] = $bb_color;
        }
    }
    
    // Now format and add the current GP colors
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
    
    // Update BB settings with new GP colors and existing BB colors
    $bb_settings->colors = array_merge($formatted_colors, $existing_bb_colors);
    
    // Save the updated settings
    FLBuilderGlobalStyles::save_settings($bb_settings);
    
    // Clear the force update flag
    delete_transient('gpbi_force_color_update');
    
    // Set a new sync timestamp
    set_transient('gpbi_colors_synced', true, 12 * HOUR_IN_SECONDS);
    
    // Log the update
    gpbi_debug_log('Updated BB global colors: ' . count($formatted_colors) . ' GP colors, ' . count($existing_bb_colors) . ' existing BB colors');
}

/**
 * Clear color caches when customizer settings are saved
 */
function gpbi_clear_color_caches() {
    // Only clear caches if we're actually updating colors
    if (!function_exists('generate_get_global_colors')) {
        return;
    }
    
    // Set force update flag
    set_transient('gpbi_force_color_update', true, 5 * MINUTE_IN_SECONDS);
    
    // Clear other caches
    delete_transient('gpbi_formatted_colors');
    delete_transient('gpbi_colors_synced');
    
    // Run cache clearing after settings save
    add_action('customize_save_after', function() { 
        try {
            // Clear BB's asset cache if possible
            if (class_exists('FLBuilderModel') && method_exists('FLBuilderModel', 'delete_asset_cache_for_all_posts')) {
                FLBuilderModel::delete_asset_cache_for_all_posts();
            }
        } catch (Exception $e) {
            gpbi_track_customizer_error([
                'type' => 'cache_clear_direct',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }, 30);
}

// Only sync when GP colors change
add_action('generate_settings_updated', 'gpbi_clear_color_caches');
add_action('customize_save_after', 'gpbi_clear_color_caches');

// Remove any hooks that might cause reverse sync
remove_filter('fl_wp_core_global_colors', 'gpbi_add_gp_colors_to_bb_palette');
remove_filter('fl_wp_core_global_colors', 'gpbi_remove_duplicate_theme_colors', 20);

/**
 * Register GeneratePress colors with FLPageData for better integration
 */
function gpbi_register_gp_colors_with_flpagedata() {
    if (!class_exists('FLPageData')) {
        return;
    }
    
    // Get GeneratePress colors
    if (!function_exists('generate_get_global_colors')) {
        return;
    }
    
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return;
    }
    
    // Format colors for registration
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
        
        // Register color with FLPageData
        FLPageData::add_site_property('global_color_' . $uid, array(
            'label'  => '<span class="prefix">' . __('GeneratePress -', 'gp-beaver-integration') . '</span>' . 
                      esc_html($label) . 
                      '<span class="swatch" style="background-color:' . 
                      esc_attr($color_value) . ';"></span>',
            'group'  => 'bb',
            'type'   => 'color',
            'getter' => function() use ($color_value) {
                return $color_value;
            },
        ));
    }
}

/**
 * Initialize the integration
 */
function gpbi_initialize_color_integration() {
    // Register colors with FLPageData
    gpbi_register_gp_colors_with_flpagedata();
}

// Run during regular page init to catch front-end usage
add_action('wp', 'gpbi_initialize_color_integration', 20);

// Run in admin to catch color picker in Beaver Builder editor
add_action('admin_init', 'gpbi_initialize_color_integration', 20);

// Also run when GP settings are updated
add_action('customize_save_after', 'gpbi_initialize_color_integration', 20);
add_action('update_option_generate_settings', 'gpbi_initialize_color_integration', 20);

/**
 * Add CSS to ensure color swatches display properly in Global Styles panel
 */
function gpbi_add_global_styles_color_css() {
    // Only load on admin pages where the Beaver Builder Global Styles might be shown
    if (!defined('FL_BUILDER_VERSION')) {
        return;
    }
    
    // Get GeneratePress colors
    if (!function_exists('generate_get_global_colors')) {
        return;
    }
    
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return;
    }
    
    echo '<style type="text/css">';
    
    // Loop through each color and create selector rules for the global styles panel
    foreach ($global_colors as $color) {
        if (empty($color['slug']) || empty($color['color'])) {
            continue;
        }
        
        $slug = sanitize_title(strtolower($color['slug']));
        $color_value = isset($color['color']) ? $color['color'] : '';
        
        // Skip empty colors
        if (empty($color_value)) {
            continue;
        }
        
        // Make sure color value is properly formatted
        if (!preg_match('/^#/', $color_value)) {
            $color_value = '#' . $color_value;
        }
        
        // Super aggressive targeting of color swatches
        echo '.fl-builder-global-settings .fl-color-picker-color[data-slug="' . esc_attr($slug) . '"],';
        echo '.fl-builder-global-settings div[data-slug="' . esc_attr($slug) . '"],';
        
        // Target by UID as well
        $uid = substr(md5($color['slug']), 0, 9);
        echo '.fl-builder-global-settings .fl-color-picker-color[data-uid="' . esc_attr($uid) . '"],';
        echo '.fl-builder-global-settings div[data-uid="' . esc_attr($uid) . '"] {';
        echo 'background-color: ' . esc_attr($color_value) . ' !important;';
        echo 'opacity: 1 !important;';
        echo '}';
    }
    
    // Force all color picker buttons to be visible
    echo '.fl-builder-global-settings .fl-color-picker-color,';
    echo '.fl-color-picker-color,';
    echo '.fl-builder-global-settings button.fl-color-picker-color,';
    echo '.fl-builder-global-settings div.fl-color-picker-color {';
    echo '  opacity: 1 !important;';
    echo '  visibility: visible !important;';
    echo '  display: inline-block !important;';
    echo '}';
    
    echo '</style>';
}
add_action('admin_head', 'gpbi_add_global_styles_color_css', 999);
add_action('wp_head', 'gpbi_add_global_styles_color_css', 999);

/**
 * Enqueue ultra-aggressive color swatch fixer and presets tab activator
 */
function gpbi_enqueue_color_fixer_script() {
    if (!defined('FL_BUILDER_VERSION')) {
        return;
    }
    
    ?>
    <script>
    (function() {
        // Direct DOM manipulation to ensure colors appear immediately
        function forceColorSwatches() {
            // Target all possible color swatch elements
            var colorSwatches = document.querySelectorAll('.fl-builder-global-settings .fl-color-picker-color, .fl-color-picker-color');
            
            // Process each swatch
            for (var i = 0; i < colorSwatches.length; i++) {
                var swatch = colorSwatches[i];
                var color = swatch.getAttribute('data-color') || '';
                var slug = swatch.getAttribute('data-slug') || '';
                var uid = swatch.getAttribute('data-uid') || '';
                
                // If there's a color, force apply it
                if (color) {
                    swatch.style.backgroundColor = color;
                    swatch.style.opacity = '1';
                    swatch.style.visibility = 'visible';
                    swatch.style.display = 'inline-block';
                    
                    // Force browser reflow/repaint
                    void swatch.offsetWidth;
                }
            }
        }
        
        // Run immediately and then every 100ms
        forceColorSwatches();
        setInterval(forceColorSwatches, 100);
        
        // Add mutation observer to catch new swatches being added
        var observer = new MutationObserver(function(mutations) {
            forceColorSwatches();
        });
        
        // Observe the entire document for changes
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-color', 'data-slug', 'data-uid', 'class']
        });
        
        // Activate presets tab on specific events
        document.addEventListener('click', function(e) {
            setTimeout(forceColorSwatches, 10);
            setTimeout(forceColorSwatches, 50);
            setTimeout(forceColorSwatches, 200);
        });
    })();
    </script>
    <?php
}
add_action('admin_footer', 'gpbi_enqueue_color_fixer_script', 9999);
add_action('wp_footer', 'gpbi_enqueue_color_fixer_script', 9999);

/**
 * Add script to activate presets tab in React-based color picker (BB 2.9+)
 * This uses the inline script approach that's proven to work
 */
function gpbi_activate_presets_tab() {
    // Skip if we're using the classic version
    if (!gpbi_is_new_bb_version()) {
        return;
    }
    
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
                $presetsTab.trigger('click');
            }
        }
        
        // Watch for color pickers opening
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
                                // Multiple timeouts to ensure we catch it at different stages of rendering
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
        });
    })(jQuery);
    </script>
    <?php
}

// Add to both footer locations with high priority
add_action('wp_footer', 'gpbi_activate_presets_tab', 999);
add_action('admin_footer', 'gpbi_activate_presets_tab', 999);

/**
 * Modify Redux store data to include GeneratePress colors
 */
function gpbi_add_gp_colors_to_redux_store($response, $request) {
    if (!is_object($request) || !method_exists($request, 'get_route') || strpos($request->get_route(), '/fl-controls/v1/state') === false) {
        return $response;
    }
    
    // Get GeneratePress colors
    if (!function_exists('generate_get_global_colors')) {
        return $response;
    }
    
    $global_colors = generate_get_global_colors();
    if (empty($global_colors)) {
        return $response;
    }
    
    // Format colors for Redux store
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
    
    // Add GeneratePress colors as a set
    $response->color->sets->generatepress = array(
        'name' => 'GeneratePress',
        'colors' => $formatted_colors
    );
    
    return $response;
}

// Add hooks for integration with BB's API
add_filter('rest_pre_echo_response', 'gpbi_add_gp_colors_to_redux_store', 10, 2);
