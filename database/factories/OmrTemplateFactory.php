<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OmrTemplate>
 */
class OmrTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['ballot', 'survey', 'test', 'questionnaire'];

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement($categories),
            'handlebars_template' => '{
                "document": {
                    "title": "{{title}}",
                    "unique_id": "{{id}}"
                }
            }',
            'sample_data' => [
                'title' => fake()->sentence(),
                'id' => fake()->unique()->bothify('DOC-####'),
            ],
            'schema' => [
                'type' => 'object',
                'required' => ['title', 'id'],
                'properties' => [
                    'title' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                ],
            ],
            'is_public' => fake()->boolean(30), // 30% chance of being public
            'user_id' => null,
        ];
    }
}
