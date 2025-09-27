<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Data;

class FinalizeErContext extends Data
{
    public function __construct(
        public PrecinctData $precinct,
        public ElectionReturnData|null $er,
        public string $disk,
        public string $folder,
        public string $payload,
        public int $maxChars,
        public bool $force,
        public ?string $qrPersistedAbs = null
    ) {}
}
