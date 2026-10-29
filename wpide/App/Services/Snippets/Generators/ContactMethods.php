<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Adds extra contact fields to user profiles.
 */
class ContactMethods extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'contact-methods';
    }

    public function info(): array
    {
        return [
            'title' => __('Contact Methods', 'wpide'),
            'description' => __('Add extra contact fields (social profiles, phone numbers...) to every user profile screen.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['users', 'profile', 'contact', 'social'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_user_contact_methods'),
                    $this->textDomainField(),
                    $this->toggle('keep_existing', __('Keep existing methods', 'wpide'), true, __('Choose No to replace the contact methods added by WordPress, the theme or other plugins.', 'wpide')),
                ],
            ],
            'methods' => [
                'label' => __('Contact methods', 'wpide'),
                'fields' => [
                    [
                        'id' => 'contact_methods',
                        'type' => 'repeater',
                        'label' => __('Fields', 'wpide'),
                        'description' => __('Each entry adds a text field under "Contact Info" on the profile screen. Read the value with get_user_meta( $user_id, $slug, true ).', 'wpide'),
                        'default' => [
                            ['contact_method_name' => 'linkedin', 'contact_method_description' => 'LinkedIn profile URL'],
                            ['contact_method_name' => 'phone', 'contact_method_description' => 'Phone number'],
                        ],
                        'fields' => [
                            $this->text('contact_method_name', __('Slug', 'wpide'), '', __('Meta key used to store the value (lowercase, no spaces).', 'wpide'), 'linkedin'),
                            $this->text('contact_method_description', __('Label', 'wpide'), '', __('Label shown next to the field.', 'wpide'), 'LinkedIn profile URL'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_user_contact_methods');
        $domain = $values['text_domain'];
        $body = $this->on($values['keep_existing']) ? '' : "\$methods = array();\n\n";

        foreach ($values['contact_methods'] as $row) {
            $slug = $this->key($row['contact_method_name'], '', 0, 'a-z0-9_\-');

            if ($slug === '') {
                continue;
            }

            $label = $row['contact_method_description'] !== '' ? $row['contact_method_description'] : $row['contact_method_name'];
            $body .= '$methods[' . $this->str($slug) . '] = ' . $this->tr($label, $domain) . ";\n";
        }

        $body .= "\nreturn \$methods;";

        return "// Adds custom contact fields to user profiles.\n"
            . $this->guardedFunction($function, '$methods', $body)
            . "add_filter( 'user_contactmethods', " . $this->str($function) . " );\n";
    }
}
