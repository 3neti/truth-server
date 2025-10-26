<?php

namespace Database\Factories;

use App\Models\TemplateData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TemplateData>
 */
class TemplateDataFactory extends Factory
{
    protected $model = TemplateData::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['election', 'survey', 'test', 'general'];

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(10),
            'template_ref' => 'local:' . fake()->word() . '/' . fake()->randomElement(['standard', 'compact', 'grid']),
            'category' => fake()->randomElement($categories),
            'data' => $this->generateSampleData(),
            'is_public' => fake()->boolean(50),
            'user_id' => null,
        ];
    }

    /**
     * Generate sample data structure.
     */
    private function generateSampleData(): array
    {
        return [
            'document' => [
                'template_ref' => 'local:' . fake()->word() . '/standard',
                'title' => fake()->sentence(4),
                'date' => fake()->date(),
            ],
            'data' => [
                'title' => fake()->sentence(4),
                'description' => fake()->sentence(8),
            ],
        ];
    }

    /**
     * Indicate that the data is for an election.
     */
    public function election(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'election',
            'template_ref' => 'local:election-ballot/standard',
            'data' => [
                'document' => [
                    'template_ref' => 'local:election-ballot/standard',
                ],
                'election_name' => fake()->words(3, true) . ' Election',
                'precinct' => fake()->bothify('###-?'),
                'date' => fake()->date(),
                'positions' => [
                    [
                        'code' => 'PRES',
                        'title' => 'President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['code' => 'P01', 'name' => fake()->name(), 'party' => fake()->word() . ' Party'],
                            ['code' => 'P02', 'name' => fake()->name(), 'party' => fake()->word() . ' Party'],
                        ]
                    ],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the data is for a survey.
     */
    public function survey(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'survey',
            'template_ref' => 'local:survey-form/standard',
            'data' => [
                'document' => [
                    'template_ref' => 'local:survey-form/standard',
                ],
                'survey_title' => fake()->sentence(4),
                'description' => fake()->sentence(8),
                'date' => fake()->date(),
                'questions' => [
                    [
                        'number' => 1,
                        'type' => 'multiple_choice',
                        'text' => fake()->sentence() . '?',
                        'options' => ['Excellent', 'Good', 'Fair', 'Poor']
                    ],
                    [
                        'number' => 2,
                        'type' => 'multiple_choice',
                        'text' => fake()->sentence() . '?',
                        'options' => ['Yes', 'Maybe', 'No']
                    ],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the data is for a test.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'test',
            'template_ref' => 'local:test-exam/standard',
            'data' => [
                'document' => [
                    'template_ref' => 'local:test-exam/standard',
                ],
                'test_title' => fake()->words(3, true) . ' Quiz',
                'subject' => fake()->randomElement(['Mathematics', 'Science', 'History', 'English']),
                'date' => fake()->date(),
                'duration' => fake()->randomElement(['30 minutes', '45 minutes', '60 minutes']),
                'questions' => [
                    [
                        'number' => 1,
                        'text' => fake()->sentence() . '?',
                        'points' => 1,
                        'options' => ['A', 'B', 'C', 'D']
                    ],
                    [
                        'number' => 2,
                        'text' => fake()->sentence() . '?',
                        'points' => 1,
                        'options' => ['True', 'False']
                    ],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the data is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the data is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
