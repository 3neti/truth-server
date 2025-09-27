<?php

namespace TruthElectionDb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\Models\Mapping;
use Illuminate\Support\Str;

class MappingFactory extends Factory
{
    protected $model = Mapping::class;

    public function definition(): array
    {
        return [
            'code' => 'MAPPING-' . Str::uuid()->toString(),
            'location_name' => $this->faker->city . ' Voting Center',
            'district' => $this->faker->randomElement(['1st District', '2nd District', '3rd District']),
        ];
    }
}
