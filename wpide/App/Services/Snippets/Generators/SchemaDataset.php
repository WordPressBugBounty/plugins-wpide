<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Dataset structured data.
 */
class SchemaDataset extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-dataset';
    }

    public function info(): array
    {
        return [
            'title' => __('Dataset Schema', 'wpide'),
            'description' => __('Make a published dataset discoverable with its publisher, license, version and download files.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'dataset', 'data', 'research', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'dataset' => [
                'label' => __('Dataset', 'wpide'),
                'fields' => [
                    $this->text('dataset_name', __('Name', 'wpide'), '{title}', 'Global Climate Data 2024'),
                    $this->textarea('dataset_description', __('Description', 'wpide'), '{excerpt}', '', __('Search engines expect between 50 and 5000 characters.', 'wpide')),
                    $this->text('dataset_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('keywords', __('Keywords', 'wpide'), '', 'climate, temperature, weather', __('Comma separated.', 'wpide')),
                    $this->text('version', __('Version', 'wpide'), '', '1.0'),
                    $this->text('license', __('License URL', 'wpide'), '', 'https://creativecommons.org/licenses/by/4.0/'),
                    $this->date('date_published', __('Published date', 'wpide'), '{date_published}'),
                    $this->date('date_modified', __('Modified date', 'wpide'), '{date_modified}'),
                ],
            ],
            'publisher' => [
                'label' => __('Publisher', 'wpide'),
                'fields' => [
                    $this->text('publisher_name', __('Publisher / creator name', 'wpide'), '{site_name}'),
                    $this->text('publisher_url', __('Publisher URL', 'wpide'), '{site_url}'),
                ],
            ],
            'distribution' => [
                'label' => __('Download', 'wpide'),
                'fields' => [
                    $this->text('distribution_url', __('Download URL', 'wpide'), '', 'https://example.com/data.csv'),
                    $this->text('file_format', __('File format', 'wpide'), '', 'text/csv', __('MIME type or format name.', 'wpide')),
                    $this->text('file_size', __('File size', 'wpide'), '', '12 MB'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $organization = $this->node('Organization', $values['publisher_name'], ['url' => $values['publisher_url']]);

        return $this->jsonLd([
            '@type' => 'Dataset',
            'name' => $values['dataset_name'],
            'description' => $values['dataset_description'],
            'url' => $values['dataset_url'],
            'keywords' => $this->items($values['keywords']),
            'version' => $values['version'],
            'license' => $values['license'],
            'datePublished' => $values['date_published'],
            'dateModified' => $values['date_modified'],
            'creator' => $organization,
            'publisher' => $organization,
            'distribution' => $values['distribution_url'] !== '' ? [[
                '@type' => 'DataDownload',
                'contentUrl' => $values['distribution_url'],
                'encodingFormat' => $values['file_format'],
                'contentSize' => $values['file_size'],
            ]] : [],
        ]);
    }
}
