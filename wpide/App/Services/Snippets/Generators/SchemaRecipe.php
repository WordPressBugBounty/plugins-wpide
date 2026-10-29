<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Recipe structured data with ingredients, steps, timing and nutrition.
 */
class SchemaRecipe extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-recipe';
    }

    public function info(): array
    {
        return [
            'title' => __('Recipe Schema', 'wpide'),
            'description' => __('Get rich results for a recipe: photo, cooking times, servings, calories, ingredients and step-by-step instructions.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'recipe', 'food', 'cooking', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'recipe' => [
                'label' => __('Recipe', 'wpide'),
                'fields' => [
                    $this->text('name', __('Recipe name', 'wpide'), '{title}', 'Classic Pancakes'),
                    $this->textarea('description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('author_name', __('Author', 'wpide'), '{author_name}'),
                    $this->date('date_published', __('Published date', 'wpide'), '{date_published}'),
                    $this->text('recipe_category', __('Category', 'wpide'), '', 'Breakfast'),
                    $this->text('recipe_cuisine', __('Cuisine', 'wpide'), '', 'American'),
                    $this->text('keywords', __('Keywords', 'wpide'), '', 'easy, quick, sweet', __('Comma separated.', 'wpide')),
                ],
            ],
            'timing' => [
                'label' => __('Time & nutrition', 'wpide'),
                'fields' => [
                    $this->text('prep_time', __('Preparation time', 'wpide'), '', '10', __('Minutes (e.g. 10) or an ISO 8601 duration (e.g. PT10M).', 'wpide')),
                    $this->text('cook_time', __('Cooking time', 'wpide'), '', '20', __('Minutes or ISO 8601 duration.', 'wpide')),
                    $this->text('total_time', __('Total time', 'wpide'), '', '30', __('Leave empty to add preparation and cooking times.', 'wpide')),
                    $this->text('recipe_yield', __('Yield', 'wpide'), '', '4 servings'),
                    $this->text('calories', __('Calories per serving', 'wpide'), '', '350 calories'),
                ],
            ],
            'ingredients' => [
                'label' => __('Ingredients', 'wpide'),
                'fields' => [
                    [
                        'id' => 'ingredients',
                        'type' => 'repeater',
                        'label' => __('Ingredients', 'wpide'),
                        'default' => [
                            ['ingredient' => '200 g flour'],
                            ['ingredient' => '2 eggs'],
                            ['ingredient' => '300 ml milk'],
                        ],
                        'fields' => [
                            $this->text('ingredient', __('Ingredient with quantity', 'wpide'), '', '200 g flour'),
                        ],
                    ],
                ],
            ],
            'instructions' => [
                'label' => __('Instructions', 'wpide'),
                'fields' => [
                    [
                        'id' => 'instructions',
                        'type' => 'repeater',
                        'label' => __('Steps', 'wpide'),
                        'default' => [
                            ['instruction_name' => 'Mix', 'instruction_text' => 'Whisk the flour, eggs and milk into a smooth batter.', 'instruction_url' => '', 'instruction_image' => ''],
                            ['instruction_name' => 'Cook', 'instruction_text' => 'Cook ladlefuls of batter in a hot pan for about 2 minutes per side.', 'instruction_url' => '', 'instruction_image' => ''],
                        ],
                        'fields' => [
                            $this->text('instruction_name', __('Step title', 'wpide'), '', 'Preheat the oven'),
                            $this->textarea('instruction_text', __('Instructions', 'wpide')),
                            $this->text('instruction_url', __('Step URL', 'wpide'), '', '{permalink}#step-1'),
                            $this->text('instruction_image', __('Step image URL', 'wpide')),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $ingredients = [];

        foreach ($values['ingredients'] as $row) {
            if (trim($row['ingredient']) !== '') {
                $ingredients[] = trim($row['ingredient']);
            }
        }

        $steps = [];

        foreach ($values['instructions'] as $row) {
            if (trim($row['instruction_text']) === '' && trim($row['instruction_name']) === '') {
                continue;
            }

            $steps[] = [
                '@type' => 'HowToStep',
                'position' => count($steps) + 1,
                'name' => $row['instruction_name'],
                'text' => trim($row['instruction_text']) !== '' ? $row['instruction_text'] : $row['instruction_name'],
                'url' => $row['instruction_url'],
                'image' => $row['instruction_image'],
            ];
        }

        $prep = $this->duration($values['prep_time']);
        $cook = $this->duration($values['cook_time']);
        $total = $values['total_time'];

        if ($total === '' && is_numeric($values['prep_time']) && is_numeric($values['cook_time'])) {
            $total = (string) ($values['prep_time'] + $values['cook_time']);
        }

        return $this->jsonLd([
            '@type' => 'Recipe',
            'name' => $values['name'],
            'description' => $values['description'],
            'image' => $values['image'] !== '' ? [$values['image']] : [],
            'author' => $this->node('Person', $values['author_name']),
            'datePublished' => $values['date_published'],
            'recipeCategory' => $values['recipe_category'],
            'recipeCuisine' => $values['recipe_cuisine'],
            'keywords' => $values['keywords'],
            'prepTime' => $prep,
            'cookTime' => $cook,
            'totalTime' => $this->duration($total),
            'recipeYield' => $values['recipe_yield'],
            'nutrition' => $values['calories'] !== '' ? ['@type' => 'NutritionInformation', 'calories' => $values['calories']] : [],
            'recipeIngredient' => $ingredients,
            'recipeInstructions' => $steps,
        ]);
    }
}
