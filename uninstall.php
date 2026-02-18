<?php
declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove plugin option.
delete_option('gpbi_settings');

// Remove all transients.
$transients = [
    'gpbi_formatted_colors',
    'gpbi_colors_synced',
    'gpbi_force_color_update',
    'gpbi_github_response',
    'gpbi_customizer_errors',
];

foreach ($transients as $key) {
    delete_transient($key);
}
