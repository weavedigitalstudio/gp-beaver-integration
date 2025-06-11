<?php
/**
 * GP Beaver Integration - Color Grid Shortcode
 *
 * This file provides the shortcode functionality for displaying GeneratePress color palettes
 * in a visual grid format. It's particularly useful for style guides and documentation.
 *
 * The shortcode [gp_global_color_grid] creates a responsive grid that shows:
 * - Color swatches using your GeneratePress Global Colors
 * - Color names from your palette
 * - CSS variable names for developer reference
 * - Hex color codes
 *
 *
 * @package GP_Beaver_Integration
 * @since 0.4.0
 * @version 1.0.8  Color Grid Component
 * @changelog
 * 1.0.8 - Text color fixed to match
 * 1.0.7 - Improved styling with better padding, spacing and font sizing
 * 1.0.6 - Fixed GeneratePress color palette integration with WordPress Admin
 * 0.6.0 - Updated naming to match new plugin name (GP Beaver Integration)
 * 0.4.2 - Updated label styling to use p tag with new font sizes
 * 0.4.1 - Added white background detection and border
 * 0.4.0 - Initial shortcode implementation
 */

// Prevent direct access to this file
if (!defined("ABSPATH")) {
	exit();
}

/**
 * Calculates whether text should be light or dark based on background color
 * Uses W3C recommendations for contrast calculations to ensure readability
 *
 * @param string $hexcolor The hex color code to analyze (with or without #)
 * @return string Returns either black (#000000) or white (#ffffff) hex code
 */
function gpbi_get_readable_text_color($hexcolor)
{
	$hex = ltrim($hexcolor, "#");
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	// Calculate relative luminance using W3C formula
	$luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
	return $luminance > 0.5 ? "#000000" : "#ffffff";
}

/**
 * Renders the color grid display
 * Creates a responsive grid showing all GeneratePress Global Colors
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output of the color grid
 */
function gpbi_render_color_grid($atts)
{
	// Parse shortcode attributes
	$attributes = shortcode_atts(
		array(
			'columns' => '4',
			'names'   => 'true',
			'values'  => 'true',
		),
		$atts
	);
	
	// Convert string values to appropriate types
	$show_names = filter_var($attributes['names'], FILTER_VALIDATE_BOOLEAN);
	$show_values = filter_var($attributes['values'], FILTER_VALIDATE_BOOLEAN);
	$columns = absint($attributes['columns']);
	
	// Validate minimum values
	$columns = max(1, $columns); // Minimum 1 column
	
	// Bail early if GeneratePress isn't active
	if (!function_exists("generate_get_option")) {
		return "<p>GeneratePress not active</p>";
	}

	// Register and enqueue styles if not already done
	if (!wp_style_is("gp-color-grid-styles")) {
		wp_register_style("gp-color-grid-styles", false, [], GPBI_VERSION);

		// Define our grid styles - keeping original styling with optional overrides
		wp_add_inline_style(
			"gp-color-grid-styles",
			'
			.gp-color-grid-alt {
				display: grid;
				grid-template-columns: repeat(' . $columns . ', 1fr);
				gap: 20px;
				margin-block: 40px;
			}
			
			@media (max-width: 768px) {
				.gp-color-grid-alt {
					grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
				}
			}

			html .gp-color-box {
				padding: 40px 20px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				text-align: center;
			}

			.gp-color-box.has-white-bg {
				border: 1px solid #000;
			}

			.gp-color-info-alt {
				display: flex;
				flex-direction: column;
				gap: 0.6em;
				align-items: center;
			}

			.gpbi-color-label {
				font-weight: 700;
				margin: 0;
				color: inherit;
			}

			html .gp-color-var-alt,
			html .gp-color-hex-alt {
				font-family: ui-monospace, Menlo, Monaco, "Courier New", monospace;
				font-size: 80%;
				color: inherit;
				padding: 0;
			}
		'
		);
		wp_enqueue_style("gp-color-grid-styles");
	}

	// Get GeneratePress settings once and cache the result
	static $global_colors = null;
	if ($global_colors === null) {
		$gp_settings = get_option("generate_settings");
		$global_colors = isset($gp_settings["global_colors"])
			? $gp_settings["global_colors"]
			: [];
	}

	// Start output buffering for clean return
	ob_start();

	echo '<section class="gp-style-guide-alt">';
	echo "<h2>" . esc_html__('Global Color Palette', 'gp-beaver-integration') . "</h2>";
	echo '<div class="gp-color-grid-alt">';

	if (!empty($global_colors)) {
		foreach ($global_colors as $color_slug => $color_data) {
			// Skip if we're missing required color data
			if (empty($color_data["color"]) || empty($color_data["name"])) {
				continue;
			}

			// Prepare our display values
			$var_name = "--" . sanitize_title(strtolower($color_data["name"]));
			$label = esc_html($color_data["name"]);
			$hex = esc_attr($color_data["color"]);
			$text_color = gpbi_get_readable_text_color($hex);

			// Check if the color is white (case insensitive)
			$is_white = strtolower($hex) === "#ffffff";
			$white_class = $is_white ? " has-white-bg" : "";

			// Output the color box with all its information
			printf(
				'<article class="gp-color-box%5$s" style="background-color: var(%1$s)">
					<div class="gp-color-info-alt" style="color: %4$s">
						%2$s
						%3$s
					</div>
				</article>',
				$var_name,
				$show_names ? '<p class="gpbi-color-label">' . $label . '</p>' : '',
				$show_values ? '<code class="gp-color-var-alt">var(' . $var_name . ')</code><code class="gp-color-hex-alt">' . $hex . '</code>' : '',
				$text_color,
				$white_class
			);
		}
	} else {
		echo "<p>No global colors found in GeneratePress Customizer color settings.</p>";
	}

	echo "</div>";
	echo "</section>";

	return ob_get_clean();
}

// Register our shortcode with WordPress
add_shortcode("gp_global_color_grid", "gpbi_render_color_grid");
