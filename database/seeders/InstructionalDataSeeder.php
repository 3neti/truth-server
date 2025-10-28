<?php

namespace Database\Seeders;

use App\Models\TemplateData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Symfony\Component\Yaml\Yaml;

class InstructionalDataSeeder extends Seeder
{
    /**
     * Seed instructional template data examples.
     */
    public function run(): void
    {
        $this->command->info('Creating instructional template data examples...');

        $user = User::where('email', 'admin@disburse.cash')->first();

        if (!$user) {
            $this->command->error('Admin user not found. Run UserSeeder first.');
            return;
        }

        // Election examples
        $this->createElectionExamples($user);

        // Philippine National Election Ballot
        $this->createPhilippineElectionBallot($user);

        // Barangay Election Ballot
        $this->createBarangayElectionBallot($user);

        // Survey examples
        $this->createSurveyExamples($user);

        // Test examples
        $this->createTestExamples($user);

        $this->command->info('✅ Instructional template data created successfully!');
    }

    private function createElectionExamples(User $user): void
    {
        // Getting Started: Simple Election
        TemplateData::create([
            'document_id' => 'DEMO-ELECTION-001',
            'name' => 'Getting Started: Simple Election',
            'template_ref' => 'local:election-ballot/standard',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:election-ballot/standard',
                ],
                'election_name' => 'My First Election',
                'precinct' => '001-A',
                'date' => '2025-05-15',
                'positions' => [
                    [
                        'code' => 'PRES',
                        'title' => 'President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['code' => 'P01', 'name' => 'Candidate A', 'party' => 'Party 1'],
                            ['code' => 'P02', 'name' => 'Candidate B', 'party' => 'Party 2'],
                        ]
                    ],
                ],
            ],
            'user_id' => $user->id,
        ]);

        // Example: School Counci        // Example: School Council Election
        TemplateData::create([
            'document_id' => 'DEMO-ELECTION-002',
            'name' => 'Example: School Council Election',
            'template_ref' => 'local:election-ballot/compact',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:election-ballot/compact',
                ],
                'election_name' => 'School Council Elections 2025',
                'precinct' => 'Grade-10',
                'date' => '2025-03-15',
                'positions' => [
                    [
                        'code' => 'PRES',
                        'title' => 'President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['code' => 'P01', 'name' => 'Sarah Johnson', 'party' => 'Leadership Party'],
                            ['code' => 'P02', 'name' => 'Michael Chen', 'party' => 'Unity Party'],
                            ['code' => 'P03', 'name' => 'Emma Rodriguez', 'party' => 'Independent'],
                        ]
                    ],
                    [
                        'code' => 'VP',
                        'title' => 'Vice President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['code' => 'VP01', 'name' => 'David Kim', 'party' => 'Leadership Party'],
                            ['code' => 'VP02', 'name' => 'Lisa Martinez', 'party' => 'Unity Party'],
                        ]
                    ],
                    [
                        'code' => 'SEC',
                        'title' => 'Secretary',
                        'max_selections' => 1,
                        'candidates' => [
                            ['code' => 'S01', 'name' => 'Alex Thompson', 'party' => 'Leadership Party'],
                            ['code' => 'S02', 'name' => 'Maya Patel', 'party' => 'Independent'],
                        ]
                    ],
                ],
            ],
            'user_id' => $user->id,
        ]);
    }

    private function createSurveyExamples(User $user): void
    {
        // Getting Started: Basic Survey
        TemplateData::create([
            'document_id' => 'DEMO-SURVEY-001',
            'name' => 'Getting Started: Basic Survey',
            'template_ref' => 'local:survey-form/standard',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:survey-form/standard',
                ],
                'survey_title' => 'My First Survey',
                'description' => 'A simple example survey',
                'date' => '2025-10-24',
                'questions' => [
                    [
                        'number' => 1,
                        'type' => 'multiple_choice',
                        'text' => 'How do you rate this?',
                        'options' => ['Excellent', 'Good', 'Fair', 'Poor']
                    ],
                    [
                        'number' => 2,
                        'type' => 'multiple_choice',
                        'text' => 'Would you recommend it?',
                        'options' => ['Yes', 'Maybe', 'No']
                    ],
                ],
            ],
            'user_id' => $user->id,
        ]);

        // Example: Customer Sati        // Example: Customer Satisfaction Survey
        TemplateData::create([
            'document_id' => 'DEMO-SURVEY-002',
            'name' => 'Example: Customer Satisfaction Survey',
            'template_ref' => 'local:survey-form/grid',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:survey-form/grid',
                ],
                'survey_title' => 'Customer Satisfaction Survey Q4 2024',
                'description' => 'Help us improve our services by sharing your feedback',
                'date' => '2024-12-15',
                'questions' => [
                    [
                        'number' => 1,
                        'type' => 'multiple_choice',
                        'text' => 'How satisfied are you with our service?',
                        'options' => ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied', 'Very Dissatisfied']
                    ],
                    [
                        'number' => 2,
                        'type' => 'multiple_choice',
                        'text' => 'How would you rate our product quality?',
                        'options' => ['Excellent', 'Good', 'Average', 'Below Average', 'Poor']
                    ],
                    [
                        'number' => 3,
                        'type' => 'multiple_choice',
                        'text' => 'How responsive was our customer support?',
                        'options' => ['Very Responsive', 'Responsive', 'Somewhat Responsive', 'Not Responsive']
                    ],
                    [
                        'number' => 4,
                        'type' => 'multiple_choice',
                        'text' => 'Would you recommend us to friends or colleagues?',
                        'options' => ['Definitely', 'Probably', 'Not Sure', 'Probably Not', 'Definitely Not']
                    ],
                ],
            ],
            'user_id' => $user->id,
        ]);
    }

    private function createPhilippineElectionBallot(User $user): void
    {
        // Load election and precinct configuration
        $electionConfig = json_decode(file_get_contents(config_path('election.json')), true);
        $precinctConfig = Yaml::parseFile(config_path('precinct.yaml'));
        $mappingConfig = Yaml::parseFile(config_path('mapping.yaml'));

        // Build a lookup map: candidate code => ordinal number from mapping (optional)
        $candidateNumbers = [];
        foreach ($mappingConfig['marks'] as $mark) {
            $key = $mark['key']; // e.g., 'A1', 'B2', 'C10'
            $candidateCode = $mark['value']; // e.g., 'LD_001'
            // Extract the numeric part from the key (e.g., '1' from 'A1', '10' from 'C10')
            preg_match('/[A-Z]+(\d+)/', $key, $matches);
            if (isset($matches[1])) {
                $candidateNumbers[$candidateCode] = (int)$matches[1];
            }
        }

        // Build positions array from election config
        $positions = [];
        foreach ($electionConfig['positions'] as $position) {
            $positionData = [
                'code' => $position['code'],
                'title' => $position['name'],
                'level' => $position['level'],
                'max_selections' => $position['count'],
                'candidates' => [],
            ];

            // Add candidates for this position with auto-assigned sequential ordinal numbers
            if (isset($electionConfig['candidates'][$position['code']])) {
                $ordinalNumber = 1; // Start numbering from 1 for each position
                foreach ($electionConfig['candidates'][$position['code']] as $candidate) {
                    $candidateCode = $candidate['code'];
                    $positionData['candidates'][] = [
                        'code' => $candidateCode,
                        'name' => $candidate['name'],
                        'party' => $candidate['alias'] ?? null,
                        // Use mapping if available, otherwise auto-assign sequential number
                        'number' => $candidateNumbers[$candidateCode] ?? $ordinalNumber,
                    ];
                    $ordinalNumber++;
                }
            }

            $positions[] = $positionData;
        }

        // Create Questionnaire (Candidate List) - for reference/posting
        TemplateData::create([
            'document_id' => 'PH-2025-QUESTIONNAIRE-CURRIMAO-001',
            'name' => 'Philippine Elections 2025 - Candidate List (Questionnaire)',
            'template_ref' => 'local:election-ballot/questionnaire',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:election-ballot/questionnaire',
                    'type' => 'questionnaire',
                ],
                'election_name' => 'Philippine National and Local Elections 2025',
                'precinct_code' => $precinctConfig['code'],
                'precinct_location' => $precinctConfig['location_name'],
                'latitude' => $precinctConfig['latitude'],
                'longitude' => $precinctConfig['longitude'],
                'date' => '2025-05-12',
                'positions' => $positions,
                'electoral_inspectors' => $precinctConfig['electoral_inspectors'],
            ],
            'user_id' => $user->id,
        ]);

        // Create Answer Sheet (Ballot) - for voter marking
        TemplateData::create([
            'document_id' => 'PH-2025-BALLOT-CURRIMAO-001',
            'name' => 'Philippine Elections 2025 - Official Ballot (Answer Sheet)',
            'template_ref' => 'local:election-ballot/answer-sheet',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:election-ballot/answer-sheet',
                    'type' => 'ballot',
                ],
                'election_name' => 'Philippine National and Local Elections 2025',
                'precinct_code' => $precinctConfig['code'],
                'precinct_location' => $precinctConfig['location_name'],
                'date' => '2025-05-12',
                'positions' => $positions,
                'instructions' => [
                    'Use a black or blue pen to shade the oval completely.',
                    'Do not overvote. Overvoting will invalidate your choices for that position.',
                    'To change your vote, ask for a new ballot from the poll worker.',
                    'Do not make any stray marks on this ballot.',
                ],
            ],
            'user_id' => $user->id,
        ]);

        $this->command->info('  ✓ Philippine election ballot created with ' . count($positions) . ' positions');
    }

    private function createBarangayElectionBallot(User $user): void
    {
        // Parse the barangay candidates CSV data
        $csvPath = resource_path('docs/data/BARANGAY_CANDIDATES - BOKIAWAN, HUNGDUAN, IFUGAO.md');
        $csvData = array_map('str_getcsv', file($csvPath));
        array_shift($csvData); // Remove header row

        // Organize candidates by position
        $positions = [];
        $positionMap = [];
        
        foreach ($csvData as $row) {
            if (empty($row[0])) continue;
            
            $position = trim($row[0]);
            $number = (int)$row[1];
            $fullName = trim($row[2]);
            $nickname = trim($row[4]);
            
            if (!isset($positionMap[$position])) {
                $positionCode = strtoupper(str_replace([' ', ','], ['_', ''], $position));
                $positionMap[$position] = [
                    'code' => $positionCode,
                    'title' => $position,
                    'level' => 'barangay',
                    'max_selections' => $position === 'PUNONG BARANGAY' ? 1 : 7,
                    'candidates' => [],
                ];
            }
            
            $positionMap[$position]['candidates'][] = [
                'code' => $positionMap[$position]['code'] . '_' . str_pad($number, 3, '0', STR_PAD_LEFT),
                'name' => $fullName,
                'party' => $nickname,
                'number' => $number,
            ];
        }
        
        $positions = array_values($positionMap);

        // Create Questionnaire (Candidate List)
        TemplateData::updateOrCreate(
            ['document_id' => 'BRGY-2025-QUESTIONNAIRE-BOKIAWAN-001'],
            [
                'name' => 'Barangay Elections 2025 - Candidate List (Questionnaire)',
                'template_ref' => 'local:election-ballot/questionnaire',
                'json_data' => [
                    'document' => [
                        'template_ref' => 'local:election-ballot/questionnaire',
                        'type' => 'questionnaire',
                    ],
                    'election_name' => 'Barangay Elections 2025',
                    'precinct_code' => 'BOKIAWAN-001',
                    'precinct_location' => 'Bokiawan, Hungduan, Ifugao',
                    'date' => '2025-10-28',
                    'positions' => $positions,
                    'electoral_inspectors' => [
                        ['id' => 'uuid-brgy-1', 'name' => 'Barangay Inspector 1', 'role' => 'chairperson'],
                        ['id' => 'uuid-brgy-2', 'name' => 'Barangay Inspector 2', 'role' => 'member'],
                        ['id' => 'uuid-brgy-3', 'name' => 'Barangay Inspector 3', 'role' => 'member'],
                    ],
                ],
                'user_id' => $user->id,
            ]
        );

        // Create Answer Sheet (Ballot)
        TemplateData::updateOrCreate(
            ['document_id' => 'BRGY-2025-BALLOT-BOKIAWAN-001'],
            [
                'name' => 'Barangay Elections 2025 - Official Ballot (Answer Sheet)',
                'template_ref' => 'local:election-ballot/answer-sheet',
                'json_data' => [
                    'document' => [
                        'template_ref' => 'local:election-ballot/answer-sheet',
                        'type' => 'ballot',
                    ],
                    'election_name' => 'Barangay Elections 2025',
                    'precinct_code' => 'BOKIAWAN-001',
                    'precinct_location' => 'Bokiawan, Hungduan, Ifugao',
                    'date' => '2025-10-28',
                    'positions' => $positions,
                    'instructions' => [
                        'Use a black or blue pen to shade the oval completely.',
                        'Vote for ONE (1) Punong Barangay only.',
                        'Vote for not more than SEVEN (7) Sangguniang Barangay Members.',
                        'Do not overvote. Overvoting will invalidate your choices for that position.',
                    ],
                ],
                'user_id' => $user->id,
            ]
        );

        $this->command->info('  ✓ Barangay election ballot created with ' . count($positions) . ' positions');
    }

    private function createTestExamples(User $user): void
    {
        // Getting Started: Simple Quiz
        TemplateData::create([
            'document_id' => 'DEMO-TEST-001',
            'name' => 'Getting Started: Simple Quiz',
            'template_ref' => 'local:test-exam/standard',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:test-exam/standard',
                ],
                'test_title' => 'My First Quiz',
                'subject' => 'General Knowledge',
                'date' => '2025-10-24',
                'duration' => '15 minutes',
                'questions' => [
                    [
                        'number' => 1,
                        'text' => 'What is 5 + 3?',
                        'points' => 1,
                        'options' => ['6', '7', '8', '9']
                    ],
                    [
                        'number' => 2,
                        'text' => 'What color is the sky?',
                        'points' => 1,
                        'options' => ['Red', 'Blue', 'Green', 'Yellow']
                    ],
                ],
            ],
            'user_id' => $user->id,
        ]);

        // Example: Math Assessme        // Example: Math Assessment
        TemplateData::create([
            'document_id' => 'DEMO-TEST-002',
            'name' => 'Example: Math Assessment',
            'template_ref' => 'local:test-exam/multiple-choice',
            'json_data' => [
                'document' => [
                    'template_ref' => 'local:test-exam/multiple-choice',
                ],
                'test_title' => 'Grade 8 Mathematics Assessment',
                'subject' => 'Mathematics',
                'date' => '2025-03-20',
                'duration' => '60 minutes',
                'questions' => [
                    [
                        'number' => 1,
                        'text' => 'Solve for x: 2x + 5 = 13',
                        'points' => 2,
                        'options' => ['x = 3', 'x = 4', 'x = 5', 'x = 6']
                    ],
                    [
                        'number' => 2,
                        'text' => 'What is the area of a rectangle with length 8 cm and width 5 cm?',
                        'points' => 2,
                        'options' => ['13 cm²', '26 cm²', '40 cm²', '45 cm²']
                    ],
                    [
                        'number' => 3,
                        'text' => 'What is 15% of 200?',
                        'points' => 2,
                        'options' => ['20', '25', '30', '35']
                    ],
                    [
                        'number' => 4,
                        'text' => 'If a triangle has angles of 60° and 70°, what is the third angle?',
                        'points' => 2,
                        'options' => ['40°', '50°', '60°', '70°']
                    ],
                    [
                        'number' => 5,
                        'text' => 'What is the square root of 144?',
                        'points' => 2,
                        'options' => ['10', '11', '12', '13']
                    ],
                ],
            ],
            'user_id' => $user->id,
        ]);
    }
}
