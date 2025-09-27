<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Transformers\EnumTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use TruthElection\Enums\ElectoralInspectorRole;
use Spatie\LaravelData\{Data, Optional};
use Spatie\LaravelData\Casts\EnumCast;
use Carbon\Carbon;

class ElectoralInspectorData extends Data
{
    public function __construct(
        public string $id,                        // id of member
        public string $name,                      // e.g. 'Juan Dela Cruz'
        #[WithTransformer(EnumTransformer::class)]
        #[WithCast(EnumCast::class, ElectoralInspectorRole::class)]
        public ElectoralInspectorRole $role,      // e.g. 'chairperson', 'member'
        public string|Optional $signature = new Optional(),
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon|Optional $signed_at = new Optional()
    ) {}
}
