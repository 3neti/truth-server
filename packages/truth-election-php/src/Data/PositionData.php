<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Transformers\EnumTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use TruthElection\Enums\Level;
use Spatie\LaravelData\Data;

class PositionData extends Data
{
    public function __construct(
        public string $code,   // e.g. "PRESIDENT"
        public string $name,   // e.g. "President of the Philippines"
        #[WithTransformer(EnumTransformer::class)]
        #[WithCast(EnumCast::class, Level::class)]
        public Level $level,  // e.g. "national"
        public int $count      // number of candidates allowed to be voted
    ) {}

    public function maxSelections(): int
    {
        return $this->count;
    }
}
