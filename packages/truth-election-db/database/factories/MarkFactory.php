<?php

namespace TruthElectionDb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\Models\Mark;
use TruthElectionDb\Models\Mapping;
use Illuminate\Support\Str;

class MarkFactory extends Factory
{
    protected $model = Mark::class;

    public function definition(): array
    {
        return [
            'mapping_id' => Mapping::factory(),
            'key' => 'key-' . Str::random(5),
            'candidate_code' => 'uuid-' . Str::random(8),
        ];
    }
}
