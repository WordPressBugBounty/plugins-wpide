<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * SoftwareApplication structured data.
 */
class SchemaSoftware extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-software';
    }

    public function info(): array
    {
        return [
            'title' => __('Software Schema', 'wpide'),
            'description' => __('Describe an app, plugin or program: category, platform, version, price and rating.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'software', 'app', 'plugin', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        $categories = [
            'BusinessApplication' => __('Business', 'wpide'),
            'CommunicationApplication' => __('Communication', 'wpide'),
            'DesignApplication' => __('Design', 'wpide'),
            'DeveloperApplication' => __('Developer tools', 'wpide'),
            'EducationalApplication' => __('Education', 'wpide'),
            'EntertainmentApplication' => __('Entertainment', 'wpide'),
            'FinanceApplication' => __('Finance', 'wpide'),
            'GameApplication' => __('Game', 'wpide'),
            'HealthApplication' => __('Health', 'wpide'),
            'LifestyleApplication' => __('Lifestyle', 'wpide'),
            'MultimediaApplication' => __('Multimedia', 'wpide'),
            'ReferenceApplication' => __('Reference', 'wpide'),
            'SecurityApplication' => __('Security', 'wpide'),
            'ShoppingApplication' => __('Shopping', 'wpide'),
            'SocialNetworkingApplication' => __('Social networking', 'wpide'),
            'TravelApplication' => __('Travel', 'wpide'),
            'UtilitiesApplication' => __('Utilities', 'wpide'),
        ];

        return [
            'software' => [
                'label' => __('Software', 'wpide'),
                'fields' => [
                    $this->text('software_name', __('Name', 'wpide'), '{title}', 'Example App'),
                    $this->textarea('software_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('software_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('software_image', __('Screenshot / icon URL', 'wpide'), '{featured_image}'),
                    $this->select('application_category', __('Category', 'wpide'), $categories, 'BusinessApplication'),
                    $this->text('operating_system', __('Operating system', 'wpide'), 'Web', 'Windows, macOS, Android'),
                    $this->text('software_version', __('Version', 'wpide'), '', '1.0.0'),
                    $this->date('release_date', __('Release date', 'wpide')),
                ],
            ],
            'publisher' => [
                'label' => __('Publisher', 'wpide'),
                'fields' => [
                    $this->text('publisher_name', __('Publisher name', 'wpide'), '{site_name}'),
                    $this->text('publisher_url', __('Publisher URL', 'wpide'), '{site_url}'),
                    $this->text('publisher_logo', __('Publisher logo URL', 'wpide'), ''),
                ],
            ],
            'offer' => [
                'label' => __('Price & rating', 'wpide'),
                'fields' => [
                    $this->text('software_price', __('Price', 'wpide'), '0', '0', __('Use 0 for free software.', 'wpide')),
                    $this->currencyField('software_currency'),
                    $this->availabilityField('software_availability'),
                    $this->text('rating_value', __('Average rating', 'wpide'), '', '4.8'),
                    $this->text('rating_count', __('Number of ratings', 'wpide'), '', '120'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $rating = [];

        if ($values['rating_value'] !== '' && $values['rating_count'] !== '') {
            $rating = ['@type' => 'AggregateRating', 'ratingValue' => $this->number($values['rating_value']), 'ratingCount' => $this->number($values['rating_count'])];
        }

        return $this->jsonLd([
            '@type' => 'SoftwareApplication',
            'name' => $values['software_name'],
            'description' => $values['software_description'],
            'url' => $values['software_url'],
            'image' => $values['software_image'],
            'applicationCategory' => $values['application_category'],
            'operatingSystem' => $values['operating_system'],
            'softwareVersion' => $values['software_version'],
            'datePublished' => $values['release_date'],
            'publisher' => $this->node('Organization', $values['publisher_name'], ['url' => $values['publisher_url'], 'logo' => $this->imageObject($values['publisher_logo'])]),
            'offers' => $values['software_price'] !== '' ? [
                '@type' => 'Offer',
                'price' => $this->price($values['software_price']),
                'priceCurrency' => $values['software_currency'],
                'availability' => $this->enum($values['software_availability']),
            ] : [],
            'aggregateRating' => $rating,
        ]);
    }
}
