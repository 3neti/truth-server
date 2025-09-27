<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Data;

class MarkData extends Data
{
    public function __construct(
        public string $key,
        public string $value,
    ) {}
}
