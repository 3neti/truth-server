<?php

namespace Tests\Unit;

use App\Services\ElectionConfigLoader;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class ElectionConfigLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset environment variable before each test
        putenv('ELECTION_CONFIG_PATH');
    }

    /** @test */
    public function it_uses_default_config_path()
    {
        $loader = new ElectionConfigLoader;

        $this->assertStringContainsString('config', $loader->getConfigPath());
    }

    /** @test */
    public function it_respects_environment_variable()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;

        $this->assertStringContainsString('resources/docs/simulation/config', $loader->getConfigPath());
    }

    /** @test */
    public function it_loads_election_json_from_default_config()
    {
        $loader = new ElectionConfigLoader;
        $election = $loader->loadElection();

        $this->assertIsArray($election);
        $this->assertArrayHasKey('positions', $election);
        $this->assertArrayHasKey('candidates', $election);
    }

    /** @test */
    public function it_loads_election_json_from_simulation_config()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;
        $election = $loader->loadElection();

        $this->assertIsArray($election);
        $this->assertArrayHasKey('positions', $election);
        $this->assertArrayHasKey('candidates', $election);

        // Verify it's the simulation config (Barangay election)
        $positionCodes = array_column($election['positions'], 'code');
        $this->assertContains('PUNONG_BARANGAY-1402702011', $positionCodes);
    }

    /** @test */
    public function it_throws_exception_if_election_json_not_found()
    {
        putenv('ELECTION_CONFIG_PATH=nonexistent/path');

        $loader = new ElectionConfigLoader;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Election config not found');

        $loader->loadElection();
    }

    /** @test */
    public function it_loads_mapping_yaml_from_default_config()
    {
        $loader = new ElectionConfigLoader;
        $mapping = $loader->loadMapping();

        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('marks', $mapping);
    }

    /** @test */
    public function it_loads_mapping_yaml_from_simulation_config()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;
        $mapping = $loader->loadMapping();

        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('marks', $mapping);

        // Verify it's simulation mapping (has 56 marks: 6 + 50)
        $this->assertCount(56, $mapping['marks']);
    }

    /** @test */
    public function it_throws_exception_if_mapping_yaml_not_found()
    {
        putenv('ELECTION_CONFIG_PATH=nonexistent/path');

        $loader = new ElectionConfigLoader;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapping config not found');

        $loader->loadMapping();
    }

    /** @test */
    public function it_loads_precinct_yaml_from_default_config()
    {
        $loader = new ElectionConfigLoader;
        $precinct = $loader->loadPrecinct();

        $this->assertIsArray($precinct);
        $this->assertArrayHasKey('code', $precinct);
    }

    /** @test */
    public function it_loads_precinct_yaml_from_simulation_config()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;
        $precinct = $loader->loadPrecinct();

        $this->assertIsArray($precinct);
        $this->assertArrayHasKey('code', $precinct);
        $this->assertEquals('27020001', $precinct['code']);
    }

    /** @test */
    public function it_finds_position_by_candidate_code()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;

        // Test finding position for a candidate
        $position = $loader->findPositionByCandidate('LD_001');

        $this->assertNotNull($position);
        $this->assertEquals('PUNONG_BARANGAY-1402702011', $position);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_candidate()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;

        $position = $loader->findPositionByCandidate('NONEXISTENT_999');

        $this->assertNull($position);
    }

    /** @test */
    public function it_gets_candidate_details_with_position()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;

        $candidate = $loader->getCandidateDetails('LD_001');

        $this->assertIsArray($candidate);
        $this->assertEquals('LD_001', $candidate['code']);
        $this->assertEquals('Leonardo DiCaprio', $candidate['name']);
        $this->assertEquals('LD', $candidate['alias']);
        $this->assertEquals('PUNONG_BARANGAY-1402702011', $candidate['position']);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_candidate_details()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;

        $candidate = $loader->getCandidateDetails('NONEXISTENT_999');

        $this->assertNull($candidate);
    }

    /** @test */
    public function it_handles_multiple_candidates_in_same_position()
    {
        putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');

        $loader = new ElectionConfigLoader;

        // Both should be in MEMBER_SANGGUNIANG_BARANGAY-1402702011
        $position1 = $loader->findPositionByCandidate('JD_001');
        $position2 = $loader->findPositionByCandidate('ES_002');

        $this->assertEquals($position1, $position2);
        $this->assertEquals('MEMBER_SANGGUNIANG_BARANGAY-1402702011', $position1);
    }
}
