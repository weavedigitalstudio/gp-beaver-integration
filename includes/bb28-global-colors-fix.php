<?php
/**
 * BB 2.8 Global Styles Color Swatches Fix
 * Uses a very direct approach to force color swatches to display in BB 2.8 Global Styles panel
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Direct HTML injection for BB 2.8 Global Styles color swatches
 */
function gpbi_bb28_direct_color_fix() {
    // Only proceed if Beaver Builder is active but not version 2.9+
    if (!defined('FL_BUILDER_VERSION') || 
        (defined('FL_BUILDER_VERSION') && version_compare(FL_BUILDER_VERSION, '2.9', '>='))) {
        return;
    }
    
    // Only run in admin where Global Styles panel appears
    if (!is_admin()) {
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
    
    // Prepare colors array for JavaScript
    $colors_data = array();
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
        
        // Add to colors data array
        $colors_data[] = array(
            'slug' => $slug,
            'color' => $color_value,
            'label' => isset($color['name']) ? $color['name'] : $color['slug']
        );
    }
    
    // Convert to JSON for JavaScript
    $colors_json = json_encode($colors_data);
    
    // Super direct color injection method
    ?>
    <style type="text/css">
    /* Force visibility of color swatches */
    .fl-builder-global-settings .fl-builder-settings-tab-colors .fl-color-picker-preset,
    .fl-builder-global-settings .fl-color-picker-presets-list .fl-color-picker-preset,
    .fl-builder-global-settings [data-tab="colors"] .fl-color-picker-preset {
        opacity: 1 !important;
        visibility: visible !important;
        display: inline-block !important;
    }
    
    /* Explicitly set background colors based on data attributes */
    <?php foreach ($global_colors as $color) : 
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
        
        // Create more specific and aggressive CSS targeting
    ?>
    /* Selector for color: <?php echo esc_html($color['slug']); ?> */ 
    .fl-builder-global-settings [data-tab="colors"] [data-name="<?php echo esc_attr($slug); ?>"],
    .fl-builder-global-settings [data-tab="colors"] [data-color="<?php echo esc_attr($color_value); ?>"],
    .fl-builder-global-settings [data-tab="colors"] .fl-color-picker-preset[data-color="<?php echo esc_attr($color_value); ?>"],
    .fl-builder-global-settings .fl-color-picker-presets-list [data-color="<?php echo esc_attr($color_value); ?>"] {
        background-color: <?php echo esc_attr($color_value); ?> !important;
    }
    <?php endforeach; ?>
    </style>
    
    <script>
    (function() {
        // Store the colors data for use in our fix
        var gpColors = <?php echo $colors_json; ?>;
        
        // Function to directly modify DOM elements
        function injectColorSwatchStyles() {
            // Find the color picker elements in the BB 2.8 Global Styles panel
            var colorPickers = document.querySelectorAll('.fl-builder-global-settings [data-tab="colors"] .fl-color-picker-preset, .fl-color-picker-presets-list .fl-color-picker-preset');
            
            if (colorPickers.length === 0) {
                // Try again with a more general selector
                colorPickers = document.querySelectorAll('.fl-builder-global-settings .fl-color-picker-preset');
            }
            
            // Loop through each color picker element
            for (var i = 0; i < colorPickers.length; i++) {
                var colorPicker = colorPickers[i];
                var dataColor = colorPicker.getAttribute('data-color');
                var dataName = colorPicker.getAttribute('data-name');
                
                // Find the matching color from our GeneratePress colors
                for (var j = 0; j < gpColors.length; j++) {
                    var gpColor = gpColors[j];
                    
                    // Match by color value or slug
                    if ((dataColor && dataColor === gpColor.color) || 
                        (dataName && dataName === gpColor.slug)) {
                        
                        // Directly set the style attribute with !important
                        colorPicker.setAttribute('style', 
                            'background-color: ' + gpColor.color + ' !important; ' +
                            'opacity: 1 !important; ' +
                            'visibility: visible !important; ' +
                            'display: inline-block !important;');
                        
                        // Also add data-color attribute if it doesn't exist
                        if (!dataColor) {
                            colorPicker.setAttribute('data-color', gpColor.color);
                        }
                        
                        // Add title attribute for easier identification
                        colorPicker.setAttribute('title', gpColor.label + ': ' + gpColor.color);
                        
                        break;
                    }
                }
            }
        }
        
        // Function to run when Global Settings is shown
        function onGlobalSettingsShown() {
            // Run multiple times with increasing delays to catch all color swatches
            injectColorSwatchStyles();
            setTimeout(injectColorSwatchStyles, 100);
            setTimeout(injectColorSwatchStyles, 500);
            setTimeout(injectColorSwatchStyles, 1000);
            setTimeout(injectColorSwatchStyles, 2000);
        }
        
        // Override the showGlobalSettings function to run our code when it's called
        if (typeof FLBuilder !== 'undefined' && FLBuilder.showGlobalSettings) {
            var originalShowGlobalSettings = FLBuilder.showGlobalSettings;
            
            FLBuilder.showGlobalSettings = function() {
                // Call original function
                originalShowGlobalSettings.apply(this, arguments);
                
                // Run our code
                onGlobalSettingsShown();
            };
        }
        
        // Also set up a mutation observer to watch for changes
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes && mutation.addedNodes.length) {
                        for (var i = 0; i < mutation.addedNodes.length; i++) {
                            // If a node was added, check if it's or contains the global settings panel
                            var node = mutation.addedNodes[i];
                            if (node.nodeType === 1) { // Element node
                                var isGlobalSettings = node.classList && 
                                                      node.classList.contains('fl-builder-global-settings');
                                var hasGlobalSettings = node.querySelector && 
                                                      node.querySelector('.fl-builder-global-settings');
                                
                                if (isGlobalSettings || hasGlobalSettings) {
                                    onGlobalSettingsShown();
                                }
                            }
                        }
                    }
                });
            });
            
            // Start observing document.body for changes
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        // Watch for tab changes in Global Settings
        document.addEventListener('click', function(e) {
            // Check if the click was on a tab in the Global Settings panel
            var target = e.target;
            while (target && target !== document) {
                if (target.classList && 
                    target.classList.contains('fl-builder-settings-tabs') && 
                    target.closest('.fl-builder-global-settings')) {
                    
                    // Run our fix after a delay to allow the tab to change
                    setTimeout(injectColorSwatchStyles, 100);
                    setTimeout(injectColorSwatchStyles, 500);
                    break;
                }
                target = target.parentNode;
            }
        }, true);
        
        // Also run on document ready to catch any existing color swatches
        document.addEventListener('DOMContentLoaded', onGlobalSettingsShown);
        
        // Run every 5 seconds to ensure the global styles panel is always properly colored
        // Will run for a total of 5 minutes after page load
        var checkInterval = setInterval(injectColorSwatchStyles, 5000);
        setTimeout(function() {
            clearInterval(checkInterval);
        }, 300000); // 5 minutes
    })();
    </script>
    <?php
}

// Add with high priority to both admin_head and admin_footer
add_action('admin_head', 'gpbi_bb28_direct_color_fix', 99999);
add_action('admin_footer', 'gpbi_bb28_direct_color_fix', 99999);

// Add a hook to apply colors immediately on page load by modifying the FLBuilderGlobalStyles._init method
function gpbi_add_bb28_init_override() {
    // Only proceed if Beaver Builder is active but not version 2.9+
    if (!defined('FL_BUILDER_VERSION') || 
        (defined('FL_BUILDER_VERSION') && version_compare(FL_BUILDER_VERSION, '2.9', '>='))) {
        return;
    }
    
    // Only run in admin where Global Styles panel appears
    if (!is_admin()) {
        return;
    }
    
    ?>
    <script>
    (function($) {
        // Hook into FLBuilderGlobalStyles initialization if it exists
        if (typeof FLBuilderGlobalStyles !== 'undefined') {
            // Store original init function
            var originalInit = FLBuilderGlobalStyles._init;
            
            // Override init function
            FLBuilderGlobalStyles._init = function() {
                // Call original init
                originalInit.apply(this, arguments);
                
                // Extra code to force color swatches
                var applyColorToSwatches = function() {
                    $('.fl-builder-global-settings .fl-color-picker-preset').each(function() {
                        var $swatch = $(this);
                        var color = $swatch.attr('data-color');
                        
                        if (color) {
                            // Force swatch to show color
                            $swatch.css({
                                'background-color': color,
                                'opacity': '1',
                                'visibility': 'visible',
                                'display': 'inline-block'
                            });
                            
                            // Simulate click to ensure BB's internal state is updated
                            setTimeout(function() {
                                // Similar effect to clicking without actually triggering click
                                $swatch.addClass('fl-color-picker-active');
                            }, 100);
                        }
                    });
                };
                
                // Run immediately and with delays
                applyColorToSwatches();
                setTimeout(applyColorToSwatches, 500);
                setTimeout(applyColorToSwatches, 1000);
                
                // Also hook into tab clicks, as they often reset the swatches
                $('body').on('click', '.fl-builder-settings-tabs a', function() {
                    setTimeout(applyColorToSwatches, 100);
                    setTimeout(applyColorToSwatches, 500);
                });
                
                // Hook into the showGlobalSettings function too
                var originalShowGlobalSettings = FLBuilder.showGlobalSettings;
                FLBuilder.showGlobalSettings = function() {
                    // Call original function
                    originalShowGlobalSettings.apply(this, arguments);
                    
                    // Apply colors after global settings are shown
                    setTimeout(applyColorToSwatches, 100);
                    setTimeout(applyColorToSwatches, 500);
                    setTimeout(applyColorToSwatches, 1000);
                };
            };
        }
    })(jQuery);
    </script>
    <?php
}
add_action('admin_footer', 'gpbi_add_bb28_init_override', 99999);

// Force colors to be visible through CSS - focusing specifically on the data-color attribute
function gpbi_add_bb28_forced_css_colors() {
    // Only proceed if Beaver Builder is active but not version 2.9+
    if (!defined('FL_BUILDER_VERSION') || 
        (defined('FL_BUILDER_VERSION') && version_compare(FL_BUILDER_VERSION, '2.9', '>='))) {
        return;
    }
    
    // Only run in admin where Global Styles panel appears
    if (!is_admin()) {
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
    
    ?>
    <style>
    /* More specific styles for BB 2.8 panels */
    .fl-lightbox .fl-builder-settings .fl-color-picker-ui .fl-color-picker-preset {
        opacity: 1 !important;
        visibility: visible !important;
        display: inline-block !important;
    }
    
    <?php foreach ($global_colors as $color) : 
        if (empty($color['slug']) || empty($color['color'])) {
            continue;
        }
        
        $color_value = isset($color['color']) ? $color['color'] : '';
        
        // Skip empty colors
        if (empty($color_value)) {
            continue;
        }
        
        // Make sure color value is properly formatted
        if (!preg_match('/^#/', $color_value)) {
            $color_value = '#' . $color_value;
        }
    ?>
    /* Force color for <?php echo esc_html($color['slug']); ?> */
    .fl-lightbox .fl-builder-settings [data-color="<?php echo esc_attr($color_value); ?>"] {
        background-color: <?php echo esc_attr($color_value); ?> !important;
    }
    <?php endforeach; ?>
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Create a direct function to "click" on all color swatches to force BB to render them
        function simulateColorSwatchClicks() {
            $('.fl-builder-global-settings .fl-color-picker-preset').each(function() {
                var $swatch = $(this);
                var color = $swatch.attr('data-color');
                
                if (color) {
                    // Apply color directly
                    $swatch.css('background-color', color);
                    
                    // Force a brief selection class to make BB recognize it
                    $swatch.addClass('fl-color-picker-active');
                    setTimeout(function() {
                        $swatch.removeClass('fl-color-picker-active');
                    }, 50);
                }
            });
        }
        
        // Run on document ready and after a delay
        simulateColorSwatchClicks();
        setTimeout(simulateColorSwatchClicks, 500);
        
        // Also simulate clicks whenever the settings tab is opened
        $(document).on('click', '.fl-builder-global-styles-button', function() {
            setTimeout(simulateColorSwatchClicks, 500);
            setTimeout(simulateColorSwatchClicks, 1000);
        });
    });
    </script>
    <?php
}
add_action('admin_head', 'gpbi_add_bb28_forced_css_colors', 99999); 