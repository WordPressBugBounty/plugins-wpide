<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Registers a custom post type with full control over labels, visibility, permalinks, capabilities and REST.
 */
class PostType extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'post-type';
    }

    public function info(): array
    {
        return [
            'title' => __('Post Type', 'wpide'),
            'description' => __('Create a custom content type (books, events, team members...) with its labels, admin menu, archive and permalink settings.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['post type', 'cpt', 'content', 'register_post_type'],
        ];
    }

    /**
     * Label field id => [labels array key, field label, automatic value pattern (%1$s singular, %2$s plural)].
     */
    protected function labelMap(): array
    {
        return [
            'label_menu_name' => ['menu_name', __('Menu name', 'wpide'), '%2$s'],
            'label_admin_bar_name' => ['name_admin_bar', __('Admin bar name', 'wpide'), '%1$s'],
            'label_archives' => ['archives', __('Archives', 'wpide'), '%1$s Archives'],
            'label_attributes' => ['attributes', __('Attributes', 'wpide'), '%1$s Attributes'],
            'label_parent_item' => ['parent_item_colon', __('Parent item', 'wpide'), 'Parent %1$s:'],
            'label_all_items' => ['all_items', __('All items', 'wpide'), 'All %2$s'],
            'label_add_new_item' => ['add_new_item', __('Add new item', 'wpide'), 'Add New %1$s'],
            'label_add_new' => ['add_new', __('Add new', 'wpide'), 'Add New'],
            'label_new_item' => ['new_item', __('New item', 'wpide'), 'New %1$s'],
            'label_edit_item' => ['edit_item', __('Edit item', 'wpide'), 'Edit %1$s'],
            'label_update_item' => ['update_item', __('Update item', 'wpide'), 'Update %1$s'],
            'label_view_item' => ['view_item', __('View item', 'wpide'), 'View %1$s'],
            'label_view_items' => ['view_items', __('View items', 'wpide'), 'View %2$s'],
            'label_search_item' => ['search_items', __('Search items', 'wpide'), 'Search %2$s'],
            'label_not_found' => ['not_found', __('Not found', 'wpide'), 'No %2$s found'],
            'label_not_found_in_trash' => ['not_found_in_trash', __('Not found in Trash', 'wpide'), 'No %2$s found in Trash'],
            'label_featured_image' => ['featured_image', __('Featured image', 'wpide'), 'Featured Image'],
            'label_set_featured_image' => ['set_featured_image', __('Set featured image', 'wpide'), 'Set featured image'],
            'label_remove_featured_image' => ['remove_featured_image', __('Remove featured image', 'wpide'), 'Remove featured image'],
            'label_use_as_featured_image' => ['use_featured_image', __('Use as featured image', 'wpide'), 'Use as featured image'],
            'label_insert_into_item' => ['insert_into_item', __('Insert into item', 'wpide'), 'Insert into %1$s'],
            'label_uploaded_to_this_item' => ['uploaded_to_this_item', __('Uploaded to this item', 'wpide'), 'Uploaded to this %1$s'],
            'label_items_list' => ['items_list', __('Items list', 'wpide'), '%2$s list'],
            'label_items_list_navigation' => ['items_list_navigation', __('Items list navigation', 'wpide'), '%2$s list navigation'],
            'label_filter_items_list' => ['filter_items_list', __('Filter items list', 'wpide'), 'Filter %2$s list'],
        ];
    }

    public function fields(): array
    {
        $labels = [];

        foreach ($this->labelMap() as $id => $label) {
            $labels[] = $this->text($id, $label[1], '', '', sprintf($label[2], 'Item', 'Items'));
        }

        $supports = [
            'title' => __('Title', 'wpide'),
            'editor' => __('Content editor', 'wpide'),
            'excerpt' => __('Excerpt', 'wpide'),
            'author' => __('Author', 'wpide'),
            'thumbnail' => __('Featured image', 'wpide'),
            'comments' => __('Comments', 'wpide'),
            'trackbacks' => __('Trackbacks', 'wpide'),
            'revisions' => __('Revisions', 'wpide'),
            'custom-fields' => __('Custom fields', 'wpide'),
            'page-attributes' => __('Page attributes (order, parent)', 'wpide'),
            'post-formats' => __('Post formats', 'wpide'),
        ];

        $positions = [
            '5' => __('5 - below Posts', 'wpide'),
            '10' => __('10 - below Media', 'wpide'),
            '15' => __('15 - below Links', 'wpide'),
            '20' => __('20 - below Pages', 'wpide'),
            '25' => __('25 - below Comments', 'wpide'),
            '60' => __('60 - below first separator', 'wpide'),
            '65' => __('65 - below Plugins', 'wpide'),
            '70' => __('70 - below Users', 'wpide'),
            '75' => __('75 - below Tools', 'wpide'),
            '80' => __('80 - below Settings', 'wpide'),
            '100' => __('100 - below second separator', 'wpide'),
        ];

        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_register_post_type'),
                    $this->textDomainField(),
                    $this->text('post_type', __('Post type key', 'wpide'), 'book', __('Up to 20 lowercase characters, dashes or underscores. Must not clash with existing post types.', 'wpide'), 'book'),
                    $this->text('label', __('Name (singular)', 'wpide'), 'Book', '', 'Book'),
                    $this->text('label_count', __('Name (plural)', 'wpide'), 'Books', '', 'Books'),
                    $this->text('description', __('Description', 'wpide'), '', __('Short summary of what this content type is for.', 'wpide')),
                    $this->text('taxonomies', __('Taxonomies', 'wpide'), '', __('Comma separated taxonomies to attach, e.g. category, post_tag.', 'wpide'), 'category, post_tag'),
                    $this->toggle('hierarchical', __('Hierarchical', 'wpide'), false, __('Allow parent/child entries like pages.', 'wpide')),
                ],
            ],
            'labels' => [
                'label' => __('Labels', 'wpide'),
                'fields' => array_merge([
                    ['id' => 'translate_labels', 'type' => 'checkbox', 'label' => __('Translate labels', 'wpide'), 'description' => __('Wrap labels in translation functions. Empty label fields are filled automatically from the singular and plural names.', 'wpide'), 'default' => true],
                ], $labels),
            ],
            'options' => [
                'label' => __('Options', 'wpide'),
                'fields' => [
                    ['id' => 'supports', 'type' => 'multiselect', 'label' => __('Supports', 'wpide'), 'description' => __('Editing features available for this post type.', 'wpide'), 'default' => ['title', 'editor', 'thumbnail', 'excerpt'], 'options' => $supports],
                    $this->toggle('exclude_from_search', __('Exclude from search', 'wpide'), false),
                    $this->toggle('can_export', __('Can be exported', 'wpide'), true, __('Include entries in Tools > Export.', 'wpide')),
                    [
                        'id' => 'has_archive',
                        'type' => 'select',
                        'label' => __('Archive page', 'wpide'),
                        'default' => 'true',
                        'options' => ['true' => __('Yes, at the post type key', 'wpide'), 'custom' => __('Yes, at a custom slug', 'wpide'), 'false' => __('No archive', 'wpide')],
                    ],
                    $this->text('custom_archive_slug', __('Archive slug', 'wpide'), '', __('Used when the archive page uses a custom slug.', 'wpide'), 'library'),
                ],
            ],
            'visibility' => [
                'label' => __('Visibility', 'wpide'),
                'fields' => [
                    $this->toggle('public', __('Public', 'wpide'), true, __('Visible to visitors and authors. Sets the defaults of the options below.', 'wpide')),
                    $this->toggle('show_ui', __('Show admin UI', 'wpide'), true),
                    $this->toggle('show_in_menu', __('Show in admin menu', 'wpide'), true),
                    ['id' => 'menu_position', 'type' => 'select', 'label' => __('Menu position', 'wpide'), 'default' => '5', 'options' => $positions],
                    $this->text('menu_icon', __('Menu icon', 'wpide'), 'dashicons-book', __('Dashicons class (e.g. dashicons-book), image URL or base64 SVG data URI.', 'wpide'), 'dashicons-admin-post'),
                    $this->toggle('show_in_admin_bar', __('Show in admin bar', 'wpide'), true),
                    $this->toggle('show_in_nav_menus', __('Available in navigation menus', 'wpide'), true),
                    $this->toggle('publicly_queryable', __('Publicly queryable', 'wpide'), true, __('Allow frontend queries and single views.', 'wpide')),
                ],
            ],
            'query' => [
                'label' => __('Query', 'wpide'),
                'fields' => [
                    [
                        'id' => 'query_var',
                        'type' => 'select',
                        'label' => __('Query variable', 'wpide'),
                        'default' => 'true',
                        'options' => ['true' => __('Default (post type key)', 'wpide'), 'custom' => __('Custom variable', 'wpide'), 'false' => __('Disabled', 'wpide')],
                    ],
                    $this->text('query_var_custom', __('Custom query variable', 'wpide'), '', __('Used when the query variable is set to custom.', 'wpide')),
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
                        'options' => ['true' => __('Default (post type key)', 'wpide'), 'custom' => __('Custom structure', 'wpide'), 'false' => __('No pretty permalinks', 'wpide')],
                    ],
                    $this->text('rewrite_slug', __('URL slug', 'wpide'), '', __('Custom structure only. Defaults to the post type key.', 'wpide'), 'books'),
                    $this->toggle('with_front', __('Prefix with the permalink front base', 'wpide'), true),
                    $this->toggle('pages', __('Paginated archive URLs', 'wpide'), true),
                    $this->toggle('feeds', __('Feed URLs', 'wpide'), true),
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
                        'options' => ['true' => __('Inherit from a base type', 'wpide'), 'custom' => __('Custom capabilities', 'wpide')],
                    ],
                    ['id' => 'capability_type', 'type' => 'select', 'label' => __('Base type', 'wpide'), 'default' => 'post', 'options' => ['post' => __('Posts', 'wpide'), 'page' => __('Pages', 'wpide')]],
                    $this->text('read_post', __('Read post', 'wpide'), 'read_post'),
                    $this->text('read_private_posts', __('Read private posts', 'wpide'), 'read_private_posts'),
                    $this->text('publish_posts', __('Publish posts', 'wpide'), 'publish_posts'),
                    $this->text('delete_post', __('Delete post', 'wpide'), 'delete_post'),
                    $this->text('edit_post', __('Edit post', 'wpide'), 'edit_post'),
                    $this->text('edit_posts', __('Edit posts', 'wpide'), 'edit_posts'),
                    $this->text('edit_others_posts', __('Edit others\' posts', 'wpide'), 'edit_others_posts'),
                ],
            ],
            'meta' => [
                'label' => __('Meta', 'wpide'),
                'fields' => [
                    $this->metaFieldsRepeater(__('Optional custom fields registered for this post type, e.g. to use them in the block editor or the REST API.', 'wpide')),
                ],
            ],
            'rest' => [
                'label' => __('REST API', 'wpide'),
                'fields' => [
                    $this->toggle('show_in_rest', __('Show in REST API', 'wpide'), true, __('Required for the block editor.', 'wpide')),
                    $this->text('rest_base', __('REST base', 'wpide'), '', __('Defaults to the post type key.', 'wpide')),
                    $this->text('rest_controller_class', __('REST controller class', 'wpide'), '', __('Defaults to WP_REST_Posts_Controller.', 'wpide')),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_register_post_type');
        $domain = $values['text_domain'];
        $key = $this->key($values['post_type'], 'book', 20);
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
            'name' => $string($plural, 'Post type general name'),
            'singular_name' => $string($singular, 'Post type singular name'),
        ];

        foreach ($this->labelMap() as $id => $label) {
            $text = trim((string) ($values[$id] ?? ''));
            $labels[$label[0]] = $string($text !== '' ? $text : sprintf($label[2], $singular, $plural));
        }

        $args = [
            'label' => $string($singular),
            'labels' => '$labels',
        ];

        if ($values['description'] !== '') {
            $args['description'] = $string($values['description']);
        }

        $args['supports'] = $values['supports'] ? $this->strList($values['supports']) : 'false';

        if ($taxonomies = $this->csv($values['taxonomies'])) {
            $args['taxonomies'] = $this->strList(array_map(function ($taxonomy) {
                return $this->key($taxonomy, 'category', 32);
            }, $taxonomies));
        }

        foreach (['hierarchical', 'public', 'show_ui', 'show_in_menu'] as $flag) {
            $args[$flag] = $this->bool($this->on($values[$flag]));
        }

        $args['menu_position'] = (string) (int) $values['menu_position'];

        if (trim($values['menu_icon']) !== '') {
            $args['menu_icon'] = $this->str(trim($values['menu_icon']));
        }

        foreach (['show_in_admin_bar', 'show_in_nav_menus', 'can_export', 'exclude_from_search', 'publicly_queryable'] as $flag) {
            $args[$flag] = $this->bool($this->on($values[$flag]));
        }

        if ($values['has_archive'] === 'custom' && trim($values['custom_archive_slug']) !== '') {
            $args['has_archive'] = $this->str($this->key($values['custom_archive_slug'], $key, 0, 'a-z0-9_\-\/'));
        } else {
            $args['has_archive'] = $this->bool($values['has_archive'] !== 'false');
        }

        if ($values['query_var'] === 'custom' && trim($values['query_var_custom']) !== '') {
            $args['query_var'] = $this->str($this->key($values['query_var_custom'], $key));
        } else {
            $args['query_var'] = $this->bool($values['query_var'] !== 'false');
        }

        $before = '';

        if ($values['rewrite'] === 'false') {
            $args['rewrite'] = 'false';
        } elseif ($values['rewrite'] === 'custom') {
            $before .= '$rewrite = ' . $this->phpArray([
                'slug' => $this->str($this->key($values['rewrite_slug'], $key, 0, 'a-z0-9_\-\/')),
                'with_front' => $this->bool($this->on($values['with_front'])),
                'pages' => $this->bool($this->on($values['pages'])),
                'feeds' => $this->bool($this->on($values['feeds'])),
            ]) . ";\n\n";
            $args['rewrite'] = '$rewrite';
        }

        if ($values['capabilities'] === 'custom') {
            $caps = [];

            foreach (['read_post', 'read_private_posts', 'publish_posts', 'delete_post', 'edit_post', 'edit_posts', 'edit_others_posts'] as $cap) {
                $caps[$cap] = $this->str(trim($values[$cap]) !== '' ? trim($values[$cap]) : $cap);
            }

            $before .= '$capabilities = ' . $this->phpArray($caps) . ";\n\n";
            $args['capabilities'] = '$capabilities';
            $args['map_meta_cap'] = 'true';
        } else {
            $args['capability_type'] = $this->str($values['capability_type'] === 'page' ? 'page' : 'post');
        }

        $args['show_in_rest'] = $this->bool($this->on($values['show_in_rest']));

        if (trim($values['rest_base']) !== '') {
            $args['rest_base'] = $this->str($this->key($values['rest_base'], $key, 0, 'a-z0-9_\-\/'));
        }

        if (trim($values['rest_controller_class']) !== '') {
            $args['rest_controller_class'] = $this->str(preg_replace('/[^A-Za-z0-9_\\\\]/', '', $values['rest_controller_class']));
        }

        $body = '$labels = ' . $this->phpArray($labels) . ";\n\n"
            . $before
            . '$args = ' . $this->phpArray($args) . ";\n\n"
            . 'register_post_type( ' . $this->str($key) . ', $args );'
            . $this->metaRegistration('register_post_meta', $key, $values['meta_fields'], $domain, 'edit_posts');

        return "// Registers the \"{$key}\" post type.\n"
            . $this->guardedFunction($function, '', $body)
            . "add_action( 'init', " . $this->str($function) . ", 0 );\n";
    }
}
