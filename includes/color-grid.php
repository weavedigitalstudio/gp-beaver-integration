<?php
/**
 * Central Color Palette - Color Grid Shortcode
 *
 * This file provides the shortcode functionality for displaying Central Color Palette colors
 * in a visual grid format. It's particularly useful for style guides and documentation.
 *
 * The shortcode [ccp_color_grid] creates a responsive grid that shows:
 * - Color swatches using your Central Color Palette colors
 * - Color names from your palette
 * - CSS variable names for developer reference
 * - Hex color codes
 *
 * @package Central_Color_Palette
 * @since 1.0.0
 * @version 1.0.7  Color Grid Component
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
function ccp_get_readable_text_color($hexcolor)
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
 * Creates a responsive grid showing all Central Color Palette colors
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output of the color grid
 */
function ccp_render_color_grid($atts)
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

	// Register and enqueue styles if not already done
	if (!wp_style_is("ccp-color-grid-styles")) {
		wp_register_style("ccp-color-grid-styles", false, [], "1.0.7");

		// Define our grid styles - matching GP Beaver Integration styling
		wp_add_inline_style(
			"ccp-color-grid-styles",
			'
			.ccp-color-grid-alt {
				display: grid;
				grid-template-columns: repeat(' . $columns . ', 1fr);
				gap: 20px;
				margin-block: 40px;
			}
			
			@media (max-width: 768px) {
				.ccp-color-grid-alt {
					grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
				}
			}

			html .ccp-color-box {
				padding: 40px 20px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				text-align: center;
			}

			.ccp-color-box.has-white-bg {
				border: 1px solid #000;
			}

			.ccp-color-info-alt {
				display: flex;
				flex-direction: column;
				gap: 0.6em;
				align-items: center;
			}

			.ccp-color-label {
				font-weight: 700;
				margin: 0;
				color: inherit;
			}

			html .ccp-color-var-alt,
			html .ccp-color-hex-alt {
				font-family: ui-monospace, Menlo, Monaco, "Courier New", monospace;
				font-size: 80%;
				color: inherit;
				padding: 0;
			}
		'
		);
		wp_enqueue_style("ccp-color-grid-styles");
	}

	// Get Central Color Palette settings once and cache the result
	static $palette = null;
	if ($palette === null) {
		$palette = get_option("kt_color_grid_palette");
		if (empty($palette) || !is_array($palette)) {
			$palette = [];
		}
	}

	// Start output buffering for clean return
	ob_start();

	echo '<section class="ccp-style-guide-alt">';
	echo "<h2>" . esc_html__('Global Color Palette', 'central-color-palette') . "</h2>";
	echo '<div class="ccp-color-grid-alt">';

	if (!empty($palette)) {
		foreach ($palette as $color) {
			// Skip if we're missing required color data
			if (empty($color["hex"]) || empty($color["name"])) {
				continue;
			}

			// Prepare our display values
			$var_name = "--" . sanitize_title(strtolower($color["variable"]));
			$label = esc_html($color["name"]);
			$hex = esc_attr($color["hex"]);
			$text_color = ccp_get_readable_text_color($hex);

			// Check if the color is white (case insensitive)
			$is_white = strtolower($hex) === "#ffffff";
			$white_class = $is_white ? " has-white-bg" : "";

			// Output the color box with all its information
			printf(
				'<article class="ccp-color-box%5$s" style="background-color: %3$s">
					<div class="ccp-color-info-alt" style="color: %4$s">
						%1$s
						%2$s
					</div>
				</article>',
				$show_names ? '<p class="ccp-color-label">' . $label . '</p>' : '',
				$show_values ? '<code class="ccp-color-var-alt">var(' . $var_name . ')</code><code class="ccp-color-hex-alt">' . $hex . '</code>' : '',
				$hex,
				$text_color,
				$white_class
			);
		}
	} else {
		echo "<p>No colors found in Central Color Palette settings.</p>";
	}

	echo "</div>";
	echo "</section>";

	return ob_get_clean();
}

// Register our shortcode with WordPress
add_shortcode("ccp_color_grid", "ccp_render_color_grid");
