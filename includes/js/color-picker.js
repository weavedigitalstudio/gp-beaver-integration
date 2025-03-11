(function ($) {
	"use strict";

	// Check for required dependencies
	if (!$.a8c || !$.a8c.iris) {
		return;
	}

	// Store original create function
	const originalCreate = $.a8c.iris.prototype._create;

	// Extend Iris with GeneratePress palette
	$.a8c.iris.prototype._create = function () {
		// Add palette if available
		if (
			typeof generatePressPalette !== "undefined" &&
			Array.isArray(generatePressPalette)
		) {
			this.options.palettes = generatePressPalette;
		}

		// Call original create function
		originalCreate.call(this);
	};
})(jQuery);
