<?php

namespace WPIDE\App\Services\Snippets\Import;

use WPIDE\App\Services\Snippets\Registry\Conditions;

/**
 * Migration from Woody Code Snippets (formerly Insert PHP / Woody ad snippets) installed on this site.
 *
 * Snippets are "wbcr-snippets" posts with "wbcr_inp_*" meta for the type, scope (run everywhere, auto insert,
 * shortcode), location, paragraph/post number, priority, description and conditional display filters. Posts and
 * meta are only read. Filters are converted to WPIDE conditions where the rule exists in both plugins.
 */
class Woody extends Importer
{
    const POST_TYPE = 'wbcr-snippets';

    const TAXONOMY = 'wbcr-snippet-tags';

    const META_PREFIX = 'wbcr_inp_';

    const STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    const TYPES = ['php' => 'php', 'text' => 'text', 'universal' => 'universal', 'css' => 'css', 'js' => 'js', 'html' => 'html', 'advert' => 'html', 'ad' => 'html'];

    const LOCATIONS = [
        'header' => 'site_wide_header',
        'footer' => 'site_wide_footer',
        'body' => 'site_wide_body',
        'body_open' => 'site_wide_body',
        'before_post' => 'before_post',
        'after_post' => 'after_post',
        'before_content' => 'before_content',
        'after_content' => 'after_content',
        'before_paragraph' => 'before_paragraph',
        'after_paragraph' => 'after_paragraph',
        'before_excerpt' => 'before_excerpt',
        'after_excerpt' => 'after_excerpt',
        'between_posts' => 'between_posts',
        'before_posts' => 'archive_before_post',
        'after_posts' => 'archive_after_post',
    ];

    /**
     * Woody page types => WPIDE "type of page" values.
     */
    const PAGE_TYPES = [
        'home' => 'is_front_page',
        'base_home' => 'is_front_page',
        'front_page' => 'is_front_page',
        'blog' => 'is_home',
        'base_blog' => 'is_home',
        'single' => 'is_single',
        'base_sing' => 'is_single',
        'singular' => 'is_single',
        'archive' => 'is_archive',
        'base_arch' => 'is_archive',
        'author' => 'is_author',
        'search' => 'is_search',
        'base_search' => 'is_search',
        '404' => 'is_404',
        'base_404' => 'is_404',
    ];

    const OPERATORS = [
        'equals' => '=',
        'equal' => '=',
        'notequal' => '!=',
        'notequals' => '!=',
        'not_equal' => '!=',
        'contains' => 'contains',
        'notcontain' => 'notcontains',
        'notcontains' => 'notcontains',
        'not_contains' => 'notcontains',
    ];

    public function id(): string
    {
        return 'woody';
    }

    public function label(): string
    {
        return __('Woody Code Snippets on this site', 'wpide');
    }

    public function description(): string
    {
        return __('Copy snippets and their conditions from Woody Code Snippets installed on this site. Its data is not changed.', 'wpide');
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

    protected function meta(int $postId, string $key, $default = '')
    {
        $value = get_post_meta($postId, self::META_PREFIX . $key, true);

        return $value === '' || $value === null ? $default : $value;
    }

    protected function items(?array $file = null): array
    {
        $items = [];

        foreach ($this->postIds() as $postId) {
            $post = get_post($postId);

            if ($post) {
                $items[] = $this->item($post);
            }
        }

        return $items;
    }

    /**
     * @param \WP_Post $post
     */
    protected function item($post): array
    {
        $id = (int) $post->ID;
        $unsupported = [];
        $warnings = [];
        $missing = [];

        $sourceType = strtolower((string) $this->meta($id, 'snippet_type', 'php'));
        $type = self::TYPES[$sourceType] ?? $sourceType;

        if (!isset(self::TYPES[$sourceType])) {
            $unsupported[] = sprintf(__('Snippet type "%s"', 'wpide'), $sourceType);
        }

        $code = (string) $post->post_content;

        if (trim($code) === '') {
            $code = (string) $this->meta($id, 'snippet_code', '');
        }

        if (in_array($type, ['php', 'universal'], true) && !preg_match('/(->|=>)/', $code) && preg_match('/(-|=)&gt;/', $code)) {
            // Code saved HTML-escaped (seen in older versions): restore it.
            $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML401, 'UTF-8');
            $warnings[] = __('The stored code was HTML-escaped and has been decoded. Review it before activating.', 'wpide');
        }

        if (in_array($type, ['css', 'js'], true) && (preg_match('/^\s*</', $code) || preg_match('/<\s*\/?\s*(script|style)\b/i', $code))) {
            $type = 'html';
            $warnings[] = __('The code contains its own HTML tags, so it is imported as an HTML snippet to be printed unchanged.', 'wpide');
        }

        $scope = strtolower((string) $this->meta($id, 'snippet_scope', ''));

        if ($scope === '') {
            // Never guess "run everywhere" for code: without a saved scope, PHP is only offered as a shortcode.
            $scope = $type === 'php' ? 'shortcode' : 'auto';
            $missing[] = __('Scope (not saved in Woody)', 'wpide');
        }
        $sourceLocation = strtolower((string) $this->meta($id, 'snippet_location', ''));
        $number = max(1, (int) $this->meta($id, 'snippet_p_number', 1));

        $snippet = [
            'title' => trim((string) $post->post_title) !== '' ? trim((string) $post->post_title) : sprintf(__('Woody snippet #%d', 'wpide'), $id),
            'note' => trim(wp_strip_all_tags((string) $this->meta($id, 'snippet_description', ''))),
            'code' => $code,
            'code_type' => $type,
            'insert_method' => 'auto',
            'location' => '',
            'insert_number' => $number,
            'priority' => (int) $this->meta($id, 'snippet_priority', 10),
            'tags' => $this->tags($id),
            'use_conditions' => false,
            'conditions' => ['show' => 'show', 'groups' => []],
            'source_ref' => $this->id() . ':' . $id,
        ];

        if ($scope === 'evrywhere' || $scope === 'everywhere') {
            if ($type === 'php') {
                $snippet['location'] = 'everywhere';
            } else {
                $snippet['location'] = self::LOCATIONS[$sourceLocation] ?? 'site_wide_header';
            }
        } elseif ($scope === 'auto') {
            if (isset(self::LOCATIONS[$sourceLocation])) {
                $snippet['location'] = self::LOCATIONS[$sourceLocation];
            } elseif ($sourceLocation === '' && in_array($type, ['css', 'js'], true)) {
                $snippet['location'] = 'site_wide_header';
            } else {
                $snippet['insert_method'] = 'shortcode';
                $unsupported[] = sprintf(__('Location "%s" (imported as shortcode only)', 'wpide'), $sourceLocation);
            }
        } else {
            if ($scope !== 'shortcode') {
                $unsupported[] = sprintf(__('Scope "%s" (imported as shortcode only)', 'wpide'), $scope);
            }

            $snippet['insert_method'] = 'shortcode';
            $warnings[] = sprintf(__('Content that uses Woody\'s shortcode for snippet %d (for example [wbcr_php_snippet id="%d"]) must be changed to use this snippet\'s WPIDE shortcode.', 'wpide'), $id, $id);

            if (in_array($type, ['php', 'universal'], true)) {
                $missing[] = __('Shortcode attribute names (Woody passes any attribute; declare the ones the code uses)', 'wpide');
            }
        }

        $linking = strtolower((string) $this->meta($id, 'snippet_linking', ''));

        if (in_array($linking, ['external', 'file'], true)) {
            if (in_array($type, ['css', 'js'], true)) {
                $snippet['settings'] = ['load_as_file' => true, 'compress_output' => false];
            } else {
                $unsupported[] = __('External file linking (only for CSS and JS)', 'wpide');
            }
        }

        $filters = $this->decode($this->meta($id, 'snippet_filters', []));
        $filtersOn = $this->meta($id, 'changed_filters', null);

        if ($filters) {
            if ($filtersOn !== null && !$this->truthy($filtersOn)) {
                $warnings[] = __('Woody has conditions saved for this snippet but they are switched off, so they are not imported.', 'wpide');
            } else {
                $conditions = $this->conditions($filters, $unsupported);

                if ($conditions) {
                    $snippet['use_conditions'] = true;
                    $snippet['conditions'] = $conditions;
                }
            }
        }

        $active = $post->post_status === 'publish' && $this->truthy($this->meta($id, 'snippet_activate', 0));

        if ($active) {
            $warnings[] = $type === 'php'
                ? __('Active in Woody. Deactivate it there before activating it here: running the same PHP twice can cause a fatal error.', 'wpide')
                : __('Active in Woody. Deactivate it there before activating it here, or it will be output twice.', 'wpide');
        }

        if ($snippet['insert_method'] === 'shortcode') {
            $snippet['location'] = '';
        }

        return [
            'key' => (string) $id,
            'title' => $snippet['title'],
            'code_type' => $type,
            'snippet' => $snippet,
            'original_status' => $active ? __('active', 'wpide') : __('inactive', 'wpide'),
            'preserved' => ['title', 'code', 'code_type', 'location', 'insert_number', 'priority', 'note', 'tags', 'conditions'],
            'missing' => $missing,
            'unsupported' => $unsupported,
            'warnings' => $warnings,
        ];
    }

    /**
     * Woody filters: a list of {type: showif|hideif, conditions: [{param, operator, type, value}]}. Conditions in a
     * filter are combined with AND and filters with OR, like WPIDE rows and groups.
     */
    protected function conditions(array $filters, array &$unsupported): array
    {
        $show = null;
        $groups = [];

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $rows = isset($filter['conditions']) && is_array($filter['conditions']) ? $filter['conditions'] : (isset($filter['param']) ? [$filter] : []);
            $mode = strtolower((string) ($filter['type'] ?? 'showif')) === 'hideif' ? 'hide' : 'show';

            if ($show === null) {
                $show = $mode;
            } elseif ($show !== $mode) {
                $unsupported[] = __('A mix of "show if" and "hide if" condition groups (only the groups matching the first one are kept)', 'wpide');
                continue;
            }

            $group = [];

            foreach ($rows as $row) {
                $mapped = is_array($row) ? $this->rule($row) : null;

                if ($mapped === null) {
                    $param = is_array($row) ? (string) ($row['param'] ?? '?') : '?';
                    $operator = is_array($row) ? (string) ($row['operator'] ?? '') : '';
                    $unsupported[] = sprintf(__('Condition "%1$s %2$s" (left out, so the remaining conditions of its group are broader)', 'wpide'), $param, $operator);
                    continue;
                }

                $group[] = $mapped;
            }

            if ($group) {
                $groups[] = $group;
            }
        }

        return $groups ? ['show' => $show ?: 'show', 'groups' => $groups] : [];
    }

    /**
     * One Woody condition as a WPIDE condition row, or null when there is no equivalent.
     */
    protected function rule(array $row): ?array
    {
        $param = strtolower((string) ($row['param'] ?? ''));
        $relation = self::OPERATORS[strtolower((string) ($row['operator'] ?? 'equals'))] ?? null;
        $value = $row['value'] ?? '';

        if ($relation === null) {
            return null;
        }

        $result = null;

        switch ($param) {
            case 'user-role':
                if ($value === 'guest' || $value === ['guest']) {
                    $result = ['type' => 'user', 'option' => 'logged_in', 'relation' => $relation, 'value' => ''];
                } elseif (is_scalar($value) && $value !== '') {
                    $result = ['type' => 'user', 'option' => 'user_role', 'relation' => $relation, 'value' => sanitize_key((string) $value)];
                }
                break;

            case 'user-registered':
            case 'user-logged-in':
            case 'user-loggedin':
                if (is_scalar($value) && in_array(strtolower((string) $value), ['yes', 'no', 'true', 'false', '1', '0'], true)) {
                    $result = ['type' => 'user', 'option' => 'logged_in', 'relation' => $relation, 'value' => $this->truthy($value) ? '1' : ''];
                }
                break;

            case 'user-mobile':
                if (is_scalar($value)) {
                    $result = ['type' => 'device', 'option' => 'device_type', 'relation' => $relation, 'value' => $this->truthy($value) ? 'mobile' : 'desktop'];
                }
                break;

            case 'user-cookie-name':
                $result = ['type' => 'device', 'option' => 'cookie_name', 'relation' => $relation, 'value' => (string) (is_scalar($value) ? $value : '')];
                break;

            case 'location-page':
            case 'location-url':
                $result = ['type' => 'page', 'option' => 'page_url', 'relation' => $relation, 'value' => (string) (is_scalar($value) ? $value : '')];
                break;

            case 'location-referrer':
                $result = ['type' => 'page', 'option' => 'referrer', 'relation' => $relation, 'value' => (string) (is_scalar($value) ? $value : '')];
                break;

            case 'location-post-type':
                if (is_scalar($value) && $value !== '') {
                    $result = ['type' => 'page', 'option' => 'post_type', 'relation' => $relation, 'value' => sanitize_key((string) $value)];
                }
                break;

            case 'location-some-page':
            case 'location-page-type':
                $key = is_scalar($value) ? strtolower((string) $value) : '';

                if (isset(self::PAGE_TYPES[$key])) {
                    $result = ['type' => 'page', 'option' => 'type_of_page', 'relation' => $relation, 'value' => self::PAGE_TYPES[$key]];
                }
                break;

            case 'location-taxonomy':
            case 'location-term':
                $ids = $this->idList($value);

                if ($ids) {
                    $result = ['type' => 'page', 'option' => 'taxonomy_term', 'relation' => $relation, 'value' => $ids];
                }
                break;

            case 'location-post':
            case 'location-post-id':
            case 'location-page-id':
                $ids = $this->idList($value);

                if ($ids) {
                    $result = ['type' => 'page', 'option' => 'post_id', 'relation' => $relation, 'value' => $ids];
                }
                break;
        }

        // Only keep rows WPIDE accepts (operator allowed for the field, value present).
        if ($result === null || Conditions::validate(['show' => 'show', 'groups' => [[$result]]])) {
            return null;
        }

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    protected function idList($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $ids = [];

        foreach ((array) $value as $item) {
            if (is_array($item)) {
                $item = $item['value'] ?? $item['id'] ?? 0;
            }

            if (is_scalar($item) && absint($item) > 0) {
                $ids[] = (string) absint($item);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Filters are stored as JSON in some versions and as a serialized array in others.
     *
     * @param mixed $value
     */
    protected function decode($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (!is_array($decoded)) {
                $decoded = json_decode(wp_unslash($value), true);
            }

            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? array_values($value) : [];
    }

    protected function truthy($value): bool
    {
        return in_array(is_string($value) ? strtolower(trim($value)) : $value, [true, 1, '1', 'yes', 'true', 'on'], true);
    }

    /**
     * Tag slugs. The taxonomy is only registered while Woody is active, so read the term tables directly.
     */
    protected function tags(int $postId): array
    {
        global $wpdb;

        $slugs = $wpdb->get_col($wpdb->prepare(
            "SELECT t.slug FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = %s AND tr.object_id = %d ORDER BY t.name ASC",
            self::TAXONOMY,
            $postId
        )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return array_values(array_map('strval', (array) $slugs));
    }
}
