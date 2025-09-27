<?php

use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Actions\InitializeSystem;
use Illuminate\Support\Facades\File;
use TruthElection\Enums\Level;

beforeEach(function () {
    // Ensure the config directory exists in the test Laravel base path
    File::ensureDirectoryExists(base_path('config'));

    // Copy the real files from the package into the test base_path
    File::copy(realpath(__DIR__ . '/../../../config/election.json'), base_path('config/election.json'));
    File::copy(realpath(__DIR__ . '/../../../config/precinct.yaml'), base_path('config/precinct.yaml'));
    File::copy(realpath(__DIR__ . '/../../../config/mapping.yaml'), base_path('config/mapping.yaml'));
});

test('InitializeSystem loads valid config and returns summary', function () {
    $result = InitializeSystem::run();

    expect($result)->toBeArray()
        ->and($result['ok'])->toBeTrue()
        ->and($result['summary'])->toHaveKeys(['positions', 'candidates', 'precinct_code'])
        ->and($result['summary'])->toHaveKeys(['positions',  'precinct_code'])
        ->and($result['files'])->toHaveKeys(['election', 'precinct'])
    ;
});

test('InitializeSystem loads election.json and parses values correctly', function () {
    // Act
    $result = InitializeSystem::run();

    // Assert: basic structure
    expect($result['ok'])->toBeTrue();
    expect($result['summary'])->toHaveKeys(['positions', 'candidates', 'precinct_code']);
    expect($result['files'])->toHaveKeys(['election', 'precinct']);

    // Assert: specific values
    $store = InMemoryElectionStore::instance();

    // Check PRESIDENT position
    $president = $store->getPosition('PRESIDENT');
    expect($president)->not->toBeNull()
        ->and($president->code)->toBe('PRESIDENT')
        ->and($president->name)->toBe('President of the Philippines')
        ->and($president->level)->toBe(Level::NATIONAL)
        ->and($president->count)->toBe(1)
    ;

    //Check specific president candidate
    $candidate = $store->getCandidate('SJ_002');

    expect($candidate)->not->toBeNull()
        ->and($candidate->code)->toBe('SJ_002')
        ->and($candidate->name)->toBe('Scarlett Johansson')
        ->and($candidate->alias)->toBe('SJ')
        ->and($candidate->position)->toBe($president)
    ;
});

test('InitializeSystem manually loads election.json and parses values correctly', function () {
    // Arrange: write a minimal election.json file inline
    $electionJson = json_encode([
        'positions' => [
            'PRESIDENT' => [
                'code' => 'PRESIDENT',
                'name' => 'President of the Philippines',
                'level' => 'national',
                'count' => 1,
            ]
        ],
        'candidates' => [
            'PRESIDENT' => [
                [
                    'code' => 'LH_001',
                    'name' => 'Lester Hurtado',
                    'alias' => 'LH',
                    'position' => 'PRESIDENT',
                ]
            ]
        ],
    ], JSON_PRETTY_PRINT);

    File::ensureDirectoryExists(base_path('config'));
    File::put(base_path('config/election.json'), $electionJson);

    $precinctYaml = <<<YAML
code: CURRIMAO-001
location_name: 'Currimao National High School'
latitude: 17.993217
longitude: 120.488902
electoral_inspectors:
  -
    id: uuid-juan
    name: 'Juan dela Cruz'
    role: 'chairperson'
  -
    id: uuid-maria
    name: 'Maria Santos'
    role: 'member'
  -
    id: uuid-pedro
    name: 'Pedro Reyes'
    role: 'member'
YAML;

    File::put(base_path('config/precinct.yaml'), $precinctYaml);

    expect(File::exists(base_path('config/election.json')))->toBeTrue();

    // Act
    $result = InitializeSystem::run();

    // Assert: overall status and keys
    expect($result['ok'])->toBeTrue();
    expect($result['summary'])->toHaveKeys(['positions', 'candidates', 'precinct_code']);
    expect($result['files'])->toHaveKeys(['election', 'precinct']);

    // Assert: parsed content in the store
    $store = InMemoryElectionStore::instance();

    // 💼 Position and Candidate checks
    $president = $store->getPosition('PRESIDENT');
    expect($president)->not->toBeNull()
        ->and($president->code)->toBe('PRESIDENT')
        ->and($president->name)->toBe('President of the Philippines')
        ->and($president->level)->toBe(Level::NATIONAL)
        ->and($president->count)->toBe(1);

    $candidate = $store->getCandidate('LH_001');
    expect($candidate)->not->toBeNull()
        ->and($candidate->name)->toBe('Lester Hurtado')
        ->and($candidate->alias)->toBe('LH')
        ->and($candidate->position)->toBe($president);

    // 🗺️ Precinct details
    $precinct = $store->precincts['CURRIMAO-001'] ?? null;

    expect($precinct)->not->toBeNull()
        ->and($precinct->code)->toBe('CURRIMAO-001')
        ->and($precinct->location_name)->toBe('Currimao National High School')
        ->and($precinct->latitude)->toBe(17.993217)
        ->and($precinct->longitude)->toBe(120.488902)
        ->and($precinct->electoral_inspectors)->toHaveCount(3);

    // 🔍 Check individual inspector
    expect($precinct->electoral_inspectors[0]->id)->toBe('uuid-juan')
        ->and($precinct->electoral_inspectors[0]->name)->toBe('Juan dela Cruz')
        ->and($precinct->electoral_inspectors[0]->role->value)->toBe('chairperson');
});

test('InitializeSystem loads mapping.yaml and stores MappingData correctly', function () {
    // Act
    $result = InitializeSystem::run();

    // Assert: structure
    expect($result['ok'])->toBeTrue();
    expect($result['summary'])->toHaveKey('mapping')
        ->and($result['summary']['mapping']['loaded'])->toBe(1);
    expect($result['files'])->toHaveKey('mapping');

    // Retrieve the store and mappings
    $store = InMemoryElectionStore::instance();
    $mappingData = $store->getMappings();

    // Check top-level fields
    expect($mappingData->code)->toBe('0102800000');
    expect($mappingData->location_name)->toBe('Currimao, Ilocos Norte');
    expect($mappingData->district)->toBe('2');

    // Should contain 60+ marks (actual count is 60+ based on mapping.yaml)
    expect($mappingData->marks)->toBeInstanceOf(\Spatie\LaravelData\DataCollection::class);
    expect($mappingData->marks->count())->toBeGreaterThan(50);

    // Check a specific mark
    $mark = $mappingData->marks->toCollection()->firstWhere('key', 'K3');
    expect($mark)->not->toBeNull()
        ->and($mark->value)->toBe('THE_DARK_KNIGHT_003');
});
