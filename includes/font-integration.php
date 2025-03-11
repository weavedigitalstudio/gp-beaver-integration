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
        // Add fonts to Beaver Builder modules
        add_filter( 'fl_builder_font_families_system', array( $this, 'add_gp_fonts_to_beaver' ) );
        
        // Optional: Remove Google fonts from builder if you're handling them locally
        // Uncomment the line below if you want to remove Google fonts
        // add_filter( 'fl_builder_font_families_google', '__return_empty_array' );
        
        // Refresh Beaver Builder font cache when GP fonts are updated
        add_action( 'save_post_gp_font', array( $this, 'clear_bb_cache' ), 20, 3 );
        add_action( 'wp_trash_post', array( $this, 'clear_bb_cache_on_delete' ), 10, 1 );
    }

    /**
     * Add GeneratePress Font Library fonts to Beaver Builder
     * 
     * @param array $system_fonts Existing system fonts in Beaver Builder
     * @return array Modified system fonts array
     */
    public function add_gp_fonts_to_beaver( $system_fonts ) {
        // Get all fonts from GP Font Library
        $gp_fonts = $this->get_generatepress_fonts();
        
        if ( empty( $gp_fonts ) || ! is_array( $gp_fonts ) ) {
            return $system_fonts;
        }
        
        // Add each GP font to the system fonts array
        foreach ( $gp_fonts as $font ) {
            // Skip disabled fonts
            if ( isset( $font['disabled'] ) && $font['disabled'] ) {
                continue;
            }
            
            // Get the font name (use alias if available, otherwise use original name)
            $font_name = ! empty( $font['alias'] ) ? $font['alias'] : $font['name'];
            
            // Parse font variants to BB format
            $weights = $this->parse_variants_to_weights( $font['variants'] );
            
            // Add font to system fonts list
            $system_fonts[ $font_name ] = array(
                'fallback' => $font['fallback'] ? $font['fallback'] : 'Helvetica, Arial, sans-serif',
                'weights'  => $weights,
            );
        }
        
        return $system_fonts;
    }
    
    /**
     * Get all fonts from GeneratePress Font Library
     * 
     * @return array Array of fonts from GP Font Library
     */
    private function get_generatepress_fonts() {
        // Check if the GeneratePress Font Library class exists
        if ( ! class_exists( 'GeneratePress_Pro_Font_Library' ) ) {
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
    private function parse_variants_to_weights( $variants ) {
        $weights = array();
        
        if ( empty( $variants ) || ! is_array( $variants ) ) {
            return array( '400' );  // Default to regular weight if no variants
        }
        
        foreach ( $variants as $variant ) {
            // Skip disabled variants
            if ( isset( $variant['disabled'] ) && $variant['disabled'] ) {
                continue;
            }
            
            $weight = $variant['fontWeight'];
            $style = $variant['fontStyle'];
            
            // Format according to BB requirements
            if ( 'italic' === $style ) {
                $weights[] = $weight . 'i';
            } else {
                $weights[] = $weight;
            }
        }
        
        // If no weights were found, add a default
        if ( empty( $weights ) ) {
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
    public function clear_bb_cache( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Only proceed if it's a GP font post type
        if ( 'gp_font' !== get_post_type( $post_id ) ) {
            return;
        }
        
        $this->delete_bb_cache();
    }
    
    /**
     * Clear Beaver Builder cache when a font is deleted
     * 
     * @param int $post_id Post ID
     */
    public function clear_bb_cache_on_delete( $post_id ) {
        if ( 'gp_font' !== get_post_type( $post_id ) ) {
            return;
        }
        
        $this->delete_bb_cache();
    }
    
    /**
     * Delete Beaver Builder cache
     */
    private function delete_bb_cache() {
        // Check if Beaver Builder is active
        if ( class_exists( 'FLBuilderModel' ) ) {
            // Clear Beaver Builder cache
            if ( method_exists( 'FLBuilderModel', 'delete_asset_cache_for_all_posts' ) ) {
                FLBuilderModel::delete_asset_cache_for_all_posts();
            }
        }
    }
}

// Initialize the integration
new GeneratePress_Beaver_Font_Integration();
