<?php
/**
 * Color Swatch Fixer
 * Forces color swatches to display correctly in Global Styles panel
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add basic CSS for color swatches and inline script to force them to display
 */
function gpbi_basic_color_swatch_fixer() {
    // NEW: Check if BB editor is active *inside* the function
    if (!class_exists('FLBuilderModel') || !FLBuilderModel::is_builder_active()) {
        return;
    }

    // Only proceed if Beaver Builder is active (redundant check, keep for safety?)
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
    
    // Output basic inline CSS
    ?>
    <style type="text/css">
    /* Force all color picker buttons to be visible */
    .fl-builder-global-settings .fl-color-picker-color {
        opacity: 1 !important;
        visibility: visible !important;
        display: inline-block !important;
    }
    
    /* Target specific colors */
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
        
        // Create simple but specific CSS rule for slug attribute
        ?>
        .fl-builder-global-settings [data-slug="<?php echo esc_attr($slug); ?>"] {
            background-color: <?php echo esc_attr($color_value); ?> !important;
        }
        <?php
        
        // Also create a rule for uid attribute (BB 2.9+)
        $uid = substr(md5($slug), 0, 9);
        ?>
        .fl-builder-global-settings [data-uid="<?php echo esc_attr($uid); ?>"] {
            background-color: <?php echo esc_attr($color_value); ?> !important;
        }
        <?php
    endforeach; ?>
    </style>
    
    <script>
    // Simple function to force color swatches to display correctly
    (function() {
        // Function to apply colors to swatches
        function fixColorSwatches() {
            // Get all color swatches in Global Styles panel
            var swatches = document.querySelectorAll('.fl-builder-global-settings .fl-color-picker-color');
            
            // Loop through each swatch
            for (var i = 0; i < swatches.length; i++) {
                var swatch = swatches[i];
                var color = swatch.getAttribute('data-color');
                
                // Set background color if we have a color value
                if (color) {
                    swatch.style.backgroundColor = color;
                    swatch.style.opacity = '1';
                }
            }
        }
        
        // Run once immediately
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            // DOM already ready, run immediately
            fixColorSwatches();
        } else {
            // Wait for DOM to be ready
            document.addEventListener('DOMContentLoaded', fixColorSwatches);
        }
        
        // Run again after a short delay to catch any lazy-loaded elements
        setTimeout(fixColorSwatches, 500);
        setTimeout(fixColorSwatches, 1500);
        
        // Also run when clicking on the Global Styles button
        document.addEventListener('click', function(event) {
            if (event.target.matches('.fl-builder-global-styles-button') || 
                event.target.closest('.fl-builder-global-styles-button')) {
                // Global Styles button clicked, run with delays
                setTimeout(fixColorSwatches, 200);
                setTimeout(fixColorSwatches, 700);
                setTimeout(fixColorSwatches, 1500);
            }
        });
    })();
    </script>
    <?php
}

/**
 * Alternate version that runs in admin_footer to catch later-loaded elements
 */
function gpbi_admin_footer_color_swatch_fixer() {
    // NEW: Check if BB editor is active *inside* the function
    if (!class_exists('FLBuilderModel') || !FLBuilderModel::is_builder_active()) {
        return;
    }

    // Only proceed if Beaver Builder is active (redundant check, keep for safety?)
    if (!defined('FL_BUILDER_VERSION') || !is_admin()) {
        return;
    }
    
    ?>
    <script>
    // Second attempt to fix color swatches, running in admin_footer
    (function() {
        function fixAllColorSwatchesAgain() {
            var swatches = document.querySelectorAll('.fl-builder-global-settings .fl-color-picker-color');
            for (var i = 0; i < swatches.length; i++) {
                var swatch = swatches[i];
                var color = swatch.getAttribute('data-color');
                if (color) {
                    if (window.requestAnimationFrame) {
                        window.requestAnimationFrame(function() { 
                            swatch.style.backgroundColor = color;
                            swatch.style.opacity = '1';
                        });
                    } else {
                        swatch.style.backgroundColor = color;
                        swatch.style.opacity = '1';
                    }
                }
            }
        }
        
        setTimeout(fixAllColorSwatchesAgain, 300);
        setTimeout(fixAllColorSwatchesAgain, 800);
        setTimeout(fixAllColorSwatchesAgain, 2000);
    })();
    </script>
    <?php
}

// Add hooks directly - the check is now inside the functions
// Add to head locations with high priority
add_action('admin_head', 'gpbi_basic_color_swatch_fixer', 999);
add_action('wp_head', 'gpbi_basic_color_swatch_fixer', 999);

// Also add to footer for a second attempt
add_action('admin_footer', 'gpbi_admin_footer_color_swatch_fixer', 999); 