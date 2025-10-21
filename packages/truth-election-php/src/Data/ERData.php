<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\{Data, DataCollection};
use Carbon\Carbon;

class ERData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        /** @var DataCollection<ERVoteCountData> The sorted vote counts per candidate without the position code and name */
        public DataCollection $tallies,
        /** @var DataCollection<ERElectoralInspectorData> Digital signatures from the electoral board without the roles and name */
        public DataCollection $signatures,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon $updated_at,
    ) {}
}

