<?php

namespace Database\Seeders;

use App\Models\TemplateData;
use App\Models\User;
use Illuminate\Database\Seeder;

class BarangayElectionSeeder extends Seeder
{
    /**
     * Seed barangay election ballot data.
     */
    public function run(): void
    {
        $this->command->info('Creating barangay election ballots...');

        $user = User::where('email', 'admin@disburse.cash')->first();

        if (!$user) {
            $this->command->error('Admin user not found. Run UserSeeder first.');
            return;
        }

        // Parse the barangay candidates CSV data
        $csvPath = resource_path('docs/data/BARANGAY_CANDIDATES - BOKIAWAN, HUNGDUAN, IFUGAO.md');
        $csvData = array_map('str_getcsv', file($csvPath));
        array_shift($csvData); // Remove header row

        // Organize candidates by position
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

        $this->command->info('✅ Barangay election ballots created!');
        $this->command->table(
            ['Position', 'Candidates'],
            [
                ['Punong Barangay', count($positionMap['PUNONG BARANGAY']['candidates'])],
                ['Sangguniang Barangay Members', count($positionMap['MEMBER, SANGGUNIANG BARANGAY']['candidates'])],
            ]
        );
    }
}
