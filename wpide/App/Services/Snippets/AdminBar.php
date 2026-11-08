<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Runtime\Guard;
use WPIDE\App\Services\Snippets\Runtime\Runtime;

/**
 * Admin bar menu for snippet managers: which snippets ran on the current page, recent errors,
 * testing mode and safe mode at a glance, with links into the Snippets screens.
 *
 * The toolbar renders in the footer, so snippets printed after it (late footer output) are not listed.
 */
class AdminBar
{
    const NODE = 'wpide-snippets';

    public static function boot()
    {
        add_action('admin_bar_menu', [__CLASS__, 'register'], 100);
        add_action('wp_head', [__CLASS__, 'styles']);
        add_action('admin_head', [__CLASS__, 'styles']);
    }

    public static function enabled(): bool
    {
        if (!is_admin_bar_showing() || !Snippets::available() || !current_user_can(Capabilities::VIEW) || !apply_filters('wpide_snippets_admin_bar', true)) {
            return false;
        }

        // The tables are normally created in wp-admin; a first visit to the front end creates them here.
        Snippets::maybeInstall();

        return Schema::isInstalled();
    }

    public static function register(\WP_Admin_Bar $bar)
    {
        if (!self::enabled()) {
            return;
        }

        $executed = Runtime::executedIds();
        $snippets = [];

        foreach (Runtime::manifestSnippets() as $snippet) {
            $snippets[(int) $snippet['id']] = $snippet;
        }

        $errors = (int) (Repository::counts()['error'] ?? 0);
        $testing = Plans::pro() && TestingMode::isEnabled();
        $disabled = Guard::disabledReason();
        $base = admin_url('admin.php?page=wpide#/snippets');

        $title = '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__('Snippets', 'wpide') . '</span>';

        if ($disabled) {
            $title .= ' <span class="wpide-ab-pill is-off">' . esc_html__('safe mode', 'wpide') . '</span>';
        } elseif ($testing) {
            $title .= ' <span class="wpide-ab-pill is-testing">' . esc_html__('testing', 'wpide') . '</span>';
        }

        if ($errors) {
            $title .= ' <span class="wpide-ab-pill is-error">' . (int) $errors . '</span>';
        }

        $bar->add_node(['id' => self::NODE, 'title' => $title, 'href' => $base]);

        if (!is_admin()) {
            $bar->add_group(['id' => self::NODE . '-page', 'parent' => self::NODE]);
            $bar->add_node([
                'id' => self::NODE . '-page-title',
                'parent' => self::NODE . '-page',
                'title' => sprintf(
                    /* translators: %d: number of snippets */
                    esc_html(_n('%d snippet ran on this page', '%d snippets ran on this page', count($executed), 'wpide')),
                    count($executed)
                ),
            ]);

            foreach (array_slice($executed, 0, 20) as $id) {
                $snippet = $snippets[$id] ?? null;
                /* translators: %d: snippet ID */
                $label = $snippet && $snippet['title'] !== '' ? $snippet['title'] : sprintf(__('Snippet #%d', 'wpide'), $id);

                $bar->add_node([
                    'id' => self::NODE . '-ran-' . $id,
                    'parent' => self::NODE . '-page',
                    'title' => esc_html($label) . ($snippet ? ' <span class="wpide-ab-type">' . esc_html(strtoupper($snippet['code_type'])) . '</span>' : ''),
                    'href' => admin_url('admin.php?page=wpide#/snippets/edit/' . $id),
                ]);
            }
        }

        $bar->add_group(['id' => self::NODE . '-links', 'parent' => self::NODE, 'meta' => ['class' => 'ab-sub-secondary']]);

        $links = [
            'all' => [__('All snippets', 'wpide'), $base],
            'add' => [__('Add snippet', 'wpide'), $base . '/edit'],
        ];

        if ($errors) {
            /* translators: %d: number of snippets with errors */
            $links['errors'] = [sprintf(_n('%d snippet with errors', '%d snippets with errors', $errors, 'wpide'), $errors), $base . '/log'];
        }

        if ($testing) {
            $links['testing'] = [__('Testing mode is on: review staged changes', 'wpide'), $base];
        }

        if (current_user_can(Capabilities::ACTIVATE) && !$disabled && !is_admin()) {
            $links['safe'] = [__('Preview this page without snippets', 'wpide'), add_query_arg('wpide-safe-mode', '1')];
        }

        foreach ($links as $key => $link) {
            $bar->add_node(['id' => self::NODE . '-' . $key, 'parent' => self::NODE . '-links', 'title' => esc_html($link[0]), 'href' => esc_url($link[1])]);
        }
    }

    public static function styles()
    {
        if (!self::enabled()) {
            return;
        }
        ?>
        <style id="wpide-snippets-admin-bar">
            #wpadminbar #wp-admin-bar-wpide-snippets > .ab-item .ab-icon:before { content: "\f475"; top: 2px; }
            #wpadminbar .wpide-ab-pill { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 9px; font-size: 11px; line-height: 17px; background: #3c434a; color: #fff; }
            #wpadminbar .wpide-ab-pill.is-error { background: #d63638; }
            #wpadminbar .wpide-ab-pill.is-testing { background: #2271b1; }
            #wpadminbar .wpide-ab-pill.is-off { background: #dba617; color: #1d2327; }
            #wpadminbar .wpide-ab-type { margin-left: 6px; opacity: .6; font-size: 10px; }
        </style>
        <?php
    }
}
