<?php

namespace WPIDE\App\Services\Snippets\Import;

use WPIDE\App\Services\Snippets\GlobalScripts;
use WPIDE\App\Services\Snippets\Repository;

/**
 * WPIDE export format: every content field of each snippet (status, schedule, device, file loading,
 * compression, generator settings included), so an export imports back without loss.
 */
class Exporter
{
    const FORMAT = 'wpide-snippets';
    const VERSION = 1;

    public static function export(array $ids = [], bool $includeGlobal = false): array
    {
        $snippets = [];

        if ($ids) {
            foreach (array_map('intval', $ids) as $id) {
                $snippet = Repository::find($id);

                if ($snippet) {
                    $snippets[] = $snippet;
                }
            }
        } else {
            $snippets = Repository::query(['status' => 'all', 'per_page' => 500, 'orderby' => 'id', 'order' => 'asc'])['items'];
        }

        $export = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => gmdate('c'),
            'site' => home_url('/'),
            'snippets' => array_map(function ($snippet) {
                return Repository::content($snippet) + [
                    'uuid' => $snippet['uuid'],
                    'provenance' => $snippet['provenance'],
                    'source_ref' => $snippet['source_ref'],
                ];
            }, $snippets),
        ];

        if ($includeGlobal) {
            $export['global_scripts'] = GlobalScripts::get();
        }

        return $export;
    }

    public static function filename(): string
    {
        return 'wpide-snippets-' . sanitize_title(wp_parse_url(home_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d') . '.json';
    }
}
