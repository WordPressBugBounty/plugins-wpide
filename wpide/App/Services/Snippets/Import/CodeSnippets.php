<?php

namespace WPIDE\App\Services\Snippets\Import;

/**
 * Migration from Code Snippets (free and Pro) installed on this site.
 *
 * Both editions keep their snippets in the {prefix}snippets table; on the main site of a multisite network the
 * network-wide snippets in {base_prefix}ms_snippets are offered as well. The tables are only read, so Code
 * Snippets keeps working until it is switched off. Each "scope" becomes a WPIDE code type and location.
 */
class CodeSnippets extends Importer
{
    /**
     * Code Snippets scope => [WPIDE code type, WPIDE location ('' = shortcode only)].
     */
    const SCOPES = [
        'global' => ['php', 'everywhere'],
        'admin' => ['php', 'admin_only'],
        'front-end' => ['php', 'frontend_only'],
        'single-use' => ['php', 'on_demand'],
        'content' => ['html', ''],
        'head-content' => ['html', 'site_wide_header'],
        'footer-content' => ['html', 'site_wide_footer'],
        'site-css' => ['css', 'site_wide_header'],
        'admin-css' => ['css', 'admin_head'],
        'site-head-js' => ['js', 'site_wide_header'],
        'site-footer-js' => ['js', 'site_wide_footer'],
    ];

    public function id(): string
    {
        return 'code-snippets';
    }

    public function label(): string
    {
        return __('Code Snippets on this site', 'wpide');
    }

    public function description(): string
    {
        return __('Copy snippets from Code Snippets or Code Snippets Pro installed on this site. Code Snippets data is not changed.', 'wpide');
    }

    public function available(): bool
    {
        global $wpdb;

        foreach ($this->tables() as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            if ($wpdb->get_var("SELECT id FROM `$table` WHERE scope != 'condition' LIMIT 1")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Snippet tables that exist and have the Code Snippets columns: 'site' and, on a network's main site, 'network'.
     */
    protected function tables(): array
    {
        global $wpdb;

        $tables = [];
        $candidates = ['site' => $wpdb->prefix . 'snippets'];

        if (is_multisite() && is_main_site()) {
            $candidates['network'] = $wpdb->base_prefix . 'ms_snippets';
        }

        foreach ($candidates as $kind => $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                continue;
            }

            $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

            // {prefix}snippets is a generic name: only accept the Code Snippets layout.
            if (!array_diff(['id', 'name', 'code', 'scope'], (array) $columns)) {
                $tables[$kind] = $table;
            }
        }

        return $tables;
    }

    protected function items(?array $file = null): array
    {
        global $wpdb;

        $items = [];

        foreach ($this->tables() as $kind => $table) {
            $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY id ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            $conditions = [];

            foreach ((array) $rows as $row) {
                if (($row['scope'] ?? '') === 'condition') {
                    $conditions[(int) $row['id']] = (string) $row['name'];
                }
            }

            foreach ((array) $rows as $row) {
                $scope = (string) ($row['scope'] ?? 'global');

                // Pro conditions are rule sets attached to other snippets, not code; trashed snippets are left behind.
                if ($scope === 'condition' || (isset($row['active']) && (int) $row['active'] < 0)) {
                    continue;
                }

                $items[] = $this->item($row, $kind, $conditions);
            }
        }

        return $items;
    }

    protected function item(array $row, string $kind, array $conditions): array
    {
        $id = (int) $row['id'];
        $scope = (string) ($row['scope'] ?? 'global');
        $code = (string) ($row['code'] ?? '');
        $unsupported = [];
        $warnings = [];
        $missing = [];
        $noteLines = [];

        if (isset(self::SCOPES[$scope])) {
            list($type, $location) = self::SCOPES[$scope];
        } else {
            list($type, $location) = $this->guessScope($scope);
            $unsupported[] = sprintf(__('Scope "%s"', 'wpide'), $scope);
        }

        if ($scope === 'content') {
            // Content snippets may embed PHP (it runs when the shortcode is used with php="yes").
            if (preg_match('/<\?(php|=)/i', $code)) {
                $type = 'universal';
            }

            $noteLines[] = sprintf(__('Imported from Code Snippets content snippet #%d.', 'wpide'), $id);
            $warnings[] = sprintf(__('Pages that use [code_snippet id="%d"] must be changed to use this snippet\'s WPIDE shortcode.', 'wpide'), $id);
            $unsupported[] = __('Per-shortcode options of [code_snippet] (php, format, shortcodes)', 'wpide');
        }

        $description = trim(wp_strip_all_tags((string) ($row['description'] ?? $row['desc'] ?? '')));

        if ($description !== '') {
            array_unshift($noteLines, $description);
        }

        $conditionId = (int) ($row['condition_id'] ?? 0);

        if ($conditionId > 0) {
            $name = $conditions[$conditionId] ?? '';
            $unsupported[] = $name !== ''
                ? sprintf(__('Code Snippets Pro condition "%1$s" (#%2$d); add equivalent WPIDE conditions', 'wpide'), $name, $conditionId)
                : sprintf(__('Code Snippets Pro condition #%d; add equivalent WPIDE conditions', 'wpide'), $conditionId);
        }

        if ($kind === 'network') {
            $warnings[] = __('Network snippet: in Code Snippets it can run on every site of the network. Here it is imported into this site only.', 'wpide');
        }

        $active = (int) ($row['active'] ?? 0) === 1;

        if ($active) {
            $warnings[] = $type === 'php'
                ? __('Active in Code Snippets. Deactivate it there before activating it here: running the same PHP twice can cause a fatal error.', 'wpide')
                : __('Active in Code Snippets. Deactivate it there before activating it here, or it will be output twice.', 'wpide');
        }

        if ($scope === 'single-use') {
            $warnings[] = __('Single-use snippet: it is imported as "On demand" and only runs when you run it manually.', 'wpide');
        }

        $sourceId = $kind === 'network' ? 'network-' . $id : (string) $id;
        $title = trim((string) ($row['name'] ?? ''));

        $snippet = [
            'title' => $title !== '' ? $title : sprintf(__('Code Snippets #%d', 'wpide'), $id),
            'note' => implode("\n\n", $noteLines),
            'code' => $code,
            'code_type' => $type,
            'insert_method' => $location === '' ? 'shortcode' : 'auto',
            'location' => $location,
            'priority' => isset($row['priority']) ? (int) $row['priority'] : 10,
            'tags' => $this->tags($row['tags'] ?? ''),
            'source_ref' => $this->id() . ':' . $sourceId,
        ];

        if ($title === '') {
            $missing[] = __('Title', 'wpide');
        }

        return [
            'key' => $sourceId,
            'title' => $snippet['title'],
            'code_type' => $type,
            'snippet' => $snippet,
            'original_status' => $active ? __('active', 'wpide') : __('inactive', 'wpide'),
            'preserved' => ['title', 'code', 'code_type', 'location', 'priority', 'tags', 'note'],
            'missing' => $missing,
            'unsupported' => $unsupported,
            'warnings' => $warnings,
        ];
    }

    /**
     * Best guess for scopes added by future Code Snippets versions. PHP falls back to "On demand" so an
     * unknown scope never starts running automatically.
     */
    protected function guessScope(string $scope): array
    {
        if (substr($scope, -4) === '-css') {
            return ['css', strpos($scope, 'admin') === 0 ? 'admin_head' : 'site_wide_header'];
        }

        if (substr($scope, -3) === '-js') {
            return ['js', strpos($scope, 'footer') !== false ? 'site_wide_footer' : 'site_wide_header'];
        }

        if (substr($scope, -7) === 'content') {
            return ['html', ''];
        }

        return ['php', 'on_demand'];
    }

    /**
     * @param string|array $tags Comma separated in the table.
     */
    protected function tags($tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        return array_values(array_filter(array_map('trim', array_map('strval', (array) $tags))));
    }
}
