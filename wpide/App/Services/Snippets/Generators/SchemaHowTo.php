<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * HowTo structured data with ordered steps, supplies and tools.
 */
class SchemaHowTo extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-how-to';
    }

    public function info(): array
    {
        return [
            'title' => __('How-To Schema', 'wpide'),
            'description' => __('Describe a tutorial as a series of steps, with the time, cost, supplies and tools it needs.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'how-to', 'tutorial', 'steps', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('Guide', 'wpide'),
                'fields' => [
                    $this->text('title', __('Title', 'wpide'), '{title}', 'How to change a bike tire'),
                    $this->textarea('description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('total_time', __('Total time', 'wpide'), '', 'PT30M', __('Minutes (e.g. 30) or an ISO 8601 duration (e.g. PT1H30M).', 'wpide')),
                    $this->text('estimated_cost', __('Estimated cost', 'wpide'), '', '15'),
                    $this->currencyField('cost_currency'),
                    $this->textarea('supply', __('Supplies', 'wpide'), '', 'Inner tube', __('One per line, or comma separated.', 'wpide')),
                    $this->textarea('tool', __('Tools', 'wpide'), '', 'Tire levers', __('One per line, or comma separated.', 'wpide')),
                ],
            ],
            'steps' => [
                'label' => __('Steps', 'wpide'),
                'fields' => [
                    [
                        'id' => 'steps',
                        'type' => 'repeater',
                        'label' => __('Steps', 'wpide'),
                        'description' => __('Steps are numbered in the order listed here.', 'wpide'),
                        'default' => [
                            ['step_name' => 'Prepare', 'step_text' => 'Gather everything you need before you start.', 'step_url' => '', 'step_image' => ''],
                            ['step_name' => 'Do the work', 'step_text' => 'Follow the instructions described on this page.', 'step_url' => '', 'step_image' => ''],
                        ],
                        'fields' => [
                            $this->text('step_name', __('Step title', 'wpide')),
                            $this->textarea('step_text', __('Instructions', 'wpide')),
                            $this->text('step_url', __('Step URL', 'wpide'), '', '{permalink}#step-1', __('Link to this step on the page (anchor).', 'wpide')),
                            $this->text('step_image', __('Step image URL', 'wpide')),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $steps = [];

        foreach ($values['steps'] as $row) {
            if (trim($row['step_text']) === '' && trim($row['step_name']) === '') {
                continue;
            }

            $steps[] = [
                '@type' => 'HowToStep',
                'position' => count($steps) + 1,
                'name' => $row['step_name'],
                'text' => trim($row['step_text']) !== '' ? $row['step_text'] : $row['step_name'],
                'url' => $row['step_url'],
                'image' => $row['step_image'],
            ];
        }

        $list = function ($text, string $type) {
            return array_map(function ($name) use ($type) {
                return ['@type' => $type, 'name' => $name];
            }, $this->items($text));
        };

        return $this->jsonLd([
            '@type' => 'HowTo',
            'name' => $values['title'],
            'description' => $values['description'],
            'image' => $values['image'],
            'totalTime' => $this->duration($values['total_time']),
            'estimatedCost' => $values['estimated_cost'] !== '' ? ['@type' => 'MonetaryAmount', 'currency' => $values['cost_currency'], 'value' => $this->price($values['estimated_cost'])] : [],
            'supply' => $list($values['supply'], 'HowToSupply'),
            'tool' => $list($values['tool'], 'HowToTool'),
            'step' => $steps,
        ]);
    }
}
