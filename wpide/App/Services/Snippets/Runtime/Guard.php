<?php

namespace WPIDE\App\Services\Snippets\Runtime;

use WPIDE\App\Services\Snippets\Capabilities;
use WPIDE\App\Services\Snippets\Events;
use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Repository;

/**
 * Execution safety: kill switches, failure attribution and quarantine.
 *
 * Kill switches work without the cloud and without an admin screen:
 * - define('WPIDE_SNIPPETS_SAFE_MODE', true) in wp-config.php
 * - an empty file named wpide-snippets-safe-mode in wp-content
 * - wp wpide snippets safe-mode on
 * - ?wpide-safe-mode=1 (only for a logged-in user who can activate snippets; remembered with a cookie, ?wpide-safe-mode=0 leaves)
 *
 * Failures are attributed to the snippet that was running. A failed activation keeps the snippet inactive,
 * an admin-side failure quarantines immediately, and frontend failures quarantine after a threshold so one
 * visitor-triggered path cannot switch off unrelated snippets.
 */
class Guard
{
    const SENTINEL = 'wpide-snippets-safe-mode';
    const PAUSE_OPTION = 'wpide_snippets_paused';
    const COOKIE = 'wpide_snippets_safe_mode';
    const THRESHOLD_OPTION = 'wpide_snippets_quarantine_threshold';
    const FAILURES_TRANSIENT = 'wpide_snippets_failures';

    /** @var array|null The snippet currently executing: ['id', 'phase', 'activation' => bool]. */
    protected static $current;

    /** @var array Generated PHP file path => snippet id, for attributing failures in later callbacks. */
    protected static $files = [];

    /** @var bool */
    protected static $registered = false;

    /** @var string|null */
    protected static $disabledReason;

    public static function init()
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        register_shutdown_function([__CLASS__, 'shutdown']);
    }

    /**
     * Why execution is disabled on this request, or null when snippets may run.
     */
    public static function disabledReason(): ?string
    {
        if (self::$disabledReason !== null) {
            return self::$disabledReason ?: null;
        }

        $reason = '';

        if (defined('WPIDE_SNIPPETS_SAFE_MODE') && WPIDE_SNIPPETS_SAFE_MODE) {
            $reason = 'constant';
        } elseif (defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR . '/' . self::SENTINEL)) {
            $reason = 'file';
        } elseif (get_option(self::PAUSE_OPTION)) {
            $reason = 'paused';
        } elseif (self::urlSafeMode()) {
            $reason = 'url';
        }

        self::$disabledReason = $reason;

        return $reason ?: null;
    }

    public static function resetCache()
    {
        self::$disabledReason = null;
    }

    protected static function urlSafeMode(): bool
    {
        // phpcs:disable WordPress.Security.NonceVerification
        $requested = isset($_GET['wpide-safe-mode']) ? sanitize_text_field(wp_unslash($_GET['wpide-safe-mode'])) : null;
        $cookie = !empty($_COOKIE[self::COOKIE]);
        // phpcs:enable

        if ($requested === null && !$cookie) {
            return false;
        }

        if (!function_exists('wp_get_current_user') || !current_user_can(Capabilities::ACTIVATE)) {
            return false;
        }

        if ($requested === '0') {
            if (!headers_sent()) {
                setcookie(self::COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            }

            return false;
        }

        if ($requested !== null && !$cookie && !headers_sent()) {
            setcookie(self::COOKIE, '1', 0, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        }

        return true;
    }

    public static function setPaused(bool $paused)
    {
        update_option(self::PAUSE_OPTION, $paused ? 1 : 0, true);
        self::resetCache();
    }

    public static function enter(array $snippet, string $phase, bool $activation = false)
    {
        self::$current = ['id' => (int) $snippet['id'], 'title' => $snippet['title'] ?? '', 'phase' => $phase, 'activation' => $activation];
    }

    public static function leave()
    {
        self::$current = null;
    }

    public static function current(): ?array
    {
        return self::$current;
    }

    public static function registerFile(string $file, int $snippetId)
    {
        self::$files[wp_normalize_path($file)] = $snippetId;
    }

    public static function snippetForFile(string $file): int
    {
        $file = wp_normalize_path($file);

        if (isset(self::$files[$file])) {
            return self::$files[$file];
        }

        // Generated files are named <id>-<hash>.php.
        if (strpos($file, '/wpide-snippets/php/') !== false && preg_match('#/(-?\d+)-[a-f0-9]+\.php$#', $file, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Handle an error caught while a snippet ran (Throwable in the snippet body).
     */
    public static function caught(int $snippetId, \Throwable $error, string $phase, bool $activation = false)
    {
        self::fail($snippetId, [
            'message' => $error->getMessage(),
            'type' => get_class($error),
            'line' => $error->getLine(),
            'file' => $error->getFile(),
        ], $phase, $activation);
    }

    /**
     * Fatal errors not caught in the snippet body (later callbacks, compile errors, memory).
     */
    public static function shutdown()
    {
        $error = error_get_last();

        if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }

        $snippetId = self::snippetForFile((string) $error['file']);
        $phase = 'callback';
        $activation = false;

        if (!$snippetId && self::$current && strpos((string) $error['file'], "eval()'d code") !== false) {
            $snippetId = self::$current['id'];
        }

        if (self::$current && (!$snippetId || $snippetId === self::$current['id'])) {
            $snippetId = self::$current['id'];
            $phase = self::$current['phase'];
            $activation = self::$current['activation'];
        }

        if ($snippetId) {
            self::fail($snippetId, $error, $phase, $activation);
        }
    }

    protected static function fail(int $snippetId, array $error, string $phase, bool $activation)
    {
        if (!function_exists('get_option')) {
            return;
        }

        // A network snippet (negative id on a sub-site) is recorded on the main-site snippet.
        if ($snippetId < 0 && is_multisite()) {
            $error['site'] = get_current_blog_id();
            \WPIDE\App\Services\Snippets\Network::onMainSite(function () use ($snippetId, $error, $phase, $activation) {
                self::fail(abs($snippetId), $error, $phase, $activation);
            });

            return;
        }

        $record = [
            'message' => (string) ($error['message'] ?? ''),
            'type' => (string) ($error['type'] ?? ''),
            'line' => (int) ($error['line'] ?? 0),
            'phase' => $phase,
            'time' => time(),
            'url' => self::requestUrl(),
            'admin' => is_admin(),
        ] + (isset($error['site']) ? ['site' => (int) $error['site']] : []);

        try {
            if ($activation) {
                Repository::updateMeta($snippetId, ['last_error' => $record]);
                Events::log($snippetId, 'activation_failed', $record['message'], $record);

                return;
            }

            $quarantine = is_admin() || wp_doing_ajax() || (defined('WP_CLI') && WP_CLI) || self::countFailure($snippetId) >= self::threshold();

            Repository::updateMeta($snippetId, ['last_error' => $record] + ($quarantine ? ['quarantined' => true] : []));
            Events::log($snippetId, $quarantine ? 'quarantined' : 'error', $record['message'], $record);

            if ($quarantine) {
                self::notify($snippetId, $record);
            }
        } catch (\Throwable $e) {
            // Never let failure bookkeeping cause a second failure.
        }
    }

    public static function threshold(): int
    {
        return max(1, (int) get_option(self::THRESHOLD_OPTION, 3));
    }

    protected static function countFailure(int $snippetId): int
    {
        $failures = get_transient(self::FAILURES_TRANSIENT);
        $failures = is_array($failures) ? $failures : [];
        $window = time() - 10 * MINUTE_IN_SECONDS;
        $list = array_filter((array) ($failures[$snippetId] ?? []), function ($time) use ($window) {
            return $time >= $window;
        });
        $list[] = time();
        $failures[$snippetId] = array_values($list);
        set_transient(self::FAILURES_TRANSIENT, $failures, HOUR_IN_SECONDS);

        return count($list);
    }

    protected static function notify(int $snippetId, array $record)
    {
        // Email notifications are Pro (WPCode Lite: upsell); the event log and auto-deactivation are free.
        if (!Plans::pro() || !get_option('wpide_snippets_notify_errors', true) || !function_exists('wp_mail')) {
            return;
        }

        $snippet = Repository::find($snippetId);
        $title = $snippet ? $snippet['title'] : '#' . $snippetId;

        /* translators: 1: site name, 2: snippet title */
        $subject = sprintf(__('[%1$s] Snippet "%2$s" was disabled after an error', 'wpide'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $title);
        $body = sprintf(
            /* translators: 1: snippet title, 2: error message, 3: URL, 4: admin URL */
            __("WPIDE disabled the snippet \"%1\$s\" because it caused an error:\n\n%2\$s\n\nPage: %3\$s\n\nReview it here: %4\$s", 'wpide'),
            $title,
            $record['message'],
            $record['url'],
            admin_url('admin.php?page=wpide#/snippets/edit/' . $snippetId)
        );

        wp_mail(get_option('admin_email'), $subject, $body);
    }

    protected static function requestUrl(): string
    {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        // phpcs:enable

        return $host !== '' ? (is_ssl() ? 'https://' : 'http://') . $host . $uri : $uri;
    }
}
