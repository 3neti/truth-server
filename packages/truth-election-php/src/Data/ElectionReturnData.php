<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Illuminate\Support\{Carbon, Collection};
use Spatie\LaravelData\{Data, Optional};
use Spatie\LaravelData\DataCollection;

/**
 * ElectionReturnData represents a full, persisted election return record,
 * including the election tallies and the electoral board's digital signatures.
 */
class ElectionReturnData extends Data
{
    public function __construct(
        public string $id,

        /** Unique code identifying this election return */
        public string $code,

        /** The associated precinct */
        public PrecinctData $precinct,

        /** @var DataCollection<VoteCountData> The sorted vote counts per candidate */
        public DataCollection $tallies,

        /** @var DataCollection<ElectoralInspectorData> Digital signatures from the electoral board */
        public DataCollection $signatures,

        /** @var DataCollection<BallotData> The ballots casted by voters  */
        public DataCollection $ballots,

        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon $created_at,

        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d\TH:i:sP')]
        public Carbon $updated_at,
    ) {}

    public function with(): array
    {
        $last = $this->ballots->toCollection()->last();

        return [
            'last_ballot' => $last ? BallotData::from($last) : null,
        ];
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $last_ballot = $array['last_ballot'] ?? null;
        if ($last_ballot instanceof Data) {
            $array['last_ballot'] = $last_ballot->toArray();
        }

        return $array;
    }

    public function signedInspectors(): Collection
    {
        return $this->signatures->toCollection()->filter(function (ElectoralInspectorData $inspector) {
            return !($inspector->signature instanceof Optional)
                && !empty($inspector->signature);
        });
    }

    public function withUpdatedSignatures(DataCollection $signatures): self
    {
        return self::from([
            ...$this->toArray(),
            'signatures' => $signatures->toArray(),
        ]);
    }

    public function withInspectorSignature(SignPayloadData $payload, ?ElectoralInspectorData $inspector): self
    {
        if (! $inspector) {
            abort(404, "Inspector with ID [{$payload->id}] not found.");
        }

        $remaining = $this->signatures->toCollection()
            ->reject(fn(ElectoralInspectorData $s) => $s->id === $payload->id);

        $signed = new ElectoralInspectorData(
            id: $inspector->id,
            name: $inspector->name,
            role: $inspector->role,
            signature: $payload->signature,
            signed_at: Carbon::now()
        );

        $signatures = new DataCollection(
            ElectoralInspectorData::class,
            $remaining->push($signed)->all()
        );

        return $this->withUpdatedSignatures($signatures);
    }

    //TODO: test these
    public function findSignatory(string $id): ElectoralInspectorData
    {
        return $this->signatures->toCollection()->firstWhere('id', $id);
    }

    public function hasInspectorSigned(string $id): bool
    {
        return $this->signedInspectors()->contains(fn($i) => $i->id === $id);
    }

    public function transformFor(string $payload = 'minimal'): array
    {
        $array = $this->toArray();

        if ($payload !== 'full') {
            unset($array['precinct']['ballots'], $array['ballots'], $array['signatures']);
        }

        return $array;
    }
}
