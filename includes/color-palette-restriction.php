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
	// Only add when Beaver Builder is active
	if (!class_exists('FLBuilderModel') || !FLBuilderModel::is_builder_active()) {
		return;
	}

	// Add inline CSS to hide the add to palette button
	wp_add_inline_style(
		'fl-builder-layout', 
		'.fl-color-picker-ui .fl-color-picker-preset-add,
		 .fl-color-picker-ui .fl-color-picker-presets-list .fl-color-picker-preset-remove {
			display: none !important;
		 }'
	);
}
add_action('wp_enqueue_scripts', 'gpbi_restrict_color_palette', 100);
add_action('admin_enqueue_scripts', 'gpbi_restrict_color_palette', 100);
