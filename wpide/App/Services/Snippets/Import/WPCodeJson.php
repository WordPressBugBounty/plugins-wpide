<?php

namespace WPIDE\App\Services\Snippets\Import;

/**
 * WPCode JSON export file (Code Snippets > Tools > Export in WPCode).
 *
 * The export format does not contain the active status, schedule, device targeting, file loading, compression,
 * generator settings or library link. Those are reported as missing; use "WPCode on this site" to keep them.
 */
class WPCodeJson extends Importer
{
    use WPCodeMapping;

    public function id(): string
    {
        return 'wpcode-json';
    }

    public function label(): string
    {
        return __('WPCode export file', 'wpide');
    }

    public function description(): string
    {
        return __('Import a .json file exported from WPCode. Imported snippets stay inactive until you review them.', 'wpide');
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
        $items = [];

        foreach ((array) $file as $index => $source) {
            if (!is_array($source) || empty($source['code_type'])) {
                continue;
            }

            list($snippet, $preserved, $unsupported) = $this->mapWPCode($source);

            $items[] = [
                'key' => (string) $index,
                'title' => $snippet['title'],
                'code_type' => $snippet['code_type'],
                'snippet' => $snippet,
                'original_status' => __('unknown (not in export)', 'wpide'),
                'preserved' => $preserved,
                'missing' => [
                    __('Active status', 'wpide'),
                    __('Schedule', 'wpide'),
                    __('Device type', 'wpide'),
                    __('Load as file', 'wpide'),
                    __('Compress output', 'wpide'),
                    __('Generator settings', 'wpide'),
                ],
                'unsupported' => $unsupported,
                'warnings' => [],
            ];
        }

        return $items;
    }
}
