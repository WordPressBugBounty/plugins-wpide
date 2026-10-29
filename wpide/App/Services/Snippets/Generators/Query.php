<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Builds a WP_Query (optionally with a loop) from a form, including taxonomy and meta queries.
 */
class Query extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'query';
    }

    public function info(): array
    {
        return [
            'title' => __('WP_Query', 'wpide'),
            'description' => __('Build a custom posts query with filters, ordering, pagination, taxonomy and custom field conditions, plus an optional output loop.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['query', 'loop', 'posts', 'wp_query'],
        ];
    }

    /**
     * A query with a loop prints HTML, so it is best inserted through a shortcode rather than run on load.
     */
    public function snippetDefaults(): array
    {
        return ['code_type' => 'php', 'insert_method' => 'shortcode', 'location' => 'everywhere'];
    }

    public function fields(): array
    {
        $orderby = [
            'none' => __('No order', 'wpide'),
            'ID' => __('Post ID', 'wpide'),
            'author' => __('Author', 'wpide'),
            'title' => __('Title', 'wpide'),
            'name' => __('Slug', 'wpide'),
            'type' => __('Post type', 'wpide'),
            'date' => __('Publish date', 'wpide'),
            'modified' => __('Last modified date', 'wpide'),
            'parent' => __('Parent ID', 'wpide'),
            'rand' => __('Random', 'wpide'),
            'comment_count' => __('Number of comments', 'wpide'),
            'relevance' => __('Search relevance', 'wpide'),
            'menu_order' => __('Menu order', 'wpide'),
            'meta_value' => __('Custom field value (text)', 'wpide'),
            'meta_value_num' => __('Custom field value (numeric)', 'wpide'),
            'post__in' => __('Same order as the "Include IDs" list', 'wpide'),
            'post_name__in' => __('Same order as the "Slugs" list', 'wpide'),
            'post_parent__in' => __('Same order as the "Parent IDs" list', 'wpide'),
        ];

        $compare = [];

        foreach (['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE'] as $operator) {
            $compare[$operator] = $operator;
        }

        $types = [];

        foreach (['CHAR', 'NUMERIC', 'BINARY', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'] as $type) {
            $types[$type] = $type;
        }

        $relation = ['AND' => __('All conditions (AND)', 'wpide'), 'OR' => __('Any condition (OR)', 'wpide')];

        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->text('var_name', __('Variable name', 'wpide'), 'custom_query', __('Name of the PHP variable that holds the query.', 'wpide'), 'custom_query'),
                    $this->toggle('loop', __('Include the loop', 'wpide'), true, __('Add a have_posts() loop that outputs a list of linked titles.', 'wpide')),
                ],
            ],
            'posts' => [
                'label' => __('Posts', 'wpide'),
                'fields' => [
                    $this->text('post_type', __('Post types', 'wpide'), 'post', __('Comma separated, or "any".', 'wpide'), 'post, page'),
                    $this->text('post_status', __('Post statuses', 'wpide'), 'publish', __('Comma separated, e.g. publish, future.', 'wpide'), 'publish'),
                    $this->text('post__in', __('Include IDs', 'wpide'), '', __('Comma separated post IDs.', 'wpide'), '12, 34'),
                    $this->text('post__not_in', __('Exclude IDs', 'wpide'), '', __('Comma separated post IDs.', 'wpide')),
                    $this->text('post_parent__in', __('Parent IDs', 'wpide'), '', __('Only children of these posts.', 'wpide')),
                    $this->text('post_parent__not_in', __('Exclude parent IDs', 'wpide'), ''),
                    $this->text('post_name__in', __('Slugs', 'wpide'), '', __('Comma separated post slugs.', 'wpide'), 'hello-world'),
                    $this->text('s', __('Search keywords', 'wpide'), ''),
                ],
            ],
            'author' => [
                'label' => __('Author', 'wpide'),
                'fields' => [
                    $this->text('author', __('Author IDs', 'wpide'), '', __('Comma separated user IDs.', 'wpide')),
                    $this->text('author__not_in', __('Exclude author IDs', 'wpide'), ''),
                    $this->text('author_name', __('Author nicename', 'wpide'), '', __('The author\'s user_nicename (slug), not the display name.', 'wpide')),
                ],
            ],
            'order' => [
                'label' => __('Order & pagination', 'wpide'),
                'fields' => [
                    ['id' => 'orderby', 'type' => 'select', 'label' => __('Order by', 'wpide'), 'default' => 'date', 'options' => $orderby],
                    ['id' => 'order', 'type' => 'select', 'label' => __('Direction', 'wpide'), 'default' => 'DESC', 'options' => ['DESC' => __('Descending', 'wpide'), 'ASC' => __('Ascending', 'wpide')]],
                    $this->text('meta_key', __('Order by custom field', 'wpide'), '', __('Meta key used when ordering by custom field value.', 'wpide')),
                    $this->toggle('nopaging', __('Return all posts', 'wpide'), false, __('Disable pagination and ignore the values below.', 'wpide')),
                    $this->text('posts_per_page', __('Posts per page', 'wpide'), '10', __('Use -1 for all posts.', 'wpide'), '10'),
                    $this->text('paged', __('Page number', 'wpide'), '', __('Leave empty to follow the current page (get_query_var( \'paged\' )).', 'wpide')),
                    $this->text('offset', __('Offset', 'wpide'), '', __('Number of posts to skip. Breaks pagination when used.', 'wpide')),
                    $this->toggle('ignore_sticky_posts', __('Ignore sticky posts', 'wpide'), true),
                ],
            ],
            'taxonomy' => [
                'label' => __('Taxonomies', 'wpide'),
                'fields' => [
                    [
                        'id' => 'tax_query',
                        'type' => 'repeater',
                        'label' => __('Taxonomy conditions', 'wpide'),
                        'default' => [],
                        'fields' => [
                            $this->text('taxonomy', __('Taxonomy', 'wpide'), '', '', 'category'),
                            ['id' => 'field', 'type' => 'select', 'label' => __('Match terms by', 'wpide'), 'default' => 'term_id', 'options' => ['term_id' => __('Term ID', 'wpide'), 'slug' => __('Slug', 'wpide'), 'name' => __('Name', 'wpide'), 'term_taxonomy_id' => __('Term taxonomy ID', 'wpide')]],
                            $this->text('terms', __('Terms', 'wpide'), '', __('Comma separated values.', 'wpide'), 'news, events'),
                            $this->toggle('include_children', __('Include child terms', 'wpide'), true),
                            ['id' => 'operator', 'type' => 'select', 'label' => __('Operator', 'wpide'), 'default' => 'IN', 'options' => ['IN' => 'IN', 'NOT IN' => 'NOT IN', 'AND' => 'AND', 'EXISTS' => 'EXISTS', 'NOT EXISTS' => 'NOT EXISTS']],
                        ],
                    ],
                    ['id' => 'relation', 'type' => 'select', 'label' => __('Combine taxonomy conditions', 'wpide'), 'default' => 'AND', 'options' => $relation],
                ],
            ],
            'meta' => [
                'label' => __('Custom fields', 'wpide'),
                'fields' => [
                    [
                        'id' => 'meta_query',
                        'type' => 'repeater',
                        'label' => __('Custom field conditions', 'wpide'),
                        'default' => [],
                        'fields' => [
                            $this->text('key', __('Meta key', 'wpide'), '', '', 'price'),
                            $this->text('value', __('Value', 'wpide'), '', __('Comma separated for IN / BETWEEN comparisons.', 'wpide')),
                            ['id' => 'compare', 'type' => 'select', 'label' => __('Compare', 'wpide'), 'default' => '=', 'options' => $compare],
                            ['id' => 'meta_type', 'type' => 'select', 'label' => __('Type', 'wpide'), 'default' => 'CHAR', 'options' => $types],
                        ],
                    ],
                    ['id' => 'meta_relation', 'type' => 'select', 'label' => __('Combine custom field conditions', 'wpide'), 'default' => 'AND', 'options' => $relation],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $var = '$' . $this->varName($values['var_name'], 'custom_query');
        $argsVar = $var . '_args';
        $args = [];

        $list = function ($value, bool $numeric = false) {
            $items = $numeric ? $this->ids($value) : $this->csv($value);

            return $items ? $this->strList($items) : null;
        };

        $types = $this->csv($values['post_type']);

        if ($types) {
            $args['post_type'] = count($types) === 1 ? $this->str($types[0]) : $this->strList($types);
        }

        $statuses = $this->csv($values['post_status']);

        if ($statuses) {
            $args['post_status'] = count($statuses) === 1 ? $this->str($statuses[0]) : $this->strList($statuses);
        }

        foreach (['post__in', 'post__not_in', 'post_parent__in', 'post_parent__not_in', 'author__not_in'] as $key) {
            if ($value = $list($values[$key], true)) {
                $args[$key] = $value;
            }
        }

        if ($value = $list($values['post_name__in'])) {
            $args['post_name__in'] = $value;
        }

        if ($authors = $this->ids($values['author'])) {
            $args['author'] = $this->str(implode(',', $authors));
        }

        if (trim($values['author_name']) !== '') {
            $args['author_name'] = $this->str(trim($values['author_name']));
        }

        if (trim($values['s']) !== '') {
            $args['s'] = $this->str(trim($values['s']));
        }

        $args['orderby'] = $this->str($values['orderby']);
        $args['order'] = $this->str($values['order']);

        if (trim($values['meta_key']) !== '') {
            $args['meta_key'] = $this->str(trim($values['meta_key']));
        }

        if ($this->on($values['nopaging'])) {
            $args['nopaging'] = 'true';
        } else {
            if (is_numeric($values['posts_per_page'])) {
                $args['posts_per_page'] = (string) (int) $values['posts_per_page'];
            }

            $args['paged'] = is_numeric($values['paged']) ? (string) max(1, (int) $values['paged']) : "max( 1, (int) get_query_var( 'paged' ) )";

            if (is_numeric($values['offset']) && (int) $values['offset'] > 0) {
                $args['offset'] = (string) (int) $values['offset'];
            }
        }

        if ($this->on($values['ignore_sticky_posts'])) {
            $args['ignore_sticky_posts'] = 'true';
        }

        if ($tax = $this->taxQuery($values)) {
            $args['tax_query'] = $tax;
        }

        if ($meta = $this->metaQuery($values)) {
            $args['meta_query'] = $meta;
        }

        $code = "// Custom posts query.\n" . $argsVar . ' = ' . $this->phpArray($args) . ";\n\n" . $var . ' = new WP_Query( ' . $argsVar . " );\n";

        if ($this->on($values['loop'])) {
            $code .= "\nif ( {$var}->have_posts() ) {\n"
                . "    echo '<ul class=\"custom-query\">';\n\n"
                . "    while ( {$var}->have_posts() ) {\n"
                . "        {$var}->the_post();\n"
                . "        printf( '<li><a href=\"%s\">%s</a></li>', esc_url( get_permalink() ), esc_html( get_the_title() ) );\n"
                . "    }\n\n"
                . "    echo '</ul>';\n"
                . "} else {\n"
                . "    echo '<p>' . esc_html__( 'No posts found.' ) . '</p>';\n"
                . "}\n\n"
                . "// Restore the global post data.\n"
                . "wp_reset_postdata();\n";
        }

        return $code;
    }

    protected function taxQuery(array $values): array
    {
        $clauses = [];

        foreach ($values['tax_query'] as $row) {
            $taxonomy = $this->key($row['taxonomy'], '', 32);

            if ($taxonomy === '') {
                continue;
            }

            $clause = ['taxonomy' => $this->str($taxonomy), 'field' => $this->str($row['field'])];

            if (!in_array($row['operator'], ['EXISTS', 'NOT EXISTS'], true)) {
                $terms = in_array($row['field'], ['term_id', 'term_taxonomy_id'], true) ? $this->ids($row['terms']) : $this->csv($row['terms']);
                $clause['terms'] = $this->strList($terms);
            }

            if (!$this->on($row['include_children'])) {
                $clause['include_children'] = 'false';
            }

            $clause['operator'] = $this->str($row['operator']);
            $clauses[] = $clause;
        }

        if (count($clauses) > 1) {
            $clauses = array_merge(['relation' => $this->str($values['relation'])], $clauses);
        }

        return $clauses;
    }

    protected function metaQuery(array $values): array
    {
        $clauses = [];

        foreach ($values['meta_query'] as $row) {
            if (trim($row['key']) === '') {
                continue;
            }

            $clause = ['key' => $this->str(trim($row['key']))];

            if (!in_array($row['compare'], ['EXISTS', 'NOT EXISTS'], true)) {
                $multiple = in_array($row['compare'], ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true);
                $clause['value'] = $multiple ? $this->strList($this->csv($row['value'])) : $this->str($row['value']);
            }

            $clause['compare'] = $this->str($row['compare']);

            if ($row['meta_type'] !== 'CHAR') {
                $clause['type'] = $this->str($row['meta_type']);
            }

            $clauses[] = $clause;
        }

        if (count($clauses) > 1) {
            $clauses = array_merge(['relation' => $this->str($values['meta_relation'])], $clauses);
        }

        return $clauses;
    }
}
