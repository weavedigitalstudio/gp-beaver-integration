# Changelog

All notable changes to GP Beaver Integration are documented here.

## 2.0.2 — 2026-03-01

### Fixed
- GitHub updater `after_install()` running for all plugin updates, not just this one — could corrupt plugin files when updating any other plugin
- Missing `plugin` key in update transient response, preventing WordPress from properly mapping the update
- Added `requires_php` and `requires` to update response so WordPress can warn sites on incompatible PHP/WP versions before attempting the update
- Added `url` to update response for "View details" link

## 2.0.1 — 2026-02-23

### Fixed
- Restored `--wp--preset--color--*` CSS custom properties output that was incorrectly removed in 2.0.0 as "redundant"
- GeneratePress outputs short-form variables (`--primary`, `--white`), but Beaver Builder references colours using `--wp--preset--color--{slug}` format — our plugin bridges the two
- Without this, BB modules using GP global colours would render with missing colours on the frontend

## 2.0.0 — 2026-02-18

Major modernisation release. Breaking: requires PHP 8.1+, WordPress 6.6+, Beaver Builder 2.9+.

### Added
- Namespaces throughout (`GPBeaverIntegration\Colors`, `Fonts`, `Updater`, `Settings`, `Hooks`, `Shortcodes`)
- `declare(strict_types=1)` on all PHP files
- PHP 8.1 typed properties and return types
- `uninstall.php` — cleans up options and transients on plugin deletion
- `phpcs.xml.dist` — WordPress Coding Standards configuration
- About section on settings page with version, author, and requirements
- Luminance-based border on light colour swatches in the colour grid shortcode
- NZ English in all user-facing strings

### Changed
- Consolidated 4 duplicate colour formatting loops into single `get_formatted_gp_colors()`
- Replaced 10 scattered `function_exists` checks with `gp_colors_available()`
- Merged 3 competing `customize_save_after` callbacks into one sync orchestrator
- Restructured `includes/` to `inc/` with 6 focused, namespaced files
- Main plugin file reduced from 411 lines to 48 (slim bootstrap)
- Default "restrict colours" setting changed to off
- Settings page moved from `add_menu_page` to `add_options_page`
- Release workflow updated for PHP 8.1
- Version bumped to 2.0.0

### Removed
- BB 2.8 support and all legacy compatibility code (~1,000 lines in Phase 1)
- `color-swatch-fixer.php` — DOM hacks no longer needed after filter fix
- `customizer-handler.js` — AJAX error tracking removed
- Redundant Redux store injection (GP colours already in "Global Colors" via BB's native WP integration)
- Redundant inline CSS variables (GeneratePress already outputs `--wp--preset--color--*`)
- Sync lock transient (single callback, no race condition)
- `size` parameter from shortcode documentation (never implemented)

## 1.1.0 — 2025-02-18

### Fixed
- GP colours not appearing in BB's background layers colour picker (Presets tab empty)
- Root cause: `fl_wp_core_global_colors` filter was being added then immediately removed

### Removed
- BB 2.8 support (4 legacy files, ~1,000 lines)
- DOM manipulation hacks (`color-swatch-fixer.php`, classic preset activator, etc.)

## 1.0.9 — 2024-05-16

### Fixed
- Duplicated colours appearing in BB Global Colors UI after updates
- Colour sync process now properly updates existing colours instead of adding new ones

## 1.0.8 — 2024-05-01

### Fixed
- Colour picker tab now defaults to presets and still allows normal tab switching

### Removed
- `color-picker-bb29.js` — functionality moved to main integration file

## 1.0.7 — 2025-04-22

### Changed
- Improved colour grid shortcode styling with better padding and readability
- Removed deprecated `size` parameter from colour grid shortcode

## 1.0.6 — 2025-04-12

### Fixed
- GeneratePress colour palette not appearing in WordPress Admin Iris colour picker
- Added separate scripts for Admin and BB to prevent conflicts

## 1.0.4 — 2025-04-09

### Fixed
- 500 error when saving in WordPress Customizer
- Colours not initialising properly in BB Global Styles
- Compatibility with both BB 2.8.x and 2.9+
- Preset tabs not appearing as default in colour pickers

## 1.0.3 — 2025-04-04

### Added
- Settings page for colour restriction control

### Fixed
- Colour picker integration with BB 2.9

## 1.0.1 — 2025-03-20

### Changed
- Added caching to prevent redundant colour synchronisation
- Added `GPBI_DEBUG` constant for controlled troubleshooting
- Reduced unnecessary database writes
- Transient-based colour sync scheduling

## 1.0.0 — 2025-03-19

### Added
- Colour picker integration for BB 2.9+ React colour picker
- Automatic Presets tab selection
- Brand consistency controls (hide add colour, remove saved colours)
- GitHub releases and auto-updater

## 0.6.0

### Added
- GeneratePress Font Library integration with Beaver Builder
- Brand consistency controls
- Renamed plugin to GP Beaver Integration

## 0.5.0 — 2025-02-20

### Added
- Automatic GitHub updates via releases

## 0.4.2 — 2025-02-13

### Added
- Colour grid shortcode for style guides

## 0.1.0 — 2024-10-12

- Initial release
