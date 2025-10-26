<?php

namespace Database\Factories;

use App\Models\TemplateFamily;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TemplateFamily>
 */
class TemplateFamilyFactory extends Factory
{
    protected $model = TemplateFamily::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['election', 'survey', 'test'];
        $name = fake()->words(3, true);

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 999),
            'name' => ucwords($name),
            'description' => fake()->sentence(10),
            'category' => fake()->randomElement($categories),
            'version' => '1.0.0',
            'is_public' => fake()->boolean(70), // 70% chance of being public
            'storage_type' => 'local',
            'repo_url' => null,
            'repo_provider' => null,
            'repo_path' => null,
            'user_id' => null,
        ];
    }

    /**
     * Indicate that the family is for elections.
     */
    public function election(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'election',
            'name' => 'Election Ballot ' . fake()->year(),
        ]);
    }

    /**
     * Indicate that the family is for surveys.
     */
    public function survey(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'survey',
            'name' => 'Survey Form ' . fake()->year(),
        ]);
    }

    /**
     * Indicate that the family is for tests.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'test',
            'name' => 'Test/Exam ' . fake()->year(),
        ]);
    }

    /**
     * Indicate that the family uses remote storage.
     */
    public function remote(): static
    {
        return $this->state(fn (array $attributes) => [
            'storage_type' => 'remote',
            'repo_url' => 'https://github.com/' . fake()->userName() . '/templates',
            'repo_provider' => 'github',
            'repo_path' => 'templates/' . fake()->word(),
        ]);
    }

    /**
     * Indicate that the family uses hybrid storage.
     */
    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'storage_type' => 'hybrid',
            'repo_url' => 'https://github.com/' . fake()->userName() . '/templates',
            'repo_provider' => 'github',
            'repo_path' => 'templates/' . fake()->word(),
        ]);
    }
}
