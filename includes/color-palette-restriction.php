<?php
/**
 * GP Beaver Integration - Color Palette Restriction
 *
 * Hides the "Add to Palette" button in Beaver Builder's color picker
 * to enforce the use of GeneratePress Global Colors.
 *
 * @package GP_Beaver_Integration
 * @since 0.6.0
 */

// Prevent direct access to this file
if (!defined("ABSPATH")) {
	exit();
}

/**
 * Enqueue CSS to hide color palette add button in Beaver Builder
 */
function gpbi_restrict_color_palette() {
	// Only continue if Beaver Builder exists and is active
	if (!class_exists('FLBuilderModel') || !FLBuilderModel::is_builder_active()) {
		return;
	}

	// Check if color restrictions are enabled
	$settings = get_option('gpbi_settings', array('restrict_colors' => 1));
	if (!isset($settings['restrict_colors']) || $settings['restrict_colors'] != 1) {
		return;
	}

	// CSS to hide undesired elements
	$css = '
		/* Hide the legacy color picker add/remove buttons */
		.fl-color-picker-ui .fl-color-picker-preset-add,
		.fl-color-picker-ui .fl-color-picker-presets-list .fl-color-picker-preset-remove,
		
		/* Hide the "Saved Colors" section completely */
		.fl-controls-swatch-group.fl-appearance-swatches,
		
		/* Hide the add color button in the toolbar */
		.fl-color-picker-toolbar > div:last-child > button {
			display: none !important;
		}
	';
	
	// Add directly to avoid any stylesheet loading issues
	echo '<style id="gpbi-color-restrict">' . $css . '</style>';
}

// Use wp_footer for frontend builder
add_action('wp_footer', 'gpbi_restrict_color_palette', 100);
add_action('admin_footer', 'gpbi_restrict_color_palette', 100);
