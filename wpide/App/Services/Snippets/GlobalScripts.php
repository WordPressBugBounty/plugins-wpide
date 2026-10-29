<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Runtime\Guard;

/**
 * Site-wide header, body and footer scripts (tracking codes, verification tags, chat widgets).
 *
 * Stored as one option, revisioned like snippets (snippet id 0), staged in testing mode, and suppressible per
 * page from the page scripts box.
 */
class GlobalScripts
{
    const OPTION = 'wpide_global_scripts';
    const AREAS = ['header', 'body', 'footer'];

    public static function boot()
    {
        if (!Snippets::available() || Guard::disabledReason()) {
            return;
        }

        add_action('wp_head', function () {
            self::output('header');
        }, 10);
        add_action('wp_body_open', function () {
            self::output('body');
        }, 10);
        add_action('wp_footer', function () {
            self::output('footer');
        }, 10);
    }

    public static function get(bool $staged = false): array
    {
        $value = get_option(self::OPTION, []);
        $value = array_merge(array_fill_keys(self::AREAS, ''), is_array($value) ? array_intersect_key($value, array_flip(self::AREAS)) : []);

        if ($staged) {
            $draft = get_option(self::OPTION . '_draft');

            if (is_array($draft)) {
                $value = array_merge($value, array_intersect_key($draft, array_flip(self::AREAS)));
            }
        }

        return $value;
    }

    public static function hasDraft(): bool
    {
        return is_array(get_option(self::OPTION . '_draft'));
    }

    public static function save(array $scripts)
    {
        $scripts = array_map('strval', array_intersect_key($scripts, array_flip(self::AREAS))) + self::get();

        if (Plans::pro() && TestingMode::isEnabled()) {
            update_option(self::OPTION . '_draft', $scripts, false);

            return;
        }

        update_option(self::OPTION, $scripts, true);
        self::recordRevision($scripts, 'global');
    }

    public static function publishDraft()
    {
        $draft = get_option(self::OPTION . '_draft');

        if (is_array($draft)) {
            delete_option(self::OPTION . '_draft');
            update_option(self::OPTION, array_merge(self::get(), $draft), true);
            self::recordRevision(self::get(), 'testing');
        }
    }

    public static function discardDraft()
    {
        delete_option(self::OPTION . '_draft');
    }

    protected static function recordRevision(array $scripts, string $source)
    {
        global $wpdb;

        // Revisions are Pro (WPCode Lite: "Header & Footer revisions" upsell).
        if (!Plans::pro() || !Schema::isInstalled()) {
            return;
        }

        $table = Schema::revisionsTable();
        $hash = Revisions::hash($scripts);
        $last = $wpdb->get_var("SELECT content_hash FROM $table WHERE snippet_id = 0 ORDER BY id DESC LIMIT 1"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($last === $hash) {
            return;
        }

        $wpdb->insert($table, [
            'snippet_id' => 0,
            'author_id' => get_current_user_id(),
            'source' => $source,
            'content_hash' => $hash,
            'payload' => wp_json_encode($scripts),
            'created_at' => current_time('mysql', true),
        ]);
    }

    public static function revisions(): array
    {
        return Plans::pro() ? Revisions::forSnippet(0) : [];
    }

    public static function output(string $area)
    {
        if (Plans::pro() && is_singular() && PageScripts::suppressesGlobal(get_queried_object_id(), $area)) {
            return;
        }

        $scripts = self::get(Plans::pro() && TestingMode::isPreviewing());
        $code = $scripts[$area] ?? '';

        if (trim($code) !== '') {
            echo SmartTags::replace($code, ['code_type' => 'html']) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }
}
