<?php

namespace TruthElection\Actions;

use TruthElection\Data\{CandidateData, PositionData, PrecinctData};
use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Services\ConfigFileReader;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;
use TruthElection\Enums\Level;
use Illuminate\Support\Arr;

class InitializeSystem
{
    use AsAction;

    public function __construct(
        protected ElectionStoreInterface $store,
        protected ConfigFileReader $reader
    ) {}

    public function handle(
        ?string $electionPath = null,
        ?string $precinctPath = null,
        ?string $mappingPath = null, // ✅ added
    ): array {
        $config = (new ConfigFileReader($electionPath, $precinctPath, $mappingPath))->read();

        $summary = [
            'positions'  => ['created' => 0, 'updated' => 0],
            'candidates' => ['created' => 0, 'updated' => 0],
            'precinct'   => ['created' => 0, 'updated' => 0],
            'mapping'    => ['loaded' => 0], // ✅ added
        ];

        $precinctCode = $this->importPrecinct($config['precinct'], $summary);
        $positionMap  = $this->loadPositions($config['election'], $summary);
        $candidateMap = $this->loadCandidates($config['election'], $positionMap, $summary);

        $this->store->setPositions($positionMap);
        $this->store->setCandidates($candidateMap);

        // ✅ New: set mappings
        try {
            $this->store->setMappings($config['mapping']);
            $summary['mapping']['loaded'] = 1;
        } catch (\Throwable $e) {
            Log::error('Failed to load mapping.yaml: ' . $e->getMessage());
            throw $e;
        }

        return [
            'ok' => true,
            'summary' => array_merge($summary, [
                'precinct_code' => $precinctCode,
            ]),
            'files' => $config['paths'],
        ];
    }

    private function importPrecinct(array $precinct, array &$summary): string
    {
        $precinctData = PrecinctData::from($precinct);
        $existingPrecinct = $this->store->precincts[$precinctData->code] ?? null;

        try {
            $this->store->putPrecinct($precinctData);
        } catch (\Throwable $e) {
            Log::error("Failed to import precinct {$precinctData->code}: " . $e->getMessage());
            throw $e;
        }

        $existingPrecinct
            ? $summary['precinct']['updated']++
            : $summary['precinct']['created']++;

        return $precinctData->code;
    }

    private function loadPositions(array $election, array &$summary): array
    {
        $positionMap = [];

        foreach (Arr::get($election, 'positions', []) as $pos) {
            $code = $pos['code'];

            $positionMap[$code] = new PositionData(
                code: $code,
                name: $pos['name'],
                level: Level::from($pos['level']),
                count: $pos['count'],
            );

            $summary['positions']['created']++;
        }

        return $positionMap;
    }

    private function loadCandidates(array $election, array $positionMap, array &$summary): array
    {
        $candidateMap = [];

        foreach (Arr::get($election, 'candidates', []) as $positionCode => $list) {
            $position = $positionMap[$positionCode] ?? null;

            if (!$position) continue;

            foreach ((array) $list as $c) {
                $candidateMap[$c['code']] = new CandidateData(
                    code: $c['code'],
                    name: $c['name'],
                    alias: $c['alias'] ?? null,
                    position: $position,
                );

                $summary['candidates']['created']++;
            }
        }

        return $candidateMap;
    }
}
