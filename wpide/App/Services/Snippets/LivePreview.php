<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Runtime\Runtime;

/**
 * The site inside the editor's preview frame: live CSS while typing and the visual selector picker.
 *
 * Only active for snippet editors on URLs carrying ?wpide-preview=css|pick. The page talks to the editor
 * through postMessage, accepting messages from the admin origin only. With wpide-preview-snippet=<id> the
 * saved version of that snippet is left out so the preview shows the code being edited instead.
 */
class LivePreview
{
    const PARAM = 'wpide-preview';
    const SNIPPET_PARAM = 'wpide-preview-snippet';

    public static function boot()
    {
        add_action('wp_head', [__CLASS__, 'print'], 1);
        add_filter('show_admin_bar', [__CLASS__, 'hideAdminBar'], 100);
        add_action('plugins_loaded', [__CLASS__, 'skipEditedSnippet'], 1);
    }

    public static function mode(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $mode = isset($_GET[self::PARAM]) ? sanitize_key(wp_unslash($_GET[self::PARAM])) : '';

        return in_array($mode, ['css', 'pick'], true) ? $mode : '';
    }

    public static function allowed(): bool
    {
        return self::mode() !== '' && function_exists('current_user_can') && current_user_can(Capabilities::EDIT_MARKUP);
    }

    public static function skipEditedSnippet()
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $id = isset($_GET[self::SNIPPET_PARAM]) ? (int) $_GET[self::SNIPPET_PARAM] : 0;

        if ($id && self::mode() === 'css' && is_user_logged_in()) {
            add_action('init', function () use ($id) {
                if (current_user_can(Capabilities::EDIT_MARKUP)) {
                    Runtime::setEditedSnippetId($id);
                }
            }, 0);
        }
    }

    public static function hideAdminBar($show)
    {
        return self::mode() !== '' ? false : $show;
    }

    public static function print()
    {
        if (!self::allowed()) {
            return;
        }

        $config = ['mode' => self::mode(), 'origin' => self::origin(admin_url())];
        $script = (string) file_get_contents(__DIR__ . '/assets/live-preview.js');

        echo '<script id="wpide-live-preview">window.wpideLivePreview = ' . wp_json_encode($config) . ";\n" . $script . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
    }

    protected static function origin(string $url): string
    {
        $parts = wp_parse_url($url);

        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
