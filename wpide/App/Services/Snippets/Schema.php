<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Snippets database schema, versioned independently from the plugin version.
 *
 * Migrations are additive (dbDelta) and only run from admin, activation or WP-CLI, never during a
 * visitor request. Downgrading the plugin leaves the tables untouched; older versions ignore them.
 */
class Schema
{
    const VERSION = '1.0.0';
    const VERSION_OPTION = 'wpide_snippets_db_version';

    public static function snippetsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wpide_snippets';
    }

    public static function revisionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wpide_snippet_revisions';
    }

    public static function eventsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wpide_snippet_events';
    }

    public static function isInstalled(): bool
    {
        return get_option(self::VERSION_OPTION) === self::VERSION;
    }

    public static function maybeInstall()
    {
        if (!self::isInstalled()) {
            self::install();
        }
    }

    public static function install(): bool
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $snippets = self::snippetsTable();
        $revisions = self::revisionsTable();
        $events = self::eventsTable();

        dbDelta("CREATE TABLE $snippets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            note text NULL,
            code longtext NULL,
            compiled_code longtext NULL,
            code_type varchar(20) NOT NULL DEFAULT 'php',
            status varchar(20) NOT NULL DEFAULT 'draft',
            insert_method varchar(20) NOT NULL DEFAULT 'auto',
            location varchar(64) NOT NULL DEFAULT '',
            insert_number int(11) NOT NULL DEFAULT 1,
            location_extra longtext NULL,
            priority int(11) NOT NULL DEFAULT 10,
            use_conditions tinyint(1) NOT NULL DEFAULT 0,
            conditions longtext NULL,
            schedule longtext NULL,
            device_type varchar(20) NOT NULL DEFAULT 'any',
            shortcode varchar(64) NOT NULL DEFAULT '',
            shortcode_attributes longtext NULL,
            tags longtext NULL,
            settings longtext NULL,
            generator varchar(64) NOT NULL DEFAULT '',
            generator_data longtext NULL,
            library_ref varchar(191) NOT NULL DEFAULT '',
            provenance varchar(32) NOT NULL DEFAULT 'local',
            source_ref varchar(191) NOT NULL DEFAULT '',
            cloud_ref varchar(191) NOT NULL DEFAULT '',
            draft longtext NULL,
            quarantined tinyint(1) NOT NULL DEFAULT 0,
            last_error longtext NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY status (status),
            KEY location (location),
            KEY code_type (code_type),
            KEY shortcode (shortcode)
        ) $charset;");

        dbDelta("CREATE TABLE $revisions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            snippet_id bigint(20) unsigned NOT NULL,
            author_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source varchar(32) NOT NULL DEFAULT 'editor',
            content_hash char(64) NOT NULL DEFAULT '',
            payload longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY snippet_id (snippet_id)
        ) $charset;");

        dbDelta("CREATE TABLE $events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            snippet_id bigint(20) unsigned NOT NULL DEFAULT 0,
            type varchar(32) NOT NULL DEFAULT '',
            message text NULL,
            context longtext NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY snippet_id (snippet_id),
            KEY created_at (created_at)
        ) $charset;");

        $ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $snippets)) === $snippets;

        if ($ok) {
            update_option(self::VERSION_OPTION, self::VERSION, true);
        }

        return $ok;
    }

    /**
     * Remove all Snippets data. Only called on explicit user request (settings), never on deactivation.
     */
    public static function uninstall()
    {
        global $wpdb;

        foreach ([self::snippetsTable(), self::revisionsTable(), self::eventsTable()] as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        delete_option(self::VERSION_OPTION);
        delete_option(Manifest::OPTION);
    }
}
