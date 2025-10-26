<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['election', 'survey', 'test'];
        $variants = ['standard', 'compact', 'grid', 'multiple-choice'];

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(10),
            'category' => fake()->randomElement($categories),
            'handlebars_template' => $this->generateHandlebarsTemplate(),
            'sample_data' => $this->generateSampleData(),
            'schema' => null,
            'json_schema' => null,
            'is_public' => fake()->boolean(60), // 60% chance of being public
            'user_id' => null,
            'family_id' => null,
            'layout_variant' => fake()->randomElement($variants),
            'version' => '1.0.0',
            'storage_type' => 'local',
        ];
    }

    /**
     * Generate a simple Handlebars template.
     */
    private function generateHandlebarsTemplate(): string
    {
        return '{
  "document": {
    "title": "{{title}}",
    "unique_id": "{{id}}",
    "date": "{{date}}"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "SECTION_1",
      "title": "{{section_title}}",
      "question": "{{question}}",
      "maxSelections": 1,
      "layout": "vertical",
      "choices": []
    }
  ]
}';
    }

    /**
     * Generate sample data for the template.
     */
    private function generateSampleData(): array
    {
        return [
            'title' => fake()->sentence(4),
            'id' => fake()->unique()->bothify('DOC-####'),
            'date' => fake()->date(),
            'section_title' => fake()->words(3, true),
            'question' => fake()->sentence() . '?',
        ];
    }

    /**
     * Indicate that the template is for elections.
     */
    public function election(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'election',
            'name' => 'Election Ballot Template',
        ]);
    }

    /**
     * Indicate that the template is for surveys.
     */
    public function survey(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'survey',
            'name' => 'Survey Form Template',
        ]);
    }

    /**
     * Indicate that the template is for tests.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'test',
            'name' => 'Test/Exam Template',
        ]);
    }
}
