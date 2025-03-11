![Weave Cache Purge Helper](https://weave-hk-github.b-cdn.net/weave/plugin-header.png)

# GP Beaver Integration

This plugin syncs and integrates GeneratePress with Beaver Builder, allowing you to maintain consistent branding across your website by synchronising both your glocal colours and fonts between your theme and Beaver Builder.

Developed for in-house use at Weave Digital Studio & HumanKind Websites to speed up our custom WordPress development with Generate Press and Beaver Builder. We wanted a single source of truth and single entry point for a theme's glocal colors and fonts across all of WordPress (front and back).

## Features

The plugin provides three main functions:

1. **Color Integration**: Makes your GeneratePress Global Colors automatically available in Beaver Builder's color picker by adding the correct block editor prefix '--wp--preset--color--'.

2. **Font Integration**: Syncs all fonts from the GeneratePress Font Library (GP Premium required) to Beaver Builder's typography controls making them available in the builder.

3. **Color Documentation**: Includes a shortcode `[gp_global_color_grid]` for use on a style guide which creates a visual display of your color palette, perfect for style guides or documentation pages.

## How It Works

The plugin works automatically once activated. You don't need to:

- Set up a separate Global Color Palette in Beaver Builder
- Configure separate font libraries in Beaver Builder
- Make any changes to your existing GeneratePress color or font settings
- Configure any plugin settings

Just set your colors and fonts in GeneratePress, and they'll be available in Beaver Builder automatically.

## Using the Color Grid

We use this to create a master style guide on the site for our clients.
To display your color palette anywhere on your site, use the shortcode:

```
[gp_global_color_grid]
```

Optional parameters:
- `size`: Square size in pixels (default: 100)
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

### 0.6.0
- Added GeneratePress Font Library integration with Beaver Builder
- Renamed plugin to GP Beaver Integration to reflect expanded functionality
- Updated documentation and code organisation

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
