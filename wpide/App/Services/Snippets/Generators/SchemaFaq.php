<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * FAQPage structured data.
 */
class SchemaFaq extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-faq';
    }

    public function info(): array
    {
        return [
            'title' => __('FAQ Schema', 'wpide'),
            'description' => __('Turn a list of questions and answers into FAQPage markup.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'faq', 'questions', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'faq' => [
                'label' => __('Questions', 'wpide'),
                'fields' => [
                    [
                        'id' => 'faq_items',
                        'type' => 'repeater',
                        'label' => __('Questions and answers', 'wpide'),
                        'description' => __('Use the same questions and answers that are visible on the page. Answers may contain basic HTML (links, lists, bold).', 'wpide'),
                        'default' => [
                            ['question' => 'How long does shipping take?', 'answer' => 'Orders usually arrive within 3 to 5 business days.'],
                            ['question' => 'Can I return a product?', 'answer' => 'Yes, unused products can be returned within 30 days of delivery.'],
                        ],
                        'fields' => [
                            $this->text('question', __('Question', 'wpide'), '', 'What is your question?'),
                            $this->textarea('answer', __('Answer', 'wpide'), '', 'The answer...'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $questions = [];

        foreach ($values['faq_items'] as $row) {
            if (trim($row['question']) === '' || trim($row['answer']) === '') {
                continue;
            }

            $questions[] = [
                '@type' => 'Question',
                'name' => trim($row['question']),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim($row['answer'])],
            ];
        }

        return $this->jsonLd(['@type' => 'FAQPage', 'mainEntity' => $questions]);
    }
}
