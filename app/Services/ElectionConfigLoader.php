<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ElectionConfigLoader
{
    protected string $configPath;

    public function __construct()
    {
        // Priority order:
        // 1. Environment variable
        // 2. Config setting (if available)
        // 3. Default path
        $envPath = env('ELECTION_CONFIG_PATH');
        
        if ($envPath) {
            $this->configPath = $envPath;
        } else {
            // Try to get config, but fall back to default if not available (e.g., in unit tests)
            try {
                $this->configPath = config('election.config_path', 'config');
            } catch (\Exception $e) {
                $this->configPath = 'config';
            }
        }
    }

    /**
     * Get the full path to config directory
     */
    public function getConfigPath(): string
    {
        return base_path($this->configPath);
    }

    /**
     * Load election.json configuration
     */
    public function loadElection(): array
    {
        $path = $this->getConfigPath().'/election.json';

        if (! File::exists($path)) {
            throw new RuntimeException("Election config not found: {$path}");
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in election.json: '.json_last_error_msg());
        }

        return $data;
    }

    /**
     * Load mapping.yaml configuration
     */
    public function loadMapping(): array
    {
        $path = $this->getConfigPath().'/mapping.yaml';

        if (! File::exists($path)) {
            throw new RuntimeException("Mapping config not found: {$path}");
        }

        $yaml = File::get($path);

        try {
            $data = Yaml::parse($yaml);
        } catch (\Exception $e) {
            throw new RuntimeException('Invalid YAML in mapping.yaml: '.$e->getMessage());
        }

        return $data;
    }

    /**
     * Load precinct.yaml configuration
     */
    public function loadPrecinct(): array
    {
        $path = $this->getConfigPath().'/precinct.yaml';

        if (! File::exists($path)) {
            throw new RuntimeException("Precinct config not found: {$path}");
        }

        $yaml = File::get($path);

        try {
            $data = Yaml::parse($yaml);
        } catch (\Exception $e) {
            throw new RuntimeException('Invalid YAML in precinct.yaml: '.$e->getMessage());
        }

        return $data;
    }

    /**
     * Find position code for a given candidate code
     *
     * @return string|null Position code or null if not found
     */
    public function findPositionByCandidate(string $candidateCode): ?string
    {
        $election = $this->loadElection();

        if (! isset($election['candidates'])) {
            return null;
        }

        foreach ($election['candidates'] as $positionCode => $candidates) {
            foreach ($candidates as $candidate) {
                if (($candidate['code'] ?? null) === $candidateCode) {
                    return $positionCode;
                }
            }
        }

        return null;
    }

    /**
     * Get candidate details by code
     *
     * @return array|null Candidate data with position, or null if not found
     */
    public function getCandidateDetails(string $candidateCode): ?array
    {
        $election = $this->loadElection();

        if (! isset($election['candidates'])) {
            return null;
        }

        foreach ($election['candidates'] as $positionCode => $candidates) {
            foreach ($candidates as $candidate) {
                if (($candidate['code'] ?? null) === $candidateCode) {
                    return array_merge($candidate, ['position' => $positionCode]);
                }
            }
        }

        return null;
    }
}
