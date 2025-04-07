/**
 * GP Beaver Integration - Customizer Handler
 * 
 * This script adds error handling for the customizer to prevent
 * the "Looks like something's gone wrong" error message.
 */
(function($) {
    'use strict';
    
    // Only run in customizer
    if (!wp.customize) {
        return;
    }
    
    // Debug flag
    const debug = window.gpbiCustomizer && window.gpbiCustomizer.debug ? true : false;
    
    // Log function
    function log(message) {
        if (debug) {
            console.log('[GP-Beaver Integration]', message);
        }
    }
    
    // Track if we're in the middle of a save
    let isSaving = false;
    
    // Track save attempts
    let saveAttempts = 0;
    const MAX_SAVE_ATTEMPTS = 3;
    
    // Track errors
    let errorCount = 0;
    const MAX_ERRORS = 5;
    
    // Function to track errors
    function trackError(error) {
        errorCount++;
        
        // Log the error
        log('Error tracked: ' + JSON.stringify(error));
        
        // Send error to server if we have ajaxurl
        if (typeof ajaxurl !== 'undefined') {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gpbi_track_error',
                    error: JSON.stringify(error),
                    nonce: wp.customize.settings.nonce.save
                },
                success: function(response) {
                    if (debug) {
                        log('Error tracking response: ' + JSON.stringify(response));
                    }
                }
            });
        }
        
        // If we've tracked too many errors, reset
        if (errorCount >= MAX_ERRORS) {
            log('Max errors tracked, resetting');
            errorCount = 0;
        }
    }
    
    // Handle customizer save
    $(document).on('click', '.customize-save', function(e) {
        // Don't interfere with the default save behavior
        if (isSaving) {
            return;
        }
        
        isSaving = true;
        saveAttempts = 0;
        errorCount = 0;
        
        log('Customizer save initiated');
    });
    
    // Handle customizer save complete
    wp.customize.bind('saved', function() {
        log('Customizer save completed');
        
        // Reset saving flag after a delay
        setTimeout(function() {
            isSaving = false;
        }, 3000); // Keep a delay to avoid rapid clicks
    });
    
    // Handle customizer save error
    wp.customize.bind('error', function(error) {
        log('Customizer error: ' + JSON.stringify(error));
        
        // Track the error
        trackError({
            type: 'customizer_error',
            message: error.message || 'Unknown error',
            code: error.code || 'unknown'
        });
        
        // Increment save attempts
        saveAttempts++;
        
        // If we've tried too many times, reset
        if (saveAttempts >= MAX_SAVE_ATTEMPTS) {
            log('Max save attempts reached, resetting');
            isSaving = false;
            saveAttempts = 0;
        }
    });
    
    // Add a custom handler for the "Looks like something's gone wrong" error
    $(document).on('customize-error', function(e, error) {
        log('Customizer error detected: ' + JSON.stringify(error));
        
        // Track the error
        trackError({
            type: 'customize_error_event',
            message: error.message || 'Unknown error',
            error: error
        });
        
        // If we're in the middle of a save, try to recover
        if (isSaving) {
            log('Attempting to recover from customizer error');
            
            // Reset the saving flag after a delay
            setTimeout(function() {
                isSaving = false;
                saveAttempts = 0;
            }, 5000);
        }
    });
    
    // Add a handler for the customizer preview refresh
    wp.customize.bind('preview-refresh', function() {
        log('Customizer preview refreshing');
    });
    
})(jQuery); 