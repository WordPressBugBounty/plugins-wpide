<?php

namespace WPIDE\App\Services\Snippets\Runtime;

use WPIDE\App\Services\Snippets\Network;

use WPIDE\App\Services\Snippets\Manifest;
use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Registry\Conditions;
use WPIDE\App\Services\Snippets\Registry\Locations;
use WPIDE\App\Services\Snippets\Registry\Types;
use WPIDE\App\Services\Snippets\Snippets;
use WPIDE\App\Services\Snippets\TestingMode;

/**
 * Snippet runtime. Loaded on every request independently of the WPIDE admin application.
 *
 * Phases:
 * 1. plugins_loaded:5  PHP snippets in everywhere / admin_only / frontend_only
 * 2. wp                PHP snippets in frontend_cl (page conditions available)
 * 3. output hooks      head, body, footer, admin, store and membership hooks, content, lists, CSS selectors
 * 4. on demand         shortcodes, the snippet block, wpide_run_snippet()
 */
class Runtime
{
    /** @var array Ids executed or rendered on this request, in order. */
    protected static $executed = [];

    /** @var array|null id => snippet, including the testing-mode overlay for previewing users. */
    protected static $snippets;

    /** @var array|null location => [snippet, ...] */
    protected static $byLocation;

    /** @var int|null */
    protected static $editedId;

    /** @var bool */
    protected static $started = false;

    public static function boot()
    {
        add_action('plugins_loaded', [__CLASS__, 'start'], 1);
    }

    public static function start()
    {
        if (self::$started || !Snippets::available()) {
            return;
        }

        self::$started = true;
        Guard::init();

        if (Guard::disabledReason()) {
            add_action('admin_notices', [__CLASS__, 'safeModeNotice']);
            add_action('init', [Shortcodes::class, 'registerDisabled']);

            return;
        }

        add_action('plugins_loaded', [__CLASS__, 'runEarly'], 5);
        add_action('wp', [__CLASS__, 'runConditional'], 10);
        add_action('init', [Shortcodes::class, 'register'], 20);

        self::registerOutputHooks();
        self::registerCustomHooks();
        ContentInserter::hooks();
        SelectorInserter::hooks();
    }

    public static function started(): bool
    {
        return self::$started;
    }

    protected static function registerOutputHooks()
    {
        $locations = Locations::runtime();

        foreach (array_keys(self::byLocation()) as $location) {
            $definition = $locations[$location] ?? null;

            if (!$definition) {
                continue;
            }

            if (!empty($definition['hook'])) {
                add_action($definition['hook'], function () use ($location) {
                    echo self::outputFor($location, 'hook'); // phpcs:ignore WordPress.Security.EscapeOutput
                }, $definition['priority']);
            } elseif (!empty($definition['filter'])) {
                add_filter($definition['filter'], function ($content) use ($location, $definition) {
                    $output = self::outputFor($location, 'hook');

                    return ($definition['position'] ?? 'after') === 'before' ? $output . $content : $content . $output;
                }, $definition['priority']);
            }
        }
    }

    /**
     * "Custom action hook" snippets: each runs on the action it names, at its own priority. PHP runs, other
     * types print their output. Conditions are checked when the action fires.
     */
    protected static function registerCustomHooks()
    {
        foreach (self::snippetsFor('custom_hook') as $snippet) {
            $hook = trim((string) (((array) ($snippet['location_extra'] ?? []))['hook_name'] ?? ''));

            if ($hook === '' || !Locations::isValidHookName($hook)) {
                continue;
            }

            add_action($hook, function () use ($snippet) {
                if (self::shouldRun($snippet)) {
                    echo self::renderTracked($snippet, 'hook'); // phpcs:ignore WordPress.Security.EscapeOutput
                }
            }, (int) ($snippet['priority'] ?? 10), 0);
        }
    }

    public static function runEarly()
    {
        $locations = ['everywhere', is_admin() ? 'admin_only' : 'frontend_only'];

        foreach ($locations as $location) {
            foreach (self::snippetsFor($location) as $snippet) {
                if (self::shouldRun($snippet)) {
                    self::renderTracked($snippet, 'early');
                }
            }
        }
    }

    public static function runConditional()
    {
        if (is_admin()) {
            return;
        }

        foreach (self::snippetsFor('frontend_cl') as $snippet) {
            if (self::shouldRun($snippet)) {
                self::renderTracked($snippet, 'conditional');
            }
        }
    }

    /**
     * Snippets that may run, with the testing-mode overlay applied for previewing users.
     */
    public static function manifestSnippets(): array
    {
        if (self::$snippets !== null) {
            return self::$snippets;
        }

        $snippets = Manifest::get()['snippets'];

        if (Plans::pro() && TestingMode::isPreviewing()) {
            $snippets = TestingMode::overlay($snippets);
        }

        // Network snippets from the main site (negative ids) run alongside this site's own.
        $snippets += Network::forCurrentSite();

        return self::$snippets = $snippets;
    }

    public static function byLocation(): array
    {
        if (self::$byLocation !== null) {
            return self::$byLocation;
        }

        $byLocation = [];

        foreach (self::manifestSnippets() as $snippet) {
            // Pro locations and types insert nothing without WPIDE Pro (as in WPCode Lite).
            if ($snippet['insert_method'] === 'auto' && $snippet['location'] !== '' && !Locations::isLocked($snippet['location']) && !Types::isLocked($snippet['code_type'])) {
                $byLocation[$snippet['location']][] = $snippet;
            }
        }

        foreach ($byLocation as &$list) {
            usort($list, function ($a, $b) {
                return $a['priority'] === $b['priority'] ? $a['id'] <=> $b['id'] : $a['priority'] <=> $b['priority'];
            });
        }

        return self::$byLocation = $byLocation;
    }

    public static function snippetsFor(string $location): array
    {
        return self::byLocation()[$location] ?? [];
    }

    public static function reset()
    {
        self::$snippets = null;
        self::$byLocation = null;
        self::$executed = [];
        self::$editedId = null;
        Manifest::flush();
    }

    /**
     * Location context, schedule, device and conditions.
     */
    public static function shouldRun(array $snippet): bool
    {
        if ((int) $snippet['id'] === self::editedSnippetId()) {
            return false;
        }

        $definition = $snippet['insert_method'] === 'auto' ? Locations::get($snippet['location']) : null;

        if ($definition) {
            if ($definition['context'] === 'frontend' && is_admin() && !wp_doing_ajax()) {
                return false;
            }

            if ($definition['context'] === 'admin' && !is_admin()) {
                return false;
            }

            if (!empty($definition['plugin']) && !Locations::isAvailable($snippet['location'])) {
                return false;
            }
        }

        if (!self::inSchedule($snippet)) {
            return false;
        }

        if (!self::matchesDevice($snippet)) {
            return false;
        }

        return Conditions::passes($snippet);
    }

    public static function inSchedule(array $snippet, ?\DateTimeImmutable $now = null): bool
    {
        // Schedules are Pro: WPCode Lite ignores a stored schedule.
        if (!Plans::pro()) {
            return true;
        }

        $schedule = $snippet['schedule'] ?? [];
        $start = trim((string) ($schedule['start'] ?? ''));
        $end = trim((string) ($schedule['end'] ?? ''));

        if ($start === '' && $end === '') {
            return true;
        }

        $timezone = wp_timezone();
        $now = $now ?: new \DateTimeImmutable('now', $timezone);

        try {
            if ($start !== '' && $now < new \DateTimeImmutable($start, $timezone)) {
                return false;
            }

            if ($end !== '' && $now > new \DateTimeImmutable($end, $timezone)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    protected static function matchesDevice(array $snippet): bool
    {
        $device = $snippet['device_type'] ?? 'any';

        if ($device === 'any' || !Plans::pro()) {
            return true;
        }

        return $device === (wp_is_mobile() ? 'mobile' : 'desktop');
    }

    public static function renderTracked(array $snippet, string $phase, array $attributes = []): string
    {
        $output = Executor::render($snippet, $phase, $attributes);
        self::$executed[] = (int) $snippet['id'];

        return $output;
    }

    public static function outputFor(string $location, string $phase = 'hook'): string
    {
        $output = '';

        foreach (self::snippetsFor($location) as $snippet) {
            if (self::shouldRun($snippet)) {
                $output .= self::renderTracked($snippet, $phase);
            }
        }

        return $output;
    }

    public static function executedIds(): array
    {
        return array_values(array_unique(self::$executed));
    }

    /**
     * The snippet being saved or activated in this admin request is not executed, so its new version can be
     * checked without "cannot redeclare" conflicts with the old one.
     */
    public static function editedSnippetId(): int
    {
        if (self::$editedId !== null) {
            return self::$editedId;
        }

        self::$editedId = 0;

        // phpcs:disable WordPress.Security.NonceVerification
        if (!is_admin() || !wp_doing_ajax() || sanitize_key(wp_unslash($_GET['action'] ?? '')) !== 'wpide_request') {
            return 0;
        }

        $route = isset($_GET['req']) ? sanitize_text_field(wp_unslash($_GET['req'])) : '';
        // phpcs:enable

        if (!preg_match('#^/?snippets/(save|activate|status|execute|testing/publish)#', $route)) {
            return 0;
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        $id = is_array($body) ? (int) ($body['id'] ?? ($body['snippet']['id'] ?? 0)) : 0;

        if ($id && function_exists('current_user_can') && current_user_can('wpide_snippets_view')) {
            self::$editedId = $id;
        }

        return self::$editedId;
    }

    public static function setEditedSnippetId(int $id)
    {
        self::$editedId = $id;
    }

    public static function safeModeNotice()
    {
        if (!current_user_can('wpide_snippets_view')) {
            return;
        }

        $reasons = [
            'constant' => __('WPIDE_SNIPPETS_SAFE_MODE is defined in wp-config.php.', 'wpide'),
            'file' => __('The file wp-content/wpide-snippets-safe-mode exists.', 'wpide'),
            'paused' => __('All snippets are paused from the Snippets screen.', 'wpide'),
            'url' => __('Safe mode is on for your browser.', 'wpide'),
        ];
        $reason = Guard::disabledReason();
        $exit = $reason === 'url' ? ' <a href="' . esc_url(add_query_arg('wpide-safe-mode', '0')) . '">' . esc_html__('Leave safe mode', 'wpide') . '</a>' : '';

        printf(
            '<div class="notice notice-warning wpide-notice" data-slug="wpide"><p><strong>%s</strong> %s%s</p></div>',
            esc_html__('WPIDE snippets are not running (safe mode).', 'wpide'),
            esc_html($reasons[$reason] ?? ''),
            $exit // phpcs:ignore WordPress.Security.EscapeOutput
        );
    }
}
