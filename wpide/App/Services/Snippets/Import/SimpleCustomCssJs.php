<?php

namespace WPIDE\App\Services\Snippets\Import;

/**
 * Migration from Simple Custom CSS and JS installed on this site.
 *
 * Each custom code is a "custom-css-js" post whose "options" meta holds the language, header/footer position,
 * the sides it runs on (frontend, admin, login), internal/external linking, priority and minification. Posts and
 * meta are only read. A code that runs on both the frontend and the admin becomes two WPIDE snippets, because a
 * WPIDE snippet has one location.
 */
class SimpleCustomCssJs extends Importer
{
    const POST_TYPE = 'custom-css-js';

    const STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    const KNOWN_OPTIONS = ['type', 'linking', 'priority', 'side', 'language', 'minify', 'preprocessor'];

    public function id(): string
    {
        return 'simple-custom-css-js';
    }

    public function label(): string
    {
        return __('Simple Custom CSS and JS on this site', 'wpide');
    }

    public function description(): string
    {
        return __('Copy custom CSS, JS and HTML codes from Simple Custom CSS and JS installed on this site. Its data is not changed.', 'wpide');
    }

    public function available(): bool
    {
        return (bool) $this->postIds(1);
    }

    protected function postIds(int $limit = 0): array
    {
        global $wpdb;

        $statuses = "'" . implode("','", self::STATUSES) . "'";
        $sql = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ($statuses) ORDER BY ID ASC", self::POST_TYPE); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return array_map('intval', (array) $wpdb->get_col($sql)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    protected function items(?array $file = null): array
    {
        $items = [];

        foreach ($this->postIds() as $postId) {
            $post = get_post($postId);

            if ($post) {
                $items = array_merge($items, $this->postItems($post));
            }
        }

        return $items;
    }

    /**
     * @param \WP_Post $post
     */
    protected function postItems($post): array
    {
        $options = get_post_meta($post->ID, 'options', true);
        $options = is_array($options) ? $options : [];
        $unsupported = [];
        $warnings = [];
        $missing = [];

        $language = strtolower((string) ($options['language'] ?? 'css'));
        $type = in_array($language, ['css', 'js', 'html'], true) ? $language : 'css';

        if (!isset($options['language'])) {
            $missing[] = __('Language (CSS assumed)', 'wpide');
        } elseif ($type !== $language) {
            $unsupported[] = sprintf(__('Language "%s" (imported as CSS)', 'wpide'), $language);
        }

        $preprocessor = strtolower((string) ($options['preprocessor'] ?? 'none'));

        if ($type === 'css' && $preprocessor === 'sass') {
            $type = 'scss';
            $warnings[] = __('Written in SASS: imported as an SCSS snippet. Check that it compiles.', 'wpide');
        } elseif ($type === 'css' && !in_array($preprocessor, ['', 'none'], true)) {
            $unsupported[] = sprintf(__('Preprocessor "%s" (the code is imported as plain CSS and must be converted)', 'wpide'), $preprocessor);
        }

        $code = (string) $post->post_content;

        if (in_array($type, ['css', 'js'], true) && (preg_match('/^\s*</', $code) || preg_match('/<\s*\/?\s*(script|style)\b/i', $code))) {
            $type = 'html';
            $warnings[] = __('The code contains its own HTML tags, so it is imported as an HTML snippet to be printed unchanged.', 'wpide');
        }

        $position = strtolower((string) ($options['type'] ?? 'header'));

        if (!in_array($position, ['header', 'footer'], true)) {
            $unsupported[] = sprintf(__('Position "%s" (imported in the header)', 'wpide'), $position);
            $position = 'header';
        }

        $sides = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($options['side'] ?? 'frontend'))))));
        $sides = $sides ?: ['frontend'];

        foreach (array_diff($sides, ['frontend', 'admin']) as $side) {
            $unsupported[] = $side === 'login'
                ? __('Login page output (WPIDE has no login page location)', 'wpide')
                : sprintf(__('Side "%s"', 'wpide'), $side);
        }

        $external = ($options['linking'] ?? 'internal') === 'external';
        $minify = !empty($options['minify']) && !in_array($options['minify'], ['no', 'false', '0'], true);
        $settings = [
            'load_as_file' => $external && in_array($type, ['css', 'scss', 'js'], true),
            'compress_output' => $minify,
        ];

        if ($external && !$settings['load_as_file']) {
            $unsupported[] = __('External file linking (only for CSS and JS)', 'wpide');
        }

        foreach ($options as $key => $value) {
            if (!in_array($key, self::KNOWN_OPTIONS, true) && $value !== '' && $value !== null && $value !== false && $value !== []) {
                $unsupported[] = sprintf(__('Setting "%s"', 'wpide'), $key);
            }
        }

        $active = $post->post_status === 'publish' && get_post_meta($post->ID, '_active', true) !== 'no';

        if ($active) {
            $warnings[] = __('Active in Simple Custom CSS and JS. Deactivate it there before activating it here, or it will be output twice.', 'wpide');
        }

        $title = trim((string) $post->post_title);
        $title = $title !== '' ? $title : sprintf(__('Custom %1$s #%2$d', 'wpide'), strtoupper($language), $post->ID);
        $targets = array_values(array_intersect(['frontend', 'admin'], $sides));
        $base = [
            'note' => '',
            'code' => $code,
            'code_type' => $type,
            'priority' => isset($options['priority']) ? (int) $options['priority'] : 10,
            'settings' => $settings,
        ];

        if (!isset($options['priority'])) {
            $missing[] = __('Priority (10 is used)', 'wpide');
        }

        if (!$targets) {
            // Login only: there is no matching location, so keep the code as a shortcode-only snippet.
            return [$this->item($post, (string) $post->ID, $base + [
                'title' => $title,
                'insert_method' => 'shortcode',
                'location' => '',
                'source_ref' => $this->id() . ':' . $post->ID,
            ], $active, $missing, $unsupported, $warnings)];
        }

        $items = [];

        foreach ($targets as $index => $side) {
            $isAdmin = $side === 'admin';
            $location = $isAdmin ? ($position === 'footer' ? 'admin_footer' : 'admin_head') : ($position === 'footer' ? 'site_wide_footer' : 'site_wide_header');
            $split = count($targets) > 1;
            $itemWarnings = $warnings;

            if ($split) {
                $itemWarnings[] = __('Runs on both the frontend and the admin: imported as two snippets, one per area.', 'wpide');
            }

            $items[] = $this->item($post, $index === 0 ? (string) $post->ID : $post->ID . ':' . $side, $base + [
                'title' => $split ? $title . ($isAdmin ? ' ' . __('(admin)', 'wpide') : ' ' . __('(frontend)', 'wpide')) : $title,
                'insert_method' => 'auto',
                'location' => $location,
                'source_ref' => $this->id() . ':' . $post->ID . ($index === 0 ? '' : ':' . $side),
            ], $active, $missing, $unsupported, $itemWarnings);
        }

        return $items;
    }

    /**
     * @param \WP_Post $post
     */
    protected function item($post, string $key, array $snippet, bool $active, array $missing, array $unsupported, array $warnings): array
    {
        return [
            'key' => $key,
            'title' => $snippet['title'],
            'code_type' => $snippet['code_type'],
            'snippet' => $snippet,
            'original_status' => $active ? __('active', 'wpide') : __('inactive', 'wpide'),
            'preserved' => ['title', 'code', 'code_type', 'location', 'priority', 'load_as_file', 'compress_output'],
            'missing' => array_merge($missing, [__('Description', 'wpide'), __('Tags', 'wpide')]),
            'unsupported' => $unsupported,
            'warnings' => $warnings,
        ];
    }
}
