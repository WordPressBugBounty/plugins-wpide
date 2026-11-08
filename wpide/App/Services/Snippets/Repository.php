<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Snippet storage.
 *
 * Every write records a revision snapshot (when the content changed) and rebuilds the runtime manifest,
 * so the snippets that run always reflect the stored state.
 */
class Repository
{
    const JSON_FIELDS = ['location_extra', 'conditions', 'schedule', 'shortcode_attributes', 'tags', 'settings', 'generator_data', 'draft', 'last_error'];

    /**
     * Fields that describe what a snippet is and how it runs. They are snapshotted in revisions.
     */
    const CONTENT_FIELDS = [
        'title', 'note', 'code', 'compiled_code', 'code_type', 'status', 'insert_method', 'location', 'insert_number',
        'location_extra', 'priority', 'use_conditions', 'conditions', 'schedule', 'device_type', 'shortcode',
        'shortcode_attributes', 'tags', 'settings', 'generator', 'generator_data', 'library_ref',
    ];

    public static function defaults(): array
    {
        return [
            'title' => '',
            'note' => '',
            'code' => '',
            'compiled_code' => '',
            'code_type' => 'php',
            'status' => 'draft',
            'insert_method' => 'auto',
            'location' => '',
            'insert_number' => 1,
            'location_extra' => [],
            'priority' => 10,
            'use_conditions' => false,
            'conditions' => ['show' => 'show', 'groups' => []],
            'schedule' => ['start' => '', 'end' => ''],
            'device_type' => 'any',
            'shortcode' => '',
            'shortcode_attributes' => [],
            'tags' => [],
            'settings' => ['load_as_file' => false, 'compress_output' => false, 'smart_tags' => true, 'network' => ['enabled' => false, 'mode' => 'all', 'sites' => []]],
            'generator' => '',
            'generator_data' => [],
            'library_ref' => '',
            'provenance' => 'local',
            'source_ref' => '',
            'cloud_ref' => '',
            'draft' => null,
            'quarantined' => false,
            'last_error' => null,
        ];
    }

    /**
     * Bring partial input to the full, typed snippet shape.
     */
    public static function normalize(array $data, array $base = []): array
    {
        $snippet = array_merge(self::defaults(), $base, array_intersect_key($data, self::defaults()));

        $snippet['title'] = trim((string) $snippet['title']);
        $snippet['note'] = (string) $snippet['note'];
        $snippet['code'] = (string) $snippet['code'];
        $snippet['compiled_code'] = (string) $snippet['compiled_code'];
        $snippet['code_type'] = (string) $snippet['code_type'];
        $snippet['status'] = in_array($snippet['status'], ['active', 'draft', 'trash'], true) ? $snippet['status'] : 'draft';
        $snippet['insert_method'] = $snippet['insert_method'] === 'shortcode' ? 'shortcode' : 'auto';
        $snippet['location'] = (string) $snippet['location'];
        $snippet['insert_number'] = max(1, (int) $snippet['insert_number']);
        $snippet['location_extra'] = is_array($snippet['location_extra']) ? $snippet['location_extra'] : [];
        $snippet['priority'] = (int) $snippet['priority'];
        $snippet['use_conditions'] = (bool) $snippet['use_conditions'];
        $snippet['conditions'] = is_array($snippet['conditions']) ? $snippet['conditions'] + ['show' => 'show', 'groups' => []] : ['show' => 'show', 'groups' => []];
        $snippet['schedule'] = array_merge(['start' => '', 'end' => ''], is_array($snippet['schedule']) ? array_intersect_key($snippet['schedule'], ['start' => 1, 'end' => 1]) : []);
        $snippet['device_type'] = in_array($snippet['device_type'], ['any', 'desktop', 'mobile'], true) ? $snippet['device_type'] : 'any';
        $snippet['shortcode'] = (string) $snippet['shortcode'];
        $snippet['shortcode_attributes'] = array_values(array_filter(array_map('sanitize_key', (array) $snippet['shortcode_attributes'])));
        $snippet['tags'] = self::normalizeTags($snippet['tags']);
        $snippet['settings'] = array_merge(self::defaults()['settings'], is_array($snippet['settings']) ? $snippet['settings'] : []);
        $snippet['settings']['load_as_file'] = (bool) $snippet['settings']['load_as_file'];
        $snippet['settings']['compress_output'] = (bool) $snippet['settings']['compress_output'];
        $snippet['settings']['smart_tags'] = (bool) $snippet['settings']['smart_tags'];
        $network = is_array($snippet['settings']['network']) ? $snippet['settings']['network'] : [];
        $snippet['settings']['network'] = [
            'enabled' => !empty($network['enabled']),
            'mode' => in_array($network['mode'] ?? 'all', ['all', 'include', 'exclude'], true) ? $network['mode'] : 'all',
            'sites' => array_values(array_unique(array_filter(array_map('intval', (array) ($network['sites'] ?? []))))),
        ];
        $snippet['settings'] = array_intersect_key($snippet['settings'], self::defaults()['settings']);
        $snippet['generator'] = (string) $snippet['generator'];
        $snippet['generator_data'] = is_array($snippet['generator_data']) ? $snippet['generator_data'] : [];
        $snippet['quarantined'] = (bool) $snippet['quarantined'];

        if ($snippet['title'] === '') {
            $snippet['title'] = __('Untitled snippet', 'wpide');
        }

        return $snippet;
    }

    public static function normalizeTags($tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        $tags = array_map(function ($tag) {
            return sanitize_title(trim((string) $tag));
        }, (array) $tags);

        return array_values(array_unique(array_filter($tags)));
    }

    protected static function decode($row): ?array
    {
        if (!$row) {
            return null;
        }

        $row = (array) $row;

        foreach (self::JSON_FIELDS as $field) {
            $row[$field] = isset($row[$field]) && $row[$field] !== '' ? json_decode($row[$field], true) : null;
        }

        $row['id'] = (int) $row['id'];
        $row['insert_number'] = (int) $row['insert_number'];
        $row['priority'] = (int) $row['priority'];
        $row['use_conditions'] = (bool) $row['use_conditions'];
        $row['quarantined'] = (bool) $row['quarantined'];
        $row['created_by'] = (int) $row['created_by'];
        $row['updated_by'] = (int) $row['updated_by'];

        $snippet = self::normalize($row);

        foreach (['id', 'uuid', 'created_by', 'updated_by', 'created_at', 'updated_at'] as $field) {
            $snippet[$field] = $row[$field];
        }

        $snippet['draft'] = $row['draft'];
        $snippet['last_error'] = $row['last_error'];

        return $snippet;
    }

    protected static function encode(array $snippet): array
    {
        $row = [];

        foreach (self::defaults() as $field => $default) {
            if (!array_key_exists($field, $snippet)) {
                continue;
            }

            $value = $snippet[$field];

            if (in_array($field, self::JSON_FIELDS, true)) {
                $value = $value === null ? null : wp_json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $row[$field] = $value;
        }

        return $row;
    }

    public static function find(int $id): ?array
    {
        global $wpdb;

        $table = Schema::snippetsTable();

        return self::decode($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function findByShortcode(string $name, int $excludeId = 0): ?array
    {
        global $wpdb;

        $table = Schema::snippetsTable();

        return self::decode($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE shortcode = %s AND id != %d AND status != 'trash' LIMIT 1", $name, $excludeId))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * @return array{items: array, total: int, counts: array}
     */
    public static function query(array $args = []): array
    {
        global $wpdb;

        $args = array_merge([
            'status' => 'all',
            'type' => '',
            'tag' => '',
            'location' => '',
            'search' => '',
            'orderby' => 'updated_at',
            'order' => 'desc',
            'page' => 1,
            'per_page' => 50,
        ], $args);

        $table = Schema::snippetsTable();
        $where = ['1=1'];
        $params = [];

        switch ($args['status']) {
            case 'active':
                $where[] = "status = 'active' AND quarantined = 0";
                break;
            case 'inactive':
                $where[] = "status = 'draft'";
                break;
            case 'trash':
                $where[] = "status = 'trash'";
                break;
            case 'error':
                $where[] = "status != 'trash' AND (quarantined = 1 OR (last_error IS NOT NULL AND last_error != '' AND last_error != 'null'))";
                break;
            case 'testing':
                $where[] = "status != 'trash' AND draft IS NOT NULL AND draft != '' AND draft != 'null'";
                break;
            case 'pinned':
                $pins = Pins::all();
                $where[] = $pins ? "status != 'trash' AND id IN (" . implode(',', array_map('intval', $pins)) . ')' : '1=0';
                break;
            default:
                $where[] = "status != 'trash'";
        }

        if ($args['type'] !== '') {
            $where[] = 'code_type = %s';
            $params[] = $args['type'];
        }

        if ($args['location'] !== '') {
            $where[] = 'location = %s';
            $params[] = $args['location'];
        }

        if ($args['tag'] !== '') {
            $where[] = 'tags LIKE %s';
            $params[] = '%' . $wpdb->esc_like('"' . sanitize_title($args['tag']) . '"') . '%';
        }

        if ($args['search'] !== '') {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(title LIKE %s OR note LIKE %s OR code LIKE %s OR shortcode LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $orderby = in_array($args['orderby'], ['title', 'updated_at', 'created_at', 'priority', 'id', 'code_type', 'location', 'status'], true) ? $args['orderby'] : 'updated_at';
        $order = strtolower($args['order']) === 'asc' ? 'ASC' : 'DESC';
        $perPage = max(1, min(500, (int) $args['per_page']));
        $offset = (max(1, (int) $args['page']) - 1) * $perPage;
        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM $table WHERE $whereSql";
        // The current user's pinned snippets come first.
        $pins = Pins::all();
        $pinnedFirst = $pins ? 'id IN (' . implode(',', array_map('intval', $pins)) . ') DESC, ' : '';
        $listSql = "SELECT * FROM $table WHERE $whereSql ORDER BY $pinnedFirst$orderby $order, id DESC LIMIT $perPage OFFSET $offset";

        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($countSql, $params) : $countSql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($params ? $wpdb->prepare($listSql, $params) : $listSql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return [
            'items' => array_map([__CLASS__, 'decode'], $rows),
            'total' => $total,
            'counts' => self::counts(),
        ];
    }

    public static function counts(): array
    {
        global $wpdb;

        if (!Schema::isInstalled()) {
            return ['all' => 0, 'active' => 0, 'inactive' => 0, 'trash' => 0, 'error' => 0, 'testing' => 0, 'pinned' => 0];
        }

        $table = Schema::snippetsTable();
        $row = $wpdb->get_row("SELECT
            SUM(status != 'trash') AS `all`,
            SUM(status = 'active' AND quarantined = 0) AS active,
            SUM(status = 'draft') AS inactive,
            SUM(status = 'trash') AS trash,
            SUM(status != 'trash' AND (quarantined = 1 OR (last_error IS NOT NULL AND last_error != '' AND last_error != 'null'))) AS error,
            SUM(status != 'trash' AND draft IS NOT NULL AND draft != '' AND draft != 'null') AS testing
            FROM $table", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $counts = array_map('intval', $row ?: []);
        $pins = Pins::all();
        $pins = array_values(array_map('intval', $pins));
        $placeholders = implode(',', array_fill(0, count($pins), '%d'));
        $counts['pinned'] = $pins ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status != 'trash' AND id IN ($placeholders)", ...$pins)) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %d placeholder per ID.

        return $counts;
    }

    public static function tags(): array
    {
        global $wpdb;

        $table = Schema::snippetsTable();
        $tags = [];

        foreach ($wpdb->get_col("SELECT tags FROM $table WHERE status != 'trash' AND tags IS NOT NULL") as $json) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ((array) json_decode($json, true) as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }

        ksort($tags);

        return $tags;
    }

    /**
     * @return array[] Active, non-quarantined snippets for the runtime manifest.
     */
    public static function runnable(): array
    {
        global $wpdb;

        $table = Schema::snippetsTable();

        return array_map([__CLASS__, 'decode'], $wpdb->get_results("SELECT * FROM $table WHERE status = 'active' AND quarantined = 0 ORDER BY priority ASC, id ASC")); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * @return array[] Snippets that have staged (testing mode) changes.
     */
    public static function withDrafts(): array
    {
        global $wpdb;

        $table = Schema::snippetsTable();

        return array_map([__CLASS__, 'decode'], $wpdb->get_results("SELECT * FROM $table WHERE status != 'trash' AND draft IS NOT NULL AND draft != '' AND draft != 'null'")); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function create(array $data, string $source = 'editor'): int
    {
        global $wpdb;

        $snippet = self::normalize($data);
        $now = current_time('mysql', true);
        $row = self::encode($snippet);
        $row['uuid'] = !empty($data['uuid']) && is_string($data['uuid']) ? $data['uuid'] : wp_generate_uuid4();
        $row['created_by'] = $row['updated_by'] = get_current_user_id();
        $row['created_at'] = $row['updated_at'] = $now;

        $wpdb->insert(Schema::snippetsTable(), $row);
        $id = (int) $wpdb->insert_id;

        if ($id) {
            if (Plans::pro()) {
                Revisions::record($id, $snippet, $source);
            }

            Manifest::rebuild();
            do_action('wpide_snippet_saved', $id, $snippet, $source);
        }

        return $id;
    }

    /**
     * Update stored fields. Only keys present in $data change.
     */
    public static function update(int $id, array $data, string $source = 'editor', bool $recordRevision = true): ?array
    {
        global $wpdb;

        $current = self::find($id);

        if (!$current) {
            return null;
        }

        $snippet = self::normalize($data, $current);
        $row = self::encode(array_intersect_key($snippet, $data + array_flip(['title'])));
        $row['updated_by'] = get_current_user_id();
        $row['updated_at'] = current_time('mysql', true);

        $wpdb->update(Schema::snippetsTable(), $row, ['id' => $id]);

        $updated = self::find($id);

        if ($recordRevision && Plans::pro()) {
            Revisions::record($id, $updated, $source);
        }

        Manifest::rebuild();
        do_action('wpide_snippet_saved', $id, $updated, $source);

        return $updated;
    }

    /**
     * Write runtime bookkeeping (errors, quarantine, drafts) without a revision.
     */
    public static function updateMeta(int $id, array $data)
    {
        global $wpdb;

        $allowed = array_intersect_key($data, array_flip(['quarantined', 'last_error', 'draft', 'status', 'compiled_code']));
        $row = self::encode(self::normalize($allowed, self::find($id) ?: []) + $allowed);
        $row = array_intersect_key($row, $allowed);

        if ($row) {
            $wpdb->update(Schema::snippetsTable(), $row, ['id' => $id]);
            Manifest::rebuild();
        }
    }

    public static function trash(int $id): bool
    {
        return (bool) self::update($id, ['status' => 'trash'], 'trash');
    }

    public static function restore(int $id): bool
    {
        return (bool) self::update($id, ['status' => 'draft'], 'restore');
    }

    public static function delete(int $id): bool
    {
        global $wpdb;

        $deleted = (bool) $wpdb->delete(Schema::snippetsTable(), ['id' => $id]);

        if ($deleted) {
            $wpdb->delete(Schema::revisionsTable(), ['snippet_id' => $id]);
            Manifest::rebuild();
            do_action('wpide_snippet_deleted', $id);
        }

        return $deleted;
    }

    public static function duplicate(int $id): int
    {
        $snippet = self::find($id);

        if (!$snippet) {
            return 0;
        }

        $copy = array_intersect_key($snippet, array_flip(self::CONTENT_FIELDS));
        /* translators: %s: snippet title */
        $copy['title'] = sprintf(__('%s (copy)', 'wpide'), $snippet['title']);
        $copy['status'] = 'draft';
        $copy['shortcode'] = '';

        return self::create($copy, 'duplicate');
    }

    /**
     * The content part of a snippet, as stored in revisions and exports.
     */
    public static function content(array $snippet): array
    {
        return array_intersect_key($snippet, array_flip(self::CONTENT_FIELDS));
    }
}
