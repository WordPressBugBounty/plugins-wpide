<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Adds a custom menu (with optional sub items) to the WordPress toolbar.
 */
class AdminBarMenu extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'admin-bar';
    }

    public function info(): array
    {
        return [
            'title' => __('Admin Bar Menu', 'wpide'),
            'description' => __('Put your own links in the WordPress toolbar, with a top-level item and any number of child links.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['admin bar', 'toolbar', 'menu', 'links'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_toolbar_menu'),
                    [
                        'id' => 'priority',
                        'type' => 'select',
                        'label' => __('Placement', 'wpide'),
                        'description' => __('Where the new item appears in the toolbar.', 'wpide'),
                        'default' => '1100',
                        'options' => [
                            '1100' => __('End of the left side', 'wpide'),
                            '30' => __('Before the site name', 'wpide'),
                            '50' => __('After the site name', 'wpide'),
                            '70' => __('Before the "+ New" menu', 'wpide'),
                            '80' => __('After the "+ New" menu', 'wpide'),
                        ],
                    ],
                    $this->text('capability', __('Required capability', 'wpide'), 'edit_posts', __('Only users with this capability see the menu. Leave empty to show it to everyone who sees the toolbar.', 'wpide'), 'manage_options'),
                ],
            ],
            'menu' => [
                'label' => __('Menu items', 'wpide'),
                'fields' => [
                    $this->text('menu_id', __('Menu ID', 'wpide'), 'custom_menu_id', __('Unique identifier of the top-level node.', 'wpide'), 'custom_menu_id'),
                    $this->text('menu_title', __('Menu title', 'wpide'), 'Quick Links', __('Text shown in the toolbar.', 'wpide')),
                    $this->text('menu_href', __('Menu link', 'wpide'), '', __('URL opened when the top-level item is clicked. Leave empty for a plain label.', 'wpide'), 'https://example.com'),
                    $this->targetField('menu_target'),
                    [
                        'id' => 'submenu',
                        'type' => 'repeater',
                        'label' => __('Child links', 'wpide'),
                        'description' => __('Links listed in the dropdown under the top-level item.', 'wpide'),
                        'default' => [
                            ['submenu_title' => 'Site Health', 'submenu_href' => '/wp-admin/site-health.php', 'submenu_target' => ''],
                            ['submenu_title' => 'WordPress.org', 'submenu_href' => 'https://wordpress.org/', 'submenu_target' => '_blank'],
                        ],
                        'fields' => [
                            $this->text('submenu_title', __('Link title', 'wpide')),
                            $this->text('submenu_href', __('Link URL', 'wpide'), '', __('Absolute URL, or a path starting with / relative to the site.', 'wpide'), 'https://'),
                            $this->targetField('submenu_target'),
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function targetField(string $id): array
    {
        return [
            'id' => $id,
            'type' => 'select',
            'label' => __('Open in', 'wpide'),
            'default' => '',
            'options' => ['' => __('Same tab', 'wpide'), '_blank' => __('New tab', 'wpide')],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_toolbar_menu');
        $parent = $this->key($values['menu_id'], 'custom_menu_id');
        $body = '';

        if (trim($values['capability']) !== '') {
            $body .= "if ( ! current_user_can( " . $this->str(trim($values['capability'])) . " ) ) {\n    return;\n}\n\n";
        }

        $body .= '$wp_admin_bar->add_node( ' . $this->phpArray($this->node($parent, '', $values['menu_title'] !== '' ? $values['menu_title'] : $parent, $values['menu_href'], $values['menu_target'])) . " );\n";

        $count = 0;

        foreach ($values['submenu'] as $row) {
            if (trim($row['submenu_title']) === '') {
                continue;
            }

            $count++;
            $body .= "\n" . '$wp_admin_bar->add_node( ' . $this->phpArray($this->node($parent . '-' . $count, $parent, $row['submenu_title'], $row['submenu_href'], $row['submenu_target'])) . " );\n";
        }

        return "// Adds a custom menu to the WordPress toolbar.\n"
            . $this->guardedFunction($function, 'WP_Admin_Bar $wp_admin_bar', $body)
            . "add_action( 'admin_bar_menu', " . $this->str($function) . ', ' . (int) $values['priority'] . " );\n";
    }

    protected function node(string $id, string $parent, string $title, string $href, string $target): array
    {
        $node = ['id' => $this->str($id)];

        if ($parent !== '') {
            $node['parent'] = $this->str($parent);
        }

        $node['title'] = $this->str($title);
        $href = trim($href);

        if ($href !== '') {
            $node['href'] = $this->url($href);
        }

        if ($target !== '') {
            $node['meta'] = ['target' => $this->str($target), 'rel' => $this->str('noopener')];
        }

        return $node;
    }

    /**
     * Site-relative paths become admin_url()/home_url() calls so the menu works on any domain.
     */
    protected function url(string $href): string
    {
        if (strpos($href, '/') !== 0 || strpos($href, '//') === 0) {
            return $this->str($href);
        }

        if (strpos($href, '/wp-admin/') === 0) {
            return 'admin_url( ' . $this->str(substr($href, strlen('/wp-admin/'))) . ' )';
        }

        return 'home_url( ' . $this->str($href) . ' )';
    }
}
