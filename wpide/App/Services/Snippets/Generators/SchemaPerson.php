<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Person structured data.
 */
class SchemaPerson extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-person';
    }

    public function info(): array
    {
        return [
            'title' => __('Person Schema', 'wpide'),
            'description' => __('Identify a person (author, founder, expert) with their job, employer, contact details and social profiles.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'person', 'author', 'profile', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'person' => [
                'label' => __('Person', 'wpide'),
                'fields' => [
                    $this->text('name', __('Name', 'wpide'), '{author_name}', 'Jane Doe'),
                    $this->textarea('description', __('Bio', 'wpide'), '{author_bio}'),
                    $this->text('url', __('Website / profile URL', 'wpide'), '{author_url}'),
                    $this->text('image_url', __('Photo URL', 'wpide'), '', 'https://example.com/photo.jpg'),
                    $this->text('job_title', __('Job title', 'wpide'), '', 'Editor in chief'),
                    $this->text('works_for', __('Works for', 'wpide'), '', 'Example Media'),
                ],
            ],
            'contact' => [
                'label' => __('Contact & profiles', 'wpide'),
                'fields' => [
                    $this->text('email', __('Email', 'wpide'), '', 'jane@example.com'),
                    $this->text('telephone', __('Telephone', 'wpide'), '', '+1-555-555-0100'),
                    $this->text('address_city', __('City', 'wpide'), '', 'Boston'),
                    $this->text('address_country', __('Country code', 'wpide'), '', 'US'),
                    $this->textarea('same_as', __('Social profiles', 'wpide'), '', "https://www.linkedin.com/in/username\nhttps://github.com/username", __('One URL per line, or comma separated.', 'wpide')),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        return $this->jsonLd([
            '@type' => 'Person',
            'name' => $values['name'],
            'description' => $values['description'],
            'url' => $values['url'],
            'image' => $values['image_url'],
            'jobTitle' => $values['job_title'],
            'worksFor' => $this->node('Organization', $values['works_for']),
            'email' => $values['email'],
            'telephone' => $values['telephone'],
            'address' => $this->postalAddress(['city' => $values['address_city'], 'country' => $values['address_country']]),
            'sameAs' => $this->items($values['same_as']),
        ]);
    }
}
