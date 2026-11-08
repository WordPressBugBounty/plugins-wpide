<?php

namespace WPIDE\App\Services\Snippets\Import;

use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Field mapping from WPCode snippets (export JSON or same-site records) to WPIDE snippets.
 * Identifiers for types, locations and condition rules are shared, so they map one to one.
 */
trait WPCodeMapping
{
    protected function mapWPCode(array $source, array $extra = []): array
    {
        $locationExtra = $source['location_extra'] ?? '';

        if (is_string($locationExtra)) {
            $decoded = json_decode($locationExtra, true);
            $locationExtra = is_array($decoded) ? $decoded : [];
        }

        $type = (string) ($source['code_type'] ?? '');
        $autoInsert = (int) ($source['auto_insert'] ?? 1) === 1;
        $tags = $source['tags'] ?? [];

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        $snippet = [
            'title' => (string) ($source['title'] ?? ''),
            'note' => (string) ($source['note'] ?? ''),
            'code' => (string) ($source['code'] ?? ''),
            'code_type' => Types::exists($type) ? $type : $type,
            'insert_method' => $autoInsert ? 'auto' : 'shortcode',
            'location' => $autoInsert ? (string) ($source['location'] ?? '') : '',
            'insert_number' => max(1, (int) ($source['insert_number'] ?? 1)),
            'location_extra' => is_array($locationExtra) ? array_intersect_key($locationExtra, ['css_selector' => 1, 'index' => 1]) : [],
            'priority' => (int) ($source['priority'] ?? 10),
            'use_conditions' => !empty($source['use_rules']),
            'conditions' => is_array($source['rules'] ?? null) && !empty($source['rules']) ? $source['rules'] : ['show' => 'show', 'groups' => []],
            'shortcode' => sanitize_key((string) ($source['custom_shortcode'] ?? '')),
            'shortcode_attributes' => array_values(array_filter((array) ($source['shortcode_attributes'] ?? []), 'is_string')),
            'tags' => $tags,
            'compiled_code' => $type === Types::SCSS ? (string) ($source['compiled_code'] ?? '') : '',
            'source_ref' => !empty($source['id']) ? 'wpcode:' . (int) $source['id'] : '',
        ];

        foreach ($extra as $key => $value) {
            $snippet[$key] = $value;
        }

        $preserved = ['title', 'code', 'code_type', 'location', 'insert_number', 'priority', 'conditions', 'tags', 'note', 'shortcode', 'shortcode_attributes', 'location_extra'];
        $unsupported = [];

        if (!Types::exists($type)) {
            /* translators: %s: code type */
            $unsupported[] = sprintf(__('Code type "%s"', 'wpide'), $type);
        }

        return [$snippet, $preserved, $unsupported];
    }
}
