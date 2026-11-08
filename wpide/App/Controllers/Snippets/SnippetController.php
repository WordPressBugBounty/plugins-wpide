<?php

namespace WPIDE\App\Controllers\Snippets;

defined( 'ABSPATH' ) || exit;

use WPIDE\App\Kernel\Request;
use WPIDE\App\Kernel\Response;
use WPIDE\App\Services\Snippets\Capabilities;
use WPIDE\App\Services\Snippets\Events;
use WPIDE\App\Services\Snippets\Generators\Registry as Generators;
use WPIDE\App\Services\Snippets\Import\Importers;
use WPIDE\App\Services\Snippets\Library;
use WPIDE\App\Services\Snippets\Manager;
use WPIDE\App\Services\Snippets\Network;
use WPIDE\App\Services\Snippets\Registry\Conditions;
use WPIDE\App\Services\Snippets\Registry\Locations;
use WPIDE\App\Services\Snippets\Registry\Types;
use WPIDE\App\Services\Snippets\Pins;
use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Repository;
use WPIDE\App\Services\Snippets\Revisions;
use WPIDE\App\Services\Snippets\Runtime\ContentInserter;
use WPIDE\App\Services\Snippets\Runtime\Guard;
use WPIDE\App\Services\Snippets\Schema;
use WPIDE\App\Services\Snippets\Search;
use WPIDE\App\Services\Snippets\Settings;
use WPIDE\App\Services\Snippets\SmartTags;
use WPIDE\App\Services\Snippets\Snippets;
use WPIDE\App\Services\Snippets\TestingMode;
use WPIDE\App\Services\Snippets\Validation;

if (class_exists(Snippets::class) && Snippets::available()) {

    /**
     * Snippets management: list, edit, activate, revisions, testing mode and diagnostics.
     * Every action checks the snippet capability it needs, on top of WPIDE's CSRF check.
     */
    class SnippetController
    {
        use JsonInput;

        public function __construct()
        {
            Schema::maybeInstall();
        }

        public function bootstrap(Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $locationGroups = [];

            foreach (Locations::all() as $id => $location) {
                $locationGroups[$location['group']][] = [
                    'id' => $id,
                    'label' => $location['label'],
                    'description' => $location['description'],
                    'types' => $location['types'],
                    'number' => !empty($location['number']),
                    'number_label' => $location['number_label'] ?? '',
                    'selector' => !empty($location['selector']),
                    'custom_hook' => !empty($location['custom_hook']),
                    'available' => Locations::isAvailable($id),
                    'pro' => Locations::isPro($id),
                ];
            }

            $groups = [];

            foreach (Locations::groups() as $id => $label) {
                if (!empty($locationGroups[$id])) {
                    $groups[] = ['id' => $id, 'label' => $label, 'locations' => $locationGroups[$id]];
                }
            }

            $types = [];

            foreach (Types::all() as $id => $type) {
                $types[] = ['id' => $id, 'label' => $type['label'], 'description' => $type['description'], 'mode' => $type['mode'], 'can_edit' => Capabilities::canEditType($id), 'pro' => Types::isPro($id)];
            }

            $response->json([
                'types' => $types,
                'location_groups' => $groups,
                'conditions' => Conditions::forUi(),
                'operators' => Conditions::operatorLabels(),
                'smart_tags' => SmartTags::forUi(),
                'smart_tag_groups' => SmartTags::groups(),
                'counts' => Repository::counts(),
                'tags' => Repository::tags(),
                'settings' => Settings::get(),
                'disabled_reason' => Guard::disabledReason(),
                'capabilities' => [
                    'edit_text' => current_user_can(Capabilities::EDIT_TEXT),
                    'edit_markup' => current_user_can(Capabilities::EDIT_MARKUP),
                    'edit_php' => current_user_can(Capabilities::EDIT_PHP),
                    'activate' => current_user_can(Capabilities::ACTIVATE),
                    'settings' => current_user_can(Capabilities::SETTINGS),
                    'special_files' => current_user_can(Capabilities::SPECIAL_FILES),
                    'tracking' => current_user_can(Capabilities::TRACKING),
                ],
                'capability_labels' => Capabilities::all(),
                'roles' => array_map(function ($role) {
                    return translate_user_role($role['name']);
                }, wp_roles()->roles),
                'generators' => Generators::forUi(),
                'importers' => Importers::forUi(),
                'library_categories' => Library::categories(),
                'site_url' => home_url('/'),
                'multisite' => Network::forUi(),
                'plans' => Plans::forUi(),
                'cloud' => (Plans::pro() && \WPIDE\App\Services\Snippets\Cloud\Connection::connected()
                    ? ['connected' => true, 'ai' => \WPIDE\App\Services\Snippets\Cloud\Connection::ai()]
                    : ['connected' => false, 'ai' => null]) + ['ai_buy_url' => \WPIDE\App\Classes\Freemius::aiBuyUrl()],
                'timezone' => wp_timezone_string(),
            ]);
        }

        public function list(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $result = Repository::query([
                'status' => sanitize_key($input['status'] ?? 'all'),
                'type' => sanitize_key($input['type'] ?? ''),
                'tag' => sanitize_title($input['tag'] ?? ''),
                'location' => sanitize_text_field($input['location'] ?? ''),
                'search' => sanitize_text_field($input['search'] ?? ''),
                'orderby' => sanitize_key($input['orderby'] ?? 'updated_at'),
                'order' => sanitize_key($input['order'] ?? 'desc'),
                'page' => (int) ($input['page'] ?? 1),
                'per_page' => (int) ($input['per_page'] ?? 50),
            ]);

            $result['items'] = array_map([$this, 'listItem'], $result['items']);
            $response->json($result);
        }

        protected function listItem(array $snippet): array
        {
            $item = Repository::content($snippet);
            unset($item['code'], $item['compiled_code']);

            return $item + [
                'id' => $snippet['id'],
                'quarantined' => $snippet['quarantined'],
                'has_error' => !empty($snippet['last_error']),
                'last_error' => $snippet['last_error'],
                'has_draft' => !empty($snippet['draft']),
                'updated_at' => $snippet['updated_at'],
                'created_by' => $snippet['created_by'],
                'author' => $snippet['created_by'] ? (string) get_the_author_meta('display_name', $snippet['created_by']) : '',
                'provenance' => $snippet['provenance'],
                'code_size' => strlen($snippet['code']),
                'pinned' => in_array((int) $snippet['id'], Pins::all(), true),
            ];
        }

        public function pin(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $pins = Pins::set((int) ($input['id'] ?? 0), !empty($input['pinned']));

            $response->json(['success' => true, 'pinned' => $pins, 'counts' => Repository::counts()]);
        }

        public function get(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $id = (int) ($this->body($request)['id'] ?? 0);
            $snippet = Repository::find($id);

            if (!$snippet) {
                return $response->json(__('Snippet not found.', 'wpide'), 404);
            }

            $response->json([
                'snippet' => $snippet,
                'revisions' => Plans::pro() ? Revisions::forSnippet($id) : [],
                'events' => Events::recent(20, $id),
                'can_edit' => Capabilities::canEditType($snippet['code_type']),
                'skipped' => ContentInserter::skipped()[$id] ?? null,
            ]);
        }

        public function save(Request $request, Response $response)
        {
            $data = $this->body($request)['snippet'] ?? [];

            if (!is_array($data) || !$this->allowedAny($response)) {
                return;
            }

            $result = Manager::save($data, 'editor');
            $response->json($result + ['counts' => Repository::counts(), 'tags' => Repository::tags()]);
        }

        public function validate(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $data = (array) ($this->body($request)['snippet'] ?? []);
            $id = (int) ($data['id'] ?? 0);
            $snippet = Repository::normalize($data, $id ? (Repository::find($id) ?: []) : []);

            $response->json(Validation::check($snippet, $id));
        }

        public function status(Request $request, Response $response)
        {
            $input = $this->body($request);

            if (!$this->allowed($response, Capabilities::ACTIVATE)) {
                return;
            }

            $result = Manager::setStatus((int) ($input['id'] ?? 0), !empty($input['active']));
            $response->json($result + ['counts' => Repository::counts()]);
        }

        public function bulk(Request $request, Response $response)
        {
            $input = $this->body($request);
            $ids = array_filter(array_map('intval', (array) ($input['ids'] ?? [])));
            $action = sanitize_key($input['action'] ?? '');
            $results = [];

            if (!$this->allowedAny($response)) {
                return;
            }

            foreach ($ids as $id) {
                $snippet = Repository::find($id);

                if (!$snippet) {
                    continue;
                }

                if (!Capabilities::canEditType($snippet['code_type'])) {
                    $results[$id] = ['success' => false, 'errors' => [__('You are not allowed to edit this type of snippet.', 'wpide')]];
                    continue;
                }

                switch ($action) {
                    case 'activate':
                    case 'deactivate':
                        $results[$id] = Manager::setStatus($id, $action === 'activate');
                        break;
                    case 'trash':
                        $results[$id] = ['success' => $this->canChange($snippet) && Repository::trash($id)];
                        break;
                    case 'restore':
                        $results[$id] = ['success' => Repository::restore($id)];
                        break;
                    case 'delete':
                        $results[$id] = ['success' => $snippet['status'] === 'trash' && Repository::delete($id)];
                        break;
                    case 'duplicate':
                        $results[$id] = ['success' => (bool) Repository::duplicate($id)];
                        break;
                    case 'release':
                        Repository::updateMeta($id, ['quarantined' => false, 'last_error' => null]);
                        $results[$id] = ['success' => true];
                        break;
                    default:
                        return $response->json(__('Unknown action.', 'wpide'), 422);
                }
            }

            $response->json(['results' => $results, 'counts' => Repository::counts()]);
        }

        /**
         * Active snippets can only be trashed by users who can activate snippets.
         */
        protected function canChange(array $snippet): bool
        {
            return $snippet['status'] !== 'active' || current_user_can(Capabilities::ACTIVATE);
        }

        public function execute(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::EDIT_PHP)) {
                return;
            }

            $result = Manager::execute((int) ($this->body($request)['id'] ?? 0));
            $response->json($result);
        }

        public function revision(Request $request, Response $response)
        {
            if (!$this->pro($response) || !$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $revision = Revisions::get((int) ($input['revision_id'] ?? 0));

            if (!$revision) {
                return $response->json(__('Revision not found.', 'wpide'), 404);
            }

            $compareId = (int) ($input['compare_to'] ?? 0);
            $compare = $compareId ? Revisions::get($compareId) : null;
            $current = $revision['snippet_id'] ? Repository::find($revision['snippet_id']) : null;
            $base = $compare ? $compare['payload'] : ($current ? Repository::content($current) : []);

            $response->json([
                'revision' => $revision,
                'compare' => $base,
                'changes' => Revisions::diff($revision['payload'], $base),
            ]);
        }

        public function restoreRevision(Request $request, Response $response)
        {
            if (!$this->pro($response)) {
                return;
            }

            $revision = Revisions::get((int) ($this->body($request)['revision_id'] ?? 0));

            if (!$revision || !$revision['snippet_id']) {
                return $response->json(__('Revision not found.', 'wpide'), 404);
            }

            $snippet = Repository::find($revision['snippet_id']);

            if (!$snippet || !Capabilities::canEditType($snippet['code_type']) || ($snippet['status'] === 'active' && !current_user_can(Capabilities::ACTIVATE))) {
                return $response->json(__('You are not allowed to restore this revision.', 'wpide'), 403);
            }

            $content = $revision['payload'];
            unset($content['status']);
            $result = Manager::save(['id' => $snippet['id']] + $content, 'restore');
            $response->json($result);
        }

        public function events(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $response->json(Events::recent((int) ($input['limit'] ?? 200), (int) ($input['id'] ?? 0), sanitize_key($input['type'] ?? '')));
        }

        public function clearEvents(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::SETTINGS)) {
                return;
            }

            Events::clear((int) ($this->body($request)['id'] ?? 0));
            $response->json(true);
        }

        public function testing(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $input = $this->body($request);
            $action = sanitize_key($input['action'] ?? 'status');
            $id = (int) ($input['id'] ?? 0);
            $result = ['success' => true];

            if (!Plans::pro()) {
                return $action === 'status' ? $response->json($result + ['enabled' => false, 'counts' => Repository::counts()]) : $this->pro($response);
            }

            switch ($action) {
                case 'enable':
                case 'disable':
                    TestingMode::setEnabled($action === 'enable');
                    break;
                case 'publish':
                    if (!current_user_can(Capabilities::ACTIVATE)) {
                        return $response->json(__('Only users who can activate snippets can publish staged changes.', 'wpide'), 403);
                    }
                    $result = $id ? ['success' => true, 'snippet' => TestingMode::publish($id)] : ['success' => true] + TestingMode::publishAll();
                    \WPIDE\App\Services\Snippets\GlobalScripts::publishDraft();
                    break;
                case 'discard':
                    $id ? TestingMode::discard($id) : TestingMode::discardAll();
                    if (!$id) {
                        \WPIDE\App\Services\Snippets\GlobalScripts::discardDraft();
                    }
                    break;
            }

            $response->json($result + ['enabled' => TestingMode::isEnabled(), 'counts' => Repository::counts()]);
        }

        public function settings(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::SETTINGS)) {
                return;
            }

            $input = (array) ($this->body($request)['settings'] ?? []);

            // Pausing all snippets is an activation decision.
            if (array_key_exists('paused', $input) && !current_user_can(Capabilities::ACTIVATE)) {
                unset($input['paused']);
            }

            $response->json(Settings::save($input));
        }

        /**
         * Search helper for condition values (posts, users, terms, snippets, products, memberships).
         */
        public function search(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $input = $this->body($request);
            $response->json(Search::run(sanitize_key($input['kind'] ?? ''), sanitize_text_field($input['term'] ?? ''), array_map('strval', (array) ($input['ids'] ?? []))));
        }
    }
}
