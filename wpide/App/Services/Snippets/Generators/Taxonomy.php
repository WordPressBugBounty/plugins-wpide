<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Registers a custom taxonomy (categories or tags) for one or more post types.
 */
class Taxonomy extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'taxonomy';
    }

    public function info(): array
    {
        return [
            'title' => __('Taxonomy', 'wpide'),
            'description' => __('Create a custom way to group content, either category-like (hierarchical) or tag-like, and attach it to any post type.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['taxonomy', 'categories', 'tags', 'register_taxonomy'],
        ];
    }

    /**
     * Label field id => [labels array key, field label, automatic value pattern (%1$s singular, %2$s plural)].
     */
    protected function labelMap(): array
    {
        return [
            'label_menu_name' => ['menu_name', __('Menu name', 'wpide'), '%2$s'],
            'label_all_items' => ['all_items', __('All items', 'wpide'), 'All %2$s'],
            'label_parent_item' => ['parent_item', __('Parent item', 'wpide'), 'Parent %1$s'],
            'label_parent_item_colon' => ['parent_item_colon', __('Parent item (with colon)', 'wpide'), 'Parent %1$s:'],
            'label_new_item' => ['new_item_name', __('New item name', 'wpide'), 'New %1$s Name'],
            'label_add_new_item' => ['add_new_item', __('Add new item', 'wpide'), 'Add New %1$s'],
            'label_edit_item' => ['edit_item', __('Edit item', 'wpide'), 'Edit %1$s'],
            'label_update_item' => ['update_item', __('Update item', 'wpide'), 'Update %1$s'],
            'label_view_item' => ['view_item', __('View item', 'wpide'), 'View %1$s'],
            'label_separate_items_with_commas' => ['separate_items_with_commas', __('Separate items with commas', 'wpide'), 'Separate %2$s with commas'],
            'label_add_or_remove_items' => ['add_or_remove_items', __('Add or remove items', 'wpide'), 'Add or remove %2$s'],
            'label_choose_from_most_used' => ['choose_from_most_used', __('Choose from most used', 'wpide'), 'Choose from the most used %2$s'],
            'label_popular_items' => ['popular_items', __('Popular items', 'wpide'), 'Popular %2$s'],
            'label_search_items' => ['search_items', __('Search items', 'wpide'), 'Search %2$s'],
            'label_not_found' => ['not_found', __('Not found', 'wpide'), 'No %2$s found'],
            'label_no_items' => ['no_terms', __('No items', 'wpide'), 'No %2$s'],
            'label_items_list' => ['items_list', __('Items list', 'wpide'), '%2$s list'],
            'label_items_list_navigation' => ['items_list_navigation', __('Items list navigation', 'wpide'), '%2$s list navigation'],
            'label_back_to_items' => ['back_to_items', __('Back to items', 'wpide'), '&larr; Back to %2$s'],
        ];
    }

    public function fields(): array
    {
        $labels = [];

        foreach ($this->labelMap() as $id => $label) {
            $labels[] = $this->text($id, $label[1], '', '', sprintf($label[2], 'Item', 'Items'));
        }

        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_register_taxonomy'),
                    $this->textDomainField(),
                    $this->text('taxonomy', __('Taxonomy key', 'wpide'), 'genre', __('Up to 32 lowercase characters, dashes or underscores.', 'wpide'), 'genre'),
                    $this->text('label', __('Name (singular)', 'wpide'), 'Genre', '', 'Genre'),
                    $this->text('label_count', __('Name (plural)', 'wpide'), 'Genres', '', 'Genres'),
                    $this->text('post_types', __('Post types', 'wpide'), 'post', __('Comma separated post type keys this taxonomy is attached to.', 'wpide'), 'post, page'),
                    $this->toggle('hierarchical', __('Hierarchical', 'wpide'), true, __('Yes behaves like categories (parents and children), No behaves like tags.', 'wpide')),
                ],
            ],
            'labels' => [
                'label' => __('Labels', 'wpide'),
                'fields' => array_merge([
                    ['id' => 'translate_labels', 'type' => 'checkbox', 'label' => __('Translate labels', 'wpide'), 'description' => __('Wrap labels in translation functions. Empty label fields are filled automatically from the singular and plural names.', 'wpide'), 'default' => true],
                ], $labels),
            ],
            'visibility' => [
                'label' => __('Visibility', 'wpide'),
                'fields' => [
                    $this->toggle('public', __('Public', 'wpide'), true),
                    $this->toggle('publicly_queryable', __('Publicly queryable', 'wpide'), true, __('Allow term archives on the frontend.', 'wpide')),
                    $this->toggle('show_ui', __('Show admin UI', 'wpide'), true),
                    $this->toggle('show_admin_column', __('Show column in posts list', 'wpide'), true),
                    $this->toggle('show_in_quick_edit', __('Show in Quick Edit', 'wpide'), true),
                    $this->toggle('show_tagcloud', __('Available in the Tag Cloud widget', 'wpide'), true),
                    $this->toggle('show_in_nav_menus', __('Available in navigation menus', 'wpide'), true),
                ],
            ],
            'permalinks' => [
                'label' => __('Permalinks', 'wpide'),
                'fields' => [
                    [
                        'id' => 'rewrite',
                        'type' => 'select',
                        'label' => __('Rewrite rules', 'wpide'),
                        'default' => 'true',
                        'options' => ['true' => __('Default (taxonomy key)', 'wpide'), 'custom' => __('Custom structure', 'wpide'), 'false' => __('No pretty permalinks', 'wpide')],
                    ],
                    $this->text('rewrite_slug', __('URL slug', 'wpide'), '', __('Custom structure only. Defaults to the taxonomy key.', 'wpide'), 'genre'),
                    $this->toggle('rewrite_with_front', __('Prefix with the permalink front base', 'wpide'), true),
                    $this->toggle('rewrite_hierarchical', __('Hierarchical URLs', 'wpide'), false, __('Include parent terms in the URL, e.g. /genre/fiction/thriller/.', 'wpide')),
                ],
            ],
            'capabilities' => [
                'label' => __('Capabilities', 'wpide'),
                'fields' => [
                    [
                        'id' => 'capabilities',
                        'type' => 'select',
                        'label' => __('Capabilities', 'wpide'),
                        'default' => 'true',
                        'options' => ['true' => __('Default capabilities', 'wpide'), 'custom' => __('Custom capabilities', 'wpide')],
                    ],
                    $this->text('manage_terms', __('Manage terms', 'wpide'), 'manage_categories'),
                    $this->text('edit_terms', __('Edit terms', 'wpide'), 'manage_categories'),
                    $this->text('delete_terms', __('Delete terms', 'wpide'), 'manage_categories'),
                    $this->text('assign_terms', __('Assign terms', 'wpide'), 'edit_posts'),
                ],
            ],
            'meta' => [
                'label' => __('Meta', 'wpide'),
                'fields' => [
                    $this->metaFieldsRepeater(__('Optional term meta registered for this taxonomy, e.g. to read it through the REST API.', 'wpide')),
                ],
            ],
            'rest' => [
                'label' => __('REST API', 'wpide'),
                'fields' => [
                    $this->toggle('show_in_rest', __('Show in REST API', 'wpide'), true, __('Required to show the taxonomy panel in the block editor.', 'wpide')),
                    $this->text('rest_base', __('REST base', 'wpide'), '', __('Defaults to the taxonomy key.', 'wpide')),
                    $this->text('rest_controller_class', __('REST controller class', 'wpide'), '', __('Defaults to WP_REST_Terms_Controller.', 'wpide')),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_register_taxonomy');
        $domain = $values['text_domain'];
        $key = $this->key($values['taxonomy'], 'genre', 32);
        $singular = $values['label'] !== '' ? $values['label'] : ucfirst($key);
        $plural = $values['label_count'] !== '' ? $values['label_count'] : $singular;
        $translate = (bool) $values['translate_labels'];
        $string = function ($value, string $context = '') use ($domain, $translate) {
            if (!$translate) {
                return $this->str($value);
            }

            return $context !== '' ? '_x( ' . $this->str($value) . ', ' . $this->str($context) . ', ' . $this->str($domain) . ' )' : $this->tr($value, $domain);
        };

        $labels = [
            'name' => $string($plural, 'Taxonomy general name'),
            'singular_name' => $string($singular, 'Taxonomy singular name'),
        ];

        foreach ($this->labelMap() as $id => $label) {
            $text = trim((string) ($values[$id] ?? ''));
            $labels[$label[0]] = $string($text !== '' ? $text : sprintf($label[2], $singular, $plural));
        }

        $postTypes = array_map(function ($type) {
            return $this->key($type, 'post', 20);
        }, $this->csv($values['post_types']));

        $args = ['labels' => '$labels'];

        foreach (['hierarchical', 'public', 'publicly_queryable', 'show_ui', 'show_admin_column', 'show_in_quick_edit', 'show_in_nav_menus', 'show_tagcloud'] as $flag) {
            $args[$flag] = $this->bool($this->on($values[$flag]));
        }

        $before = '';

        if ($values['rewrite'] === 'false') {
            $args['rewrite'] = 'false';
        } elseif ($values['rewrite'] === 'custom') {
            $before .= '$rewrite = ' . $this->phpArray([
                'slug' => $this->str($this->key($values['rewrite_slug'], $key, 0, 'a-z0-9_\-\/')),
                'with_front' => $this->bool($this->on($values['rewrite_with_front'])),
                'hierarchical' => $this->bool($this->on($values['rewrite_hierarchical'])),
            ]) . ";\n\n";
            $args['rewrite'] = '$rewrite';
        }

        if ($values['capabilities'] === 'custom') {
            $caps = [];

            foreach (['manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'edit_posts'] as $cap => $fallback) {
                $caps[$cap] = $this->str(trim($values[$cap]) !== '' ? trim($values[$cap]) : $fallback);
            }

            $before .= '$capabilities = ' . $this->phpArray($caps) . ";\n\n";
            $args['capabilities'] = '$capabilities';
        }

        $args['show_in_rest'] = $this->bool($this->on($values['show_in_rest']));

        if (trim($values['rest_base']) !== '') {
            $args['rest_base'] = $this->str($this->key($values['rest_base'], $key, 0, 'a-z0-9_\-\/'));
        }

        if (trim($values['rest_controller_class']) !== '') {
            $args['rest_controller_class'] = $this->str(preg_replace('/[^A-Za-z0-9_\\\\]/', '', $values['rest_controller_class']));
        }

        $objects = count($postTypes) === 1 ? $this->str($postTypes[0]) : $this->strList($postTypes);

        $body = '$labels = ' . $this->phpArray($labels) . ";\n\n"
            . $before
            . '$args = ' . $this->phpArray($args) . ";\n\n"
            . 'register_taxonomy( ' . $this->str($key) . ', ' . $objects . ', $args );'
            . $this->metaRegistration('register_term_meta', $key, $values['meta_fields'], $domain, 'manage_categories');

        return "// Registers the \"{$key}\" taxonomy.\n"
            . $this->guardedFunction($function, '', $body)
            . "add_action( 'init', " . $this->str($function) . ", 0 );\n";
    }
}
