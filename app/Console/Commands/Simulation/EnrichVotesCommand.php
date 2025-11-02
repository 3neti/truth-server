<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class EnrichVotesCommand extends Command
{
    protected $signature = 'simulation:enrich-votes
                            {results-file : Path to appreciation results JSON}
                            {--config-dir= : Config directory containing election.json and mapping.yaml}
                            {--output= : Output path (default: same directory as results)}
                            {--fields=key,value,position,name,alias : Comma-separated fields to include}';

    protected $description = 'Enrich detected votes with candidate information from config';

    public function handle(): int
    {
        $resultsFile = $this->argument('results-file');
        $configDir = $this->option('config-dir');
        $output = $this->option('output');
        $fieldsOption = $this->option('fields');

        // Parse fields
        $fields = array_map('trim', explode(',', $fieldsOption));

        // Validate inputs
        if (!File::exists($resultsFile)) {
            $this->error("Results file not found: {$resultsFile}");
            return 1;
        }

        if (!$configDir) {
            $this->error("Config directory is required (use --config-dir)");
            return 1;
        }

        // Load results
        $results = json_decode(File::get($resultsFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON in results file");
            return 1;
        }

        // Load config files
        $electionPath = "{$configDir}/election.json";
        $mappingPath = "{$configDir}/mapping.yaml";

        if (!File::exists($electionPath) || !File::exists($mappingPath)) {
            $this->error("Missing election.json or mapping.yaml in {$configDir}");
            return 1;
        }

        $electionConfig = json_decode(File::get($electionPath), true);
        $mappingConfig = Yaml::parseFile($mappingPath);

        // Build lookup tables
        $keyToValue = $this->buildKeyToValueMap($mappingConfig);
        $valueToCandidateInfo = $this->buildCandidateInfoMap($electionConfig);

        // Extract detected votes
        $bubbleResults = $results['results'] ?? [];
        $detectedVotes = array_filter($bubbleResults, fn($r) => $r['filled'] ?? false);

        // Enrich votes with candidate information
        $enrichedVotes = [];
        foreach ($detectedVotes as $vote) {
            $bubbleId = $vote['id'] ?? null;
            if (!$bubbleId) continue;

            // Extract grid key from bubble ID (e.g., "ROW_A_A2" -> "A2")
            $key = $this->extractKeyFromBubbleId($bubbleId);

            $enrichedVote = [
                'bubble_id' => $bubbleId,
                'fill_ratio' => $vote['fill_ratio'] ?? 0,
                'confidence' => $vote['confidence'] ?? 0,
                'warnings' => $vote['warnings'] ?? null,
            ];

            // Add configured fields
            if ($key && isset($keyToValue[$key])) {
                $candidateCode = $keyToValue[$key];
                $candidateInfo = $valueToCandidateInfo[$candidateCode] ?? [];

                if (in_array('key', $fields)) {
                    $enrichedVote['key'] = $key;
                }
                if (in_array('value', $fields)) {
                    $enrichedVote['value'] = $candidateCode;
                }
                if (in_array('position', $fields) && isset($candidateInfo['position_code'])) {
                    $enrichedVote['position'] = $candidateInfo['position_code'];
                }
                if (in_array('position_name', $fields) && isset($candidateInfo['position_name'])) {
                    $enrichedVote['position_name'] = $candidateInfo['position_name'];
                }
                if (in_array('name', $fields) && isset($candidateInfo['name'])) {
                    $enrichedVote['name'] = $candidateInfo['name'];
                }
                if (in_array('alias', $fields) && isset($candidateInfo['alias'])) {
                    $enrichedVote['alias'] = $candidateInfo['alias'];
                }
                if (in_array('number', $fields) && isset($candidateInfo['number'])) {
                    $enrichedVote['number'] = $candidateInfo['number'];
                }
            }

            $enrichedVotes[] = $enrichedVote;
        }

        // Build output
        $enrichedOutput = [
            'timestamp' => date('c'),
            'detected_votes' => $enrichedVotes,
            'summary' => [
                'total_bubbles' => count($bubbleResults),
                'filled_bubbles' => count($detectedVotes),
                'unfilled_bubbles' => count($bubbleResults) - count($detectedVotes),
            ],
            'configuration' => [
                'fields_included' => $fields,
                'config_source' => [
                    'election' => basename($electionPath),
                    'mapping' => basename($mappingPath),
                ],
            ],
        ];

        // Determine output path
        $outputPath = $output ?: dirname($resultsFile) . '/votes.json';

        // Write output
        File::put($outputPath, json_encode($enrichedOutput, JSON_PRETTY_PRINT));

        $this->info("✓ Enriched votes written to: {$outputPath}");
        $this->info("  Detected votes: " . count($enrichedVotes));
        $this->info("  Fields included: " . implode(', ', $fields));

        return 0;
    }

    /**
     * Build key-to-value map from mapping.yaml
     */
    protected function buildKeyToValueMap(array $mappingConfig): array
    {
        $map = [];
        foreach ($mappingConfig['marks'] ?? [] as $mark) {
            $key = $mark['key'] ?? null;
            $value = $mark['value'] ?? null;
            if ($key && $value) {
                $map[$key] = $value;
            }
        }
        return $map;
    }

    /**
     * Build candidate info map from election.json
     */
    protected function buildCandidateInfoMap(array $electionConfig): array
    {
        $map = [];

        // Build position name lookup
        $positionNames = [];
        foreach ($electionConfig['positions'] ?? [] as $position) {
            $positionNames[$position['code']] = $position['name'];
        }

        // Build candidate info
        foreach ($electionConfig['candidates'] ?? [] as $positionCode => $candidates) {
            $number = 1;
            foreach ($candidates as $candidate) {
                $candidateCode = $candidate['code'];
                $map[$candidateCode] = [
                    'name' => $candidate['name'] ?? '',
                    'alias' => $candidate['alias'] ?? null,
                    'position_code' => $positionCode,
                    'position_name' => $positionNames[$positionCode] ?? $positionCode,
                    'number' => $number,
                ];
                $number++;
            }
        }

        return $map;
    }

    /**
     * Extract grid key from bubble ID
     * E.g., "ROW_A_A2" -> "A2"
     */
    protected function extractKeyFromBubbleId(string $bubbleId): ?string
    {
        // Handle grid-based IDs (ROW_A_A2)
        if (str_starts_with($bubbleId, 'ROW_')) {
            $parts = explode('_', $bubbleId);
            return end($parts); // Return "A2"
        }

        // For other formats, try to extract key
        // (Could be extended for other ID formats)
        return null;
    }
}
