![GP Beaver Integration](https://weave-hk-github.b-cdn.net/weave/plugin-header.png)

# GP Beaver Integration
Integrates GeneratePress Global Colors and Font Library with Beaver Builder page builder for brand consistency. Just set your colours and fonts in GeneratePress, and they'll be available in Beaver Builder automatically.

Developed for in-house use at Weave Digital Studio & HumanKind Websites to speed up our custom WordPress development with Generate Press and Beaver Builder. We wanted a single source of truth and single entry point for a theme's global colors and fonts across all of WordPress (front and back).

## Features

The plugin provides several key functions for design consistency:

1. **Color Integration**: Automatically syncs GeneratePress Global Colors to Beaver Builder's color picker and Beaver Builder Global Styles.

2. **Font Integration**: Makes all fonts added via GeneratePress Font Library (GP Premium required) to Beaver Builder's typography controls. no extra PHP needed.

3. **Color Documentation**: Includes a shortcode `[gp_global_color_grid]` for use on a style guide which creates a visual display of your color palette, perfect for style guides or documentation pages.

4. **Brand Consistency Controls**: 
   - Disables the "Add to Palette" button in Beaver Builder's new color picker to prevent users from creating custom colors outside the GeneratePress palette
   - Removes both system fonts and Google fonts from Beaver Builder by default to enforce the use of your GeneratePress Font Library
   - Hides the "Saved Colors" section in the color picker to keep focus on the global brand colors
   - Automatically sets the "Presets" tab as the default active tab in color pickers for faster building and quick access to your brand colors

5. **Settings Control**: Option to enable/disable color restrictions


## Usage

### Color Integration

The plugin automatically:
- Syncs your GeneratePress Global Colors with Beaver Builder and BB Glocal Styles
- Makes colors available in all Beaver Builder color pickers
- Optionally restricts color selection to Global Colors only

### Font Integration

The plugin automatically:
- Makes all GeneratePress Font Library fonts available in Beaver Builder
- Handles font variants and weights correctly
- Clears Beaver Builder cache when fonts are updated

### Color Grid Shortcode

Use the shortcode `[gp_global_color_grid]` to display your color palette. Options:

- `columns`: Number of columns (default: 4)
- `names`: Show color names (default: true)
- `values`: Show hex values (default: true)

Example:
```
[gp_global_color_grid columns="3" names="true" values="true"]
```

This creates a responsive grid showing all your GeneratePress Global Colors with their names, CSS variables, and hex values.

![Default Colors](https://weave-hk-github.b-cdn.net/screens/default-global-colors.png)

![Global Color Grid](https://weave-hk-github.b-cdn.net/screens/color-palette2.png)


## Settings

Under Settings > GP Beaver Integration, you can:
- Administrator can Enable/disable color restrictions
- Control whether users can add custom colors
- **Note** For our own use we always have this enabled so clients cannot change easily add random colors. This is the default.

---

## Plugin Installation  

### Manual Installation  
1. Download the latest `.zip` file from the [Releases Page](https://github.com/weavedigitalstudio/gp-beaver-integration/releases).  
2. Go to **Plugins > Add New > Upload Plugin**.  
3. Upload the zip file, install, and activate!  

### Auto-Updater via GitHub  
This plugin supports automatic updates directly from GitHub using a custom updater. To ensure updates work:  
1. Keep the plugin installed in `wp-content/plugins/gp-beaver-integration`.  
2. When a new release is available, the WordPress updater will notify you.  
3. Click **Update Now** in the Plugins page to install the latest version.

---

## Requirements

- GeneratePress theme / child theme
- Beaver Builder plugin
- WordPress Global Styles disabled (recommended for performance)
- GeneratePress Premium required for Font Library integration

## Developer Configuration

### Debug Mode

For troubleshooting, you can enable debug mode by adding the following constant to your wp-config.php file:

```php
define('GPBI_DEBUG', true);
```

This will output detailed logs to your error log when WP_DEBUG is also enabled. For production sites, this should be set to false (which is the default).

---

## Changelog

### 1.0.9 - 2024-05-16
- **Fixed** Duplicated colors appearing in Beaver Builder Global Colors UI after updates
- **Improved** Color sync process to properly update existing colors instead of adding new ones

### 1.0.8 - 2024-05-01
- **Fixed** Color picker tab now defaults to presets and still allows normal tab switching
- **Removed** JS file (color-picker-bb29.js) as functionality moved to main integration file

### 1.0.7 - 2025-04-22
- Improved color grid shortcode styling with better padding and better readability
- Removed deprecated size parameter from color grid shortcode

### 1.0.6 - 2025-04-12
- Fixed GeneratePress color palette not appearing in WordPress Admin iris color picker.
- Added separate scripts for WordPress Admin and Beaver Builder to prevent conflicts.

### 1.0.4 - 2025-04-09
- Fixed 500 error when saving in WordPress customizer..
- Fixed colors not initializing properly in Beaver Builder Global Styles interfaces
- Fixed compatibility with both Beaver Builder 2.8.x and 2.9+ versions
- Fixed preset tabs not appearing as default in color pickers, broken in last version.
- **Note** Global color palette automatic display in Global Styles panel works fully in BB 2.9+, but BB 2.8.x users will need to click on each color swatch to activate it

### 1.0.3 - 2025-04-04
- Added settings page for color restriction control
- Fixed color picker integration with BB 2.9
- Improved error handling

### 1.0.1 - 2025-03-20
**Small Performance Tweaks**
- Added caching system to prevent redundant color synchronisation
- Implemented GPBI_DEBUG constant for more controlled troubleshooting
- Improved database operations by reducing unnecessary writes
- Improved performance with transient-based color sync scheduling

### 1.0.0 - 2025-03-19
- Updated the color picker integration for Beaver Builder 2.9+ and its new React color picker.
- Added automatic "Presets" tab selection in the color picker for faster site building.
- Removed the "Saved Colors" section in the color picker to help lock down brand colour palettes.
- Hide the 'add color' button to enforce brand color consistency
- **Added to Github**

### 0.6.0
- Added GeneratePress Font Library integration with Beaver Builder
- Added brand consistency controls (disabled color add button, removed system/Google fonts)
- Renamed plugin to GP Beaver Integration to reflect expanded functionality
- Updated documentation and code organization

### 0.5.0 - 2025-02-20
- Implemented automatic GitHub updates for the plugin.
- Now updates are detected via GitHub releases, allowing seamless plugin updates in WordPress.

### 0.4.2 - 2025-02-13
- Added color grid shortcode for displaying color palettes in style guides
- Updated plugin name for clarity
- Improved code organization and inline documentation
- Enhanced compatibility checks

### 0.3.0 - 2025-01-26
- Minified CSS output for better performance
- Removed unnecessary comments and whitespace in generated styles

### 0.1.0 - 2024-10-12
- Code tidy up - using wp_get_theme()->get_template() instead of wp_get_theme()->get('Template')

### 0.0.5 - 2024-08-01
- Added theme check for Generate Press & GP Child themes

### 0.0.2 - 2024-06-02
- Initial release for testing
