<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Snippet packs: curated groups of library snippets installed together.
 *
 * Installing a pack adds each missing snippet and activates it through the usual activation check, so a
 * snippet that fails stays inactive. Snippets marked "personalize" (a logo URL, a message) are added
 * inactive for the user to fill in first. Snippets already on the site are reused, not duplicated.
 * Removing a pack moves the snippets it added to the trash (recoverable), keeping any another installed
 * pack still includes.
 */
class Packs
{
    const STATE_OPTION = 'wpide_snippets_packs';

    public static function definitions(): array
    {
        $packs = Library::data()['packs'] ?? [];

        return is_array($packs) ? $packs : [];
    }

    public static function state(): array
    {
        $state = get_option(self::STATE_OPTION, []);

        return is_array($state) ? $state : [];
    }

    public static function find(string $id): ?array
    {
        foreach (self::definitions() as $pack) {
            if ($pack['id'] === $id) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * Packs with each member's library entry and current local state.
     */
    public static function all(): array
    {
        $installed = Library::installed();
        $state = self::state();
        $snippets = [];

        foreach (Library::data()['snippets'] as $entry) {
            $snippets[$entry['id']] = $entry;
        }

        $active = self::activeIds(array_column($installed, 'snippet_id'));
        $list = [];

        foreach (self::definitions() as $pack) {
            $members = [];

            foreach ($pack['snippets'] as $member) {
                $entry = $snippets[$member['id']] ?? null;

                if (!$entry) {
                    continue;
                }

                $local = $installed[$member['id']]['snippet_id'] ?? 0;
                $members[] = [
                    'id' => $member['id'],
                    'title' => $entry['title'],
                    'description' => $entry['description'],
                    'code_type' => $entry['code_type'],
                    'personalize' => !empty($member['personalize']),
                    'snippet_id' => $local,
                    'active' => $local && in_array($local, $active, true),
                ];
            }

            $list[] = [
                'id' => $pack['id'],
                'title' => $pack['title'],
                'description' => $pack['description'],
                'icon' => $pack['icon'] ?? 'ni-package',
                'requires' => Library::requirementNames($pack),
                'available' => Library::requirementsMet($pack),
                'pro' => (bool) array_filter(array_map(function ($member) use ($snippets) {
                    return isset($snippets[$member['id']]) && Library::isPro($snippets[$member['id']]);
                }, $pack['snippets'])),
                'installed' => isset($state[$pack['id']]),
                'installed_at' => $state[$pack['id']]['installed_at'] ?? 0,
                'snippets' => $members,
            ];
        }

        return $list;
    }

    /**
     * @return array{success: bool, errors: string[], added: array, reused: array, failed: array, inactive: array}
     */
    public static function install(string $id): array
    {
        $pack = self::find($id);
        $report = ['success' => false, 'errors' => [], 'added' => [], 'reused' => [], 'failed' => [], 'inactive' => []];

        if (!$pack) {
            $report['errors'][] = __('Pack not found.', 'wpide');

            return $report;
        }

        if (!Library::requirementsMet($pack)) {
            $report['errors'][] = __('This pack needs a plugin that is not active.', 'wpide');

            return $report;
        }

        $installed = Library::installed();
        $state = self::state();
        $owned = $state[$id]['snippets'] ?? [];

        foreach ($pack['snippets'] as $member) {
            $existing = $installed[$member['id']]['snippet_id'] ?? 0;

            if ($existing) {
                $report['reused'][] = $existing;
                continue;
            }

            $result = Library::install($member['id']);

            if (empty($result['success'])) {
                $report['failed'][] = ['id' => $member['id'], 'error' => implode(' ', $result['errors'] ?? [])];
                continue;
            }

            $snippetId = (int) $result['snippet']['id'];
            $owned[$member['id']] = $snippetId;
            $report['added'][] = $snippetId;

            if (!empty($member['personalize'])) {
                $report['inactive'][] = ['id' => $snippetId, 'reason' => 'personalize'];
                continue;
            }

            $status = Manager::setStatus($snippetId, true);

            if (empty($status['success'])) {
                $report['inactive'][] = ['id' => $snippetId, 'reason' => implode(' ', $status['errors'] ?? [])];
            }
        }

        if ($report['added'] || $report['reused']) {
            $state[$id] = ['installed_at' => $state[$id]['installed_at'] ?? time(), 'snippets' => $owned];
            update_option(self::STATE_OPTION, $state, false);
            $report['success'] = true;
        }

        return $report;
    }

    /**
     * @return int Number of snippets moved to the trash.
     */
    public static function remove(string $id): int
    {
        $state = self::state();

        if (!isset($state[$id])) {
            return 0;
        }

        $claimed = [];

        foreach ($state as $otherId => $other) {
            if ($otherId === $id) {
                continue;
            }

            foreach ((self::find($otherId)['snippets'] ?? []) as $member) {
                $claimed[$member['id']] = true;
            }
        }

        $trashed = 0;

        foreach ($state[$id]['snippets'] ?? [] as $libraryId => $snippetId) {
            if (isset($claimed[$libraryId])) {
                continue;
            }

            $snippet = Repository::find((int) $snippetId);

            if ($snippet && $snippet['status'] !== 'trash' && Repository::trash((int) $snippetId)) {
                $trashed++;
            }
        }

        unset($state[$id]);
        update_option(self::STATE_OPTION, $state, false);
        Manifest::rebuild();

        return $trashed;
    }

    protected static function activeIds(array $ids): array
    {
        global $wpdb;

        $ids = array_filter(array_map('intval', $ids));

        if (!$ids) {
            return [];
        }

        $table = Schema::snippetsTable();

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE status = 'active' AND id IN ($placeholders)", ...array_values($ids)))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %d placeholder per ID.
    }
}
