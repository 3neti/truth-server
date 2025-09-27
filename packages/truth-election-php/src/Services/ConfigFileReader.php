<?php

namespace TruthElection\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class ConfigFileReader
{
    protected string $defaultElectionPath;
    protected string $defaultPrecinctPath;
    protected string $defaultMappingPath;

    public function __construct(
        string $electionPath = null,
        string $precinctPath = null,
        string $mappingPath = null,
    ) {
        $this->defaultElectionPath = $electionPath ?? base_path('config/election.json');
        $this->defaultPrecinctPath = $precinctPath ?? base_path('config/precinct.yaml');
        $this->defaultMappingPath  = $mappingPath  ?? base_path('config/mapping.yaml');
    }

    public function read(): array
    {
        $errors = [];

        if (! File::exists($this->defaultElectionPath)) {
            $errors[] = "❌ Election file not found at path: {$this->defaultElectionPath}";
        }

        if (! File::exists($this->defaultPrecinctPath)) {
            $errors[] = "❌ Precinct file not found at path: {$this->defaultPrecinctPath}";
        }

        if (! File::exists($this->defaultMappingPath)) {
            $errors[] = "❌ Mapping file not found at path: {$this->defaultMappingPath}";
        }

        if ($errors) {
            throw new \RuntimeException(implode(PHP_EOL, $errors));
        }

        $election = json_decode(File::get($this->defaultElectionPath), true);
        $precinct = Yaml::parse(File::get($this->defaultPrecinctPath));
        $mapping  = Yaml::parse(File::get($this->defaultMappingPath));

        return [
            'election' => $election,
            'precinct' => $precinct,
            'mapping'  => $mapping,

            'paths' => [
                'election' => $this->defaultElectionPath,
                'precinct' => $this->defaultPrecinctPath,
                'mapping'  => $this->defaultMappingPath,
            ],

            'precinct_code' => $precinct['code'] ?? null,
        ];
    }
}
