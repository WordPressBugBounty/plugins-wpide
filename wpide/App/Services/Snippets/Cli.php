<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Import\Exporter;
use WPIDE\App\Services\Snippets\Import\Importers;
use WPIDE\App\Services\Snippets\Runtime\Guard;

/**
 * Manage WPIDE snippets from WP-CLI. Works when the dashboard does not load, e.g. to recover from a bad snippet.
 */
class Cli
{
    public static function register()
    {
        if (class_exists('WP_CLI') && Snippets::available()) {
            \WP_CLI::add_command('wpide snippets', new self());
        }
    }

    /**
     * List snippets.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : all, active, inactive, error, testing or trash.
     * ---
     * default: all
     * ---
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     * ---
     * default: table
     * ---
     *
     * @subcommand list
     */
    public function list_($args, $assoc)
    {
        Schema::maybeInstall();
        $items = Repository::query(['status' => $assoc['status'] ?? 'all', 'per_page' => 500, 'orderby' => 'id', 'order' => 'asc'])['items'];
        $rows = array_map(function ($snippet) {
            return [
                'id' => $snippet['id'],
                'title' => $snippet['title'],
                'type' => $snippet['code_type'],
                'status' => $snippet['quarantined'] ? 'quarantined' : $snippet['status'],
                'location' => $snippet['insert_method'] === 'auto' ? $snippet['location'] : 'shortcode',
                'priority' => $snippet['priority'],
            ];
        }, $items);

        \WP_CLI\Utils\format_items($assoc['format'] ?? 'table', $rows, ['id', 'title', 'type', 'status', 'location', 'priority']);
    }

    /**
     * Show a snippet as JSON.
     *
     * <id>
     * : Snippet id.
     */
    public function get($args)
    {
        $snippet = Repository::find((int) $args[0]);

        if (!$snippet) {
            \WP_CLI::error('Snippet not found.');
        }

        \WP_CLI::line(wp_json_encode($snippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Activate snippets (validated and test-run first).
     *
     * <id>...
     * : Snippet ids.
     */
    public function activate($args)
    {
        foreach ($args as $id) {
            $result = Manager::setStatus((int) $id, true);
            $result['success'] ? \WP_CLI::success("Snippet $id activated.") : \WP_CLI::warning("Snippet $id: " . implode(' ', $result['errors']));
        }
    }

    /**
     * Deactivate snippets.
     *
     * <id>...
     * : Snippet ids, or --all.
     *
     * [--all]
     * : Deactivate every active snippet.
     */
    public function deactivate($args, $assoc)
    {
        if (!empty($assoc['all'])) {
            $args = wp_list_pluck(Repository::query(['status' => 'active', 'per_page' => 500])['items'], 'id');
        }

        foreach ($args as $id) {
            Manager::setStatus((int) $id, false);
            \WP_CLI::success("Snippet $id deactivated.");
        }
    }

    /**
     * Turn the snippets safe mode on or off. While on, no snippet runs.
     *
     * <state>
     * : on, off or status.
     *
     * @subcommand safe-mode
     */
    public function safe_mode($args)
    {
        $state = $args[0] ?? 'status';

        if ($state === 'on' || $state === 'off') {
            Guard::setPaused($state === 'on');
        }

        \WP_CLI::line('Snippets are ' . (Guard::disabledReason() ? 'NOT running (' . Guard::disabledReason() . ')' : 'running') . '.');
    }

    /**
     * Run an on-demand PHP snippet now.
     *
     * <id>
     * : Snippet id.
     */
    public function run($args)
    {
        $result = Manager::execute((int) $args[0]);

        if (!$result['success']) {
            \WP_CLI::error(implode(' ', $result['errors']));
        }

        if ($result['output'] !== '') {
            \WP_CLI::line($result['output']);
        }

        \WP_CLI::success('Snippet ran.');
    }

    /**
     * Release a quarantined snippet so it can run again (it stays active).
     *
     * <id>
     * : Snippet id.
     */
    public function release($args)
    {
        Repository::updateMeta((int) $args[0], ['quarantined' => false, 'last_error' => null]);
        \WP_CLI::success('Snippet released from quarantine.');
    }

    /**
     * Export snippets to a WPIDE JSON file (stdout when no file is given).
     *
     * [<file>]
     * : Output file.
     *
     * [--ids=<ids>]
     * : Comma-separated snippet ids.
     */
    public function export($args, $assoc)
    {
        $ids = isset($assoc['ids']) ? array_filter(array_map('intval', explode(',', $assoc['ids']))) : [];
        $json = wp_json_encode(Exporter::export($ids, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!empty($args[0])) {
            file_put_contents($args[0], $json);
            \WP_CLI::success('Exported to ' . $args[0]);
        } else {
            \WP_CLI::line($json);
        }
    }

    /**
     * Import snippets (always inactive).
     *
     * <importer>
     * : wpide-json, wpcode-json, wpcode-site, or another importer id.
     *
     * [<file>]
     * : JSON file for file importers.
     *
     * [--dry-run]
     * : Show what would be imported.
     */
    public function import($args, $assoc)
    {
        $importer = Importers::get($args[0]);

        if (!$importer) {
            \WP_CLI::error('Unknown importer. Available: ' . implode(', ', array_keys(Importers::all())));
        }

        $file = null;

        if ($importer->acceptsFile()) {
            if (empty($args[1]) || !is_readable($args[1])) {
                \WP_CLI::error('A readable JSON file is required.');
            }

            $file = json_decode((string) file_get_contents($args[1]), true);
        }

        $analysis = $importer->analyze($file);

        if (!empty($assoc['dry-run'])) {
            \WP_CLI::line(wp_json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $report = $importer->commit(wp_list_pluck($analysis['items'], 'key'), $file, wp_list_pluck($analysis['extras'], 'key'));
        \WP_CLI::success(sprintf('%d imported, %d skipped, %d renamed.', count($report['imported']), count($report['skipped']), count($report['renamed'])));

        foreach ($report['skipped'] as $skipped) {
            \WP_CLI::warning($skipped['title'] . ': ' . implode(' ', $skipped['errors']));
        }
    }

    /**
     * List a snippet's revisions.
     *
     * <id>
     * : Snippet id.
     */
    public function revisions($args)
    {
        self::requirePro();
        \WP_CLI\Utils\format_items('table', Revisions::forSnippet((int) $args[0]), ['id', 'created_at', 'author', 'source']);
    }

    /**
     * Restore a revision (creates a new revision; the snippet keeps its status).
     *
     * <revision>
     * : Revision id.
     */
    public function restore($args)
    {
        self::requirePro();
        Revisions::restore((int) $args[0]) ? \WP_CLI::success('Revision restored.') : \WP_CLI::error('Revision not found.');
    }

    /**
     * Create or upgrade the snippets database tables.
     */
    protected static function requirePro()
    {
        if (!Plans::pro()) {
            \WP_CLI::error(__('Revisions need WPIDE Pro.', 'wpide'));
        }
    }

    public function install()
    {
        Schema::install() ? \WP_CLI::success('Snippets tables are ready.') : \WP_CLI::error('Could not create the tables.');
    }
}
