<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Shortcodes;

defined('ABSPATH') || exit;

/**
 * Calculate relative luminance of a hex colour (0 = black, 1 = white).
 */
function luminance(string $hexcolor): float {
    $hex = ltrim($hexcolor, '#');
    $r   = hexdec(substr($hex, 0, 2));
    $g   = hexdec(substr($hex, 2, 2));
    $b   = hexdec(substr($hex, 4, 2));

    return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
}

/**
 * Calculate whether text should be light or dark based on background colour.
 */
function readable_text_color(string $hexcolor): string {
    return luminance($hexcolor) > 0.5 ? '#000000' : '#ffffff';
}

/**
 * Check whether a colour is light enough to need a border against white.
 */
function needs_light_border(string $hexcolor): bool {
    return luminance($hexcolor) > 0.85;
}

/**
 * Render the [gp_global_color_grid] shortcode.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function render_color_grid(array|string $atts = []): string {
    $attributes = shortcode_atts(
        [
            'columns' => '4',
            'names'   => 'true',
            'values'  => 'true',
        ],
        $atts,
    );

    $show_names  = filter_var($attributes['names'], FILTER_VALIDATE_BOOLEAN);
    $show_values = filter_var($attributes['values'], FILTER_VALIDATE_BOOLEAN);
    $columns     = max(1, absint($attributes['columns']));

    if (!function_exists('generate_get_option')) {
        return '<p>GeneratePress not active</p>';
    }

    // Register grid styles.
    if (!wp_style_is('gp-color-grid-styles')) {
        wp_register_style('gp-color-grid-styles', false, [], GPBI_VERSION);
        wp_add_inline_style('gp-color-grid-styles', '
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
            .gp-color-box.has-light-bg {
                border: 1px solid #d0d0d0;
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
        ');
        wp_enqueue_style('gp-color-grid-styles');
    }

    // Get colours directly from GP settings (different data shape to BB formatter).
    static $global_colors = null;
    if ($global_colors === null) {
        $gp_settings   = get_option('generate_settings');
        $global_colors = $gp_settings['global_colors'] ?? [];
    }

    ob_start();

    echo '<section class="gp-style-guide-alt">';
    echo '<h2>' . esc_html__('Global Colour Palette', 'gp-beaver-integration') . '</h2>';
    echo '<div class="gp-color-grid-alt">';

    if (!empty($global_colors)) {
        foreach ($global_colors as $color_data) {
            if (empty($color_data['color']) || empty($color_data['name'])) {
                continue;
            }

            $var_name    = '--' . sanitize_title(strtolower($color_data['name']));
            $label       = esc_html($color_data['name']);
            $hex         = esc_attr($color_data['color']);
            $text_color  = readable_text_color($hex);
            $white_class = needs_light_border($hex) ? ' has-light-bg' : '';

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
                $white_class,
            );
        }
    } else {
        echo '<p>No global colours found in GeneratePress Customizer colour settings.</p>';
    }

    echo '</div>';
    echo '</section>';

    return ob_get_clean();
}

add_shortcode('gp_global_color_grid', __NAMESPACE__ . '\\render_color_grid');
