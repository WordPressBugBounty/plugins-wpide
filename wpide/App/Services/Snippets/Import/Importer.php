<?php

namespace WPIDE\App\Services\Snippets\Import;

use WPIDE\App\Services\Snippets\Manager;
use WPIDE\App\Services\Snippets\Registry\Locations;
use WPIDE\App\Services\Snippets\Registry\Types;
use WPIDE\App\Services\Snippets\Repository;
use WPIDE\App\Services\Snippets\Validation;

/**
 * Base importer: analyze (dry run with a preservation report), then commit the selected items.
 *
 * Imported snippets are always saved inactive until reviewed. Nothing existing is overwritten: duplicates are
 * reported and shortcode name conflicts are renamed and reported. Values the source does not provide are not
 * invented; they are listed as missing.
 */
abstract class Importer
{
    abstract public function id(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /**
     * Whether source data exists on this site (for site importers) or a file is expected.
     */
    abstract public function available(): bool;

    public function acceptsFile(): bool
    {
        return false;
    }

    /**
     * Source records converted to items: ['key', 'title', 'snippet' => data, 'original_status', 'preserved' => [],
     * 'missing' => [], 'unsupported' => [], 'warnings' => []].
     *
     * @param array|null $file Decoded upload for file importers.
     */
    abstract protected function items(?array $file = null): array;

    /**
     * Extra, non-snippet data the importer can bring (global scripts, page scripts, files).
     */
    public function extras(?array $file = null): array
    {
        return [];
    }

    public function commitExtras(array $selected, ?array $file = null): array
    {
        return [];
    }

    public function info(): array
    {
        return [
            'id' => $this->id(),
            'label' => $this->label(),
            'description' => $this->description(),
            'available' => $this->available(),
            'accepts_file' => $this->acceptsFile(),
        ];
    }

    public function analyze(?array $file = null): array
    {
        $hashes = $this->existingHashes();
        $items = [];

        foreach ($this->items($file) as $item) {
            $item = $this->finalize($item);
            $hash = $this->codeHash($item['snippet']);
            $item['duplicate_of'] = $hashes[$hash] ?? 0;
            $check = Validation::check(Repository::normalize($item['snippet']));
            $item['errors'] = $check['errors'];
            $item['warnings'] = array_values(array_unique(array_merge($item['warnings'], $check['warnings'])));
            unset($item['snippet']['status']);
            $items[] = $item;
        }

        return ['importer' => $this->info(), 'items' => $items, 'extras' => $this->extras($file)];
    }

    /**
     * @param string[] $keys Items to import.
     */
    public function commit(array $keys, ?array $file = null, array $extras = []): array
    {
        $report = ['imported' => [], 'skipped' => [], 'renamed' => [], 'extras' => []];
        $keys = array_map('strval', $keys);

        foreach ($this->items($file) as $item) {
            if (!in_array((string) $item['key'], $keys, true)) {
                continue;
            }

            $item = $this->finalize($item);
            $data = $item['snippet'];
            $data['status'] = 'draft';

            if ($data['shortcode'] ?? '') {
                $name = $data['shortcode'];
                $unique = $name;
                $i = 2;

                while (Repository::findByShortcode($unique) || (shortcode_exists($unique) && $unique !== $name)) {
                    $unique = substr($name, 0, 58) . '-' . $i++;
                }

                if ($unique !== $name) {
                    $data['shortcode'] = $unique;
                    $report['renamed'][] = ['key' => $item['key'], 'from' => $name, 'to' => $unique];
                }
            }

            $result = Manager::save($data, 'import');

            if (!$result['success'] && $this->canFallBackToShortcode($data, $result)) {
                // Keep the code: unsupported insertion becomes a shortcode-only snippet, and it is reported.
                $data['insert_method'] = 'shortcode';
                $data['location'] = '';
                $result = Manager::save($data, 'import');

                if ($result['success']) {
                    $result['warnings'][] = __('The original insertion could not be used; the snippet was imported as shortcode only.', 'wpide');
                }
            }

            if ($result['success']) {
                $report['imported'][] = ['key' => $item['key'], 'id' => $result['snippet']['id'], 'title' => $result['snippet']['title'], 'warnings' => $result['warnings'] ?? [], 'missing' => $item['missing']];
            } else {
                $report['skipped'][] = ['key' => $item['key'], 'title' => $item['title'], 'errors' => $result['errors']];
            }
        }

        if ($extras) {
            $report['extras'] = $this->commitExtras($extras, $file);
        }

        return $report;
    }

    protected function canFallBackToShortcode(array $data, array $result): bool
    {
        return ($data['insert_method'] ?? 'auto') === 'auto';
    }

    protected function finalize(array $item): array
    {
        $item += ['preserved' => [], 'missing' => [], 'unsupported' => [], 'warnings' => [], 'original_status' => 'inactive'];
        $item['snippet'] += ['provenance' => 'imported'];
        $location = $item['snippet']['location'] ?? '';

        if (($item['snippet']['insert_method'] ?? 'auto') === 'auto' && $location !== '' && !Locations::exists($location)) {
            $item['unsupported'][] = sprintf(__('Location "%s"', 'wpide'), $location);
        }

        return $item;
    }

    protected function codeHash(array $snippet): string
    {
        return md5(($snippet['code_type'] ?? '') . '|' . trim((string) ($snippet['code'] ?? '')));
    }

    protected function existingHashes(): array
    {
        $hashes = [];

        foreach (Repository::query(['status' => 'all', 'per_page' => 500])['items'] as $snippet) {
            $hashes[$this->codeHash($snippet)] = $snippet['id'];
        }

        return $hashes;
    }
}
