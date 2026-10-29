<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Snippet capabilities, checked server-side on every management action.
 *
 * Administrators (manage_options) receive all capabilities. Other roles can be granted text, markup or PHP
 * editing and activation from the settings. Markup and PHP editing always also require unfiltered_html,
 * and PHP can be disabled site-wide with WPIDE_SNIPPETS_DISABLE_PHP.
 */
class Capabilities
{
    const VIEW = 'wpide_snippets_view';
    const EDIT_TEXT = 'wpide_snippets_edit_text';
    const EDIT_MARKUP = 'wpide_snippets_edit_markup';
    const EDIT_PHP = 'wpide_snippets_edit_php';
    const ACTIVATE = 'wpide_snippets_activate';
    const SETTINGS = 'wpide_snippets_settings';
    const TRACKING = 'wpide_tracking_manage';
    const SPECIAL_FILES = 'wpide_special_files';

    const ACCESS_OPTION = 'wpide_snippets_access';

    /**
     * Grantable capabilities (labels in all(), which is only used by the admin UI).
     */
    const GRANTABLE = [self::EDIT_TEXT, self::EDIT_MARKUP, self::EDIT_PHP, self::ACTIVATE, self::SETTINGS, self::TRACKING, self::SPECIAL_FILES];

    /**
     * Each editing level includes the levels below it.
     */
    const IMPLIES = [
        self::EDIT_PHP => [self::EDIT_MARKUP, self::EDIT_TEXT, self::VIEW],
        self::EDIT_MARKUP => [self::EDIT_TEXT, self::VIEW],
        self::EDIT_TEXT => [self::VIEW],
        self::ACTIVATE => [self::VIEW],
        self::SETTINGS => [self::VIEW],
        self::TRACKING => [self::VIEW],
    ];

    public static function all(): array
    {
        return [
            self::EDIT_TEXT => __('Edit Text and Blocks snippets', 'wpide'),
            self::EDIT_MARKUP => __('Edit HTML, CSS, SCSS and JavaScript snippets', 'wpide'),
            self::EDIT_PHP => __('Edit PHP and Universal snippets', 'wpide'),
            self::ACTIVATE => __('Activate and deactivate snippets', 'wpide'),
            self::SETTINGS => __('Manage snippet settings', 'wpide'),
            self::TRACKING => __('Manage conversion tracking', 'wpide'),
            self::SPECIAL_FILES => __('Manage robots.txt, ads.txt and service worker files', 'wpide'),
        ];
    }

    public static function init()
    {
        add_filter('user_has_cap', [__CLASS__, 'grant'], 10, 4);
    }

    public static function phpDisabled(): bool
    {
        // The constant always applies; the settings switch is part of access control (the Pro plan, as in WPCode).
        return (defined('WPIDE_SNIPPETS_DISABLE_PHP') && WPIDE_SNIPPETS_DISABLE_PHP) || (Plans::allows('access_control') && (bool) get_option('wpide_snippets_disable_php', false));
    }

    public static function access(): array
    {
        $access = get_option(self::ACCESS_OPTION, []);

        return is_array($access) ? $access : [];
    }

    public static function grant($allcaps, $caps, $args, $user)
    {
        if (!$user instanceof \WP_User) {
            return $allcaps;
        }

        // Network policy: only super admins manage snippets on any site.
        if (is_multisite() && Network::accessPolicy() === 'super_admins' && !is_super_admin($user->ID)) {
            return $allcaps;
        }

        $isAdmin = !empty($allcaps['manage_options']);
        // Granting snippet access to other roles is access control (the Pro plan, as WPCode's); below it, admins only.
        $access = Plans::allows('access_control') ? self::access() : [];
        $granted = [];

        foreach (self::GRANTABLE as $cap) {
            $roles = isset($access[$cap]) ? (array) $access[$cap] : [];

            if ($isAdmin || array_intersect($roles, (array) $user->roles)) {
                $granted[$cap] = true;

                foreach (self::IMPLIES[$cap] ?? [] as $implied) {
                    $granted[$implied] = true;
                }
            }
        }

        // WordPress removes unfiltered_html in map_meta_cap (multisite non-super-admins, DISALLOW_UNFILTERED_HTML),
        // not in the role's capabilities, so apply the same rules here.
        $unfiltered = !empty($allcaps['unfiltered_html'])
            && !(defined('DISALLOW_UNFILTERED_HTML') && DISALLOW_UNFILTERED_HTML)
            && (!is_multisite() || is_super_admin($user->ID));

        if (!$unfiltered) {
            unset($granted[self::EDIT_MARKUP], $granted[self::EDIT_PHP]);
        }

        if (self::phpDisabled()) {
            unset($granted[self::EDIT_PHP]);
        }

        foreach ($granted as $cap => $value) {
            $allcaps[$cap] = true;
        }

        return $allcaps;
    }

    public static function canEditType(string $type, $user = null): bool
    {
        $cap = Types::capability($type);

        return $user ? user_can($user, $cap) : current_user_can($cap);
    }

    public static function canView(): bool
    {
        return current_user_can(self::VIEW);
    }

    public static function canActivate(): bool
    {
        return current_user_can(self::ACTIVATE);
    }

    /**
     * Whether the current user may use the Snippets screens at all.
     */
    public static function canAccess(): bool
    {
        return self::canView();
    }
}
