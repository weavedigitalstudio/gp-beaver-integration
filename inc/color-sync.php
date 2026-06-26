<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Colors;

defined('ABSPATH') || exit;

const CACHE_FORMATTED_COLORS = 'gpbi_formatted_colors';
const CACHE_COLORS_SYNCED    = 'gpbi_colors_synced';
const CACHE_FORCE_UPDATE     = 'gpbi_force_color_update';

/**
 * Beaver Builder version that added the native "Default to Presets Tab" setting.
 *
 * From this version on, BB defaults the colour picker to the Presets tab itself,
 * so we hand off to core (see maybe_seed_presets_tab_default) and retire the JS
 * tab-clicking fallback. Gated on '2.11-beta' rather than '2.11' on purpose:
 * version_compare() treats '2.11-beta.4' as *less than* '2.11', so a '2.11' gate
 * would miss the public beta. This way the hand-off also activates on the betas.
 */
const BB_NATIVE_PRESETS_TAB_VERSION = '2.11-beta';

/**
 * Check whether GeneratePress global colours are available.
 */
function gp_colors_available(): bool {
    return function_exists('generate_get_global_colors');
}

/**
 * Whether the active Beaver Builder handles the Presets-tab default natively.
 */
function bb_has_native_presets_tab(): bool {
    return defined('FL_BUILDER_VERSION')
        && version_compare(FL_BUILDER_VERSION, BB_NATIVE_PRESETS_TAB_VERSION, '>=');
}

/**
 * Single source of truth for GP colours in BB format.
 *
 * Returns an array of [ uid, label, color, slug, isGlobalColor ] entries.
 * Uses a static cache per-request and a 12-hour transient.
 */
function get_formatted_gp_colors(): array {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $transient = get_transient(CACHE_FORMATTED_COLORS);
    if (is_array($transient)) {
        $cached = $transient;
        return $cached;
    }

    if (!gp_colors_available()) {
        $cached = [];
        return $cached;
    }

    $global_colors = \generate_get_global_colors();
    if (empty($global_colors)) {
        $cached = [];
        return $cached;
    }

    $formatted = [];
    foreach ($global_colors as $color) {
        if (empty($color['slug']) || empty($color['color'])) {
            continue;
        }

        // Only prepend '#' to a bare hex value. GP's colour picker has an alpha
        // channel, so a global colour can be rgb()/rgba()/hsl() — those must pass
        // through untouched. Blindly prefixing would corrupt them into
        // '#rgba(0,0,0,.5)' and poison both BB Global Styles and the CSS vars.
        $color_value = $color['color'];
        if ($color_value[0] !== '#' && preg_match('/^[a-fA-F0-9]{3,8}$/', $color_value)) {
            $color_value = '#' . $color_value;
        }

        $formatted[] = [
            'uid'           => substr(md5($color['slug']), 0, 9),
            'label'         => $color['name'] ?? $color['slug'],
            'color'         => $color_value,
            'slug'          => sanitize_title(strtolower($color['slug'])),
            'isGlobalColor' => true,
        ];
    }

    set_transient(CACHE_FORMATTED_COLORS, $formatted, 12 * HOUR_IN_SECONDS);
    $cached = $formatted;
    return $formatted;
}

/**
 * Invalidate both the static and transient colour caches.
 */
function invalidate_color_cache(): void {
    // Reset static cache by clearing transient — next call rebuilds both.
    delete_transient(CACHE_FORMATTED_COLORS);
    delete_transient(CACHE_COLORS_SYNCED);
    delete_transient(CACHE_FORCE_UPDATE);
}

/**
 * Single sync orchestrator — replaces 3 competing customize_save_after callbacks.
 *
 * Hooked to: customize_save_after, update_option_generate_settings, generate_settings_updated.
 */
function on_gp_colors_changed(): void {
    invalidate_color_cache();
    sync_to_bb_global_styles();
    clear_bb_asset_cache();
}

/**
 * One-way sync: push GP colours into BB Global Styles.
 */
function sync_to_bb_global_styles(): void {
    if (!gp_colors_available() || !class_exists('FLBuilderGlobalStyles')) {
        return;
    }

    $bb_settings = \FLBuilderGlobalStyles::get_settings(false);
    if (!is_object($bb_settings)) {
        return;
    }

    // Separate existing non-GP colours.
    $gp_slugs = array_column(get_formatted_gp_colors(), 'slug');
    $existing  = [];
    if (isset($bb_settings->colors) && is_array($bb_settings->colors)) {
        foreach ($bb_settings->colors as $bb_color) {
            if (isset($bb_color['slug']) && in_array(sanitize_title(strtolower($bb_color['slug'])), $gp_slugs, true)) {
                continue;
            }
            $existing[] = $bb_color;
        }
    }

    $bb_settings->colors = array_merge(get_formatted_gp_colors(), $existing);
    \FLBuilderGlobalStyles::save_settings($bb_settings);

    set_transient(CACHE_COLORS_SYNCED, true, 12 * HOUR_IN_SECONDS);

    debug_log('Synced ' . count(get_formatted_gp_colors()) . ' GP colours to BB Global Styles');
}

/**
 * Clear Beaver Builder's compiled asset cache.
 */
function clear_bb_asset_cache(): void {
    if (class_exists('FLBuilderModel') && method_exists('FLBuilderModel', 'delete_asset_cache_for_all_posts')) {
        \FLBuilderModel::delete_asset_cache_for_all_posts();
    }
}

// --- CSS custom properties ---------------------------------------------------

/**
 * Output GP colours as --wp--preset--color--{slug} CSS custom properties.
 *
 * GeneratePress outputs short-form variables (--primary, --white, etc.) but
 * Beaver Builder stores colour references using the WordPress standard
 * --wp--preset--color--{slug} format. This bridges the two.
 */
function enqueue_color_css_properties(): void {
    if (!gp_colors_available()) {
        return;
    }

    $colors = get_formatted_gp_colors();
    if (empty($colors)) {
        return;
    }

    $css = ':root{';
    foreach ($colors as $color) {
        $css .= sprintf(
            '--wp--preset--color--%s:%s;',
            esc_attr($color['slug']),
            esc_attr($color['color'])
        );
    }
    $css .= '}';

    if (wp_style_is('generate-style', 'enqueued')) {
        wp_add_inline_style('generate-style', $css);
    }
}

// --- Filters ----------------------------------------------------------------

/**
 * Add GP colours to BB's WP Core colour palette (Presets tab).
 *
 * @param array $colors Existing colours.
 * @return array Modified colours.
 */
function filter_bb_wp_core_colors(array $colors): array {
    if (!gp_colors_available()) {
        return $colors;
    }

    $global_colors = \generate_get_global_colors();
    if (empty($global_colors)) {
        return $colors;
    }

    foreach ($global_colors as $color) {
        if (!isset($color['slug'], $color['color'])) {
            continue;
        }

        $colors[] = [
            'slug'  => sanitize_title(strtolower($color['slug'])),
            'color' => class_exists('FLBuilderColor') ? \FLBuilderColor::hex_or_rgb($color['color']) : $color['color'],
            'name'  => isset($color['name']) ? esc_html($color['name']) : esc_html($color['slug']),
        ];
    }

    return $colors;
}

// --- FLPageData registration -------------------------------------------------

/**
 * Register GP colours with FLPageData for field connections.
 */
function register_with_fl_page_data(): void {
    if (!class_exists('FLPageData') || !gp_colors_available()) {
        return;
    }

    foreach (get_formatted_gp_colors() as $color) {
        \FLPageData::add_site_property('global_color_' . $color['uid'], [
            'label'  => '<span class="prefix">' . __('GeneratePress -', 'gp-beaver-integration') . '</span>' .
                        esc_html($color['label']) .
                        '<span class="swatch" style="background-color:' . esc_attr($color['color']) . ';"></span>',
            'group'  => 'bb',
            'type'   => 'color',
            'getter' => function () use ($color): string {
                return $color['color'];
            },
        ]);
    }
}

// --- Presets tab auto-activation ---------------------------------------------

/**
 * Auto-activate the Presets tab when a colour picker dialog opens.
 * Uses vanilla JS with a targeted MutationObserver.
 */
function activate_presets_tab(): void {
    if (!class_exists('FLBuilderModel') || !\FLBuilderModel::is_builder_active()) {
        return;
    }

    // Beaver Builder 2.11+ opens the picker on the Presets tab natively
    // (seeded by maybe_seed_presets_tab_default), so this JS fallback is only
    // needed for older builders.
    if (bb_has_native_presets_tab()) {
        return;
    }

    ?>
    <script>
    (function() {
        function activatePresetsTab(dialog) {
            var tabs = dialog.querySelector('.fl-controls-picker-bottom-tabs');
            if (!tabs) return;

            var buttons = tabs.querySelectorAll('.fl-control');
            if (!buttons.length) return;

            var presetsTab = buttons[buttons.length - 1];
            if (!presetsTab.classList.contains('is-selected')) {
                presetsTab.click();
            }
        }

        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType !== 1) continue;

                    if (node.classList && node.classList.contains('fl-controls-dialog')) {
                        setTimeout(function() { activatePresetsTab(node); }, 50);
                    } else if (node.querySelector) {
                        var dialogs = node.querySelectorAll('.fl-controls-dialog');
                        dialogs.forEach(function(d) {
                            setTimeout(function() { activatePresetsTab(d); }, 50);
                        });
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
}

// --- Native Presets tab default (BB 2.11+) -----------------------------------

/**
 * Seed Beaver Builder's native "Default to Presets Tab" setting once on BB 2.11+.
 *
 * BB 2.11 added the option `_fl_builder_default_presets_tab` (default off). We
 * switch it on a single time so sites keep the presets-first behaviour once the
 * legacy JS fallback retires. After seeding we never touch it again, so an admin
 * can turn it off in Builder > Tools > Global Settings > Advanced and we respect
 * that. If BB is not yet 2.11 we do nothing and try again on a later request,
 * so the option gets seeded when the site eventually updates Beaver Builder.
 */
function maybe_seed_presets_tab_default(): void {
    if (get_option('gpbi_presets_tab_seeded')) {
        return;
    }

    if (!bb_has_native_presets_tab()) {
        return;
    }

    // Only seed when the admin has not already chosen a value (false = unset).
    if (false === get_option('_fl_builder_default_presets_tab', false)) {
        update_option('_fl_builder_default_presets_tab', 1);
    }

    update_option('gpbi_presets_tab_seeded', 1);
}

// --- Palette restriction CSS -------------------------------------------------

/**
 * Output CSS to hide the "Add to Palette" UI when restriction is enabled.
 */
function output_palette_restriction_css(): void {
    if (!class_exists('FLBuilderModel') || !\FLBuilderModel::is_builder_active()) {
        return;
    }

    $settings = get_option('gpbi_settings', ['restrict_colors' => 0]);
    if (empty($settings['restrict_colors'])) {
        return;
    }

    // Legacy Beaver Builder (< 2.11) colour picker markup.
    $css = '
        .fl-color-picker-ui .fl-color-picker-preset-add,
        .fl-color-picker-ui .fl-color-picker-presets-list .fl-color-picker-preset-remove,
        .fl-controls-swatch-group.fl-appearance-swatches,
        .fl-color-picker-toolbar > div:last-child > button {
            display: none !important;
        }
    ';

    // Beaver Builder 2.11 rebuilt the colour picker in React; the class names
    // changed, so the legacy selectors above no longer match. These target the
    // "create / pick a custom colour" affordances (toolbar add button, eyedropper
    // and hex input) so only the global presets remain usable.
    //
    // TODO(verify-on-2.11): derived from the 2.11-beta.4 bundle, NOT yet confirmed
    // against a live 2.11 site. Re-check these selectors hide the right controls
    // (and nothing else) before relying on the restriction feature on BB 2.11.
    if (bb_has_native_presets_tab()) {
        $css .= '
            .fl-color-picker-toolbar .fl-controls-color-eyedropper,
            .fl-controls-color-input {
                display: none !important;
            }
        ';
    }

    echo '<style id="gpbi-color-restrict">' . $css . '</style>';
}

// --- Debug helper ------------------------------------------------------------

/**
 * Log a message when both WP_DEBUG and GPBI_DEBUG are true.
 */
function debug_log(string $message): void {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('GPBI_DEBUG') && GPBI_DEBUG) {
        error_log('[GP-Beaver Integration] ' . $message);
    }
}

// =============================================================================
// Hook registration
// =============================================================================

// Colour sync — one callback per trigger event.
add_action('customize_save_after', __NAMESPACE__ . '\\on_gp_colors_changed', 30);
add_action('update_option_generate_settings', __NAMESPACE__ . '\\on_gp_colors_changed', 30);
add_action('generate_settings_updated', __NAMESPACE__ . '\\on_gp_colors_changed', 30);

// CSS custom properties for BB compatibility.
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_color_css_properties', 20);

// BB colour filter (official API for Presets tab).
add_filter('fl_wp_core_global_colors', __NAMESPACE__ . '\\filter_bb_wp_core_colors');

// FLPageData registration — front-end and admin only.
add_action('wp', __NAMESPACE__ . '\\register_with_fl_page_data', 20);
add_action('admin_init', __NAMESPACE__ . '\\register_with_fl_page_data', 20);

// Presets tab — seed BB's native setting on 2.11+, JS fallback on older builders.
add_action('admin_init', __NAMESPACE__ . '\\maybe_seed_presets_tab_default');
add_action('wp_footer', __NAMESPACE__ . '\\activate_presets_tab', 999);
add_action('admin_footer', __NAMESPACE__ . '\\activate_presets_tab', 999);

// Palette restriction CSS.
add_action('wp_footer', __NAMESPACE__ . '\\output_palette_restriction_css', 100);
add_action('admin_footer', __NAMESPACE__ . '\\output_palette_restriction_css', 100);
