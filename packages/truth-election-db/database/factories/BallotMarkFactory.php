<?php

namespace TruthElectionDb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\Models\BallotMark;
use Illuminate\Support\Str;

class BallotMarkFactory extends Factory
{
    protected $model = BallotMark::class;

    public function definition(): array
    {
        return [
            'ballot_code' => 'BALLOT-' . Str::uuid()->toString(),
            'mark_key' => 'key-' . Str::random(5),
        ];
    }
}
