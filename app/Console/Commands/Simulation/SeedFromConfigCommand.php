<?php

namespace App\Console\Commands\Simulation;

use App\Models\Template;
use App\Models\TemplateData;
use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class SeedFromConfigCommand extends Command
{
    protected $signature = 'simulation:seed-from-config 
                            {--config-dir=config : Directory containing election config files}
                            {--fresh : Drop existing templates and data}';

    protected $description = 'Seed database with ballot templates from custom config files';

    public function handle(): int
    {
        $configDir = $this->option('config-dir');
        
        // Resolve to absolute path if relative
        if (!str_starts_with($configDir, '/')) {
            $configDir = base_path($configDir);
        }

        if (!is_dir($configDir)) {
            $this->error("Config directory not found: {$configDir}");
            return self::FAILURE;
        }

        $this->info("Loading config from: {$configDir}");

        // Check for required files
        $electionPath = "{$configDir}/election.json";
        $precinctPath = "{$configDir}/precinct.yaml";
        $mappingPath = "{$configDir}/mapping.yaml";

        if (!file_exists($electionPath) || !file_exists($precinctPath) || !file_exists($mappingPath)) {
            $this->error('Missing required config files (election.json, precinct.yaml, mapping.yaml)');
            return self::FAILURE;
        }

        // Get or create admin user
        $user = User::where('email', 'admin@disburse.cash')->first();
        if (!$user) {
            $this->warn('Creating admin user...');
            $user = User::factory()->create([
                'email' => 'admin@disburse.cash',
                'name' => 'Admin User',
            ]);
        }

        // Clear existing data if --fresh
        if ($this->option('fresh')) {
            $this->warn('Clearing existing ballot templates and data...');
            TemplateData::whereIn('document_id', [
                'SIM-QUESTIONNAIRE-001',
                'SIM-BALLOT-001',
            ])->forceDelete(); // Use forceDelete to bypass soft deletes if any
        }

        // Load config files
        $electionConfig = json_decode(file_get_contents($electionPath), true);
        $precinctConfig = Yaml::parseFile($precinctPath);
        $mappingConfig = Yaml::parseFile($mappingPath);

        if (!$electionConfig || !$precinctConfig || !$mappingConfig) {
            $this->error('Failed to parse config files');
            return self::FAILURE;
        }

        $this->info('✓ Config files loaded');

        // Build candidate number lookup from mapping
        $candidateNumbers = [];
        foreach ($mappingConfig['marks'] as $mark) {
            $key = $mark['key'];
            $candidateCode = $mark['value'];
            
            // Extract numeric part from key (e.g., '1' from 'A1', '10' from 'C10')
            preg_match('/[A-Z]+(\d+)/', $key, $matches);
            if (isset($matches[1])) {
                $candidateNumbers[$candidateCode] = (int)$matches[1];
            }
        }

        // Build grid-based data structure from mapping.yaml
        $gridData = $this->buildGridFromMapping($mappingConfig, $electionConfig);
        
        // Build positions array (for questionnaire compatibility)
        $positions = [];
        foreach ($electionConfig['positions'] as $position) {
            $positionData = [
                'code' => $position['code'],
                'title' => $position['name'],
                'level' => $position['level'] ?? 'local',
                'max_selections' => $position['count'],
                'candidates' => [],
            ];

            // Add candidates
            if (isset($electionConfig['candidates'][$position['code']])) {
                $ordinalNumber = 1;
                foreach ($electionConfig['candidates'][$position['code']] as $candidate) {
                    $candidateCode = $candidate['code'];
                    $positionData['candidates'][] = [
                        'code' => $candidateCode,
                        'name' => $candidate['name'],
                        'party' => $candidate['alias'] ?? null,
                        'number' => $candidateNumbers[$candidateCode] ?? $ordinalNumber,
                    ];
                    $ordinalNumber++;
                }
            }

            $positions[] = $positionData;
        }

        $this->info('✓ Parsed ' . count($positions) . ' positions with ' . 
                    collect($positions)->sum(fn($p) => count($p['candidates'])) . ' candidates');

        // Ensure ballot templates exist
        $this->ensureBallotTemplatesExist($user);

        // Create questionnaire data
        TemplateData::updateOrCreate(
            ['document_id' => 'SIM-QUESTIONNAIRE-001'],
            [
                'name' => 'Simulation - Candidate List (Questionnaire)',
                'template_ref' => 'local:election-ballot/questionnaire',
                'json_data' => [
                    'document' => [
                        'template_ref' => 'local:election-ballot/questionnaire',
                        'type' => 'questionnaire',
                    ],
                    'election_name' => $electionConfig['election_name'] ?? 'Simulation Election',
                    'precinct_code' => $precinctConfig['code'] ?? 'SIM-001',
                    'precinct_location' => $precinctConfig['location_name'] ?? 'Simulation Precinct',
                    'date' => date('Y-m-d'),
                    'positions' => $positions,
                    'electoral_inspectors' => $precinctConfig['electoral_inspectors'] ?? [],
                ],
                'user_id' => $user->id,
            ]
        );

        $this->info('✓ Questionnaire data seeded');

        // Create ballot (answer sheet) data with grid layout
        TemplateData::updateOrCreate(
            ['document_id' => 'SIM-BALLOT-001'],
            [
                'name' => 'Simulation - Official Ballot (Answer Sheet)',
                'template_ref' => 'local:election-ballot/answer-sheet-grid',
                'json_data' => [
                    'document' => [
                        'template_ref' => 'local:election-ballot/answer-sheet-grid',
                        'type' => 'ballot',
                    ],
                    'election_name' => $electionConfig['election_name'] ?? 'Simulation Election',
                    'precinct_code' => $precinctConfig['code'] ?? 'SIM-001',
                    'precinct_location' => $precinctConfig['location_name'] ?? 'Simulation Precinct',
                    'date' => date('Y-m-d'),
                    'grid' => $gridData, // Grid layout from mapping.yaml
                    'positions' => $positions, // For metadata/validation
                    'instructions' => [
                        'Use a black or blue pen to shade the oval completely.',
                        'Do not overvote. Overvoting will invalidate your choices for that position.',
                        'To change your vote, ask for a new ballot from the poll worker.',
                        'Do not make any stray marks on this ballot.',
                    ],
                ],
                'user_id' => $user->id,
            ]
        );

        $this->info('✓ Ballot data seeded');

        $this->newLine();
        $this->info('✅ Database seeded successfully from config!');
        $this->table(
            ['Document ID', 'Type', 'Positions', 'Candidates'],
            [
                [
                    'SIM-QUESTIONNAIRE-001',
                    'Questionnaire',
                    count($positions),
                    collect($positions)->sum(fn($p) => count($p['candidates'])),
                ],
                [
                    'SIM-BALLOT-001',
                    'Answer Sheet',
                    count($positions),
                    collect($positions)->sum(fn($p) => count($p['candidates'])),
                ],
            ]
        );

        return self::SUCCESS;
    }

    private function ensureBallotTemplatesExist(User $user): void
    {
        // Check if templates exist
        $questionnaireTemplate = Template::where('layout_variant', 'questionnaire')->first();
        $answerSheetTemplate = Template::where('layout_variant', 'answer-sheet')->first();
        $answerSheetGridTemplate = Template::where('layout_variant', 'answer-sheet-grid')->first();

        if ($questionnaireTemplate && $answerSheetTemplate && $answerSheetGridTemplate) {
            $this->info('✓ Ballot templates already exist');
            return;
        }

        $this->warn('Ballot templates missing, running template seeder...');
        
        // Run the template seeder
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\TemplateSeeder',
        ]);

        $this->info('✓ Ballot templates created');
    }

    /**
     * Build grid-based data structure from mapping.yaml
     * 
     * Organizes candidates into rows and columns matching the mapping layout
     */
    private function buildGridFromMapping(array $mappingConfig, array $electionConfig): array
    {
        // Build candidate lookup: code => candidate data
        $candidateLookup = [];
        foreach ($electionConfig['candidates'] ?? [] as $positionCode => $candidates) {
            foreach ($candidates as $candidate) {
                $candidateLookup[$candidate['code']] = [
                    'code' => $candidate['code'],
                    'name' => $candidate['name'],
                    'party' => $candidate['alias'] ?? null,
                    'position_code' => $positionCode,
                ];
            }
        }

        // Build grid from mapping marks
        $grid = [];
        $rows = [];
        $currentPosition = null;
        $currentPositionTitle = null;
        
        foreach ($mappingConfig['marks'] as $mark) {
            $key = $mark['key']; // e.g., 'A1', 'B2'
            $candidateCode = $mark['value']; // e.g., 'LD_001'
            
            // Extract row and column from key
            preg_match('/^([A-Z])(\d+)$/', $key, $matches);
            if (!isset($matches[1], $matches[2])) {
                continue;
            }
            
            $row = $matches[1];
            $col = (int)$matches[2];
            
            // Get position info from candidate
            $positionCode = $candidateLookup[$candidateCode]['position_code'] ?? null;
            
            // Initialize row if needed
            if (!isset($rows[$row])) {
                // Find position title for this row
                $positionTitle = '';
                if ($positionCode) {
                    foreach ($electionConfig['positions'] as $pos) {
                        if ($pos['code'] === $positionCode) {
                            $positionTitle = $pos['name'];
                            break;
                        }
                    }
                }
                
                // Check if this is a new position (first row of position)
                $isNewPosition = ($positionCode !== $currentPosition);
                $currentPosition = $positionCode;
                $currentPositionTitle = $positionTitle;
                
                $rows[$row] = [
                    'row' => $row,
                    'columns' => [],
                    'position_code' => $positionCode,
                    'position_title' => $positionTitle,
                    'is_new_position' => $isNewPosition,
                ];
            }
            
            // Get candidate data
            $candidateData = $candidateLookup[$candidateCode] ?? [
                'code' => $candidateCode,
                'name' => 'Unknown',
                'party' => null,
                'position_code' => null,
            ];
            
            // Extract number from candidate code (e.g., "LD_001" -> 1, "JD_012" -> 12)
            $number = $col; // Default to column number
            if (preg_match('/_0*(\d+)$/', $candidateCode, $numMatches)) {
                $number = (int)$numMatches[1];
            }
            
            // Add bubble to row
            $rows[$row]['columns'][] = [
                'id' => $key,
                'column' => $col,
                'number' => $number,
                'candidate' => $candidateData,
            ];
        }
        
        // Sort rows alphabetically and columns numerically
        ksort($rows);
        foreach ($rows as &$row) {
            usort($row['columns'], fn($a, $b) => $a['column'] <=> $b['column']);
        }
        
        return [
            'rows' => array_values($rows),
            'total_rows' => count($rows),
            'max_columns' => max(array_map(fn($r) => count($r['columns']), $rows)),
        ];
    }
}
