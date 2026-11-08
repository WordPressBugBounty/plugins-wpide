<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Registry\Types;
use WPIDE\App\Services\Snippets\Runtime\Executor;
use WPIDE\App\Services\Snippets\Runtime\Guard;
use WPIDE\App\Services\Snippets\Runtime\Runtime;

/**
 * Snippet use cases shared by the admin controllers, WP-CLI and importers.
 *
 * Saving and activating go through the same checks: capability for the code type, strict validation and,
 * for PHP and Universal snippets that will run, an activation probe executed before anything is stored, so a
 * failing new version never replaces a working one.
 */
class Manager
{
    /**
     * @return array{success: bool, snippet?: array, errors?: string[], warnings?: string[], staged?: bool}
     */
    public static function save(array $input, string $source = 'editor'): array
    {
        // Saves can come from WP-CLI, cron or the cloud agent, where admin_init never installed the tables.
        Schema::maybeInstall();

        $id = (int) ($input['id'] ?? 0);
        $current = $id ? Repository::find($id) : null;

        if ($id && !$current) {
            return self::failure([__('Snippet not found.', 'wpide')]);
        }

        $snippet = Repository::normalize($input, $current ?: []);

        $permission = self::checkPermission($snippet, $current);

        if ($permission) {
            return self::failure([$permission]);
        }

        if ($snippet['code_type'] === Types::TEXT && !current_user_can('unfiltered_html')) {
            $snippet['code'] = wp_kses_post($snippet['code']);
        }

        // Free plan: Pro options keep their stored value, and the editor can't add Pro types, locations or rules.
        $snippet = Plans::keepProFields($snippet, $current);
        $locked = in_array($source, ['editor', 'library', 'restore'], true) ? Plans::newlyLocked($snippet, $current) : [];

        if ($locked) {
            /* translators: %s: list of Pro features */
            return self::failure([sprintf(__('WPIDE Pro is needed for %s.', 'wpide'), implode(', ', $locked))]) + ['pro_required' => true];
        }

        if ($snippet['code_type'] !== Types::SCSS) {
            $snippet['compiled_code'] = '';
        } elseif (!Plans::pro()) {
            $snippet['compiled_code'] = $current['compiled_code'] ?? '';
        } elseif (!$current || $current['code'] !== $snippet['code'] || $current['code_type'] !== Types::SCSS || $snippet['compiled_code'] === '') {
            $compiled = Scss::compile($snippet['code']);

            if ($compiled['error']) {
                /* translators: %s: compiler error message */
                return self::failure([sprintf(__('SCSS error: %s', 'wpide'), $compiled['error'])]);
            }

            $snippet['compiled_code'] = $compiled['css'];
        }

        $check = Validation::check($snippet, $id);

        if ($check['errors']) {
            return self::failure($check['errors'], $check['warnings']);
        }

        // Testing mode: stage instead of changing what runs.
        if ($source === 'editor' && Plans::pro() && TestingMode::isEnabled()) {
            $requested = $snippet;

            if (!$current) {
                $create = $snippet;
                $create['status'] = 'draft';
                $id = Repository::create($create, 'testing');
            }

            TestingMode::stage($id, $requested);

            return ['success' => true, 'snippet' => Repository::find($id), 'warnings' => $check['warnings'], 'staged' => true];
        }

        $willRun = $snippet['status'] === 'active';
        $codeChanged = !$current || Repository::content($current) != Repository::content($snippet);

        if ($willRun && $codeChanged) {
            $probe = self::probe(array_merge($snippet, ['id' => $id ?: 0]));

            if ($probe !== null) {
                if ($current) {
                    Repository::updateMeta($id, ['last_error' => ['message' => $probe, 'phase' => 'activation', 'time' => time()]]);
                }

                /* translators: %s: error message */
                return self::failure([sprintf(__('The snippet was not saved as active because it failed when run: %s', 'wpide'), $probe)], $check['warnings']) + ['activation_failed' => true];
            }
        }

        $data = array_intersect_key($snippet, array_flip(Repository::CONTENT_FIELDS));

        foreach (['provenance', 'source_ref', 'cloud_ref', 'uuid'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if ($current) {
            $saved = Repository::update($id, $data, $source);

            if ($willRun && ($saved['quarantined'] || $saved['last_error'])) {
                Repository::updateMeta($id, ['quarantined' => false, 'last_error' => null]);
                $saved = Repository::find($id);
            }
        } else {
            $id = Repository::create($data, $source);
            $saved = Repository::find($id);
        }

        if ($willRun && !($current && $current['status'] === 'active')) {
            /* translators: %s: user display name */
            Events::log($id, 'activated', sprintf(__('Activated by %s', 'wpide'), wp_get_current_user()->display_name ?: __('system', 'wpide')));
        }

        return ['success' => true, 'snippet' => $saved, 'warnings' => $check['warnings']];
    }

    public static function setStatus(int $id, bool $active): array
    {
        $snippet = Repository::find($id);

        if (!$snippet) {
            return self::failure([__('Snippet not found.', 'wpide')]);
        }

        if (!current_user_can(Capabilities::ACTIVATE) && !(defined('WP_CLI') && WP_CLI) && !self::$system) {
            return self::failure([__('You are not allowed to activate or deactivate snippets.', 'wpide')]);
        }

        if (!$active) {
            return ['success' => true, 'snippet' => Repository::update($id, ['status' => 'draft'], 'deactivate')];
        }

        $check = Validation::check($snippet, $id);

        if ($check['errors']) {
            return self::failure($check['errors'], $check['warnings']);
        }

        $probe = self::probe($snippet);

        if ($probe !== null) {
            Repository::updateMeta($id, ['last_error' => ['message' => $probe, 'phase' => 'activation', 'time' => time()]]);

            /* translators: %s: error message */
            return self::failure([sprintf(__('The snippet was not activated because it failed when run: %s', 'wpide'), $probe)]) + ['activation_failed' => true];
        }

        $saved = Repository::update($id, ['status' => 'active'], 'activate');
        Repository::updateMeta($id, ['quarantined' => false, 'last_error' => null]);
        /* translators: %s: user display name */
        Events::log($id, 'activated', sprintf(__('Activated by %s', 'wpide'), wp_get_current_user()->display_name ?: __('system', 'wpide')));

        return ['success' => true, 'snippet' => Repository::find($id), 'warnings' => $check['warnings']];
    }

    /**
     * Run a PHP or Universal snippet once to catch errors before it goes live. Returns an error message or null.
     * Like any execution this can have side effects; on-demand snippets are not probed.
     */
    public static function probe(array $snippet): ?string
    {
        if (!Types::executesPhp($snippet['code_type']) || ($snippet['insert_method'] === 'auto' && $snippet['location'] === 'on_demand')) {
            return null;
        }

        $syntax = Validation::syntaxError($snippet['code_type'], (string) $snippet['code']);

        if ($syntax !== null) {
            return $syntax;
        }

        // Probe a copy with id 0 when unsaved; the generated file is not reused.
        $probe = $snippet;
        $probe['id'] = (int) ($snippet['id'] ?? 0);

        // Shortcode attributes exist as (empty) variables, as they do when the shortcode runs.
        $attributes = array_fill_keys((array) ($snippet['shortcode_attributes'] ?? []), '');

        try {
            Executor::render($probe, 'activation', $attributes, true);
        } catch (\Throwable $e) {
            return sprintf('%s (line %d)', $e->getMessage(), $e->getLine());
        }

        return null;
    }

    /**
     * Run an on-demand snippet now, without changing its status.
     */
    public static function execute(int $id): array
    {
        $snippet = Repository::find($id);

        if (!$snippet || !Types::executesPhp($snippet['code_type'])) {
            return self::failure([__('Only PHP snippets can be run on demand.', 'wpide')]);
        }

        if (!Capabilities::canEditType($snippet['code_type']) && !(defined('WP_CLI') && WP_CLI)) {
            return self::failure([__('You are not allowed to run this snippet.', 'wpide')]);
        }

        try {
            $output = Executor::render($snippet, 'manual', [], true);
        } catch (\Throwable $e) {
            return self::failure([sprintf('%s (line %d)', $e->getMessage(), $e->getLine())]);
        }

        Events::log($id, 'executed', __('Run on demand', 'wpide'));

        return ['success' => true, 'output' => $output];
    }

    /**
     * Null when allowed, otherwise the reason.
     */
    /** @var bool set only while the cloud agent applies a deployment (no user is logged in during cron) */
    protected static $system = false;

    /**
     * Run code with system rights: used by the cloud deployment agent, never reachable from a request.
     */
    public static function asSystem(callable $callback)
    {
        $previous = self::$system;
        self::$system = true;

        try {
            return $callback();
        } finally {
            self::$system = $previous;
        }
    }

    public static function checkPermission(array $snippet, ?array $current): ?string
    {
        if ((defined('WP_CLI') && WP_CLI) || self::$system) {
            return null;
        }

        if (!Capabilities::canEditType($snippet['code_type']) || ($current && !Capabilities::canEditType($current['code_type']))) {
            return __('You are not allowed to edit this type of snippet.', 'wpide');
        }

        $network = !empty($snippet['settings']['network']['enabled']);
        $wasNetwork = $current && !empty($current['settings']['network']['enabled']);

        if (($network || $wasNetwork) && $snippet['settings']['network'] != ($current['settings']['network'] ?? null) && !Network::canManage()) {
            return __('Only network administrators can change where a snippet runs across the network, from the main site.', 'wpide');
        }

        $wasActive = $current && $current['status'] === 'active';
        $statusChanges = ($current ? $current['status'] : 'draft') !== $snippet['status'];

        if (($wasActive || $statusChanges || (!$current && $snippet['status'] === 'active')) && !Capabilities::canActivate()) {
            return $wasActive
                ? __('This snippet is active. Only users who can activate snippets can change it.', 'wpide')
                : __('You are not allowed to activate snippets.', 'wpide');
        }

        return null;
    }

    protected static function failure(array $errors, array $warnings = []): array
    {
        return ['success' => false, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Mark a snippet as edited for this request so the runtime does not also run its old version.
     */
    public static function markEdited(int $id)
    {
        Runtime::setEditedSnippetId($id);
        Guard::leave();
    }
}
