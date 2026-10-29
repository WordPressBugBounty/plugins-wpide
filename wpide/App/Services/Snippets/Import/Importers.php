<?php

namespace WPIDE\App\Services\Snippets\Import;

/**
 * Available importers. File importers accept an uploaded export; site importers read another plugin's data
 * on this site and never modify it.
 */
class Importers
{
    /**
     * @return Importer[] id => importer
     */
    public static function all(): array
    {
        $classes = [
            WPCodeJson::class,
            WPCodeSite::class,
            WPIDEJson::class,
            CodeSnippets::class,
            HeaderFooterCodeManager::class,
            PostSnippets::class,
            SimpleCustomCssJs::class,
            Woody::class,
        ];

        $importers = [];

        foreach ($classes as $class) {
            $importer = new $class();
            $importers[$importer->id()] = $importer;
        }

        return apply_filters('wpide_snippets_importers', $importers);
    }

    public static function get(string $id): ?Importer
    {
        return self::all()[$id] ?? null;
    }

    public static function forUi(): array
    {
        return array_values(array_map(function (Importer $importer) {
            return $importer->info();
        }, self::all()));
    }
}
