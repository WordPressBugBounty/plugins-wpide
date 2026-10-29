<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Service structured data with its provider and contact details.
 */
class SchemaService extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-service';
    }

    public function info(): array
    {
        return [
            'title' => __('Service Schema', 'wpide'),
            'description' => __('Describe a service you offer, the area you serve and the business providing it.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'service', 'local business', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'service' => [
                'label' => __('Service', 'wpide'),
                'fields' => [
                    $this->text('service_name', __('Service name', 'wpide'), '{title}', 'Website maintenance'),
                    $this->textarea('service_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('service_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('service_image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('service_type', __('Service type', 'wpide'), '', 'Web development', __('Kind of service, in plain words.', 'wpide')),
                    $this->text('area_served', __('Area served', 'wpide'), '', 'Chicago, IL', __('City, region or country. Comma separated for several.', 'wpide')),
                    $this->text('service_price', __('Starting price', 'wpide'), '', '99.00'),
                    $this->currencyField('service_currency'),
                ],
            ],
            'provider' => [
                'label' => __('Provider', 'wpide'),
                'fields' => [
                    $this->select('provider_type', __('Provider type', 'wpide'), [
                        'Organization' => __('Organization', 'wpide'),
                        'LocalBusiness' => __('Local business', 'wpide'),
                        'ProfessionalService' => __('Professional service', 'wpide'),
                        'Corporation' => __('Corporation', 'wpide'),
                        'Person' => __('Person', 'wpide'),
                    ], 'Organization'),
                    $this->text('provider_name', __('Provider name', 'wpide'), '{site_name}'),
                    $this->text('provider_url', __('Provider URL', 'wpide'), '{site_url}'),
                    $this->text('provider_logo', __('Logo URL', 'wpide'), '', 'https://example.com/logo.png'),
                    $this->text('contact_email', __('Email', 'wpide'), '', 'hello@example.com'),
                    $this->text('contact_telephone', __('Telephone', 'wpide'), '', '+1-555-555-0100'),
                    $this->text('contact_address', __('Address', 'wpide'), '', '123 Main St, Chicago, IL 60601'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $type = $values['provider_type'];
        $provider = $this->node($type, $values['provider_name'], [
            'url' => $values['provider_url'],
            ($type === 'Person' ? 'image' : 'logo') => $values['provider_logo'],
            'email' => $values['contact_email'],
            'telephone' => $values['contact_telephone'],
            'address' => $values['contact_address'],
        ]);

        $areas = array_map(function ($area) {
            return ['@type' => 'Place', 'name' => $area];
        }, $this->items($values['area_served']));

        return $this->jsonLd([
            '@type' => 'Service',
            'name' => $values['service_name'],
            'description' => $values['service_description'],
            'url' => $values['service_url'],
            'image' => $values['service_image'],
            'serviceType' => $values['service_type'],
            'areaServed' => $this->oneOrMany($areas),
            'provider' => $provider,
            'offers' => $values['service_price'] !== '' ? ['@type' => 'Offer', 'price' => $this->price($values['service_price']), 'priceCurrency' => $values['service_currency']] : [],
        ]);
    }
}
