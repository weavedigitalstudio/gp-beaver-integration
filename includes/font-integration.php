<?php
/**
 * GeneratePress Font Library Integration with Beaver Builder
 *
 * This file integrates the GeneratePress Font Library with Beaver Builder,
 * making all fonts from GP Font Library available in BB modules.
 *
 * @package GP_Beaver_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access, please.
}

/**
 * Main class to handle integration between GeneratePress Font Library and Beaver Builder
 */
class GeneratePress_Beaver_Font_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        // Add GeneratePress fonts to Beaver Builder
        add_filter( 'fl_builder_font_families_system', array( $this, 'replace_with_gp_fonts' ) );
        
        /**
         * Google Fonts Integration
         * 
         * By default, Google Fonts are removed from Beaver Builder to encourage using
         * only the local fonts from GeneratePress Font Library.
         * 
         * To enable Google Fonts, comment out the line below.
         */
        add_filter( 'fl_builder_font_families_google', '__return_empty_array' );
        
        // Refresh Beaver Builder font cache when GP fonts are updated
        add_action( 'save_post_gp_font', array( $this, 'clear_bb_cache' ), 20, 3 );
        add_action( 'wp_trash_post', array( $this, 'clear_bb_cache_on_delete' ), 10, 1 );
    }

    /**
     * Replace all system fonts with GeneratePress fonts
     * 
     * @param array $system_fonts Existing system fonts in Beaver Builder
     * @return array Modified font array with only the GP fonts
     */
    public function replace_with_gp_fonts( $system_fonts ) {
        // Get all fonts from GP Font Library
        $gp_fonts = $this->get_generatepress_fonts();
        
        // Start with default only
        $new_fonts = array();
        
        // Keep the Default option
        if (isset($system_fonts['Default'])) {
            $new_fonts['Default'] = $system_fonts['Default'];
        }
        
        /**
         * System Fonts Integration
         * 
         * By default, system fonts are completely removed to ensure design consistency.
         * To keep system fonts available, uncomment the line below.
         */
        // $new_fonts = $system_fonts; // Uncomment to keep system fonts
        
        // Add each GP font
        if (!empty($gp_fonts) && is_array($gp_fonts)) {
            foreach ($gp_fonts as $font) {
                // Skip disabled fonts
                if (isset($font['disabled']) && $font['disabled']) {
                    continue;
                }
                
                // Get the font name (use alias if available, otherwise use original name)
                $font_name = !empty($font['alias']) ? $font['alias'] : $font['name'];
                
                // Parse font variants to BB format
                $weights = $this->parse_variants_to_weights($font['variants']);
                
                // Add font to the fonts list
                $new_fonts[$font_name] = array(
                    'fallback' => $font['fallback'] ? $font['fallback'] : 'Helvetica, Arial, sans-serif',
                    'weights'  => $weights,
                );
            }
        }
        
        return $new_fonts;
    }
    
    /**
     * Get all fonts from GeneratePress Font Library
     * 
     * @return array Array of fonts from GP Font Library
     */
    private function get_generatepress_fonts() {
        // Check if the GeneratePress Font Library class exists
        if (!class_exists('GeneratePress_Pro_Font_Library')) {
            return array();
        }
        
        // Get all fonts using the GP Font Library method
        return GeneratePress_Pro_Font_Library::get_fonts();
    }
    
    /**
     * Parse font variants to Beaver Builder weight format
     * 
     * @param array $variants Font variants from GP
     * @return array Weights in BB format
     */
    private function parse_variants_to_weights($variants) {
        $weights = array();
        
        if (empty($variants) || !is_array($variants)) {
            return array('400');  // Default to regular weight if no variants
        }
        
        foreach ($variants as $variant) {
            // Skip disabled variants
            if (isset($variant['disabled']) && $variant['disabled']) {
                continue;
            }
            
            $weight = $variant['fontWeight'];
            $style = $variant['fontStyle'];
            
            // Format according to BB requirements
            if ('italic' === $style) {
                $weights[] = $weight . 'i';
            } else {
                $weights[] = $weight;
            }
        }
        
        // If no weights were found, add a default
        if (empty($weights)) {
            $weights[] = '400';
        }
        
        return $weights;
    }
    
    /**
     * Clear Beaver Builder cache when a font is updated
     * 
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function clear_bb_cache($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Only proceed if it's a GP font post type
        if ('gp_font' !== get_post_type($post_id)) {
            return;
        }
        
        $this->delete_bb_cache();
    }
    
    /**
     * Clear Beaver Builder cache when a font is deleted
     * 
     * @param int $post_id Post ID
     */
    public function clear_bb_cache_on_delete($post_id) {
        if ('gp_font' !== get_post_type($post_id)) {
            return;
        }
        
        $this->delete_bb_cache();
    }
    
    /**
     * Delete Beaver Builder cache
     */
    private function delete_bb_cache() {
        // Check if Beaver Builder is active
        if (class_exists('FLBuilderModel')) {
            // Clear Beaver Builder cache
            if (method_exists('FLBuilderModel', 'delete_asset_cache_for_all_posts')) {
                FLBuilderModel::delete_asset_cache_for_all_posts();
            }
        }
    }
}

// Initialize the integration
new GeneratePress_Beaver_Font_Integration();
