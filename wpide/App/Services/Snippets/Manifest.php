<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Runtime manifest: a compact, versioned projection of the snippets that may run.
 *
 * Rebuilt on every repository write, read once per request (one non-autoloaded option) and never
 * rebuilt during a visitor request unless it is missing.
 */
class Manifest
{
    const OPTION = 'wpide_snippets_manifest';
    const FORMAT = 1;

    /** @var array|null */
    protected static $cache;

    const RUNTIME_FIELDS = [
        'id', 'title', 'code', 'compiled_code', 'code_type', 'insert_method', 'location', 'insert_number', 'location_extra',
        'priority', 'use_conditions', 'conditions', 'schedule', 'device_type', 'shortcode', 'shortcode_attributes',
        'settings', 'updated_at',
    ];

    public static function build(array $snippets): array
    {
        $manifest = [
            'format' => self::FORMAT,
            'schema' => Schema::VERSION,
            'built' => time(),
            'snippets' => [],
            'locations' => [],
            'shortcodes' => [],
            'migrated' => [],
        ];

        foreach ($snippets as $snippet) {
            $id = (int) $snippet['id'];
            $manifest['snippets'][$id] = array_intersect_key($snippet, array_flip(self::RUNTIME_FIELDS));

            if ($snippet['insert_method'] === 'auto' && $snippet['location'] !== '') {
                $manifest['locations'][$snippet['location']][] = $id;
            }

            if ($snippet['shortcode'] !== '') {
                $manifest['shortcodes'][$snippet['shortcode']] = $id;
            }

            // [wpcode id="…"] in migrated content resolves to the imported snippet.
            if (isset($snippet['source_ref']) && preg_match('/^wpcode:(\d+)$/', (string) $snippet['source_ref'], $m)) {
                $manifest['migrated'][(int) $m[1]] = $id;
            }
        }

        // Priority, then id: the stable execution order within a location.
        foreach ($manifest['locations'] as &$ids) {
            usort($ids, function ($a, $b) use ($manifest) {
                $pa = $manifest['snippets'][$a]['priority'];
                $pb = $manifest['snippets'][$b]['priority'];

                return $pa === $pb ? $a <=> $b : $pa <=> $pb;
            });
        }

        return $manifest;
    }

    public static function rebuild(): array
    {
        if (!Schema::isInstalled()) {
            return self::empty();
        }

        $manifest = self::build(Repository::runnable());
        update_option(self::OPTION, $manifest, false);
        self::$cache = $manifest;

        do_action('wpide_snippets_manifest_rebuilt', $manifest);

        return $manifest;
    }

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $manifest = get_option(self::OPTION);

        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ($manifest['schema'] ?? null) !== Schema::VERSION) {
            // A missing manifest is rebuilt once; a schema mismatch disables execution until an admin migrates.
            $manifest = Schema::isInstalled() ? self::rebuild() : self::empty();
        }

        return self::$cache = $manifest;
    }

    public static function flush()
    {
        self::$cache = null;
    }

    public static function empty(): array
    {
        return ['format' => self::FORMAT, 'schema' => Schema::VERSION, 'built' => 0, 'snippets' => [], 'locations' => [], 'shortcodes' => []];
    }
}
