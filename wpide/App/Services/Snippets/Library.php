<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Registry\Locations;
use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * The snippet library: WPIDE team snippets and snippets other users published, from the public library on
 * WPIDE Cloud (PublicLibrary), with the bundled copy of the team snippets as the fallback when the cloud
 * has not been reached yet. No account needed.
 *
 * Like WPCode Lite's library, it is free to browse and install. An entry that uses a Pro type or location is
 * marked "pro" and only installs with WPIDE Pro.
 *
 * Installing creates an inactive snippet that remembers its library id and version, so the list can show
 * when the library has a newer version of a snippet you installed.
 */
class Library
{
    /** @var array|null */
    protected static $data;

    public static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $file = __DIR__ . '/data/library.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        $data = is_array($data) ? $data : ['version' => 0, 'categories' => [], 'snippets' => []];
        $team = ['name' => 'WPIDE Team', 'team' => true, 'slug' => 'wpide-team', 'avatar' => null];
        $snippets = [];

        foreach ($data['snippets'] as $snippet) {
            $snippets[$snippet['id']] = $snippet + ['author' => $team];
        }

        // The cloud copy is newer than the bundled one and adds community snippets. Only screens that show the
        // library refresh it; the front end never waits for the cloud.
        $cloud = PublicLibrary::cached(is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST));

        if ($cloud) {
            foreach ($cloud['snippets'] as $snippet) {
                if (!empty($snippet['id']) && !empty($snippet['code'])) {
                    $snippets[$snippet['id']] = $snippet;
                }
            }

            if (!empty($cloud['categories'])) {
                $data['categories'] = array_map(function ($category) {
                    return ['id' => $category['id'], 'label' => $category['label']];
                }, $cloud['categories']);
            }
        }

        $data['snippets'] = array_values($snippets);

        return self::$data = apply_filters('wpide_snippets_library', $data);
    }

    public static function categories(): array
    {
        $counts = [];

        foreach (self::data()['snippets'] as $snippet) {
            $counts[$snippet['category']] = ($counts[$snippet['category']] ?? 0) + 1;
        }

        return array_values(array_map(function ($category) use ($counts) {
            return $category + ['count' => $counts[$category['id']] ?? 0];
        }, self::data()['categories']));
    }

    public static function installed(): array
    {
        global $wpdb;

        $installed = [];
        $table = Schema::snippetsTable();

        foreach ($wpdb->get_results("SELECT id, library_ref FROM $table WHERE library_ref != '' AND status != 'trash'", ARRAY_A) as $row) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            list($ref, $version) = array_pad(explode('@', $row['library_ref'], 2), 2, '0');
            $installed[$ref] = ['snippet_id' => (int) $row['id'], 'version' => (int) $version];
        }

        return $installed;
    }

    /**
     * @param string $author '' for everyone, 'team' for WPIDE's own snippets, 'community' for snippets users published,
     *                       or one author's page address (their slug in the public library).
     */
    public static function search(string $search = '', string $category = '', bool $favoritesOnly = false, string $author = ''): array
    {
        $installed = self::installed();
        $favorites = Plans::pro() ? Favorites::all() : [];
        $search = strtolower(trim($search));
        $results = [];

        foreach (self::data()['snippets'] as $snippet) {
            if (($category !== '' && $snippet['category'] !== $category) || ($favoritesOnly && !in_array($snippet['id'], $favorites, true))) {
                continue;
            }

            if (in_array($author, ['team', 'community'], true) && ($author === 'team') !== !empty($snippet['author']['team'])) {
                continue;
            }

            if ($author !== '' && !in_array($author, ['team', 'community'], true) && ($snippet['author']['slug'] ?? '') !== $author) {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower($snippet['title'] . ' ' . $snippet['description'] . ' ' . implode(' ', (array) ($snippet['tags'] ?? [])));

                if (strpos($haystack, $search) === false) {
                    continue;
                }
            }

            $results[] = self::summary($snippet, $installed) + ['favorite' => in_array($snippet['id'], $favorites, true)];
        }

        return $results;
    }

    protected static function summary(array $snippet, array $installed): array
    {
        $state = $installed[$snippet['id']] ?? null;

        return [
            'id' => $snippet['id'],
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'category' => $snippet['category'],
            'tags' => $snippet['tags'] ?? [],
            'code_type' => $snippet['code_type'],
            'requires' => self::requirementNames($snippet),
            'available' => self::requirementsMet($snippet),
            'pro' => self::isPro($snippet),
            'version' => (int) ($snippet['version'] ?? 1),
            'author' => $snippet['author'] ?? ['name' => 'WPIDE Team', 'team' => true, 'slug' => 'wpide-team', 'avatar' => null],
            'installs' => (int) ($snippet['installs'] ?? 0),
            'installed_id' => $state ? $state['snippet_id'] : 0,
            'update_available' => $state && $state['version'] < (int) ($snippet['version'] ?? 1),
        ];
    }

    public static function get(string $id): ?array
    {
        foreach (self::data()['snippets'] as $snippet) {
            if ($snippet['id'] === $id) {
                return $snippet;
            }
        }

        return null;
    }

    /**
     * A library snippet, also one that is not in the listing: a link-only snippet someone shared, or one of
     * this site's own private snippets (asked from the cloud).
     */
    public static function find(string $id): ?array
    {
        $entry = self::get($id) ?? PublicLibrary::fetch($id);

        return $entry && !empty($entry['code']) ? $entry : null;
    }

    public static function isPro(array $entry): bool
    {
        return Types::isPro((string) ($entry['code_type'] ?? '')) || (($entry['insert_method'] ?? 'auto') === 'auto' && Locations::isPro((string) ($entry['location'] ?? '')));
    }

    /**
     * Plugins a library snippet can need, with how to tell they are active: [name, 'class:..' | 'const:..' | 'function:..'].
     * A snippet whose plugin is missing shows "Needs ..." and does not install.
     */
    const REQUIREMENTS = [
        'woocommerce' => ['WooCommerce', 'class:WooCommerce'],
        'edd' => ['Easy Digital Downloads', 'class:Easy_Digital_Downloads'],
        'memberpress' => ['MemberPress', 'const:MEPR_VERSION'],
        'pmpro' => ['Paid Memberships Pro', 'const:PMPRO_VERSION'],
        'acf' => ['Advanced Custom Fields', 'class:ACF'],
        'elementor' => ['Elementor', 'const:ELEMENTOR_VERSION'],
        'elementor-pro' => ['Elementor Pro', 'const:ELEMENTOR_PRO_VERSION'],
        'beaver-builder' => ['Beaver Builder', 'class:FLBuilder'],
        'divi' => ['Divi', 'const:ET_CORE_VERSION'],
        'bricks' => ['Bricks', 'const:BRICKS_VERSION'],
        'cf7' => ['Contact Form 7', 'const:WPCF7_VERSION'],
        'gravityforms' => ['Gravity Forms', 'class:GFForms'],
        'wpforms' => ['WPForms', 'function:wpforms'],
        'fluentforms' => ['Fluent Forms', 'const:FLUENTFORM_VERSION'],
        'yoast' => ['Yoast SEO', 'const:WPSEO_VERSION'],
        'rankmath' => ['Rank Math', 'const:RANK_MATH_VERSION'],
        'aioseo' => ['All in One SEO', 'const:AIOSEO_VERSION'],
        'wpml' => ['WPML', 'const:ICL_SITEPRESS_VERSION'],
        'polylang' => ['Polylang', 'const:POLYLANG_VERSION'],
        'learndash' => ['LearnDash', 'const:LEARNDASH_VERSION'],
        'lifterlms' => ['LifterLMS', 'const:LLMS_PLUGIN_FILE'],
        'tutor' => ['Tutor LMS', 'const:TUTOR_VERSION'],
        'buddypress' => ['BuddyPress', 'class:BuddyPress'],
        'bbpress' => ['bbPress', 'class:bbPress'],
        'events-calendar' => ['The Events Calendar', 'class:Tribe__Events__Main'],
        'givewp' => ['GiveWP', 'const:GIVE_VERSION'],
        'affiliatewp' => ['AffiliateWP', 'function:affiliate_wp'],
        'jetpack' => ['Jetpack', 'const:JETPACK__VERSION'],
    ];

    public static function requirementsMet(array $snippet): bool
    {
        foreach ((array) ($snippet['requires'] ?? []) as $plugin) {
            $check = self::REQUIREMENTS[$plugin][1] ?? '';
            list($kind, $name) = array_pad(explode(':', $check, 2), 2, '');
            $active = ($kind === 'class' && class_exists($name)) || ($kind === 'const' && defined($name)) || ($kind === 'function' && function_exists($name));

            if (!$active) {
                return false;
            }
        }

        return true;
    }

    /** The plugins a snippet needs, by name ("WooCommerce"), for "Needs ..." labels. */
    public static function requirementNames(array $snippet): array
    {
        return array_map(function ($plugin) {
            return self::REQUIREMENTS[$plugin][0] ?? $plugin;
        }, (array) ($snippet['requires'] ?? []));
    }

    /**
     * The library entry as snippet data (inactive), ready for the editor or Manager::save().
     */
    public static function toSnippet(array $entry): array
    {
        $location = $entry['location'] ?? '';

        return [
            'title' => $entry['title'],
            'note' => trim($entry['description'] . (!empty($entry['notes']) ? "\n\n" . $entry['notes'] : '')),
            'code' => $entry['code'],
            'code_type' => $entry['code_type'],
            'insert_method' => $entry['insert_method'] ?? 'auto',
            'location' => Locations::exists($location) ? $location : '',
            'insert_number' => (int) ($entry['insert_number'] ?? 1),
            'tags' => array_merge(['library'], (array) ($entry['tags'] ?? [])),
            'status' => 'draft',
            'library_ref' => $entry['id'] . '@' . (int) ($entry['version'] ?? 1),
            'provenance' => 'library',
        ];
    }

    public static function install(string $id): array
    {
        $entry = self::find($id);

        if (!$entry) {
            return ['success' => false, 'errors' => [__('Library snippet not found.', 'wpide')]];
        }

        if (self::isPro($entry) && !Plans::pro()) {
            return ['success' => false, 'pro_required' => true, 'errors' => [__('This library snippet needs WPIDE Pro.', 'wpide')]];
        }

        $result = Manager::save(self::toSnippet($entry), 'library');

        if (!empty($result['success'])) {
            PublicLibrary::installed($id);
        }

        return $result;
    }

    /**
     * Replace an installed snippet's code with the newer library version (as a new revision; status kept).
     */
    public static function update(int $snippetId): array
    {
        $snippet = Repository::find($snippetId);
        $ref = $snippet ? explode('@', $snippet['library_ref'])[0] : '';
        $entry = $ref !== '' ? self::get($ref) : null;

        if (!$entry) {
            return ['success' => false, 'errors' => [__('This snippet is not from the library.', 'wpide')]];
        }

        $data = self::toSnippet($entry);
        unset($data['status'], $data['title'], $data['tags']);
        $data['id'] = $snippetId;

        return Manager::save($data, 'library');
    }
}
