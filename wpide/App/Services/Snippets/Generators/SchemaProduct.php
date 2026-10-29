<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Product structured data with one or more offers and an optional aggregate rating.
 */
class SchemaProduct extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-product';
    }

    public function info(): array
    {
        return [
            'title' => __('Product Schema', 'wpide'),
            'description' => __('Show price, availability and ratings for a product in search results, with one or several offers.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'product', 'price', 'ecommerce', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'product' => [
                'label' => __('Product', 'wpide'),
                'fields' => [
                    $this->text('product_name', __('Product name', 'wpide'), '{title}'),
                    $this->textarea('product_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('product_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('product_image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('product_brand', __('Brand', 'wpide'), ''),
                    $this->text('product_sku', __('SKU', 'wpide'), ''),
                    $this->text('product_gtin', __('GTIN / EAN / UPC', 'wpide'), ''),
                    $this->text('product_mpn', __('Manufacturer part number', 'wpide'), ''),
                ],
            ],
            'offers' => [
                'label' => __('Offers', 'wpide'),
                'fields' => [
                    [
                        'id' => 'offer_items',
                        'type' => 'repeater',
                        'label' => __('Offers', 'wpide'),
                        'description' => __('Add one row per price (e.g. variants or sellers).', 'wpide'),
                        'default' => [
                            ['offer_name' => '', 'product_price' => '49.00', 'product_currency' => 'USD', 'product_availability' => 'InStock', 'price_valid_until' => '', 'offer_url' => '', 'offer_condition' => 'NewCondition'],
                        ],
                        'fields' => [
                            $this->text('offer_name', __('Offer name', 'wpide'), '', 'Regular price'),
                            $this->text('product_price', __('Price', 'wpide'), '', '49.00'),
                            $this->currencyField('product_currency'),
                            $this->availabilityField('product_availability'),
                            $this->select('offer_condition', __('Condition', 'wpide'), ['NewCondition' => __('New', 'wpide'), 'UsedCondition' => __('Used', 'wpide'), 'RefurbishedCondition' => __('Refurbished', 'wpide'), 'DamagedCondition' => __('Damaged', 'wpide')], 'NewCondition'),
                            $this->date('price_valid_until', __('Price valid until', 'wpide')),
                            $this->text('offer_url', __('Offer URL', 'wpide'), '', '', __('Defaults to the product URL.', 'wpide')),
                        ],
                    ],
                ],
            ],
            'rating' => [
                'label' => __('Rating', 'wpide'),
                'fields' => [
                    $this->text('rating_value', __('Average rating', 'wpide'), '', '4.6'),
                    $this->text('review_count', __('Number of reviews', 'wpide'), '', '89'),
                    $this->text('best_rating', __('Best possible rating', 'wpide'), '5'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $offers = [];

        foreach ($values['offer_items'] as $row) {
            if ($row['product_price'] === '') {
                continue;
            }

            $offers[] = [
                '@type' => 'Offer',
                'name' => $row['offer_name'],
                'price' => $this->price($row['product_price']),
                'priceCurrency' => $row['product_currency'],
                'availability' => $this->enum($row['product_availability']),
                'itemCondition' => $this->enum($row['offer_condition']),
                'priceValidUntil' => $row['price_valid_until'],
                'url' => $row['offer_url'] !== '' ? $row['offer_url'] : $values['product_url'],
            ];
        }

        $rating = [];

        if ($values['rating_value'] !== '' && $values['review_count'] !== '') {
            $rating = [
                '@type' => 'AggregateRating',
                'ratingValue' => $this->number($values['rating_value']),
                'reviewCount' => $this->number($values['review_count']),
                'bestRating' => $this->number($values['best_rating']),
            ];
        }

        return $this->jsonLd([
            '@type' => 'Product',
            'name' => $values['product_name'],
            'description' => $values['product_description'],
            'url' => $values['product_url'],
            'image' => $values['product_image'],
            'brand' => $this->node('Brand', $values['product_brand']),
            'sku' => $values['product_sku'],
            'gtin' => $values['product_gtin'],
            'mpn' => $values['product_mpn'],
            'offers' => $this->oneOrMany($offers),
            'aggregateRating' => $rating,
        ]);
    }
}
