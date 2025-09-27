<?php

namespace TruthElectionDb\Database\Factories;

use TruthElectionDb\Models\{ElectionReturn, Precinct};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ElectionReturnFactory extends Factory
{

    protected $model =  ElectionReturn::class;

    public function definition(): array
    {
        return [
            'code' => 'ER-' . Str::uuid(),
        ];
    }

    public function forPrecinct(?Precinct $precinct = null): static
    {
        return $this->state(function () use ($precinct) {
            $precinct = $precinct ?? Precinct::factory()->create();

            return [
                'precinct_code' => $precinct->code,
            ];
        });
    }

    public function withSignatures(array $overrides = []): static
    {
        return $this->state(fn () => array_merge(self::er_signatures(), $overrides));
    }

    protected static function er_signatures(): array
    {
        return [
            'signatures' => self::signatures(),
        ];
    }

    public static function signatures(): array
    {
        return  [
            ['id' => 'uuid-juan',  'name' => 'Juan dela Cruz', 'role' => 'chairperson', 'signature' => 'base64-image-data', 'signed_at' => '2025-08-07T12:00:00+08:00'],
            ['id' => 'uuid-maria', 'name' => 'Maria Santos',   'role' => 'member'     , 'signature' => 'base64-image-data', 'signed_at' => '2025-08-07T12:05:00+08:00'],
            ['id' => 'uuid-pedro', 'name' => 'Pedro Reyes',    'role' => 'member'     , 'signature' => 'base64-image-data', 'signed_at' => '2025-08-07T12:05:00+08:00'],
        ];
    }
}
