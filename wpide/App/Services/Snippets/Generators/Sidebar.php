<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Registers widget areas (sidebars).
 */
class Sidebar extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'sidebar';
    }

    public function info(): array
    {
        return [
            'title' => __('Sidebar', 'wpide'),
            'description' => __('Register widget areas with their own markup, ready to be displayed with dynamic_sidebar().', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['sidebar', 'widget area', 'widgets', 'theme'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_register_sidebars'),
                    $this->textDomainField(),
                ],
            ],
            'sidebars' => [
                'label' => __('Sidebars', 'wpide'),
                'fields' => [
                    [
                        'id' => 'sidebars',
                        'type' => 'repeater',
                        'label' => __('Widget areas', 'wpide'),
                        'description' => __('Show an area in your theme with dynamic_sidebar( \'id\' ). %1$s and %2$s in the widget wrapper are replaced by the widget id and classes.', 'wpide'),
                        'default' => [
                            [
                                'sidebar_id' => 'custom-sidebar',
                                'sidebar_name' => 'Custom Sidebar',
                                'sidebar_description' => 'Widgets added here appear in the custom sidebar.',
                                'sidebar_css_class' => '',
                                'before_title' => '<h2 class="widget-title">',
                                'after_title' => '</h2>',
                                'before_widget' => '<section id="%1$s" class="widget %2$s">',
                                'after_widget' => '</section>',
                            ],
                        ],
                        'fields' => [
                            $this->text('sidebar_id', __('ID', 'wpide'), '', __('Unique lowercase identifier.', 'wpide'), 'custom-sidebar'),
                            $this->text('sidebar_name', __('Name', 'wpide'), '', '', 'Custom Sidebar'),
                            $this->text('sidebar_description', __('Description', 'wpide')),
                            $this->text('sidebar_css_class', __('CSS class', 'wpide'), '', __('Extra class added in the Widgets screen.', 'wpide')),
                            ['id' => 'before_title', 'type' => 'textarea', 'label' => __('Before title', 'wpide'), 'default' => '<h2 class="widget-title">'],
                            ['id' => 'after_title', 'type' => 'textarea', 'label' => __('After title', 'wpide'), 'default' => '</h2>'],
                            ['id' => 'before_widget', 'type' => 'textarea', 'label' => __('Before widget', 'wpide'), 'default' => '<section id="%1$s" class="widget %2$s">'],
                            ['id' => 'after_widget', 'type' => 'textarea', 'label' => __('After widget', 'wpide'), 'default' => '</section>'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_register_sidebars');
        $domain = $values['text_domain'];
        $calls = [];

        foreach ($values['sidebars'] as $index => $row) {
            $id = $this->key($row['sidebar_id'], $row['sidebar_name'] !== '' ? $this->key($row['sidebar_name'], '') : '');

            if ($id === '') {
                continue;
            }

            $args = [
                'id' => $this->str($id),
                'name' => $this->tr($row['sidebar_name'] !== '' ? $row['sidebar_name'] : $id, $domain),
            ];

            if ($row['sidebar_description'] !== '') {
                $args['description'] = $this->tr($row['sidebar_description'], $domain);
            }

            if ($row['sidebar_css_class'] !== '') {
                $args['class'] = $this->str($row['sidebar_css_class']);
            }

            foreach (['before_widget', 'after_widget', 'before_title', 'after_title'] as $wrapper) {
                $args[$wrapper] = $this->str($row[$wrapper]);
            }

            $calls[] = 'register_sidebar( ' . $this->phpArray($args) . ' );';
        }

        return "// Registers widget areas.\n"
            . $this->guardedFunction($function, '', $calls ? implode("\n\n", $calls) : '// Add at least one sidebar.')
            . "add_action( 'widgets_init', " . $this->str($function) . " );\n";
    }
}
