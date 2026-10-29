<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Registers theme navigation menu locations.
 */
class Menu extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'menu';
    }

    public function info(): array
    {
        return [
            'title' => __('Navigation Menu', 'wpide'),
            'description' => __('Register menu locations so menus can be assigned to them under Appearance > Menus.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['menu', 'navigation', 'theme', 'location'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_register_menus'),
                    $this->textDomainField(),
                ],
            ],
            'menus' => [
                'label' => __('Menu locations', 'wpide'),
                'fields' => [
                    [
                        'id' => 'menus',
                        'type' => 'repeater',
                        'label' => __('Locations', 'wpide'),
                        'description' => __('Display a location in your theme with wp_nav_menu( array( \'theme_location\' => \'slug\' ) ).', 'wpide'),
                        'default' => [
                            ['menu_name' => 'primary-menu', 'menu_description' => 'Primary Menu'],
                            ['menu_name' => 'footer-menu', 'menu_description' => 'Footer Menu'],
                        ],
                        'fields' => [
                            $this->text('menu_name', __('Location slug', 'wpide'), '', __('Identifier used in the theme code.', 'wpide'), 'primary-menu'),
                            $this->text('menu_description', __('Location label', 'wpide'), '', __('Name shown in the admin.', 'wpide'), 'Primary Menu'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_register_menus');
        $locations = [];

        foreach ($values['menus'] as $row) {
            $slug = $this->key($row['menu_name'], '');

            if ($slug !== '') {
                $locations[$slug] = $this->tr($row['menu_description'] !== '' ? $row['menu_description'] : $row['menu_name'], $values['text_domain']);
            }
        }

        $body = $locations ? 'register_nav_menus( ' . $this->phpArray($locations) . ' );' : '// Add at least one menu location.';

        return "// Registers navigation menu locations.\n"
            . $this->guardedFunction($function, '', $body)
            . "add_action( 'after_setup_theme', " . $this->str($function) . " );\n";
    }
}
