<?php

namespace WPIDE\App\Controllers\Snippets;

defined( 'ABSPATH' ) || exit;

use WPIDE\App\Classes\Freemius;
use WPIDE\App\Kernel\Request;
use WPIDE\App\Kernel\Response;
use WPIDE\App\Services\Snippets\Capabilities;
use WPIDE\App\Services\Snippets\Cloud\Connection;
use WPIDE\App\Services\Snippets\Favorites;
use WPIDE\App\Services\Snippets\Generators\Registry as Generators;
use WPIDE\App\Services\Snippets\GlobalScripts;
use WPIDE\App\Services\Snippets\Import\Exporter;
use WPIDE\App\Services\Snippets\Import\Importers;
use WPIDE\App\Services\Snippets\Library;
use WPIDE\App\Services\Snippets\LibraryException;
use WPIDE\App\Services\Snippets\Packs;
use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\PublicLibrary;
use WPIDE\App\Services\Snippets\Repository;
use WPIDE\App\Services\Snippets\Revisions;
use WPIDE\App\Services\Snippets\Schema;
use WPIDE\App\Services\Snippets\Scss;
use WPIDE\App\Services\Snippets\Snippets;
use WPIDE\App\Services\Snippets\SpecialFiles;

if (class_exists(Snippets::class) && Snippets::available()) {

    /**
     * Snippet tools: import/export, library, generators, global scripts, special files, SCSS preview.
     */
    class ToolsController
    {
        use JsonInput;

        const MAX_IMPORT_BYTES = 10485760;

        public function __construct()
        {
            Schema::maybeInstall();
        }

        public function export(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $ids = array_filter(array_map('intval', (array) ($input['ids'] ?? [])));

            $response->json([
                'filename' => Exporter::filename(),
                'data' => Exporter::export($ids, !empty($input['include_global'])),
            ]);
        }

        protected function importFile(array $input): ?array
        {
            $content = (string) ($input['file'] ?? '');

            if ($content === '' || strlen($content) > self::MAX_IMPORT_BYTES) {
                return null;
            }

            // Strip a UTF-8 byte order mark.
            if (strpos($content, "\xEF\xBB\xBF") === 0) {
                $content = substr($content, 3);
            }

            $data = json_decode($content, true);

            return is_array($data) ? $data : null;
        }

        public function analyzeImport(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $input = $this->body($request);
            $importer = Importers::get(sanitize_key($input['importer'] ?? ''));

            if (!$importer) {
                return $response->json(__('Unknown importer.', 'wpide'), 422);
            }

            $file = null;

            if ($importer->acceptsFile()) {
                $file = $this->importFile($input);

                if ($file === null) {
                    return $response->json(__('The file is not a valid JSON export (or is larger than 10 MB).', 'wpide'), 422);
                }
            }

            $response->json($importer->analyze($file));
        }

        public function commitImport(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $input = $this->body($request);
            $importer = Importers::get(sanitize_key($input['importer'] ?? ''));

            if (!$importer) {
                return $response->json(__('Unknown importer.', 'wpide'), 422);
            }

            $file = $importer->acceptsFile() ? $this->importFile($input) : null;

            if ($importer->acceptsFile() && $file === null) {
                return $response->json(__('The file is not a valid JSON export.', 'wpide'), 422);
            }

            $extras = array_map('sanitize_key', (array) ($input['extras'] ?? []));

            // Global scripts, page scripts and files are markup: they need markup editing rights.
            if ($extras && !current_user_can(Capabilities::EDIT_MARKUP)) {
                $extras = [];
            }

            $response->json($importer->commit(array_map('strval', (array) ($input['keys'] ?? [])), $file, $extras));
        }

        public function library(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $response->json([
                'categories' => Library::categories(),
                'snippets' => Library::search(sanitize_text_field($input['search'] ?? ''), sanitize_key($input['category'] ?? ''), !empty($input['favorites']), sanitize_key($input['author'] ?? '')),
                'favorites' => Plans::pro() ? count(array_filter(Favorites::all(), [Library::class, 'get'])) : 0,
                'favorites_synced' => Plans::pro() && Connection::connected(),
            ]);
        }

        public function libraryFavorite(Request $request, Response $response)
        {
            if (!$this->pro($response) || !$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $input = $this->body($request);
            $response->json(Favorites::set(sanitize_key($input['id'] ?? ''), !empty($input['favorite'])));
        }

        public function libraryItem(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $entry = Library::find(sanitize_key($this->body($request)['id'] ?? ''));

            if (!$entry) {
                return $response->json(__('Library snippet not found.', 'wpide'), 404);
            }

            $response->json($entry + ['snippet' => Library::toSnippet($entry)]);
        }

        public function libraryInstall(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $result = Library::install(sanitize_key($this->body($request)['id'] ?? ''));
            $response->json($result);
        }

        public function libraryUpdate(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $result = Library::update((int) ($this->body($request)['snippet_id'] ?? 0));
            $response->json($result);
        }

        /**
         * Publishing to the public library: whether this site can (it needs its Freemius connection), and the
         * snippets it published, with where each stands.
         */
        public function libraryAccount(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $account = ['registered' => PublicLibrary::identity() !== null, 'pro' => Plans::pro(), 'website' => PublicLibrary::website(), 'categories' => Library::categories(), 'connect_url' => Freemius::sdk()->is_registered() ? Freemius::sdk()->get_account_url() : Freemius::sdk()->get_reconnect_url()];

            if (!$account['registered']) {
                return $response->json($account + ['author' => null, 'snippets' => []]);
            }

            try {
                $response->json($account + PublicLibrary::mine());
            } catch (LibraryException $e) {
                $response->json($account + ['author' => null, 'snippets' => [], 'error' => wp_specialchars_decode($e->getMessage(), ENT_QUOTES), 'code' => $e->errorCode]);
            }
        }

        public function libraryPublish(Request $request, Response $response)
        {
            $input = $this->body($request);
            $snippet = Repository::find((int) ($input['id'] ?? 0));

            if (!$snippet) {
                return $response->json(['success' => false, 'message' => __('Snippet not found.', 'wpide')]);
            }

            // Sharing code needs the same right as editing it.
            if (!Capabilities::canEditType($snippet['code_type'])) {
                return $response->json(['success' => false, 'message' => __('You are not allowed to publish this type of snippet.', 'wpide')], 403);
            }

            try {
                $response->json(['success' => true, 'snippet' => PublicLibrary::publish((int) $snippet['id'], $input)]);
            } catch (LibraryException $e) {
                $response->json(['success' => false, 'message' => wp_specialchars_decode($e->getMessage(), ENT_QUOTES), 'code' => $e->errorCode]);
            }
        }

        public function libraryUnpublish(Request $request, Response $response)
        {
            $snippet = Repository::find((int) ($this->body($request)['id'] ?? 0));

            if (!$snippet || !Capabilities::canEditType($snippet['code_type'])) {
                return $response->json(['success' => false, 'message' => __('Snippet not found.', 'wpide')], 404);
            }

            try {
                PublicLibrary::unpublish((int) $snippet['id']);
                $response->json(['success' => true]);
            } catch (LibraryException $e) {
                $response->json(['success' => false, 'message' => wp_specialchars_decode($e->getMessage(), ENT_QUOTES), 'code' => $e->errorCode]);
            }
        }

        public function packs(Request $request, Response $response)
        {
            if ($this->allowed($response, Capabilities::VIEW)) {
                $response->json(Packs::all());
            }
        }

        public function installPack(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::ACTIVATE)) {
                return;
            }

            $report = Packs::install(sanitize_key($this->body($request)['id'] ?? ''));
            $response->json($report + ['packs' => Packs::all()]);
        }

        public function removePack(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::ACTIVATE)) {
                return;
            }

            $trashed = Packs::remove(sanitize_key($this->body($request)['id'] ?? ''));
            $response->json(['success' => true, 'trashed' => $trashed, 'packs' => Packs::all()]);
        }

        public function generator(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::VIEW)) {
                return;
            }

            $generator = Generators::get(sanitize_key($this->body($request)['id'] ?? ''));

            if (!$generator) {
                return $response->json(__('Generator not found.', 'wpide'), 404);
            }

            $response->json($generator->definition());
        }

        public function generate(Request $request, Response $response)
        {
            if (!$this->allowedAny($response)) {
                return;
            }

            $input = $this->body($request);
            $generator = Generators::get(sanitize_key($input['id'] ?? ''));

            if (!$generator) {
                return $response->json(__('Generator not found.', 'wpide'), 404);
            }

            $values = $generator->values((array) ($input['values'] ?? []));

            $response->json([
                'code' => $generator->generate($values),
                'values' => $values,
                'snippet' => $generator->snippetDefaults() + ['generator' => $generator->id(), 'generator_data' => $values, 'title' => $generator->info()['title']],
            ]);
        }

        public function globalScripts(Response $response)
        {
            if (!$this->allowed($response, Capabilities::EDIT_MARKUP)) {
                return;
            }

            $response->json([
                'scripts' => GlobalScripts::get(),
                'staged' => GlobalScripts::hasDraft() ? GlobalScripts::get(true) : null,
                'revisions' => Plans::pro() ? GlobalScripts::revisions() : [],
            ]);
        }

        public function saveGlobalScripts(Request $request, Response $response)
        {
            if (!$this->allowed($response, Capabilities::EDIT_MARKUP) || !$this->allowed($response, Capabilities::ACTIVATE)) {
                return;
            }

            GlobalScripts::save((array) ($this->body($request)['scripts'] ?? []));
            $this->globalScripts($response);
        }

        public function globalRevision(Request $request, Response $response)
        {
            if (!$this->pro($response) || !$this->allowed($response, Capabilities::EDIT_MARKUP)) {
                return;
            }

            $revision = Revisions::get((int) ($this->body($request)['revision_id'] ?? 0));

            if (!$revision || $revision['snippet_id'] !== 0) {
                return $response->json(__('Revision not found.', 'wpide'), 404);
            }

            $response->json($revision);
        }

        public function files(Response $response)
        {
            if (!$this->pro($response) || !$this->allowed($response, Capabilities::SPECIAL_FILES)) {
                return;
            }

            $response->json(array_values(SpecialFiles::all()));
        }

        public function saveFile(Request $request, Response $response)
        {
            if (!$this->pro($response) || !$this->allowed($response, Capabilities::SPECIAL_FILES)) {
                return;
            }

            $input = $this->body($request);
            $name = (string) ($input['name'] ?? '');

            // A service worker is JavaScript served from the site root: PHP-level trust required.
            if ($name === 'service-worker.js' && !current_user_can(Capabilities::EDIT_MARKUP)) {
                return $response->json(__('You are not allowed to edit the service worker.', 'wpide'), 403);
            }

            if (!SpecialFiles::save($name, (string) ($input['content'] ?? ''), !empty($input['enabled'])) && !isset(SpecialFiles::files()[$name])) {
                return $response->json(__('Unknown file.', 'wpide'), 422);
            }

            $this->files($response);
        }

        public function scss(Request $request, Response $response)
        {
            if (!$this->pro($response) || !$this->allowed($response, Capabilities::EDIT_MARKUP)) {
                return;
            }

            $response->json(Scss::compile((string) ($this->body($request)['code'] ?? '')));
        }
    }
}
