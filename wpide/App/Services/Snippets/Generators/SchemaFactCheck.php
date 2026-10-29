<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * ClaimReview (fact check) structured data.
 */
class SchemaFactCheck extends SchemaGenerator
{
    /**
     * Verdict => [rating value on a 1-5 scale, readable name].
     */
    protected function verdicts(): array
    {
        return [
            'true' => [5, __('True', 'wpide')],
            'mostly-true' => [4, __('Mostly true', 'wpide')],
            'half-true' => [3, __('Half true', 'wpide')],
            'mostly-false' => [2, __('Mostly false', 'wpide')],
            'false' => [1, __('False', 'wpide')],
            'unproven' => [0, __('Unproven', 'wpide')],
        ];
    }

    public function id(): string
    {
        return 'schema-fact-check';
    }

    public function info(): array
    {
        return [
            'title' => __('Fact Check Schema', 'wpide'),
            'description' => __('Publish a ClaimReview for an article that checks whether a claim is true.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'fact check', 'claim review', 'news', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        $verdicts = [];

        foreach ($this->verdicts() as $key => $verdict) {
            $verdicts[$key] = $verdict[1];
        }

        return [
            'claim' => [
                'label' => __('Claim', 'wpide'),
                'fields' => [
                    $this->text('claim_title', __('Claim reviewed', 'wpide'), '', 'The moon is made of cheese.', __('Short summary of the claim being checked.', 'wpide')),
                    $this->textarea('claim_description', __('Review summary', 'wpide'), '{excerpt}'),
                    $this->select('fact_check_rating', __('Verdict', 'wpide'), $verdicts, 'false'),
                    $this->text('claim_author', __('Who made the claim', 'wpide'), '', 'John Smith'),
                    $this->date('claim_date', __('Date of the claim', 'wpide')),
                    $this->text('claim_appearance', __('Where the claim appeared (URL)', 'wpide'), '', 'https://example.com/original-post'),
                ],
            ],
            'review' => [
                'label' => __('Fact check', 'wpide'),
                'fields' => [
                    $this->text('fact_check_url', __('Fact check URL', 'wpide'), '{permalink}'),
                    $this->select('author_type', __('Fact checker type', 'wpide'), ['Organization' => __('Organization', 'wpide'), 'Person' => __('Person', 'wpide')], 'Organization'),
                    $this->text('author_name', __('Fact checker name', 'wpide'), '{site_name}'),
                    $this->text('author_url', __('Fact checker URL', 'wpide'), '{site_url}'),
                    $this->date('date_published', __('Published date', 'wpide'), '{date_published}'),
                    $this->date('date_modified', __('Modified date', 'wpide'), '{date_modified}'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $verdicts = $this->verdicts();
        $verdict = $verdicts[$values['fact_check_rating']] ?? $verdicts['false'];
        $rating = ['@type' => 'Rating', 'alternateName' => $verdict[1]];

        if ($verdict[0] > 0) {
            $rating += ['ratingValue' => $verdict[0], 'bestRating' => 5, 'worstRating' => 1];
        }

        return $this->jsonLd([
            '@type' => 'ClaimReview',
            'url' => $values['fact_check_url'],
            'claimReviewed' => $values['claim_title'],
            'reviewBody' => $values['claim_description'],
            'datePublished' => $values['date_published'],
            'dateModified' => $values['date_modified'],
            'author' => $this->node($values['author_type'], $values['author_name'], ['url' => $values['author_url']]),
            'itemReviewed' => [
                '@type' => 'Claim',
                'author' => $this->node('Person', $values['claim_author']),
                'datePublished' => $values['claim_date'],
                'appearance' => $values['claim_appearance'] !== '' ? ['@type' => 'CreativeWork', 'url' => $values['claim_appearance']] : [],
            ],
            'reviewRating' => $rating,
        ]);
    }
}
