<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Multisite: network snippets and the network access policy.
 *
 * A super admin marks a main-site snippet as a network snippet and chooses the sites: all, only some, or
 * all except some. Active network snippets are copied into a network option whenever the main site's
 * manifest is rebuilt, and each other site merges them into its own runtime with negative ids so they
 * never collide with the site's own snippets. Errors are recorded on the main-site snippet.
 */
class Network
{
    const MANIFEST_OPTION = 'wpide_snippets_network_manifest';
    const ACCESS_OPTION = 'wpide_snippets_network_access';

    public static function boot()
    {
        if (!is_multisite()) {
            return;
        }

        add_action('wpide_snippets_manifest_rebuilt', [__CLASS__, 'publishFromMainSite']);
    }

    /**
     * Network snippets are a 30-site plan feature (WPCode: Bundle only).
     */
    public static function enabled(): bool
    {
        return is_multisite() && Plans::allows('multisite');
    }

    public static function mainSiteId(): int
    {
        return (int) get_main_site_id();
    }

    /**
     * Super admins on the main site can create network snippets.
     */
    public static function canManage(): bool
    {
        return self::enabled() && is_main_site() && is_super_admin();
    }

    public static function publishFromMainSite(array $manifest)
    {
        if (!is_main_site()) {
            return;
        }

        $network = [];

        foreach ($manifest['snippets'] as $id => $snippet) {
            if (!empty($snippet['settings']['network']['enabled'])) {
                $network[(int) $id] = $snippet;
            }
        }

        update_site_option(self::MANIFEST_OPTION, ['built' => time(), 'snippets' => $network]);
    }

    public static function runsOn(array $snippet, int $siteId): bool
    {
        $settings = $snippet['settings']['network'] ?? [];

        if (empty($settings['enabled']) || $siteId === self::mainSiteId()) {
            return false;
        }

        $sites = array_map('intval', (array) ($settings['sites'] ?? []));

        switch ($settings['mode'] ?? 'all') {
            case 'include':
                return in_array($siteId, $sites, true);
            case 'exclude':
                return !in_array($siteId, $sites, true);
            default:
                return true;
        }
    }

    /**
     * Network snippets for the current (non-main) site, keyed by negative id.
     */
    public static function forCurrentSite(): array
    {
        if (!self::enabled() || is_main_site()) {
            return [];
        }

        $manifest = get_site_option(self::MANIFEST_OPTION, []);
        $siteId = get_current_blog_id();
        $snippets = [];

        foreach ((array) ($manifest['snippets'] ?? []) as $id => $snippet) {
            if (self::runsOn($snippet, $siteId)) {
                $snippet['id'] = -abs((int) $id);
                $snippet['network'] = true;
                $snippets[$snippet['id']] = $snippet;
            }
        }

        return $snippets;
    }

    /**
     * Run a callback against the main site's tables (to record errors of network snippets).
     */
    public static function onMainSite(callable $callback)
    {
        if (!is_multisite() || is_main_site()) {
            return $callback();
        }

        switch_to_blog(self::mainSiteId());

        try {
            return $callback();
        } finally {
            restore_current_blog();
            // The manifest cache now holds the main site's manifest: drop it so this site reloads its own.
            Manifest::flush();
        }
    }

    /**
     * "site_admins": each site's administrators manage that site's snippets (markup and PHP still need
     * unfiltered_html, which only super admins have on a network). "super_admins": only super admins.
     */
    public static function accessPolicy(): string
    {
        $policy = is_multisite() ? get_site_option(self::ACCESS_OPTION, 'site_admins') : 'site_admins';

        return in_array($policy, ['site_admins', 'super_admins'], true) ? $policy : 'site_admins';
    }

    public static function setAccessPolicy(string $policy)
    {
        if (is_multisite() && in_array($policy, ['site_admins', 'super_admins'], true)) {
            update_site_option(self::ACCESS_OPTION, $policy);
        }
    }

    public static function sites(int $limit = 500): array
    {
        if (!is_multisite()) {
            return [];
        }

        return array_map(function ($site) {
            return ['id' => (int) $site->blog_id, 'name' => get_blog_option($site->blog_id, 'blogname'), 'url' => get_home_url($site->blog_id, '/'), 'main' => (int) $site->blog_id === self::mainSiteId()];
        }, get_sites(['number' => $limit, 'deleted' => 0, 'archived' => 0, 'spam' => 0]));
    }

    public static function forUi(): array
    {
        if (!is_multisite()) {
            return ['enabled' => false];
        }

        if (!self::enabled()) {
            return ['enabled' => false, 'locked' => true, 'plan' => Plans::feature('multisite')['plan'], 'main' => is_main_site(), 'super_admin' => is_super_admin(), 'running_here' => []];
        }

        return [
            'enabled' => true,
            'main' => is_main_site(),
            'can_manage' => self::canManage(),
            'super_admin' => is_super_admin(),
            'access_policy' => self::accessPolicy(),
            'sites' => self::canManage() ? self::sites() : [],
            'running_here' => array_values(array_map(function ($snippet) {
                return ['id' => abs($snippet['id']), 'title' => $snippet['title'], 'code_type' => $snippet['code_type']];
            }, self::forCurrentSite())),
            'main_site_url' => get_admin_url(self::mainSiteId(), 'admin.php?page=wpide#/snippets'),
        ];
    }
}
