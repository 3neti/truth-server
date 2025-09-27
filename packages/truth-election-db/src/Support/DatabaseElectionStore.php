<?php

namespace TruthElectionDb\Support;


use TruthElection\Data\{BallotData,
    CandidateData,
    ElectionReturnData,
    ElectoralInspectorData,
    MappingData,
    MarkData,
    PositionData,
    PrecinctData,
    VoteData};
use TruthElectionDb\Models\{Ballot, Candidate, ElectionReturn, Position, Precinct};
use TruthElection\Support\ElectionStoreInterface;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\{Cache, DB};
use Spatie\LaravelData\DataCollection;
use TruthElectionDb\Models\{BallotMark, Mapping, Mark};

class DatabaseElectionStore implements ElectionStoreInterface
{
    private static ?self $instance = null;

    /**
     * Access the singleton instance.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function getBallotsForPrecinct(string $precinctCode): array
    {
        $precinct = Precinct::whereCode($precinctCode)->first();

        return $precinct
            ? $precinct->ballots
            : [];
    }

    public function getBallots(string $precinctCode): DataCollection
    {
        $precinct = Precinct::whereCode($precinctCode)->first();

        if (! $precinct) {
            return new DataCollection(BallotData::class, []);
        }

        return new DataCollection(BallotData::class, $precinct->ballots);
    }

    public function putBallot(BallotData $ballot, string $precinctCode): void
    {
        DB::transaction(function () use ($ballot, $precinctCode) {
            $precinct = Precinct::whereCode($precinctCode)->first();

            if (! $precinct) {
                throw new \RuntimeException("Precinct [$precinctCode] not found.");
            }

            // 🔍 Look for existing ballot by code and precinct
            $existing = Ballot::query()
                ->where('code', $ballot->code)
                ->where('precinct_code', $precinctCode)
                ->first();

            if ($existing) {
                // 🧩 Merge incoming ballot data with existing
                $existingData = $existing->getData();

                $merged = $existingData->mergeWith($ballot);

                // 🗑 Remove old ballot before inserting merged one
                $existing->delete();

                // 💾 Save merged ballot
                $merged->setPrecinctCode($precinctCode);
                Ballot::fromData($merged);
            } else {
                // 💾 Save new ballot
                $ballot->setPrecinctCode($precinctCode);
                Ballot::fromData($ballot);
            }
        });
    }

    public function getPrecinct(?string $code = null): ?PrecinctData
    {
        if ($code === null) {
            return Precinct::first()?->getData();
        }

        return Precinct::whereCode($code)->first()?->getData();
    }

    public function putPrecinct(PrecinctData $precinct): void
    {
        DB::transaction(fn () => Precinct::fromData($precinct));
    }

    public function putElectionReturn(ElectionReturnData $er): void
    {
        DB::transaction(fn () => ElectionReturn::fromData($er));
    }

    public function getElectionReturn(?string $code = null): ?ElectionReturnData
    {
        if ($code === null) {
            return ElectionReturn::first()?->getData();
        }

        return ElectionReturn::whereCode($code)->first()?->getData();
    }

    public function getElectionReturnByPrecinct(string $precinctCode): ?ElectionReturnData
    {
        return ElectionReturn::get()
            ->first(fn ($er) =>
                $er->belongsTo(Precinct::class, 'precinct_code', 'code')->getResults()?->code === $precinctCode
            )?->getData();
    }

    public function replaceElectionReturn(ElectionReturnData $er): void
    {
        $this->putElectionReturn($er);
    }

    public function load(array $positions, PrecinctData $precinct): void
    {
        DB::transaction(function () use ($positions, $precinct) {
            Precinct::fromData($precinct);

            foreach ($positions as $posArr) {
                $position = PositionData::from($posArr);
                $this->positions[$position->code] = $position;

                $ballot = new BallotData(
                    code: $position->code,
                    votes: new DataCollection(VoteData::class, []) // ✅ empty collection
                );
                $ballot->setPrecinctCode($precinct->code);
                Ballot::fromData($ballot);
            }
        });
    }

    public function setPositions(array $positionMap): void
    {
        DB::transaction(function () use ($positionMap) {
            foreach ($positionMap as $position) {
                Position::fromData($position);
            }
        });
    }

    public function getPosition(string $code): ?PositionData
    {
        return Position::query()->where('code', $code)->first()?->getData() ?? null; //TODO: test this
    }

    public function setCandidates(array $candidateMap): void
    {
        DB::transaction(function () use ($candidateMap) {
            foreach ($candidateMap as $candidate) {
                Candidate::fromData($candidate);
            }
        });
    }

    public function getCandidate(string $code): ?CandidateData
    {
        return Candidate::query()->where('code', $code)->first()?->getData() ?? null; //TODO: test this
    }

    public function allPositions(): array
    {
        return (new DataCollection(PositionData::class, Position::all()->toArray() ?? []))->toArray(); //TODO: test this
    }

    public function allCandidates(): array
    {
        return (new DataCollection(CandidateData::class, Candidate::with('position')->get()->toArray() ?? []))->toArray(); //TODO: test this
    }

    public function findInspector(ElectionReturnData $er, string $id): ?ElectoralInspectorData
    {
        return $er->precinct->electoral_inspectors->toCollection()
            ->firstWhere('id', $id);
    }

    public function findPrecinctInspector(ElectionReturnData $er, string $id): ?ElectoralInspectorData
    {
        return $er->precinct->electoral_inspectors->toCollection()
            ->firstWhere('id', $id);
    }

    public function getInspectorsForPrecinct(string $precinctCode): DataCollection
    {
        $precinct = Precinct::query()
            ->whereCode($precinctCode)
            ->first();

        if (! $precinct || !is_array($precinct->electoral_inspectors)) {
            return new DataCollection(ElectoralInspectorData::class, []);
        }

        return new DataCollection(
            ElectoralInspectorData::class,
            collect($precinct->electoral_inspectors)
                ->map(fn ($inspector) => ElectoralInspectorData::from($inspector))
                ->all()
        );
    }

    public function findSignatory(ElectionReturnData $er, string $id): ElectoralInspectorData
    {
        return $er->signatures->toCollection()->firstWhere('id', $id);
    }

    public function reset(): void
    {
        DB::transaction(function () {
            Ballot::truncate();
            ElectionReturn::truncate();
            Precinct::truncate();
            Position::truncate();
            Candidate::truncate();
        });
    }

    public function setMappings(array|MappingData $mappings): void
    {
        DB::transaction(function () use ($mappings) {
            if ($mappings instanceof MappingData) {
                $mappingModel = Mapping::updateOrCreate(
                    ['code' => $mappings->code],
                    [
                        'location_name' => $mappings->location_name,
                        'district' => $mappings->district,
                    ]
                );

                foreach ($mappings->marks as $mark) {
                    Mark::updateOrCreate(
                        [
                            'mapping_id' => $mappingModel->id,
                            'key' => $mark->key,
                        ],
                        [
                            'value' => $mark->value,
                        ]
                    );
                }

                return;
            }

            if (!isset($mappings['code'], $mappings['location_name'], $mappings['district'], $mappings['marks'])) {
                throw new \InvalidArgumentException('Missing required keys in mapping array: code, location_name, district, marks');
            }

            $mappingModel = Mapping::updateOrCreate(
                ['code' => $mappings['code']],
                [
                    'location_name' => $mappings['location_name'],
                    'district' => $mappings['district'],
                ]
            );

            foreach ($mappings['marks'] as $mark) {
                Mark::updateOrCreate(
                    [
                        'mapping_id' => $mappingModel->id,
                        'key' => $mark['key'],
                    ],
                    [
                        'value' => $mark['value'],
                    ]
                );
            }
        });
    }

    public function getMappings(): MappingData
    {
        $mapping = Mapping::with('marks')->first();

        if (!$mapping instanceof Mapping) {
            throw new \RuntimeException('No mapping found.');
        }

        return new MappingData(
            code: $mapping->code,
            location_name: $mapping->location_name,
            district: $mapping->district,
            marks: new DataCollection(MarkData::class, $mapping->marks?->toArray() ?? []),
        );
    }

    public function addBallotMark(string $ballotCode, string $key): void
    {
        BallotMark::firstOrCreate([
            'ballot_code' => $ballotCode,
            'mark_key' => $key,
        ]);
    }

    public function getBallotMarkKeys(string $ballotCode): array
    {
        return BallotMark::query()
            ->where('ballot_code', $ballotCode)
            ->pluck('mark_key')
            ->toArray();
    }
}
