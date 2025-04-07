<?php
/**
 * Classic Preset Tab Activator
 * Makes the Presets tab visible in Beaver Builder's classic color picker (BB 2.8 and earlier)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add script to activate presets in classic color picker (BB 2.8 and earlier)
 */
function gpbi_activate_classic_presets_tab() {
    // Only load when builder is active
    if (!class_exists('FLBuilderModel') || !FLBuilderModel::is_builder_active()) {
        return;
    }
    
    // Only load for BB 2.8 and earlier
    if (defined('FL_BUILDER_VERSION') && version_compare(FL_BUILDER_VERSION, '2.9', '>=')) {
        return;
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        /**
         * Observes DOM changes and forces the colour picker presets to be visible.
         */
        function enforcePresetVisibility() {
            $('.fl-color-picker-ui').each(function() {
                const presetsWrap = $(this).find('.fl-color-picker-presets-list-wrap');

                if (presetsWrap.length && presetsWrap.css('display') === 'none') {
                    // Make the presets list visible
                    presetsWrap.css('display', 'block');

                    // Ensure proper toggling classes are applied
                    $(this)
                        .find('.fl-color-picker-presets-open-label')
                        .addClass('fl-color-picker-active');
                    $(this)
                        .find('.fl-color-picker-presets-close-label')
                        .removeClass('fl-color-picker-active');
                }
            });
        }

        /**
         * Observe DOM mutations to detect changes in the color picker.
         */
        const observer = new MutationObserver(() => {
            enforcePresetVisibility();
        });

        // Attach observer to the document body
        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });

        // Enforce visibility when the color picker button is clicked
        $(document).on('click', '.fl-color-picker-color', function() {
            enforcePresetVisibility();
        });
        
        // Also try to enforce on initial page load
        enforcePresetVisibility();
        
        // And again after a short delay to catch lazy-loaded pickers
        setTimeout(enforcePresetVisibility, 500);
        setTimeout(enforcePresetVisibility, 1000);
    });
    </script>
    <?php
}

// Add to both footer locations - use a lower priority to ensure it runs after BB scripts
add_action('wp_footer', 'gpbi_activate_classic_presets_tab', 999);
add_action('admin_footer', 'gpbi_activate_classic_presets_tab', 999); 