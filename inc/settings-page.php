<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Settings;

defined('ABSPATH') || exit;

/**
 * Register plugin settings with the Settings API.
 */
function register(): void {
    register_setting('gpbi_settings', 'gpbi_settings', [
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize',
    ]);
}

/**
 * Sanitize settings before saving.
 *
 * @param array<string, mixed> $input Raw input.
 * @return array<string, int>
 */
function sanitize(mixed $input): array {
    $input = is_array($input) ? $input : [];
    return [
        'restrict_colors' => isset($input['restrict_colors']) ? 1 : 0,
    ];
}

/**
 * Add the settings page under Settings menu.
 */
function add_page(): void {
    add_options_page(
        'GP Beaver Integration Settings',
        'GP Beaver Integration',
        'manage_options',
        'gp-beaver-integration',
        __NAMESPACE__ . '\\render',
    );
}

/**
 * Render the settings page.
 */
function render(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings        = get_option('gpbi_settings', ['restrict_colors' => 0]);
    $restrict_colors = $settings['restrict_colors'] ?? 0;

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('gpbi_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Colour Restrictions</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gpbi_settings[restrict_colors]" value="1" <?php checked($restrict_colors, 1); ?>>
                            Restrict colour picker to Global Colours only
                        </label>
                        <p class="description">
                            When enabled, users can only select from your GeneratePress Global Colours in the Beaver Builder colour picker.<br>
                            When disabled, users can add custom colours to the colour picker.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>

        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <img src="https://weave-hk-github.b-cdn.net/weave/icon-128x128.png" alt="Plugin icon" width="48" height="48" style="border-radius: 4px;">
                <h2 style="margin: 0;">About GP Beaver Integration</h2>
            </div>
            <p>Integrates GeneratePress Global Colors and Font Library with Beaver Builder for brand consistency. Colours sync automatically from GeneratePress to Beaver Builder's colour picker presets.</p>
            <p><strong>Version:</strong> <?php echo esc_html(GPBI_VERSION); ?></p>
            <p><strong>Author:</strong> <a href="https://weave.co.nz" target="_blank" rel="noopener noreferrer">Weave Digital Studio</a></p>
            <p><strong>Repository:</strong> <a href="https://github.com/weavedigitalstudio/gp-beaver-integration" target="_blank" rel="noopener noreferrer">GitHub</a></p>
            <p><strong>Requires:</strong> PHP 8.1+, WordPress 6.6+, GeneratePress + Beaver Builder 2.9+</p>
        </div>

        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>Colour Grid Shortcode</h2>
            <p>Display your colour palette anywhere on your site using the shortcode:</p>
            <pre><code>[gp_global_color_grid]</code></pre>

            <h3>Optional Parameters</h3>
            <ul>
                <li><code>columns</code>: Number of columns (default: 4)</li>
                <li><code>names</code>: Show colour names (default: true)</li>
                <li><code>values</code>: Show colour hex values (default: true)</li>
            </ul>

            <h3>Example</h3>
            <pre><code>[gp_global_color_grid columns="3" names="true" values="false"]</code></pre>

            <p>This creates a responsive grid showing all your GeneratePress Global Colours with their names, CSS variables, and hex values.</p>

            <div style="margin-top: 20px;">
                <img src="https://weave-hk-github.b-cdn.net/screens/global-color-grid.png" alt="Global Colour Grid Example" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
    </div>
    <?php
}

// Hook registration.
add_action('admin_init', __NAMESPACE__ . '\\register');
add_action('admin_menu', __NAMESPACE__ . '\\add_page');
