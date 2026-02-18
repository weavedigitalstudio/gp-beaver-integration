<?php
declare(strict_types=1);

namespace GPBeaverIntegration\Updater;

defined('ABSPATH') || exit;

/**
 * Checks GitHub Releases for plugin updates and integrates with the
 * WordPress plugin update system.
 */
final class GitHubUpdater {

    private string $file;
    private string $basename;
    private ?array $plugin          = null;
    private ?object $github_response = null;

    private string $github_username = 'weavedigitalstudio';
    private string $github_repo    = 'gp-beaver-integration';

    private const ICON_SMALL = 'https://weave-hk-github.b-cdn.net/weave/icon-128x128.png';
    private const ICON_LARGE = 'https://weave-hk-github.b-cdn.net/weave/icon-256x256.png';

    private const CACHE_KEY              = 'gpbi_github_response';
    private const CACHE_DURATION         = 4;  // hours
    private const ERROR_CACHE_DURATION   = 1;  // hour

    private static ?self $instance = null;

    private function __construct(string $file) {
        $this->file     = $file;
        $this->basename = plugin_basename($file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    public static function init(string $file): self {
        if (self::$instance === null) {
            self::$instance = new self($file);
        }
        return self::$instance;
    }

    private function get_plugin_data(): ?array {
        if ($this->plugin === null && is_admin() && function_exists('get_plugin_data')) {
            $this->plugin = get_plugin_data($this->file);
        }
        return $this->plugin;
    }

    private function normalize_version(string $version): string {
        return ltrim($version, 'v');
    }

    /**
     * Fetch latest release info from GitHub with caching.
     */
    private function get_repository_info(): object|false {
        if ($this->github_response !== null) {
            return $this->github_response;
        }

        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            if (is_array($cached) && ($cached['status'] ?? '') === 'error') {
                return false;
            }
            $this->github_response = $cached;
            return $this->github_response;
        }

        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo,
        );

        $response = wp_remote_get($request_uri, [
            'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version')],
        ]);

        if (is_wp_error($response)) {
            error_log('GitHub API request failed: ' . $response->get_error_message());
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            error_log('GitHub API request failed with response code: ' . wp_remote_retrieve_response_code($response));
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (!isset($body->tag_name, $body->assets) || empty($body->assets)) {
            error_log('GitHub API response missing required fields or assets.');
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        $body->zipball_url = $body->assets[0]->browser_download_url ?? '';

        if (empty($body->zipball_url)) {
            error_log('No valid download URL found for the latest release.');
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        set_transient(self::CACHE_KEY, $body, self::CACHE_DURATION * HOUR_IN_SECONDS);
        $this->github_response = $body;
        return $this->github_response;
    }

    public function check_update(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_data = $this->get_plugin_data();
        $repo_info   = $this->get_repository_info();

        if (!$repo_info || !$plugin_data) {
            return $transient;
        }

        $current = $this->normalize_version($plugin_data['Version']);
        $latest  = $this->normalize_version($repo_info->tag_name);

        if (version_compare($latest, $current, '>')) {
            $transient->response[$this->basename] = (object) [
                'slug'        => dirname($this->basename),
                'package'     => $repo_info->zipball_url,
                'new_version' => $latest,
                'tested'      => get_bloginfo('version'),
                'icons'       => ['1x' => self::ICON_SMALL, '2x' => self::ICON_LARGE],
            ];
        } else {
            unset($transient->response[$this->basename]);

            if (!isset($transient->no_update[$this->basename])) {
                $transient->no_update[$this->basename] = (object) [
                    'slug'        => dirname($this->basename),
                    'plugin'      => $this->basename,
                    'new_version' => $latest,
                    'url'         => '',
                    'package'     => '',
                    'icons'       => ['1x' => self::ICON_SMALL, '2x' => self::ICON_LARGE],
                ];
            }
        }

        return $transient;
    }

    /**
     * @param object|false $res
     */
    public function plugin_info(mixed $res, string $action, object $args): mixed {
        if ($action !== 'plugin_information' || $args->slug !== dirname($this->basename)) {
            return $res;
        }

        $plugin_data = $this->get_plugin_data();
        $repo_info   = $this->get_repository_info();

        if (!$repo_info || !$plugin_data) {
            return $res;
        }

        $info                 = new \stdClass();
        $info->name           = $plugin_data['Name'] ?? 'GP Beaver Integration';
        $info->slug           = dirname($this->basename);
        $info->version        = $this->normalize_version($repo_info->tag_name);
        $info->author         = $plugin_data['Author'] ?? 'Weave Digital Studio';
        $info->author_profile = $plugin_data['AuthorURI'] ?? 'https://weave.co.nz';
        $info->tested         = get_bloginfo('version');
        $info->last_updated   = $repo_info->published_at ?? '';
        $info->sections       = [
            'description' => $plugin_data['Description'] ?? 'Integrates GeneratePress with Beaver Builder for consistent branding by synchronising colours and fonts.',
            'changelog'   => $repo_info->body ?? '',
        ];
        $info->icons         = ['1x' => self::ICON_SMALL, '2x' => self::ICON_LARGE];
        $info->download_link = $repo_info->zipball_url;

        return $info;
    }

    /**
     * @param bool|\WP_Error $response
     * @param array<string, mixed> $hook_extra
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function after_install(mixed $response, array $hook_extra, array $result): array {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        delete_transient(self::CACHE_KEY);

        return $result;
    }
}
