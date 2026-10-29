<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Runtime\Guard;

/**
 * Snippets settings, each stored in its own option so the runtime only reads what it needs.
 *
 * As in WPCode Lite, error emails, revisions, access control (including the "disable PHP" switch) and testing
 * mode are Pro settings; on the free plan they read as off and are not saved.
 */
class Settings
{
    public static function get(): array
    {
        $pro = Plans::pro();

        return [
            'paused' => (bool) get_option(Guard::PAUSE_OPTION, false),
            'disable_php' => (bool) get_option('wpide_snippets_disable_php', false),
            'disable_php_constant' => defined('WPIDE_SNIPPETS_DISABLE_PHP') && WPIDE_SNIPPETS_DISABLE_PHP,
            'safe_mode_constant' => defined('WPIDE_SNIPPETS_SAFE_MODE') && WPIDE_SNIPPETS_SAFE_MODE,
            'safe_mode_file' => defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR . '/' . Guard::SENTINEL),
            'notify_errors' => $pro && (bool) get_option('wpide_snippets_notify_errors', true),
            'quarantine_threshold' => Guard::threshold(),
            'revisions_keep' => $pro ? Revisions::keepCount() : 0,
            'access' => Capabilities::access(),
            'testing_mode' => $pro && TestingMode::isEnabled(),
            'safe_mode_url' => add_query_arg('wpide-safe-mode', '1', home_url('/')),
            'network_access' => Network::accessPolicy(),
            'access_control' => Plans::feature('access_control'),
        ];
    }

    public static function save(array $input): array
    {
        if (array_key_exists('paused', $input)) {
            Guard::setPaused((bool) $input['paused']);
        }

        if (!Plans::pro()) {
            $input = array_diff_key($input, array_flip(['disable_php', 'notify_errors', 'revisions_keep', 'access', 'testing_mode']));
        }

        // Role access and the "disable PHP" switch are access control, from the Pro plan.
        if (!Plans::allows('access_control')) {
            $input = array_diff_key($input, array_flip(['disable_php', 'access']));
        }

        if (array_key_exists('disable_php', $input)) {
            update_option('wpide_snippets_disable_php', (bool) $input['disable_php'] ? 1 : 0, true);
        }

        if (array_key_exists('notify_errors', $input)) {
            update_option('wpide_snippets_notify_errors', (bool) $input['notify_errors'] ? 1 : 0, false);
        }

        if (array_key_exists('quarantine_threshold', $input)) {
            update_option(Guard::THRESHOLD_OPTION, max(1, min(100, (int) $input['quarantine_threshold'])), false);
        }

        if (array_key_exists('revisions_keep', $input)) {
            update_option(Revisions::KEEP_OPTION, max(0, min(100000, (int) $input['revisions_keep'])), false);
        }

        if (array_key_exists('network_access', $input) && is_multisite() && is_super_admin()) {
            Network::setAccessPolicy((string) $input['network_access']);
        }

        if (array_key_exists('access', $input) && is_array($input['access'])) {
            $roles = array_keys(wp_roles()->roles);
            $access = [];

            foreach (Capabilities::GRANTABLE as $cap) {
                $access[$cap] = array_values(array_intersect((array) ($input['access'][$cap] ?? []), $roles));
            }

            update_option(Capabilities::ACCESS_OPTION, $access, true);
        }

        if (array_key_exists('testing_mode', $input)) {
            TestingMode::setEnabled((bool) $input['testing_mode']);
        }

        return self::get();
    }
}
