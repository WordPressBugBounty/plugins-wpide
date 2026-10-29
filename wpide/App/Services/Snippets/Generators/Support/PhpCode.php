<?php

namespace WPIDE\App\Services\Snippets\Generators\Support;

/**
 * Shared building blocks for generators that output PHP.
 *
 * Lives outside the generator folder root so the registry does not try to instantiate it.
 * Every generated snippet is stored without the opening PHP tag and uses 4-space indentation.
 */
trait PhpCode
{
    /** @var string|null */
    protected static $uniqueSuffix;

    /**
     * Short suffix, stable for the current request, used to make default function names unique.
     */
    protected function uid(): string
    {
        if (self::$uniqueSuffix === null) {
            self::$uniqueSuffix = substr(md5(uniqid('', true)), 0, 6);
        }

        return self::$uniqueSuffix;
    }

    /**
     * Yes/No select stored as 'true'/'false' strings (the values used by imported generator data).
     */
    protected function toggle(string $id, string $label, bool $default, string $description = ''): array
    {
        return [
            'id' => $id,
            'type' => 'select',
            'label' => $label,
            'description' => $description,
            'default' => $default ? 'true' : 'false',
            'options' => ['true' => __('Yes', 'wpide'), 'false' => __('No', 'wpide')],
        ];
    }

    protected function text(string $id, string $label, $default = '', string $description = '', string $placeholder = ''): array
    {
        return ['id' => $id, 'type' => 'text', 'label' => $label, 'description' => $description, 'default' => $default, 'placeholder' => $placeholder];
    }

    protected function functionNameField(string $default, string $description = ''): array
    {
        return $this->text(
            'function_name',
            __('Function name', 'wpide'),
            $default . '_' . $this->uid(),
            $description !== '' ? $description : __('A unique PHP function name. Change it if you generate this snippet more than once.', 'wpide'),
            $default
        );
    }

    protected function textDomainField(): array
    {
        return $this->text('text_domain', __('Text domain', 'wpide'), 'text_domain', __('Translation text domain used for the labels (usually your theme or plugin slug).', 'wpide'), 'text_domain');
    }

    protected function on($value): bool
    {
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }

    /**
     * Comma (or newline) separated list to a clean array of strings.
     */
    protected function csv($value): array
    {
        $items = preg_split('/[,\r\n]+/', (string) $value);

        return array_values(array_filter(array_map('trim', $items), 'strlen'));
    }

    /**
     * Comma separated list of IDs to integers.
     */
    protected function ids($value): array
    {
        return array_values(array_filter(array_map('intval', $this->csv($value))));
    }

    /**
     * Lowercase key made of a-z, 0-9, dashes and underscores (post type, taxonomy, hook names...).
     */
    protected function key($value, string $fallback, int $max = 0, string $allowed = 'a-z0-9_\-'): string
    {
        $key = preg_replace('/[^' . $allowed . ']/', '', str_replace(' ', '_', strtolower(trim((string) $value))));

        if ($max > 0) {
            $key = substr($key, 0, $max);
        }

        return $key === '' ? $fallback : $key;
    }

    /**
     * PHP variable name (without the dollar sign).
     */
    protected function varName($value, string $fallback): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', ltrim(trim((string) $value), '$'));

        return $name === '' || is_numeric($name[0]) || $name === 'this' ? $fallback : $name;
    }

    /**
     * Inline PHP list of string literals: array( 'a', 'b' ).
     */
    protected function strList(array $items): string
    {
        if (!$items) {
            return 'array()';
        }

        return 'array( ' . implode(', ', array_map(function ($item) {
            return is_int($item) || is_float($item) ? (string) $item : $this->str($item);
        }, $items)) . ' )';
    }

    /**
     * Multi-line PHP array. Values are PHP expressions (already rendered) or nested arrays.
     * Integer keys are written without a key (a list, or the clauses of a tax/meta query).
     */
    protected function phpArray(array $entries, int $depth = 0): string
    {
        if (!$entries) {
            return 'array()';
        }

        $pad = str_repeat('    ', $depth + 1);
        $isList = array_keys($entries) === range(0, count($entries) - 1);
        $width = 0;

        if (!$isList) {
            foreach (array_keys($entries) as $key) {
                $width = is_int($key) ? $width : max($width, strlen($this->str($key)));
            }
        }

        $lines = [];

        foreach ($entries as $key => $value) {
            $value = is_array($value) ? $this->phpArray($value, $depth + 1) : $value;
            $lines[] = $isList || is_int($key) ? $pad . $value . ',' : $pad . str_pad($this->str($key), $width) . ' => ' . $value . ',';
        }

        return "array(\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ')';
    }

    /**
     * Indent every non-empty line.
     */
    protected function indent(string $code, int $levels = 1): string
    {
        $pad = str_repeat('    ', $levels);

        return implode("\n", array_map(function ($line) use ($pad) {
            return trim($line) === '' ? '' : $pad . $line;
        }, explode("\n", rtrim($code, "\n"))));
    }

    /**
     * Function declaration wrapped in a function_exists() guard.
     */
    protected function guardedFunction(string $name, string $params, string $body): string
    {
        $params = trim($params) === '' ? '' : ' ' . trim($params) . ' ';

        return "if ( ! function_exists( " . $this->str($name) . " ) ) {\n"
            . "    function {$name}({$params}) {\n"
            . $this->indent($body, 2) . "\n"
            . "    }\n"
            . "}\n";
    }

    /**
     * Repeater describing meta keys registered together with a post type or taxonomy.
     */
    protected function metaFieldsRepeater(string $description): array
    {
        return [
            'id' => 'meta_fields',
            'type' => 'repeater',
            'label' => __('Registered meta keys', 'wpide'),
            'description' => $description,
            'default' => [],
            'fields' => [
                $this->text('meta_key', __('Meta key', 'wpide'), '', '', 'subtitle'),
                [
                    'id' => 'meta_type',
                    'type' => 'select',
                    'label' => __('Type', 'wpide'),
                    'default' => 'string',
                    'options' => ['string' => __('Text', 'wpide'), 'integer' => __('Integer', 'wpide'), 'number' => __('Number', 'wpide'), 'boolean' => __('True/false', 'wpide')],
                ],
                $this->text('meta_description', __('Description', 'wpide')),
                ['id' => 'meta_show_in_rest', 'type' => 'checkbox', 'label' => __('Expose in the REST API', 'wpide'), 'default' => true],
            ],
        ];
    }

    /**
     * register_post_meta() / register_term_meta() calls for the meta_fields repeater rows.
     */
    protected function metaRegistration(string $register, string $object, array $rows, string $domain, string $capability): string
    {
        $sanitizers = ['string' => 'sanitize_text_field', 'integer' => 'intval', 'number' => 'floatval', 'boolean' => 'rest_sanitize_boolean'];
        $calls = [];

        foreach ($rows as $row) {
            $key = $this->key($row['meta_key'] ?? '', '', 0, 'a-z0-9_\-');

            if ($key === '') {
                continue;
            }

            $type = isset($sanitizers[$row['meta_type'] ?? '']) ? $row['meta_type'] : 'string';
            $args = ['type' => $this->str($type)];

            if (($row['meta_description'] ?? '') !== '') {
                $args['description'] = $this->tr($row['meta_description'], $domain);
            }

            $args['single'] = 'true';
            $args['show_in_rest'] = $this->bool(!empty($row['meta_show_in_rest']));
            $args['sanitize_callback'] = $this->str($sanitizers[$type]);
            $args['auth_callback'] = "function () {\n" . str_repeat('    ', 2) . 'return current_user_can( ' . $this->str($capability) . " );\n    }";

            $calls[] = $register . '( ' . $this->str($object) . ', ' . $this->str($key) . ', ' . $this->phpArray($args) . ' );';
        }

        return $calls ? "\n\n" . implode("\n\n", $calls) : '';
    }

    /**
     * User supplied PHP body: strip a leading/trailing PHP tag and fall back to a comment.
     */
    protected function userCode($code, string $fallback): string
    {
        $code = trim((string) $code);
        $code = preg_replace('/^<\?(php)?\s*/i', '', $code);
        $code = preg_replace('/\?>\s*$/', '', $code);
        $code = trim($code);

        return $code === '' ? $fallback : $code;
    }
}
