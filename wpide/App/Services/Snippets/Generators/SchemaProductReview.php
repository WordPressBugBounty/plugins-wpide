<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Editorial product review structured data (Product with a nested Review).
 */
class SchemaProductReview extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-product-review';
    }

    public function info(): array
    {
        return [
            'title' => __('Product Review Schema', 'wpide'),
            'description' => __('Mark up a review of a product with its rating, reviewer, pros and cons.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'review', 'rating', 'product', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'review' => [
                'label' => __('Review', 'wpide'),
                'fields' => [
                    $this->text('review_title', __('Review title', 'wpide'), '{title}'),
                    $this->textarea('review_description', __('Review summary', 'wpide'), '{excerpt}'),
                    $this->text('review_url', __('Review URL', 'wpide'), '{permalink}'),
                    $this->date('review_date', __('Review date', 'wpide'), '{date_published}'),
                    $this->text('reviewer_name', __('Reviewer name', 'wpide'), '{author_name}'),
                    $this->text('review_rating', __('Rating', 'wpide'), '4.5', '4.5'),
                    $this->text('best_rating', __('Best possible rating', 'wpide'), '5', '5'),
                    $this->text('worst_rating', __('Worst possible rating', 'wpide'), '1', '1'),
                    $this->textarea('pros', __('Pros', 'wpide'), '', "Long battery life\nGreat screen", __('One per line.', 'wpide')),
                    $this->textarea('cons', __('Cons', 'wpide'), '', 'Expensive', __('One per line.', 'wpide')),
                ],
            ],
            'product' => [
                'label' => __('Product', 'wpide'),
                'fields' => [
                    $this->text('product_name', __('Product name', 'wpide'), '', 'Example Phone X'),
                    $this->text('product_url', __('Product URL', 'wpide'), ''),
                    $this->text('product_image', __('Product image URL', 'wpide'), '{featured_image}'),
                    $this->text('product_brand', __('Brand', 'wpide'), ''),
                    $this->text('product_sku', __('SKU', 'wpide'), ''),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $notes = function ($text, string $type) {
            $items = $this->items($text);

            if (!$items) {
                return [];
            }

            $list = [];

            foreach ($items as $index => $item) {
                $list[] = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item];
            }

            return ['@type' => $type, 'itemListElement' => $list];
        };

        $name = $values['product_name'] !== '' ? $values['product_name'] : $values['review_title'];

        return $this->jsonLd([
            '@type' => 'Product',
            'name' => $name,
            'url' => $values['product_url'],
            'image' => $values['product_image'],
            'brand' => $this->node('Brand', $values['product_brand']),
            'sku' => $values['product_sku'],
            'review' => [
                '@type' => 'Review',
                'name' => $values['review_title'],
                'reviewBody' => $values['review_description'],
                'url' => $values['review_url'],
                'datePublished' => $values['review_date'],
                'author' => $this->node('Person', $values['reviewer_name']),
                'reviewRating' => $values['review_rating'] !== '' ? [
                    '@type' => 'Rating',
                    'ratingValue' => $this->number($values['review_rating']),
                    'bestRating' => $this->number($values['best_rating']),
                    'worstRating' => $this->number($values['worst_rating']),
                ] : [],
                'positiveNotes' => $notes($values['pros'], 'ItemList'),
                'negativeNotes' => $notes($values['cons'], 'ItemList'),
            ],
        ]);
    }
}
