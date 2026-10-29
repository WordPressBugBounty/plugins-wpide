<?php

namespace WPIDE\App\Services\Snippets\Import;

use WPIDE\App\Services\Snippets\GlobalScripts;
use WPIDE\App\Services\Snippets\Repository;

/**
 * WPIDE export file. All fields are kept except the active status: snippets arrive inactive for review,
 * and the report shows which ones were active on the source site.
 */
class WPIDEJson extends Importer
{
    public function id(): string
    {
        return 'wpide-json';
    }

    public function label(): string
    {
        return __('WPIDE export file', 'wpide');
    }

    public function description(): string
    {
        return __('Import snippets exported from another site running WPIDE.', 'wpide');
    }

    public function available(): bool
    {
        return true;
    }

    public function acceptsFile(): bool
    {
        return true;
    }

    protected function items(?array $file = null): array
    {
        if (($file['format'] ?? '') !== Exporter::FORMAT) {
            return [];
        }

        $items = [];

        foreach ((array) ($file['snippets'] ?? []) as $index => $source) {
            if (!is_array($source)) {
                continue;
            }

            $snippet = array_intersect_key($source, array_flip(Repository::CONTENT_FIELDS));
            $snippet['source_ref'] = (string) ($source['source_ref'] ?? '');
            $snippet['provenance'] = 'imported';

            $items[] = [
                'key' => (string) $index,
                'title' => (string) ($snippet['title'] ?? ''),
                'code_type' => (string) ($snippet['code_type'] ?? ''),
                'snippet' => $snippet,
                'original_status' => ($source['status'] ?? '') === 'active' ? __('active', 'wpide') : __('inactive', 'wpide'),
                'preserved' => array_keys($snippet),
                'missing' => [],
                'unsupported' => [],
                'warnings' => [],
            ];
        }

        return $items;
    }

    public function extras(?array $file = null): array
    {
        return isset($file['global_scripts']) && array_filter((array) $file['global_scripts'])
            ? [['key' => 'global_scripts', 'label' => __('Global header, body and footer scripts', 'wpide'), 'detail' => implode(', ', array_keys(array_filter((array) $file['global_scripts'])))]]
            : [];
    }

    public function commitExtras(array $selected, ?array $file = null): array
    {
        if (in_array('global_scripts', $selected, true) && isset($file['global_scripts'])) {
            GlobalScripts::save((array) $file['global_scripts']);

            return [__('Global scripts imported.', 'wpide')];
        }

        return [];
    }
}
