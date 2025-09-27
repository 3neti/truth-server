<?php

namespace TruthElectionDb\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\Models\Ballot;
use Illuminate\Support\Str;

class BallotFactory extends Factory
{
    protected $model = Ballot::class;

    public function definition(): array
    {
        return [
            'code' => 'BALLOT-' . Str::uuid()->toString(),
            'votes' => self::votes()
        ];
    }

    public static function votes(): array
    {
        return [
            [
                'position' => [
                    'code' => 'PRESIDENT',
                    'name' => 'President of the Philippines',
                    'level' => 'national',
                    'count' => 1,
                ],
                'candidates' => [
                    [
                        'code' => 'uuid-bbm-1234',
                        'name' => 'Ferdinand Marcos Jr.',
                        'alias' => 'BBM',
                        'position' => [
                            'code' => 'PRESIDENT',
                            'name' => 'President of the Philippines',
                            'level' => 'national',
                            'count' => 1,
                        ],
                    ],
                ],
            ],
            [
                'position' => [
                    'code' => 'SENATOR',
                    'name' => 'Senator of the Philippines',
                    'level' => 'national',
                    'count' => 12,
                ],
                'candidates' => [
                    [
                        'code' => 'uuid-jdc-001',
                        'name' => 'Juan Dela Cruz',
                        'alias' => 'JDC',
                        'position' => [
                            'code' => 'SENATOR',
                            'name' => 'Senator of the Philippines',
                            'level' => 'national',
                            'count' => 12,
                        ],
                    ],
                    [
                        'code' => 'uuid-mrp-002',
                        'name' => 'Maria Rosario P.',
                        'alias' => 'MRP',
                        'position' => [
                            'code' => 'SENATOR',
                            'name' => 'Senator of the Philippines',
                            'level' => 'national',
                            'count' => 12,
                        ],
                    ],
                ],
            ],
        ];
    }
}
