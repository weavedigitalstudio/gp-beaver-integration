![GP Beaver Integration](https://weave-hk-github.b-cdn.net/weave/plugin-header.png)

# GP Beaver Integration

Integrates GeneratePress Global Colours and Font Library with Beaver Builder for brand consistency. Set your colours and fonts in GeneratePress, and they'll be available in Beaver Builder automatically.

Developed for in-house use at Weave Digital Studio & HumanKind Websites to speed up custom WordPress development with GeneratePress and Beaver Builder. A single source of truth for a theme's global colours and fonts across the front-end and back-end.

## Features

1. **Colour Integration** — Automatically syncs GeneratePress Global Colours to Beaver Builder's colour picker Presets tab and Global Styles panel.

2. **Font Integration** — Makes all fonts from the GeneratePress Font Library (GP Premium required) available in Beaver Builder's typography controls.

3.**Presets Tab Auto-Activation** — The Presets tab is automatically selected and shown at the front when a colour picker opens, giving immediate access to brand colours. This is how it shoud be.

4. **Colour Grid Shortcode** — `[gp_global_color_grid]` renders a responsive visual grid of your colour palette, ideal for style guides and documentation pages.

5. **Brand Consistency Controls** — Optional setting to hide the "Add to Palette" button, "Saved Colors" section, and custom colour controls in Beaver Builder's colour picker. Enforces use of the global palette only.

7. **Font Lockdown** — Removes system fonts and Google Fonts from Beaver Builder by default, enforcing the GeneratePress Font Library as the single font source.

## Requirements

| Dependency | Minimum Version |
|---|---|
| PHP | 8.1 |
| WordPress | 6.6 |
| GeneratePress | Latest (theme or child theme) |
| Beaver Builder | 2.9+ |
| GeneratePress Premium | Required for Font Library integration |

## Installation

### Manual Installation
1. Download the latest `.zip` from the [Releases Page](https://github.com/weavedigitalstudio/gp-beaver-integration/releases).
2. Go to **Plugins > Add New > Upload Plugin**.
3. Upload the zip, install, and activate.

### Auto-Updates via GitHub
The plugin includes a built-in GitHub updater. When a new release is published, WordPress will notify you on the Plugins page. Click **Update Now** to install.

## Settings

Under **Settings > GP Beaver Integration**:

- **Colour Restrictions** — Toggle to restrict the Beaver Builder colour picker to Global Colours only (disabled by default).

## Colour Grid Shortcode

Display your colour palette anywhere with `[gp_global_color_grid]`.

### Parameters

| Parameter | Default | Description |
|---|---|---|
| `columns` | `4` | Number of grid columns |
| `names` | `true` | Show colour names |
| `values` | `true` | Show hex values and CSS variable names |

### Example

```
[gp_global_color_grid columns="3" names="true" values="false"]
```

![Global Colour Grid](https://weave-hk-github.b-cdn.net/screens/global-color-grid.png)

## Developer Configuration

Enable debug logging by adding to `wp-config.php`:

```php
define('GPBI_DEBUG', true);
```

This outputs detailed logs to the error log when `WP_DEBUG` is also enabled. Defaults to `false`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full release history.

## License

GPL v2 or later. See [LICENSE](LICENSE).
