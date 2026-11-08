<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Bounded snippet event log: errors, quarantines, activations, imports.
 */
class Events
{
    const MAX_ROWS = 2000;

    public static function log(int $snippetId, string $type, string $message, array $context = [])
    {
        global $wpdb;

        if (!Schema::isInstalled()) {
            return;
        }

        $wpdb->insert(Schema::eventsTable(), [
            'snippet_id' => $snippetId,
            'type' => sanitize_key($type),
            'message' => wp_strip_all_tags($message),
            'context' => wp_json_encode($context),
            'created_at' => current_time('mysql', true),
        ]);

        // Keep the table bounded without a cron job.
        if (wp_rand(1, 50) === 1) {
            self::prune();
        }
    }

    public static function recent(int $limit = 100, int $snippetId = 0, string $type = ''): array
    {
        global $wpdb;

        $table = Schema::eventsTable();
        $where = '1=1';
        $params = [];

        if ($snippetId) {
            $where .= ' AND snippet_id = %d';
            $params[] = $snippetId;
        }

        if ($type !== '') {
            $where .= ' AND type = %s';
            $params[] = $type;
        }

        $limit = max(1, min(1000, $limit));
        $sql = "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT $limit";
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql, $params) : $sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array_map(function ($row) {
            $row['id'] = (int) $row['id'];
            $row['snippet_id'] = (int) $row['snippet_id'];
            $row['context'] = json_decode((string) $row['context'], true) ?: [];

            return $row;
        }, $rows);
    }

    public static function clear(int $snippetId = 0)
    {
        global $wpdb;

        $table = Schema::eventsTable();

        if ($snippetId) {
            $wpdb->delete($table, ['snippet_id' => $snippetId]);
        } else {
            $wpdb->query("TRUNCATE TABLE $table"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public static function prune()
    {
        global $wpdb;

        $table = Schema::eventsTable();
        $threshold = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table ORDER BY id DESC LIMIT 1 OFFSET %d", self::MAX_ROWS)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($threshold) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id <= %d", $threshold)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }
}
