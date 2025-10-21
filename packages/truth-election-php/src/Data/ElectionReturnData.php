<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Illuminate\Support\{Carbon, Collection};
use Spatie\LaravelData\{Data, Optional};
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\ElectoralInspectorRole;
use TruthElection\Services\ConfigFileReader;

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

    public static function fromERData(ERData $ERData): static
    {
        // Load configuration files using ConfigFileReader
        $packageConfigPath = __DIR__ . '/../../config';
        $configReader = new ConfigFileReader(
            electionPath: $packageConfigPath . '/election.json',
            precinctPath: $packageConfigPath . '/precinct.yaml',
            mappingPath: $packageConfigPath . '/mapping.yaml'
        );
        $config = $configReader->read();
        
        $electionConfig = $config['election'];
        $precinctConfig = $config['precinct'];
        
        // Create candidate lookup maps
        $candidateToPosition = [];
        $candidateDetails = [];
        
        foreach ($electionConfig['candidates'] as $positionCode => $candidates) {
            foreach ($candidates as $candidate) {
                $candidateToPosition[$candidate['code']] = $positionCode;
                $candidateDetails[$candidate['code']] = $candidate;
            }
        }
        
        // Create position lookup map
        $positions = [];
        foreach ($electionConfig['positions'] as $position) {
            $positions[$position['code']] = $position;
        }
        
        // Create position order mapping
        $positionOrder = [];
        foreach ($electionConfig['positions'] as $index => $position) {
            $positionOrder[$position['code']] = $index;
        }
        
        // Transform associative array to VoteCountData
        $tallies = collect($ERData->tallies)->map(function (int $count, string $candidateCode) use ($candidateToPosition, $candidateDetails) {
            $positionCode = $candidateToPosition[$candidateCode] ?? null;
            $candidate = $candidateDetails[$candidateCode] ?? null;
            
            if (!$positionCode || !$candidate) {
                throw new \InvalidArgumentException("Unknown candidate code: {$candidateCode}");
            }
            
            return new VoteCountData(
                position_code: $positionCode,
                candidate_code: $candidateCode,
                candidate_name: $candidate['name'],
                count: $count
            );
        })
        // Sort by position order, then by vote count (desc), then by candidate code (asc)
        ->sortBy(function (VoteCountData $tally) use ($positionOrder) {
            $positionIndex = $positionOrder[$tally->position_code] ?? 999;
            // Use negative count for descending order (highest votes first)
            return [$positionIndex, -$tally->count, $tally->candidate_code];
        });
        
        // Transform ERElectoralInspectorData to ElectoralInspectorData
        $signatures = $ERData->signatures->toCollection()->map(function (ERElectoralInspectorData $erInspector) use ($precinctConfig) {
            $inspectorConfig = collect($precinctConfig['electoral_inspectors'])
                ->firstWhere('id', $erInspector->id);
            
            if (!$inspectorConfig) {
                throw new \InvalidArgumentException("Unknown electoral inspector ID: {$erInspector->id}");
            }
            
            return new ElectoralInspectorData(
                id: $erInspector->id,
                name: $inspectorConfig['name'],
                role: ElectoralInspectorRole::from($inspectorConfig['role']),
                signature: $erInspector->signature,
                signed_at: $erInspector->signed_at
            );
        });
        
        // Create electoral inspectors for precinct (from config)
        $electoralInspectors = collect($precinctConfig['electoral_inspectors'])->map(function ($inspector) {
            return new ElectoralInspectorData(
                id: $inspector['id'],
                name: $inspector['name'],
                role: ElectoralInspectorRole::from($inspector['role'])
            );
        });
        
        // Create PrecinctData
        $precinct = new PrecinctData(
            code: $precinctConfig['code'],
            location_name: $precinctConfig['location_name'],
            latitude: (float) $precinctConfig['latitude'],
            longitude: (float) $precinctConfig['longitude'],
            electoral_inspectors: new DataCollection(ElectoralInspectorData::class, $electoralInspectors->all()),
        );
        
        // Create empty ballots collection
        $ballots = new DataCollection(BallotData::class, []);
        
        // Return new ElectionReturnData instance
        return new static(
            id: $ERData->id,
            code: $ERData->code,
            precinct: $precinct,
            tallies: new DataCollection(VoteCountData::class, $tallies->all()),
            signatures: new DataCollection(ElectoralInspectorData::class, $signatures->all()),
            ballots: $ballots,
            created_at: Carbon::parse($ERData->created_at),
            updated_at: Carbon::parse($ERData->updated_at),
        );
    }

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
