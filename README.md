![Weave Cache Purge Helper](https://weave-hk-github.b-cdn.net/weave/plugin-header.png)

# GP Beaver Integration
This plugin syncs and integrates GeneratePress with Beaver Builder, allowing you to maintain consistent branding across your website by synchronising both your global colours and fonts between your theme and Beaver Builder.

Developed for in-house use at Weave Digital Studio & HumanKind Websites to speed up our custom WordPress development with Generate Press and Beaver Builder. We wanted a single source of truth and single entry point for a theme's global colors and fonts across all of WordPress (front and back).

## Features

The plugin provides several key functions for design consistency:

1. **Color Integration**: Makes your GeneratePress Global Colors automatically available in Beaver Builder's color picker by adding the correct block editor prefix '--wp--preset--color--'.

2. **Font Integration**: Syncs all fonts from the GeneratePress Font Library (GP Premium required) to Beaver Builder's typography controls.

3. **Color Documentation**: Includes a shortcode `[gp_global_color_grid]` for use on a style guide which creates a visual display of your color palette, perfect for style guides or documentation pages.

4. **Brand Consistency Controls**: 
   - Disables the "Add to Palette" button in Beaver Builder's new color picker to prevent users from creating custom colors outside the GeneratePress palette
   - Removes both system fonts and Google fonts from Beaver Builder by default to enforce the use of your GeneratePress Font Library
   - Hides the "Saved Colors" section in the color picker to keep focus on the global brand colors
   - Automatically sets the "Presets" tab as the default active tab in color pickers for faster building and quick access to your brand colors

5. **Performance Optimized**: 
   - Intelligent caching system prevents redundant operations
   - Minimal database writes with transient-based synchronization
   - Debug mode for troubleshooting

## How It Works

The plugin works automatically once activated. You don't need to:

- Set up a Global Color Palette in Beaver Builder
- Use PHP to add custom fonts to Beaver Builder
- Make any changes to your existing GeneratePress color or font settings
- Configure any plugin settings 

Just set your colours and fonts in GeneratePress, and they'll be available in Beaver Builder automatically.

## Developer Configuration

### Debug Mode

For troubleshooting, you can enable debug mode by adding the following constant to your wp-config.php file:

```php
define('GPBI_DEBUG', true);
```

This will output detailed logs to your error log when WP_DEBUG is also enabled. For production sites, this should be set to false (which is the default).

## Using the Color Grid

To display your color palette anywhere on your site, use the shortcode:

```
[gp_global_color_grid]
```

Optional parameters:
- `size`: Square size in pixels (default: 190)
- `columns`: Number of columns (default: 4)
- `names`: Show color names (default: true)
- `values`: Show color hex values (default: true)

Example:
```
[gp_global_color_grid size="150" columns="3" names="true" values="false"]
```

This creates a responsive grid showing all your GeneratePress Global Colors with their names, CSS variables, and hex values.

![Global Color Grid](https://weave-hk-github.b-cdn.net/screens/global-color-grid.png)

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

## Removing Global Styles

For optimal performance, add this code to your theme's functions.php file or use something like Perfmatters.

```php
add_action("wp_enqueue_scripts", "remove_global_styles");
function remove_global_styles()
{
	wp_dequeue_style("global-styles");
}
```

For more information about removing global inline styles, see:
[https://perfmatters.io/docs/remove-global-inline-styles-wordpress/](https://perfmatters.io/docs/remove-global-inline-styles-wordpress/)

---

## Changelog

### 1.0.1
**Small Performance Tweaks**
- Added caching system to prevent redundant color synchronisation
- Implemented GPBI_DEBUG constant for more controlled troubleshooting
- Improved database operations by reducing unnecessary writes
- Improved performance with transient-based color sync scheduling

### 1.0.0
- Updated the color picker integration for Beaver Builder 2.9+ and its new React color picker.
- Added automatic "Presets" tab selection in the color picker for faster site building.
- Removed the "Saved Colors" section in the color picker to help lock down brand colour palettes.
- Hide the 'add color' button to enforce brand color consistency

### 0.6.0
- Added GeneratePress Font Library integration with Beaver Builder
- Added brand consistency controls (disabled color add button, removed system/Google fonts)
- Renamed plugin to GP Beaver Integration to reflect expanded functionality
- Updated documentation and code organization

### 0.5.0
- Implemented automatic GitHub updates for the plugin.
- Now updates are detected via GitHub releases, allowing seamless plugin updates in WordPress.

### 0.4.2
- Added color grid shortcode for displaying color palettes in style guides
- Updated plugin name for clarity
- Improved code organization and inline documentation
- Enhanced compatibility checks

### 0.3.0
- Minified CSS output for better performance
- Removed unnecessary comments and whitespace in generated styles
