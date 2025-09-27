<?php

namespace TruthElectionDb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\Models\Candidate;

class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->ean8(),
            'name' => $this->faker->name(),
            'alias' => $this->faker->firstName(),
        ];
    }
}
