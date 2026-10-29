<?php

namespace WPIDE\App\Services\Snippets\Integrations;

use WPIDE\App\Services\Snippets\Capabilities;
use WPIDE\App\Services\Snippets\Events;
use WPIDE\App\Services\Snippets\Library;
use WPIDE\App\Services\Snippets\Repository;
use WPIDE\App\Services\Snippets\Settings;
use WPIDE\App\Services\Snippets\Snippets;

/**
 * WordPress Abilities API: read-only abilities so AI agents and automation can inspect snippets.
 * Registered only when the API exists (WordPress 6.9+). Nothing here changes or runs code.
 */
class Abilities
{
    public static function registerCategory()
    {
        if (Snippets::available() && function_exists('wp_register_ability_category')) {
            wp_register_ability_category('wpide', ['label' => 'WPIDE', 'description' => __('WPIDE code snippets.', 'wpide')]);
        }
    }

    public static function register()
    {
        if (!Snippets::available() || !function_exists('wp_register_ability')) {
            return;
        }

        $canView = function () {
            return current_user_can(Capabilities::VIEW);
        };

        $common = ['permission_callback' => $canView, 'meta' => ['annotations' => ['readonly' => true, 'destructive' => false]]];

        $category = ['category' => 'wpide'];

        wp_register_ability('wpide/list-snippets', $common + $category + [
            'label' => __('List snippets', 'wpide'),
            'description' => __('Lists WPIDE snippets with their type, status, location and errors.', 'wpide'),
            'input_schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'enum' => ['all', 'active', 'inactive', 'error']], 'search' => ['type' => 'string']]],
            'output_schema' => ['type' => 'array'],
            'execute_callback' => function ($input) {
                $input = (array) $input;
                $result = Repository::query(['status' => $input['status'] ?? 'all', 'search' => $input['search'] ?? '', 'per_page' => 200]);

                return array_map(function ($snippet) {
                    return [
                        'id' => $snippet['id'],
                        'title' => $snippet['title'],
                        'type' => $snippet['code_type'],
                        'status' => $snippet['status'],
                        'location' => $snippet['insert_method'] === 'auto' ? $snippet['location'] : 'shortcode',
                        'quarantined' => $snippet['quarantined'],
                        'last_error' => $snippet['last_error']['message'] ?? null,
                    ];
                }, $result['items']);
            },
        ]);

        wp_register_ability('wpide/detect-snippet-errors', $common + $category + [
            'label' => __('Detect snippet errors', 'wpide'),
            'description' => __('Lists snippets that are quarantined or recorded an error.', 'wpide'),
            'output_schema' => ['type' => 'array'],
            'execute_callback' => function () {
                return array_map(function ($snippet) {
                    return ['id' => $snippet['id'], 'title' => $snippet['title'], 'quarantined' => $snippet['quarantined'], 'error' => $snippet['last_error']];
                }, Repository::query(['status' => 'error', 'per_page' => 200])['items']);
            },
        ]);

        wp_register_ability('wpide/get-snippet-log', $common + $category + [
            'label' => __('Get the snippet event log', 'wpide'),
            'description' => __('Recent snippet errors, quarantines and activations.', 'wpide'),
            'input_schema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500]]],
            'output_schema' => ['type' => 'array'],
            'execute_callback' => function ($input) {
                return Events::recent((int) (((array) $input)['limit'] ?? 50));
            },
        ]);

        wp_register_ability('wpide/search-snippet-library', $common + $category + [
            'label' => __('Search the snippet library', 'wpide'),
            'description' => __('Searches the bundled WPIDE snippet library.', 'wpide'),
            'input_schema' => ['type' => 'object', 'properties' => ['search' => ['type' => 'string']]],
            'output_schema' => ['type' => 'array'],
            'execute_callback' => function ($input) {
                return Library::search((string) (((array) $input)['search'] ?? ''));
            },
        ]);

        wp_register_ability('wpide/get-snippet-settings', $common + $category + [
            'label' => __('Get snippet settings', 'wpide'),
            'description' => __('Returns the WPIDE snippet settings (no secrets).', 'wpide'),
            'output_schema' => ['type' => 'object'],
            'execute_callback' => function () {
                return Settings::get();
            },
        ]);
    }
}
