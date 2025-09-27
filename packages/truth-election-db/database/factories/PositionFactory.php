<?php

namespace TruthElectionDb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\Models\Position;
use TruthElection\Enums\Level;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'code' => fake()->word(),
            'name' => fake()->name(),
            'level' => Level::random()->value,
            'count' => fake()->randomNumber(),
        ];
    }
}
