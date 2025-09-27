<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Data;

class CandidateData extends Data
{
    public function __construct(
        public string $code,   // UUID or unique identifier
        public string $name,   // Full name
        public string $alias,  // Nickname or ballot alias
        public PositionData $position,
    ) {}
}
