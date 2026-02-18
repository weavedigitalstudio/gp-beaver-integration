<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Fonts;

defined('ABSPATH') || exit;

/**
 * Integrates GeneratePress Font Library with Beaver Builder,
 * making all GP fonts available in BB typography controls.
 */
final class FontIntegration {

    private static ?self $instance = null;

    private function __construct() {
        add_filter('fl_builder_font_families_system', [$this, 'replace_with_gp_fonts']);

        // Remove Google Fonts — encourage local-only via GP Font Library.
        add_filter('fl_builder_font_families_google', '__return_empty_array');

        // Refresh BB font cache when GP fonts change.
        add_action('save_post_gp_font', [$this, 'clear_bb_cache'], 20, 3);
        add_action('wp_trash_post', [$this, 'clear_bb_cache_on_delete']);
    }

    public static function init(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Replace system fonts with GeneratePress Font Library fonts.
     *
     * @param array<string, array{fallback: string, weights: string[]}> $system_fonts
     * @return array<string, array{fallback: string, weights: string[]}>
     */
    public function replace_with_gp_fonts(array $system_fonts): array {
        $gp_fonts  = $this->get_generatepress_fonts();
        $new_fonts = [];

        // Keep the Default option.
        if (isset($system_fonts['Default'])) {
            $new_fonts['Default'] = $system_fonts['Default'];
        }

        if (!empty($gp_fonts) && is_array($gp_fonts)) {
            foreach ($gp_fonts as $font) {
                if (!empty($font['disabled'])) {
                    continue;
                }

                $font_name = !empty($font['alias']) ? $font['alias'] : $font['name'];
                $weights   = $this->parse_variants_to_weights($font['variants']);

                $new_fonts[$font_name] = [
                    'fallback' => $font['fallback'] ?: 'Helvetica, Arial, sans-serif',
                    'weights'  => $weights,
                ];
            }
        }

        return $new_fonts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_generatepress_fonts(): array {
        if (!class_exists('GeneratePress_Pro_Font_Library')) {
            return [];
        }
        return \GeneratePress_Pro_Font_Library::get_fonts();
    }

    /**
     * Convert GP font variants to BB weight format.
     *
     * @param array<int, array{fontWeight: string, fontStyle: string, disabled?: bool}> $variants
     * @return string[]
     */
    private function parse_variants_to_weights(array $variants): array {
        $weights = [];

        if (empty($variants)) {
            return ['400'];
        }

        foreach ($variants as $variant) {
            if (!empty($variant['disabled'])) {
                continue;
            }

            $weight = $variant['fontWeight'];
            $style  = $variant['fontStyle'];

            $weights[] = $style === 'italic' ? $weight . 'i' : $weight;
        }

        return $weights ?: ['400'];
    }

    /**
     * Clear BB asset cache when a GP font post is saved.
     */
    public function clear_bb_cache(int $post_id, \WP_Post $post, bool $update): void {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || get_post_type($post_id) !== 'gp_font') {
            return;
        }
        $this->delete_bb_cache();
    }

    /**
     * Clear BB asset cache when a GP font post is trashed.
     */
    public function clear_bb_cache_on_delete(int $post_id): void {
        if (get_post_type($post_id) !== 'gp_font') {
            return;
        }
        $this->delete_bb_cache();
    }

    private function delete_bb_cache(): void {
        if (class_exists('FLBuilderModel') && method_exists('FLBuilderModel', 'delete_asset_cache_for_all_posts')) {
            \FLBuilderModel::delete_asset_cache_for_all_posts();
        }
    }
}
