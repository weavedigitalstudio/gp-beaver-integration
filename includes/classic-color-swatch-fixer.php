<?php
/**
 * Classic Color Swatch Fixer
 * Forces color swatches to display correctly in Global Styles panel for BB 2.8 and earlier
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add CSS and JS to fix color swatches in classic BB interface
 */
function gpbi_classic_color_swatch_fixer() {
    // Only proceed if Beaver Builder is active but not version 2.9+
    if (!defined('FL_BUILDER_VERSION') || 
        (defined('FL_BUILDER_VERSION') && version_compare(FL_BUILDER_VERSION, '2.9', '>='))) {
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
    
    // Output CSS for classic BB color swatches
    ?>
    <style type="text/css">
    /* Force all classic color swatches to be visible */
    .fl-builder-global-settings .fl-color-picker-preset,
    .fl-builder-global-settings .fl-builder-settings-tab-colors .fl-color-picker-preset,
    .fl-color-picker-presets-list .fl-color-picker-preset {
        opacity: 1 !important;
        visibility: visible !important;
        display: inline-block !important;
    }
    
    /* Target specific colors in classic color picker */
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
        
        // Classic BB stores colors differently, target by multiple attributes
        ?>
        .fl-builder-global-settings .fl-color-picker-preset[data-color="<?php echo esc_attr($color_value); ?>"],
        .fl-builder-global-settings .fl-color-picker-preset[data-value="<?php echo esc_attr($color_value); ?>"],
        .fl-builder-global-settings .fl-color-picker-preset[data-default="<?php echo esc_attr($color_value); ?>"],
        .fl-builder-global-settings .fl-builder-settings-tab-colors .fl-color-picker-preset[data-name="<?php echo esc_attr($slug); ?>"] {
            background-color: <?php echo esc_attr($color_value); ?> !important;
        }
        <?php
    endforeach; ?>
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        /**
         * Function to ensure color presets are properly displayed in Global Settings
         */
        function fixClassicColorSwatches() {
            // Various selectors for color swatches in BB 2.8
            var $swatches = $('.fl-builder-global-settings .fl-color-picker-preset, ' +
                               '.fl-builder-global-settings .fl-builder-settings-tab-colors .fl-color-picker-preset, ' +
                               '.fl-color-picker-presets-list .fl-color-picker-preset');
            
            $swatches.each(function() {
                var $this = $(this);
                var color = $this.data('color') || $this.data('value') || $this.attr('data-color') || '';
                
                if (color) {
                    $this.css('background-color', color);
                    $this.css('opacity', 1);
                    $this.css('visibility', 'visible');
                    $this.css('display', 'inline-block');
                }
            });
            
            // Force all preset lists to be visible
            $('.fl-color-picker-presets-list').show();
            $('.fl-color-picker-presets-list-wrap').show().css('display', 'block');
        }
        
        // Run immediately and with delays
        fixClassicColorSwatches();
        setTimeout(fixClassicColorSwatches, 500);
        setTimeout(fixClassicColorSwatches, 1000);
        setTimeout(fixClassicColorSwatches, 2000);
        
        // Set up observer to catch changes
        var observer = new MutationObserver(function() {
            fixClassicColorSwatches();
        });
        
        // Start observing the body
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Also run when the Global Styles button is clicked
        $(document).on('click', '.fl-builder-global-styles-button, .fl-builder-button-secondary', function() {
            fixClassicColorSwatches();
            setTimeout(fixClassicColorSwatches, 500);
            setTimeout(fixClassicColorSwatches, 1000);
            setTimeout(fixClassicColorSwatches, 2000);
        });
        
        // Watch for tab changes in Global Styles
        $(document).on('click', '.fl-builder-settings-tabs a', function() {
            fixClassicColorSwatches();
            setTimeout(fixClassicColorSwatches, 500);
            setTimeout(fixClassicColorSwatches, 1000);
        });
        
        // Additional fix: directly insert inline styles
        function applyColorsDirectly() {
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
                
                // Apply color directly using jQuery
                ?>
                $('.fl-builder-global-settings .fl-color-picker-preset[data-color="<?php echo esc_attr($color_value); ?>"], ' +
                  '.fl-builder-global-settings .fl-color-picker-preset[data-value="<?php echo esc_attr($color_value); ?>"], ' +
                  '.fl-builder-global-settings .fl-color-picker-preset[data-default="<?php echo esc_attr($color_value); ?>"], ' +
                  '.fl-builder-global-settings .fl-builder-settings-tab-colors .fl-color-picker-preset[data-name="<?php echo esc_attr($slug); ?>"]')
                    .css('background-color', '<?php echo esc_attr($color_value); ?>')
                    .css('opacity', 1)
                    .css('visibility', 'visible')
                    .css('display', 'inline-block');
            <?php endforeach; ?>
        }
        
        // Apply direct colors on load and with delays
        applyColorsDirectly();
        setTimeout(applyColorsDirectly, 1000);
        setTimeout(applyColorsDirectly, 2000);
        
        // Run continuous checks for 30 seconds after page load to ensure colors appear
        var checkInterval = setInterval(function() {
            fixClassicColorSwatches();
            applyColorsDirectly();
        }, 2000);
        
        // Stop continuous checks after 30 seconds
        setTimeout(function() {
            clearInterval(checkInterval);
        }, 30000);
    });
    </script>
    <?php
}

// Add to both head and footer locations
add_action('admin_head', 'gpbi_classic_color_swatch_fixer', 999);
add_action('wp_head', 'gpbi_classic_color_swatch_fixer', 999);
add_action('admin_footer', 'gpbi_classic_color_swatch_fixer', 999);

/**
 * Add a direct jQuery hook to fix colors on tab click
 */
function gpbi_add_inline_hook_for_bb28_colors() {
    // Only proceed if Beaver Builder is active but not version 2.9+
    if (!defined('FL_BUILDER_VERSION') || 
        (defined('FL_BUILDER_VERSION') && version_compare(FL_BUILDER_VERSION, '2.9', '>='))) {
        return;
    }
    
    ?>
    <script>
    // Add a hook to the FLBuilder object if it exists to catch Global Styles panel opening
    if (typeof FLBuilder !== 'undefined') {
        var originalShowGlobalSettings = FLBuilder.showGlobalSettings;
        
        FLBuilder.showGlobalSettings = function() {
            // Call original function
            originalShowGlobalSettings.apply(this, arguments);
            
            // Then fix the color swatches with a delay
            setTimeout(function() {
                if (typeof jQuery !== 'undefined') {
                    // Find all color presets and apply backgrounds
                    jQuery('.fl-builder-global-settings .fl-color-picker-preset').each(function() {
                        var $this = jQuery(this);
                        var color = $this.data('color') || $this.data('value') || $this.attr('data-color') || '';
                        
                        if (color) {
                            $this.css('background-color', color);
                            $this.css('opacity', 1);
                            $this.css('visibility', 'visible');
                            $this.css('display', 'inline-block');
                        }
                    });
                }
            }, 500);
        };
    }
    </script>
    <?php
}

// Add the hook override in admin footer
add_action('admin_footer', 'gpbi_add_inline_hook_for_bb28_colors', 999); 