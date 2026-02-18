<?php
/**
 * Plugin Name: GP Beaver Integration
 * Plugin URI: https://github.com/weavedigitalstudio/gp-beaver-integration
 * Description: Integrates GeneratePress Global Colors and Font Library with Beaver Builder page builder for brand consistency.
 * Version: 2.0.0
 * Author: Weave Digital Studio, Gareth Bissland
 * Author URI: https://weave.co.nz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gp-beaver-integration
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * GitHub Plugin URI: weavedigitalstudio/gp-beaver-integration
 * Primary Branch: main
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('GPBI_VERSION', '2.0.0');
define('GPBI_FILE', __FILE__);
define('GPBI_DIR', plugin_dir_path(__FILE__));
define('GPBI_URL', plugin_dir_url(__FILE__));

if (!defined('GPBI_DEBUG')) {
    define('GPBI_DEBUG', false);
}

// Always-loaded modules.
require_once GPBI_DIR . 'inc/hooks.php';
require_once GPBI_DIR . 'inc/settings-page.php';
require_once GPBI_DIR . 'inc/github-updater.php';
require_once GPBI_DIR . 'inc/shortcodes.php';

// Beaver Builder–dependent modules — load once BB is confirmed present.
add_action('plugins_loaded', static function (): void {
    if (!defined('FL_BUILDER_VERSION')) {
        return;
    }

    require_once GPBI_DIR . 'inc/color-sync.php';
    require_once GPBI_DIR . 'inc/font-integration.php';

    GPBeaverIntegration\Fonts\FontIntegration::init();
});
