<?php
/**
 * Preset Tab Activator
 * Makes the Presets tab the default active tab in Beaver Builder's color picker
 * Based on the confirmed working code from BB Color Presets First plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add script to activate presets tab in React-based color picker
 * This uses the exact same inline script approach from the working plugin
 */
function gpbi_activate_presets_tab_inline() {
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

// Add to both footer locations - use exact same priority as the working plugin
add_action('wp_footer', 'gpbi_activate_presets_tab_inline', 999);
add_action('admin_footer', 'gpbi_activate_presets_tab_inline', 999); 