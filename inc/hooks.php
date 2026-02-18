<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Hooks;

defined('ABSPATH') || exit;

/**
 * Activation check — deactivates if GP or BB is missing.
 */
function on_activation(): void {
    $theme             = wp_get_theme();
    $is_generatepress  = 'GeneratePress' === $theme->get('Name')
                      || 'generatepress' === $theme->get_template();

    if (!$is_generatepress || !class_exists('FLBuilder')) {
        deactivate_plugins(plugin_basename(GPBI_FILE));
        $missing = !$is_generatepress ? 'GeneratePress theme' : 'Beaver Builder';
        wp_die(
            "This plugin requires both GeneratePress theme and Beaver Builder to be installed and active. Missing: {$missing}",
            'Plugin Activation Error',
            ['back_link' => true],
        );
    }
}

/**
 * Add "Settings" link on the Plugins page.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function add_settings_link(array $links): array {
    $url  = admin_url('options-general.php?page=gp-beaver-integration');
    $link = '<a href="' . esc_url($url) . '">' . __('Settings', 'gp-beaver-integration') . '</a>';
    array_unshift($links, $link);
    return $links;
}

/**
 * Initialise the GitHub updater in admin context.
 */
function init_github_updater(): void {
    if (is_admin()) {
        \GPBeaverIntegration\Updater\GitHubUpdater::init(GPBI_FILE);
    }
}

/**
 * Inject GP Global Colours into the WordPress Iris colour picker palette.
 *
 * This makes GP colours available in any admin plugin that uses the
 * standard wp-color-picker (e.g. Ultimate Dashboard, widget settings).
 * Hooked to admin_print_footer_scripts at priority 99 so it runs after
 * all footer scripts (including wp-color-picker) have been printed.
 */
function inject_iris_palette(): void {
    if (!wp_script_is('wp-color-picker', 'done')) {
        return;
    }

    if (!function_exists('generate_get_global_colors')) {
        return;
    }

    $global_colors = \generate_get_global_colors();
    if (empty($global_colors)) {
        return;
    }

    $palette = [];
    foreach ($global_colors as $color) {
        if (!empty($color['color'])) {
            $value = $color['color'];
            if (!str_starts_with($value, '#')) {
                $value = '#' . $value;
            }
            $palette[] = $value;
        }
    }

    if (empty($palette)) {
        return;
    }

    $palette_json = wp_json_encode($palette);

    ?>
    <style>
    /* GP Global Colours — larger palette swatches in the Iris picker. */
    .iris-palette-container { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px 0; }
    .iris-palette-container .iris-palette { width: 20px !important; height: 20px !important; margin: 0 !important; border-radius: 3px; }
    .iris-picker.iris-border { padding-bottom: 70px !important; }
    </style>
    <script>
    (function($) {
        if (!$ || !$.wp || !$.wp.wpColorPicker) return;
        var palette = <?php echo $palette_json; ?>;

        // Set default palette for all future pickers.
        $.wp.wpColorPicker.prototype.options.palettes = palette;

        // Update any already-initialised pickers.
        $('input.wp-color-picker').each(function() {
            if ($(this).data('a8cIris')) {
                $(this).iris('option', 'palettes', palette);
            }
        });
    })(jQuery);
    </script>
    <?php
}

// Hook registration.
register_activation_hook(GPBI_FILE, __NAMESPACE__ . '\\on_activation');
add_filter('plugin_action_links_' . plugin_basename(GPBI_FILE), __NAMESPACE__ . '\\add_settings_link');
add_action('init', __NAMESPACE__ . '\\init_github_updater');
add_action('admin_print_footer_scripts', __NAMESPACE__ . '\\inject_iris_palette', 99);
