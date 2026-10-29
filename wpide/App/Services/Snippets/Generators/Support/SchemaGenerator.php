<?php

namespace WPIDE\App\Services\Snippets\Generators\Support;

use WPIDE\App\Services\Snippets\Generators\Generator;

/**
 * Base for JSON-LD structured data generators.
 *
 * Outputs an HTML snippet printed in the site header. Field values may contain smart tags such as {title}
 * or {permalink}; they are replaced when the snippet renders, so defaults use them to describe the current page.
 */
abstract class SchemaGenerator extends Generator
{
    public function snippetDefaults(): array
    {
        return ['code_type' => 'html', 'insert_method' => 'auto', 'location' => 'site_wide_header'];
    }

    /**
     * JSON-LD block that can never close its own script tag (a "</" in a value is written as "<\/").
     */
    protected function jsonLd(array $data): string
    {
        $data = array_merge(['@context' => 'https://schema.org'], $this->prune($data));
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = str_replace(['</', '<!--'], ['<\/', '<!--'], (string) $json);

        return '<script type="application/ld+json">' . "\n" . $json . "\n</script>";
    }

    // Field builders.

    protected function text(string $id, string $label, string $default = '', string $placeholder = '', string $description = ''): array
    {
        return ['id' => $id, 'type' => 'text', 'label' => $label, 'description' => $description, 'default' => $default, 'placeholder' => $placeholder];
    }

    protected function textarea(string $id, string $label, string $default = '', string $placeholder = '', string $description = ''): array
    {
        return ['id' => $id, 'type' => 'textarea', 'label' => $label, 'description' => $description, 'default' => $default, 'placeholder' => $placeholder];
    }

    protected function date(string $id, string $label, string $default = '', string $description = ''): array
    {
        return $this->text($id, $label, $default, 'YYYY-MM-DD', $description !== '' ? $description : __('ISO 8601 date, e.g. 2025-06-30, or a smart tag.', 'wpide'));
    }

    protected function select(string $id, string $label, array $options, string $default, string $description = ''): array
    {
        return ['id' => $id, 'type' => 'select', 'label' => $label, 'description' => $description, 'default' => $default, 'options' => $options];
    }

    protected function availabilityField(string $id, string $default = 'InStock'): array
    {
        return $this->select($id, __('Availability', 'wpide'), [
            'InStock' => __('In stock', 'wpide'),
            'OutOfStock' => __('Out of stock', 'wpide'),
            'PreOrder' => __('Available for pre-order', 'wpide'),
            'BackOrder' => __('On back order', 'wpide'),
            'LimitedAvailability' => __('Limited availability', 'wpide'),
            'OnlineOnly' => __('Online only', 'wpide'),
            'SoldOut' => __('Sold out', 'wpide'),
            'Discontinued' => __('Discontinued', 'wpide'),
        ], $default);
    }

    protected function currencyField(string $id, string $default = 'USD'): array
    {
        return $this->text($id, __('Currency', 'wpide'), $default, 'USD', __('Three-letter ISO 4217 code.', 'wpide'));
    }

    // Value helpers.

    /**
     * Full schema.org enumeration URL for a short member name.
     */
    protected function enum($value): string
    {
        $value = trim((string) $value);

        if ($value === '' || preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return 'https://schema.org/' . str_replace(' ', '', $value);
    }

    /**
     * Typed node, or an empty array when the main property is empty (pruned from the output).
     */
    protected function node(string $type, $name, array $extra = []): array
    {
        if (trim((string) $name) === '') {
            return [];
        }

        return array_merge(['@type' => $type, 'name' => $name], $extra);
    }

    protected function imageObject($url): array
    {
        return trim((string) $url) === '' ? [] : ['@type' => 'ImageObject', 'url' => $url];
    }

    /**
     * Numeric strings become numbers, everything else (including smart tags) stays a string.
     */
    protected function number($value)
    {
        $value = trim((string) $value);

        if ($value === '' || !is_numeric($value)) {
            return $value;
        }

        return strpos($value, '.') === false ? (int) $value : (float) $value;
    }

    /**
     * Price as a string with a dot decimal separator (accepts "1,299.00" or "19,99").
     */
    protected function price($value): string
    {
        $value = trim((string) $value);

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
            return str_replace(',', '', $value);
        }

        if (preg_match('/^\d+,\d{1,2}$/', $value)) {
            return str_replace(',', '.', $value);
        }

        return $value;
    }

    /**
     * ISO 8601 duration. Plain numbers are read in the given unit (M = minutes, S = seconds, H = hours).
     */
    protected function duration($value, string $unit = 'M'): string
    {
        $value = trim((string) $value);

        if ($value === '' || !is_numeric($value)) {
            return $value;
        }

        $seconds = (int) round((float) $value * ($unit === 'H' ? 3600 : ($unit === 'M' ? 60 : 1)));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        $out = 'PT' . ($hours ? $hours . 'H' : '') . ($minutes ? $minutes . 'M' : '') . ($rest ? $rest . 'S' : '');

        return $out === 'PT' ? 'PT0S' : $out;
    }

    /**
     * One item per line (or comma separated when the text has a single line).
     */
    protected function items($value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $items = strpos($value, "\n") !== false ? preg_split('/\r?\n/', $value) : explode(',', $value);

        return array_values(array_filter(array_map('trim', $items), 'strlen'));
    }

    /**
     * A single node when there is one, a list otherwise.
     */
    protected function oneOrMany(array $nodes)
    {
        $nodes = array_values(array_filter(array_map(function ($node) {
            return is_array($node) ? $this->prune($node) : $node;
        }, $nodes), function ($node) {
            return $node !== [] && $node !== '' && $node !== null;
        }));

        return count($nodes) === 1 ? $nodes[0] : $nodes;
    }

    protected function postalAddress(array $parts): array
    {
        $address = $this->prune([
            'streetAddress' => $parts['street'] ?? '',
            'addressLocality' => $parts['city'] ?? '',
            'addressRegion' => $parts['region'] ?? '',
            'postalCode' => $parts['postal'] ?? '',
            'addressCountry' => $parts['country'] ?? '',
        ]);

        return $address ? ['@type' => 'PostalAddress'] + $address : [];
    }
}
