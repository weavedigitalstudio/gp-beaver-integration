(function($) {
    "use strict";

    // Only run when Beaver Builder is active
    if (!window.FLBuilder || !window.FLBuilder._config) {
        return;
    }

    // Store the original color picker context
    const originalColorPickerContext = window.FLBuilder._config.ColorPickerContext;

    // Create our own context provider that extends the original
    const ColorPickerProvider = function(props) {
        // Get the original provider
        const OriginalProvider = originalColorPickerContext.Provider;
        
        // Create a new context value that includes our modifications
        const contextValue = {
            ...props.value,
            defaultActiveTab: 'presets', // Force presets tab to be active by default
            presets: [
                ...(props.value.presets || []),
                ...(window.generatePressPalette || []).map(color => ({
                    color: color,
                    label: 'GP Color'
                }))
            ]
        };

        // Return the original provider with our modified context
        return React.createElement(OriginalProvider, {
            ...props,
            value: contextValue
        });
    };

    // Replace the original context provider with our modified one
    window.FLBuilder._config.ColorPickerContext.Provider = ColorPickerProvider;
})(jQuery); 