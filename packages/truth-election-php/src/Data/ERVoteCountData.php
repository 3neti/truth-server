<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Data;

class ERVoteCountData extends Data
{
    public function __construct(
        public string $candidate_code,
        public int $count = 0
    ){}
}