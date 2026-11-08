<?php

namespace WPIDE\App\Services\Snippets\Import;

/**
 * Migration from Post Snippets installed on this site.
 *
 * Current versions keep snippets in {prefix}pspro_snippets (groups in {prefix}pspro_groups); older versions kept
 * them in the post_snippets_options option. The table is read when it has rows, the option otherwise. Nothing
 * is changed. Each snippet's title is its shortcode tag, so it becomes the WPIDE shortcode name, and its
 * variables become declared shortcode attributes.
 */
class PostSnippets extends Importer
{
    const OPTION = 'post_snippets_options';

    public function id(): string
    {
        return 'post-snippets';
    }

    public function label(): string
    {
        return __('Post Snippets on this site', 'wpide');
    }

    public function description(): string
    {
        return __('Copy snippets from Post Snippets installed on this site, keeping their shortcode names and variables. Its data is not changed.', 'wpide');
    }

    public function available(): bool
    {
        return (bool) $this->records();
    }

    protected function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . $name;
    }

    protected function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    /**
     * Source snippets in one shape: id, title, code, vars, description, shortcode, kind (php|html|js|css),
     * texturize, groups, status.
     */
    protected function records(): array
    {
        global $wpdb;

        $records = [];
        $table = $this->table('pspro_snippets');

        if ($this->tableExists($table)) {
            $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY ID ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            $groups = $this->groupNames();
            $kinds = ['1' => 'php', '2' => 'js', '3' => 'css'];

            foreach ((array) $rows as $row) {
                $names = [];

                foreach ($this->groupIds($row['snippet_group'] ?? '') as $groupId) {
                    $name = $groups[(int) $groupId] ?? '';

                    if ($name !== '' && strtolower($name) !== 'ungrouped') {
                        $names[] = $name;
                    }
                }

                $records[] = [
                    'id' => 'table-' . (int) $row['ID'],
                    'title' => (string) ($row['snippet_title'] ?? ''),
                    'code' => (string) ($row['snippet_content'] ?? ''),
                    'vars' => (string) ($row['snippet_vars'] ?? ''),
                    'description' => (string) ($row['snippet_desc'] ?? ''),
                    'shortcode' => $this->flag($row['snippet_shortcode'] ?? ''),
                    'kind' => $kinds[(string) ($row['snippet_php'] ?? '0')] ?? 'html',
                    'texturize' => $this->flag($row['snippet_wptexturize'] ?? ''),
                    'groups' => $names,
                    'status' => isset($row['snippet_status']) ? (string) $row['snippet_status'] : '',
                ];
            }
        }

        if ($records) {
            return $records;
        }

        $legacy = get_option(self::OPTION);

        foreach (is_array($legacy) ? $legacy : [] as $index => $snippet) {
            if (!is_array($snippet)) {
                continue;
            }

            $records[] = [
                'id' => 'option-' . $index,
                'title' => (string) ($snippet['title'] ?? ''),
                'code' => (string) ($snippet['snippet'] ?? ''),
                'vars' => (string) ($snippet['vars'] ?? ''),
                'description' => (string) ($snippet['description'] ?? ''),
                'shortcode' => $this->flag($snippet['shortcode'] ?? ''),
                'kind' => $this->flag($snippet['php'] ?? '') ? 'php' : 'html',
                'texturize' => $this->flag($snippet['wptexturize'] ?? ''),
                'groups' => [],
                'status' => '',
            ];
        }

        return $records;
    }

    protected function groupNames(): array
    {
        global $wpdb;

        $table = $this->table('pspro_groups');

        if (!$this->tableExists($table)) {
            return [];
        }

        $names = [];

        foreach ((array) $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A) as $row) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            $names[(int) ($row['ID'] ?? 0)] = (string) ($row['group_name'] ?? '');
        }

        return $names;
    }

    /**
     * Group ids are stored serialized; JSON and comma separated lists are accepted too.
     *
     * @param mixed $value
     *
     * @return int[]
     */
    protected function groupIds($value): array
    {
        if (is_string($value)) {
            $value = trim($value);

            if (is_serialized($value)) {
                $value = @unserialize($value, ['allowed_classes' => false]); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions
            } elseif (is_array(json_decode($value, true))) {
                $value = json_decode($value, true);
            } else {
                $value = $value === '' ? [] : explode(',', $value);
            }
        }

        return array_values(array_filter(array_map('absint', array_filter((array) $value, 'is_scalar'))));
    }

    protected function flag($value): bool
    {
        return in_array(is_string($value) ? strtolower(trim($value)) : $value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    protected function items(?array $file = null): array
    {
        $items = [];

        foreach ($this->records() as $record) {
            $items[] = $record['kind'] === 'js' || $record['kind'] === 'css' ? $this->assetItem($record) : $this->shortcodeItem($record);
        }

        return $items;
    }

    /**
     * PHP and HTML snippets: shortcode snippets named after the Post Snippets tag.
     */
    protected function shortcodeItem(array $record): array
    {
        $isPhp = $record['kind'] === 'php';
        $code = $record['code'];
        $unsupported = [];
        $warnings = [];
        $missing = [];
        $noteLines = [];

        list($attributes, $defaults, $renamed) = $this->variables($record['vars']);

        if ($defaults) {
            /* translators: %s: list of default variable values */
            $unsupported[] = sprintf(__('Default variable values (%s); attributes now default to empty', 'wpide'), implode(', ', $defaults));
        }

        foreach ($renamed as $from => $to) {
            /* translators: 1: original variable name, 2: new variable name */
            $warnings[] = sprintf(__('Variable "%1$s" is renamed to "%2$s" (shortcode attributes are lowercase).', 'wpide'), $from, $to);
        }

        if ($isPhp) {
            // Post Snippets makes variables available to PHP code; in WPIDE declared attributes are PHP variables too.
            foreach (array_keys($attributes) as $original) {
                if (strpos($code, '{' . $original . '}') !== false) {
                    /* translators: 1: original variable name, 2: new variable name */
                    $warnings[] = sprintf(__('The PHP code uses the placeholder {%1$s}. Replace it with the variable $%2$s.', 'wpide'), $original, $attributes[$original]);
                }
            }
        } else {
            // {name} placeholders become WPIDE {attr:name} placeholders.
            foreach ($attributes as $original => $key) {
                $code = str_replace('{' . $original . '}', '{attr:' . $key . '}', $code);
            }
        }

        $shortcode = '';
        $sourceTag = trim($record['title']);

        if ($record['shortcode'] || $isPhp) {
            $shortcode = $this->shortcodeName($sourceTag);

            if ($shortcode === '') {
                /* translators: %s: original shortcode name */
                $warnings[] = sprintf(__('"%s" cannot be used as a shortcode name. Content that uses it must be changed to this snippet\'s WPIDE shortcode.', 'wpide'), $sourceTag);
            } elseif (shortcode_exists($shortcode)) {
                // Usually Post Snippets itself, while it is still active.
                /* translators: %s: shortcode name */
                $warnings[] = sprintf(__('The shortcode [%s] is still registered by another plugin (Post Snippets is probably active), so the snippet is imported without a custom shortcode name. Deactivate Post Snippets, then set the name on the snippet.', 'wpide'), $shortcode);
                /* translators: %s: shortcode name */
                $noteLines[] = sprintf(__('Original shortcode: [%s]', 'wpide'), $shortcode);
                $shortcode = '';
            } elseif ($shortcode !== $sourceTag) {
                /* translators: 1: original shortcode name, 2: new shortcode name */
                $warnings[] = sprintf(__('Content that uses [%1$s] must be changed to [%2$s].', 'wpide'), $sourceTag, $shortcode);
            }

            if (!$record['shortcode']) {
                $warnings[] = __('PHP snippet without the shortcode option: Post Snippets can only run it as a shortcode, so it is imported as one.', 'wpide');
            }
        } else {
            $warnings[] = __('Not a shortcode in Post Snippets: its content was pasted into posts by the editor button, so existing posts keep their copy. It is imported as a shortcode snippet for reuse.', 'wpide');
            $missing[] = __('Shortcode name (this snippet had none)', 'wpide');
        }

        if ($record['texturize']) {
            $unsupported[] = __('wptexturize formatting of the output', 'wpide');
        }

        $description = trim(wp_strip_all_tags($record['description']));

        if ($description !== '') {
            array_unshift($noteLines, $description);
        }

        $snippet = [
            /* translators: %s: Post Snippets record ID */
            'title' => $sourceTag !== '' ? $sourceTag : sprintf(__('Post Snippets %s', 'wpide'), $record['id']),
            'note' => implode("\n\n", $noteLines),
            'code' => $code,
            'code_type' => $isPhp ? 'php' : 'html',
            'insert_method' => 'shortcode',
            'location' => '',
            'shortcode' => $shortcode,
            'shortcode_attributes' => array_values($attributes),
            'tags' => $record['groups'],
            'source_ref' => $this->id() . ':' . $record['id'],
        ];

        return $this->item($record, $snippet, ['title', 'code', 'code_type', 'shortcode', 'shortcode_attributes', 'note', 'tags'], $missing, $unsupported, $warnings);
    }

    /**
     * JavaScript and CSS snippets (Post Snippets Pro): printed in the header or footer of the site or admin.
     * Their position is kept in the variables field (header/footer, frontend/admin).
     */
    protected function assetItem(array $record): array
    {
        $type = $record['kind'];
        $tokens = array_map('strtolower', array_map('trim', explode(',', $record['vars'])));
        $admin = in_array('admin', $tokens, true);
        $footer = $type === 'js' && in_array('footer', $tokens, true);
        $location = $admin ? ($footer ? 'admin_footer' : 'admin_head') : ($footer ? 'site_wide_footer' : 'site_wide_header');
        $warnings = [];
        $code = $record['code'];

        if (preg_match('/^\s*</', $code) || preg_match('/<\s*\/?\s*(script|style)\b/i', $code)) {
            $type = 'html';
            $warnings[] = __('The code contains its own HTML tags, so it is imported as an HTML snippet to be printed unchanged.', 'wpide');
        }

        $description = trim(wp_strip_all_tags($record['description']));
        $title = trim($record['title']);

        $snippet = [
            /* translators: %s: Post Snippets record ID */
            'title' => $title !== '' ? $title : sprintf(__('Post Snippets %s', 'wpide'), $record['id']),
            'note' => $description,
            'code' => $code,
            'code_type' => $type,
            'insert_method' => 'auto',
            'location' => $location,
            'tags' => $record['groups'],
            'source_ref' => $this->id() . ':' . $record['id'],
        ];

        $warnings[] = __('Post Snippets prints this code automatically. Deactivate it there before activating it here, or it will be output twice.', 'wpide');

        return $this->item($record, $snippet, ['title', 'code', 'code_type', 'location', 'note', 'tags'], [__('Priority (Post Snippets has none; 10 is used)', 'wpide')], $record['texturize'] ? [__('wptexturize formatting of the output', 'wpide')] : [], $warnings);
    }

    protected function item(array $record, array $snippet, array $preserved, array $missing, array $unsupported, array $warnings): array
    {
        if ($record['status'] !== '') {
            $status = in_array(strtolower($record['status']), ['1', 'active', 'publish', 'enabled'], true) ? __('active', 'wpide') : __('inactive', 'wpide');
        } else {
            $status = __('active (Post Snippets snippets are always available)', 'wpide');
        }

        return [
            'key' => $record['id'],
            'title' => $snippet['title'],
            'code_type' => $snippet['code_type'],
            'snippet' => $snippet,
            'original_status' => $status,
            'preserved' => $preserved,
            'missing' => $missing,
            'unsupported' => $unsupported,
            'warnings' => $warnings,
        ];
    }

    /**
     * Parse "name,other=default" into [original => attribute key], defaults and renamed variables.
     */
    protected function variables(string $vars): array
    {
        $attributes = [];
        $defaults = [];
        $renamed = [];

        foreach (explode(',', $vars) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $pieces = explode('=', $part, 2);
            $original = trim($pieces[0]);
            $key = sanitize_key($original);

            if ($key === '') {
                continue;
            }

            $attributes[$original] = $key;

            if ($key !== $original) {
                $renamed[$original] = $key;
            }

            if (isset($pieces[1]) && trim($pieces[1]) !== '') {
                $defaults[] = $key . '=' . trim($pieces[1]);
            }
        }

        return [$attributes, $defaults, $renamed];
    }

    /**
     * WPIDE shortcode names allow [a-z0-9_-], up to 64 characters.
     */
    protected function shortcodeName(string $tag): string
    {
        $name = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($tag));
        $name = substr(trim((string) $name, '-'), 0, 64);

        return $name === 'wpide_snippet' ? '' : $name;
    }
}
