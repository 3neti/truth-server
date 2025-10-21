<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\{Data, DataCollection, Optional};
use Carbon\Carbon;

class ERData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        /** @var array<string, int> Vote counts per candidate code (candidate_code => count) */
        public array $tallies,
        /** @var DataCollection<ERElectoralInspectorData> Digital signatures from the electoral board without the roles and name */
        public DataCollection $signatures,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon $updated_at,
    ) {}

    public static function fromElectionReturnData(ElectionReturnData $electionReturnData): static
    {
        // Transform VoteCountData to associative array (candidate_code => count)
        $minifiedTallies = [];
        foreach ($electionReturnData->tallies as $voteCount) {
            $minifiedTallies[$voteCount->candidate_code] = $voteCount->count;
        }
        
        // Transform ElectoralInspectorData to ERElectoralInspectorData (minify by removing name and role)
        // Only include inspectors who have actually signed (have signature and signed_at)
        $minifiedSignatures = $electionReturnData->signatures->toCollection()
            ->filter(function (ElectoralInspectorData $inspector) {
                // Only include inspectors who have signed
                return !($inspector->signature instanceof Optional) 
                    && !empty($inspector->signature)
                    && !($inspector->signed_at instanceof Optional)
                    && $inspector->signed_at !== null;
            })
            ->map(function (ElectoralInspectorData $inspector) {
                return new ERElectoralInspectorData(
                    id: $inspector->id,
                    signature: $inspector->signature,
                    signed_at: $inspector->signed_at
                );
            });
        
        // Create ERData instance with minified data
        return new static(
            id: $electionReturnData->id,
            code: $electionReturnData->code,
            tallies: $minifiedTallies,
            signatures: new DataCollection(ERElectoralInspectorData::class, $minifiedSignatures->all()),
            created_at: $electionReturnData->created_at,
            updated_at: $electionReturnData->updated_at,
        );
    }
}

