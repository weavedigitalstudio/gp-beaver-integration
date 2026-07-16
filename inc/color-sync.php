<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Colors;

defined('ABSPATH') || exit;

const CACHE_FORMATTED_COLORS = 'gpbi_formatted_colors';
const CACHE_COLORS_SYNCED    = 'gpbi_colors_synced';
const CACHE_FORCE_UPDATE     = 'gpbi_force_color_update';

/**
 * Option recording the uids of GP colours we last pushed into BB.
 *
 * This is how a DELETION propagates: a colour removed from GP no longer
 * matches the current GP set, so matching against that set alone cannot
 * distinguish "the user's own BB colour" from "a GP colour we synced that
 * has since been deleted" — and the sync preserved deleted GP colours in
 * BB forever (found on testing.firehawk, 16 Jul 2026). An option, not a
 * transient: losing it silently re-orphans deleted colours.
 */
const SYNCED_UIDS_OPTION = 'gpbi_synced_gp_uids';

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
 * Seeding-logic version for the native Presets-tab default.
 *
 * Stored in the `gpbi_presets_tab_seeded` option. Bump this when the seeding
 * logic changes so sites that ran an earlier (buggy) version re-seed once.
 * v1 used a `false === get_option(...)` guard that silently skipped seeding
 * whenever Beaver Builder had already written the option as 0 — which it does
 * the moment its Advanced settings are saved — so those sites never got
 * presets-first switched on. v2 drops that guard and re-seeds them.
 */
const PRESETS_SEED_VERSION = 2;

/**
 * Check whether GeneratePress global colours are available.
 */
function gp_colors_available(): bool {
    return function_exists('generate_get_global_colors');
}

/**
 * Whether a BB colour entry has the shape of a colour we once synced from
 * GP: it still carries our isGlobalColor tag, or its uid is the
 * deterministic md5-of-slug that get_formatted_gp_colors() generates.
 * BB-native colours can't false-match — their uids are random and they
 * never carry the tag. Used as the backfill for sites that synced before
 * SYNCED_UIDS_OPTION existed.
 *
 * @param array $bb_color One entry from BB Global Styles colours.
 */
function is_synced_gp_shape(array $bb_color): bool {
    if (!empty($bb_color['isGlobalColor'])) {
        return true;
    }

    $slug = isset($bb_color['slug']) ? sanitize_title(strtolower((string) $bb_color['slug'])) : '';
    $uid  = (string) ($bb_color['uid'] ?? '');

    return '' !== $slug && '' !== $uid && substr(md5($slug), 0, 9) === $uid;
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
 *
 * @param bool $force_refresh Discard both caches and rebuild from GP settings.
 *                            Needed after a Customizer save: admin_init has
 *                            already primed the static cache with the pre-save
 *                            palette earlier in the same request.
 */
function get_formatted_gp_colors(bool $force_refresh = false): array {
    static $cached = null;

    if ($force_refresh) {
        $cached = null;
        delete_transient(CACHE_FORMATTED_COLORS);
    }

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
    delete_transient(CACHE_COLORS_SYNCED);
    delete_transient(CACHE_FORCE_UPDATE);

    // Rebuild from fresh GP data, discarding the per-request static cache.
    // A Customizer save arrives via admin-ajax, where our admin_init callbacks
    // have already primed the static cache with the PRE-save palette; deleting
    // the transient alone left that stale copy in place, so the sync that
    // follows pushed old colours into BB and new colours never arrived.
    get_formatted_gp_colors(true);
}

/**
 * Single sync orchestrator — replaces 3 competing customize_save_after callbacks.
 *
 * Hooked to: customize_save_after, update_option_generate_settings, generate_settings_updated.
 */
function on_gp_colors_changed(): void {
    invalidate_color_cache();
    if (sync_to_bb_global_styles()) {
        clear_bb_asset_cache();
    }
}

/**
 * Self-healing re-sync safety net.
 *
 * The synced flag expires after 12 hours, so any sync that was missed (cache
 * flush, GP changed while BB was deactivated, a failed save) is repaired on
 * the next admin request instead of waiting for the next GP colour change.
 * The change detection in sync_to_bb_global_styles() makes the routine case
 * a read-only no-op.
 */
function maybe_resync(): void {
    if (get_transient(CACHE_COLORS_SYNCED)) {
        return;
    }

    if (sync_to_bb_global_styles()) {
        clear_bb_asset_cache();
    }
}

/**
 * One-way sync: push GP colours into BB Global Styles.
 *
 * @return bool Whether BB's stored colours actually changed.
 */
function sync_to_bb_global_styles(): bool {
    if (!gp_colors_available() || !class_exists('FLBuilderGlobalStyles')) {
        return false;
    }

    $bb_settings = \FLBuilderGlobalStyles::get_settings(false);
    if (!is_object($bb_settings)) {
        return false;
    }

    // Separate existing non-GP colours. A previously synced GP colour is
    // recognised three ways, because matching against the CURRENT GP set is
    // not enough — a colour deleted from GP is exactly the one that no longer
    // matches it, so slug/uid matching alone preserved deleted GP colours in
    // BB forever while additions synced fine:
    //   1. slug or uid in the current GP set (refreshed by the merge below;
    //      uid is deterministic md5-of-slug and part of BB's own schema, so
    //      it survives BB's save/sanitise round-trip even if the custom slug
    //      key gets stripped — the 1.x duplicates bug);
    //   2. uid in the recorded list of previously synced uids — the reliable
    //      deletion signal;
    //   3. GP shape (is_synced_gp_shape) — backfill for colours synced before
    //      the recorded list existed.
    // Whatever remains is genuinely the user's own BB colour and survives.
    $gp_colors = get_formatted_gp_colors();
    $gp_slugs  = array_column($gp_colors, 'slug');
    $gp_uids   = array_column($gp_colors, 'uid');
    $previously_synced = array_map('strval', (array) get_option(SYNCED_UIDS_OPTION, []));
    $current   = (isset($bb_settings->colors) && is_array($bb_settings->colors)) ? $bb_settings->colors : [];
    $existing  = [];
    foreach ($current as $bb_color) {
        if (isset($bb_color['slug']) && in_array(sanitize_title(strtolower($bb_color['slug'])), $gp_slugs, true)) {
            continue;
        }
        if (isset($bb_color['uid']) && in_array($bb_color['uid'], $gp_uids, true)) {
            continue;
        }
        if (isset($bb_color['uid']) && in_array((string) $bb_color['uid'], $previously_synced, true)) {
            continue;
        }
        if (is_array($bb_color) && is_synced_gp_shape($bb_color)) {
            continue;
        }
        $existing[] = $bb_color;
    }

    $merged = array_merge($gp_colors, $existing);

    // Record what this sync pushed, so the next one can tell a deleted GP
    // colour from a user's own BB colour. Recorded on the no-change path too:
    // the backfill case (heuristic matched, list empty) changes nothing in BB
    // but must still be remembered.
    update_option(SYNCED_UIDS_OPTION, array_map('strval', $gp_uids), false);

    // Loose comparison: BB's save/load round-trip can juggle scalar types.
    if ($current == $merged) {
        set_transient(CACHE_COLORS_SYNCED, true, 12 * HOUR_IN_SECONDS);
        return false;
    }

    $bb_settings->colors = $merged;
    \FLBuilderGlobalStyles::save_settings($bb_settings);

    set_transient(CACHE_COLORS_SYNCED, true, 12 * HOUR_IN_SECONDS);

    debug_log('Synced ' . count($gp_colors) . ' GP colours to BB Global Styles');
    return true;
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
 * BB 2.11 added the option `_fl_builder_default_presets_tab` (default off) and
 * reads it via plain get_option() in class-fl-builder-config.php and
 * class-fl-controls.php, defaulting the colour picker to the Presets tab when
 * it equals '1'/1. We switch it on a single time so sites keep the
 * presets-first behaviour once the legacy JS fallback retires.
 *
 * We force the value rather than only seeding an "unset" option: BB writes the
 * option as 0 the moment its Advanced settings are saved, so a presence check
 * would skip every site that had ever opened that screen. After seeding we
 * never touch it again, so an admin can still turn it off in
 * Builder > Tools > Global Settings > Advanced and we respect that.
 *
 * The `gpbi_presets_tab_seeded` flag stores the seeding-logic version
 * (PRESETS_SEED_VERSION), so a bump re-seeds sites that ran an earlier build.
 * If BB is not yet 2.11 we do nothing and try again on a later request, so the
 * option gets seeded when the site eventually updates Beaver Builder.
 */
function maybe_seed_presets_tab_default(): void {
    if (!bb_has_native_presets_tab()) {
        return;
    }

    if ((int) get_option('gpbi_presets_tab_seeded') >= PRESETS_SEED_VERSION) {
        return;
    }

    update_option('_fl_builder_default_presets_tab', 1);
    update_option('gpbi_presets_tab_seeded', PRESETS_SEED_VERSION);
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

// Self-healing safety net — repairs any missed sync within 12 hours.
add_action('admin_init', __NAMESPACE__ . '\\maybe_resync', 30);

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
