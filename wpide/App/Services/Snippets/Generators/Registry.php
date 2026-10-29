<?php

namespace WPIDE\App\Services\Snippets\Generators;

/**
 * Generator registry: every class in this folder (except the base) is a generator.
 */
class Registry
{
    /** @var Generator[]|null */
    protected static $generators;

    /**
     * @return Generator[] id => generator
     */
    public static function all(): array
    {
        if (self::$generators !== null) {
            return self::$generators;
        }

        $generators = [];

        foreach (glob(__DIR__ . '/*.php') as $file) {
            $class = __NAMESPACE__ . '\\' . basename($file, '.php');

            if (in_array(basename($file, '.php'), ['Generator', 'Registry'], true) || !class_exists($class)) {
                continue;
            }

            $generator = new $class();

            if ($generator instanceof Generator) {
                $generators[$generator->id()] = $generator;
            }
        }

        uasort($generators, function ($a, $b) {
            return strcmp($a->info()['title'], $b->info()['title']);
        });

        return self::$generators = apply_filters('wpide_snippets_generators', $generators);
    }

    public static function get(string $id): ?Generator
    {
        return self::all()[$id] ?? null;
    }

    public static function forUi(): array
    {
        return array_values(array_map(function (Generator $generator) {
            $definition = $generator->definition();
            unset($definition['fields']);

            return $definition;
        }, self::all()));
    }
}
