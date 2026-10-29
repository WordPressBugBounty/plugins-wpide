<?php

namespace WPIDE\App\Services\Snippets\Import;

/**
 * Migration from Header Footer Code Manager (99 Robots) installed on this site.
 *
 * Reads the {prefix}hfcm_scripts table without changing it. The "Site Display" rules become WPIDE conditions
 * where an equivalent exists; the rest is reported. Snippets set to "Shortcode only" get a per-snippet shortcode.
 */
class HeaderFooterCodeManager extends Importer
{
    const TYPES = ['html' => 'html', 'js' => 'js', 'css' => 'css'];

    const LOCATIONS = [
        'header' => 'site_wide_header',
        'footer' => 'site_wide_footer',
        'before_content' => 'before_content',
        'after_content' => 'after_content',
    ];

    const DEVICES = ['both' => 'any', 'desktop' => 'desktop', 'mobile' => 'mobile'];

    public function id(): string
    {
        return 'header-footer-code-manager';
    }

    public function label(): string
    {
        return __('Header Footer Code Manager on this site', 'wpide');
    }

    public function description(): string
    {
        return __('Copy snippets and their display rules from Header Footer Code Manager installed on this site. Its data is not changed.', 'wpide');
    }

    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'hfcm_scripts';
    }

    protected function tableExists(): bool
    {
        global $wpdb;

        $table = $this->table();

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public function available(): bool
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return false;
        }

        $table = $this->table();

        return (bool) $wpdb->get_var("SELECT script_id FROM `$table` LIMIT 1"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    protected function items(?array $file = null): array
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return [];
        }

        $table = $this->table();
        $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY script_id ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $items = [];

        foreach ((array) $rows as $row) {
            $items[] = $this->item($row);
        }

        return $items;
    }

    protected function item(array $row): array
    {
        $id = (int) $row['script_id'];
        $unsupported = [];
        $warnings = [];

        // HFCM stores the snippet HTML-entity encoded and decodes it on output.
        $code = html_entity_decode((string) ($row['snippet'] ?? ''), ENT_QUOTES | ENT_HTML401, 'UTF-8');

        $sourceType = (string) ($row['snippet_type'] ?? 'html');
        $type = self::TYPES[$sourceType] ?? 'html';

        if (!isset(self::TYPES[$sourceType])) {
            $unsupported[] = sprintf(__('Snippet type "%s" (imported as HTML)', 'wpide'), $sourceType);
        }

        // HFCM prints the code as it is, so CSS/JS snippets often carry their own <style>/<script> tags.
        if ($type !== 'html' && $this->hasMarkup($code)) {
            $type = 'html';
            $warnings[] = __('The code contains its own HTML tags, so it is imported as an HTML snippet to be printed unchanged.', 'wpide');
        }

        $device = (string) ($row['device_type'] ?? 'both');
        $display = (string) ($row['display_on'] ?? 'All');
        $sourceLocation = (string) ($row['location'] ?? '');

        $snippet = [
            'title' => trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : sprintf(__('HFCM snippet #%d', 'wpide'), $id),
            'code' => $code,
            'code_type' => $type,
            'insert_method' => 'auto',
            'location' => '',
            'device_type' => self::DEVICES[$device] ?? 'any',
            'use_conditions' => false,
            'conditions' => ['show' => 'show', 'groups' => []],
            'source_ref' => $this->id() . ':' . $id,
        ];

        if (!isset(self::DEVICES[$device])) {
            $unsupported[] = sprintf(__('Device "%s"', 'wpide'), $device);
        }

        if ($display === 'manual') {
            $snippet['insert_method'] = 'shortcode';
            $name = 'hfcm-' . $id;

            if (preg_match('/^[a-z0-9_-]{1,64}$/', $name) && !shortcode_exists($name)) {
                $snippet['shortcode'] = $name;
                $warnings[] = sprintf(__('Content that uses [hfcm id="%1$d"] must be changed to [%2$s].', 'wpide'), $id, $name);
            } else {
                $warnings[] = sprintf(__('Content that uses [hfcm id="%d"] must be changed to use this snippet\'s WPIDE shortcode.', 'wpide'), $id);
            }
        } else {
            if (isset(self::LOCATIONS[$sourceLocation])) {
                $snippet['location'] = self::LOCATIONS[$sourceLocation];
            } else {
                $snippet['insert_method'] = 'shortcode';
                $unsupported[] = sprintf(__('Location "%s" (imported as shortcode only)', 'wpide'), $sourceLocation);
            }

            $groups = $this->groups($row, $display, $unsupported, $warnings);

            if ($groups === null) {
                // Nothing to show on (no pages selected, or an unknown rule): never insert automatically.
                $snippet['insert_method'] = 'shortcode';
                $snippet['location'] = '';
            } elseif ($groups) {
                $snippet['use_conditions'] = true;
                $snippet['conditions'] = ['show' => 'show', 'groups' => $groups];
            }
        }

        if ($snippet['insert_method'] === 'shortcode') {
            $snippet['location'] = '';
        }

        $active = ($row['status'] ?? '') === 'active';

        if ($active) {
            $warnings[] = __('Active in Header Footer Code Manager. Deactivate it there before activating it here, or it will be output twice.', 'wpide');
        }

        return [
            'key' => (string) $id,
            'title' => $snippet['title'],
            'code_type' => $type,
            'snippet' => $snippet,
            'original_status' => $active ? __('active', 'wpide') : __('inactive', 'wpide'),
            'preserved' => ['title', 'code', 'code_type', 'location', 'device_type', 'conditions', 'shortcode'],
            'missing' => [__('Priority (HFCM has none; 10 is used)', 'wpide'), __('Description', 'wpide'), __('Tags', 'wpide')],
            'unsupported' => $unsupported,
            'warnings' => $warnings,
        ];
    }

    /**
     * Condition groups for a "Site Display" rule: [] = everywhere, null = cannot be inserted automatically.
     */
    protected function groups(array $row, string $display, array &$unsupported, array &$warnings): ?array
    {
        switch ($display) {
            case 'All':
                $excluded = array_values(array_unique(array_merge($this->ids($row['ex_pages'] ?? ''), $this->ids($row['ex_posts'] ?? ''))));

                if (!$excluded) {
                    return [];
                }

                // HFCM only skips the excluded posts and pages when they are viewed on their own.
                return [
                    [$this->rule('type_of_page', '!=', 'is_single'), $this->rule('type_of_page', '!=', 'is_front_page')],
                    [$this->rule('post_id', '!=', $excluded)],
                ];

            case 's_pages':
                $ids = $this->ids($row['s_pages'] ?? '');

                if (!$ids) {
                    return $this->nothingSelected($warnings);
                }

                $groups = [
                    [$this->rule('type_of_page', '=', 'is_single'), $this->rule('post_id', '=', $ids)],
                    [$this->rule('type_of_page', '=', 'is_front_page'), $this->rule('post_id', '=', $ids)],
                ];

                // HFCM matches the posts page by its page ID too.
                if (in_array((string) get_option('page_for_posts'), $ids, true)) {
                    $groups[] = [$this->rule('type_of_page', '=', 'is_home')];
                }

                return $groups;

            case 's_posts':
                $ids = $this->ids($row['s_posts'] ?? '');

                return $ids ? [[$this->rule('type_of_page', '=', 'is_single'), $this->rule('post_id', '=', $ids)]] : $this->nothingSelected($warnings);

            case 's_categories':
            case 's_tags':
                $ids = $this->ids($row[$display] ?? '');

                return $ids ? [[$this->rule('taxonomy_term', '=', $ids)]] : $this->nothingSelected($warnings);

            case 's_custom_posts':
                $types = $this->ids($row['s_custom_posts'] ?? '', false);

                if (!$types) {
                    return $this->nothingSelected($warnings);
                }

                return array_map(function ($postType) {
                    return [$this->rule('type_of_page', '=', 'is_single'), $this->rule('post_type', '=', $postType)];
                }, $types);

            case 's_is_home':
                // is_home() is the blog index, which is also the front page when the front page shows posts.
                return [[$this->rule('type_of_page', '=', get_option('show_on_front') === 'page' ? 'is_home' : 'is_front_page')]];

            case 's_is_archive':
                return [[$this->rule('type_of_page', '=', 'is_archive')], [$this->rule('type_of_page', '=', 'is_author')]];

            case 's_is_search':
                return [[$this->rule('type_of_page', '=', 'is_search')]];

            case 'latest_posts':
                $count = (int) ($row['lp_count'] ?? 0);
                $unsupported[] = $count > 0
                    ? sprintf(__('Display on the %d latest posts only (imported as: all single posts)', 'wpide'), $count)
                    : __('Display on the latest posts only (imported as: all single posts)', 'wpide');

                return [[$this->rule('type_of_page', '=', 'is_single'), $this->rule('post_type', '=', 'post')]];
        }

        $unsupported[] = sprintf(__('Site display rule "%s" (imported as shortcode only)', 'wpide'), $display);

        return null;
    }

    protected function nothingSelected(array &$warnings): ?array
    {
        $warnings[] = __('No pages, posts or terms were selected in Header Footer Code Manager, so it was shown nowhere. It is imported as shortcode only.', 'wpide');

        return null;
    }

    protected function rule(string $option, string $relation, $value): array
    {
        return ['type' => 'page', 'option' => $option, 'relation' => $relation, 'value' => $value];
    }

    /**
     * Decode a stored selection (JSON in current versions, serialized or comma separated in older ones).
     *
     * @param mixed $value
     *
     * @return string[]
     */
    protected function ids($value, bool $numeric = true): array
    {
        if (!is_array($value)) {
            $value = trim((string) $value);

            if ($value === '' || $value === 'null') {
                return [];
            }

            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            } elseif (is_serialized($value)) {
                $value = (array) @unserialize($value, ['allowed_classes' => false]); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions
            } else {
                $value = explode(',', $value);
            }
        }

        $list = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $item = $numeric ? (string) absint($item) : sanitize_key((string) $item);

            if ($item !== '' && $item !== '0') {
                $list[] = $item;
            }
        }

        return array_values(array_unique($list));
    }

    protected function hasMarkup(string $code): bool
    {
        // Plain CSS or JavaScript never starts with a tag; markup around it always shows up as <script> or <style>.
        return (bool) (preg_match('/^\s*</', $code) || preg_match('/<\s*\/?\s*(script|style)\b/i', $code));
    }
}
