<?php

namespace WPIDE\App\Services\Snippets\Generators;

/**
 * Form-driven code generator.
 *
 * A generator describes its form (tabs of fields) and turns submitted values into code. Generated snippets
 * keep the generator id and values so they can be reopened and regenerated.
 *
 * Field shape: ['id' => string, 'type' => 'text'|'textarea'|'number'|'select'|'checkbox'|'multiselect'|'repeater',
 *               'label' => string, 'description' => string, 'default' => mixed, 'placeholder' => string,
 *               'options' => [value => label], 'fields' => [...] (repeater sub-fields), 'required' => bool]
 */
abstract class Generator
{
    abstract public function id(): string;

    /**
     * ['title' => string, 'description' => string, 'category' => 'wordpress'|'schema', 'tags' => string[]]
     */
    abstract public function info(): array;

    /**
     * [tabKey => ['label' => string, 'fields' => [field, ...]], ...]
     */
    abstract public function fields(): array;

    /**
     * Code for the given values (already merged with defaults and sanitized by values()).
     */
    abstract public function generate(array $values): string;

    /**
     * Snippet settings applied when the generated code is saved as a snippet.
     */
    public function snippetDefaults(): array
    {
        return ['code_type' => 'php', 'insert_method' => 'auto', 'location' => 'everywhere'];
    }

    public function definition(): array
    {
        return ['id' => $this->id()] + $this->info() + ['fields' => $this->fields(), 'snippet' => $this->snippetDefaults()];
    }

    /**
     * Merge submitted values with field defaults and sanitize by field type.
     */
    public function values(array $input): array
    {
        $values = [];

        foreach ($this->fields() as $tab) {
            foreach ($tab['fields'] as $field) {
                $values[$field['id']] = $this->sanitizeField($field, $input[$field['id']] ?? ($field['default'] ?? null));
            }
        }

        return $values;
    }

    protected function sanitizeField(array $field, $value)
    {
        switch ($field['type']) {
            case 'checkbox':
                return (bool) $value;
            case 'number':
                return is_numeric($value) ? $value + 0 : ($field['default'] ?? 0);
            case 'textarea':
                return is_scalar($value) ? (string) $value : '';
            case 'select':
                $value = is_scalar($value) ? (string) $value : '';

                return isset($field['options'][$value]) ? $value : (string) ($field['default'] ?? '');
            case 'multiselect':
                return array_values(array_intersect(array_map('strval', (array) $value), array_map('strval', array_keys($field['options'] ?? []))));
            case 'repeater':
                $rows = [];

                foreach ((array) $value as $row) {
                    $clean = [];

                    foreach ($field['fields'] as $sub) {
                        $clean[$sub['id']] = $this->sanitizeField($sub, is_array($row) ? ($row[$sub['id']] ?? ($sub['default'] ?? null)) : null);
                    }

                    $rows[] = $clean;
                }

                return $rows;
        }

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    // Helpers for generated PHP.

    /**
     * PHP string literal (single quoted, escaped).
     */
    protected function str($value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
    }

    /**
     * Translatable PHP string literal: __( '...', 'text_domain' ).
     */
    protected function tr($value, string $domain): string
    {
        return '__( ' . $this->str($value) . ', ' . $this->str($domain) . ' )';
    }

    /**
     * Valid PHP function name from user input.
     */
    protected function fn($value, string $fallback): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $value);

        return $name === '' || is_numeric($name[0]) ? $fallback : $name;
    }

    protected function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * JSON-LD script block for schema generators.
     */
    protected function jsonLd(array $data): string
    {
        $data = array_merge(['@context' => 'https://schema.org'], $this->prune($data));

        return '<script type="application/ld+json">' . "\n" . wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n</script>";
    }

    /**
     * Remove empty values recursively so optional fields do not produce empty properties.
     */
    protected function prune(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->prune($value);
                $data[$key] = $value;
            }

            if ($value === '' || $value === null || $value === []) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
