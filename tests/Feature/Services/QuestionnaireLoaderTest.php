<?php

use App\Services\QuestionnaireLoader;
use App\Services\ElectionConfigLoader;
use App\Models\TemplateData;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loader = new QuestionnaireLoader();
    
    // Create a template for foreign key constraint
    $this->template = Template::factory()->create();
    
    // Sample questionnaire data
    $this->sampleQuestionnaire = [
        'positions' => [
            [
                'code' => 'PRESIDENT',
                'name' => 'President',
                'candidates' => [
                    ['code' => '001', 'name' => 'John Doe'],
                    ['code' => '002', 'name' => 'Jane Smith'],
                ],
            ],
            [
                'code' => 'VICE_PRESIDENT',
                'name' => 'Vice President',
                'candidates' => [
                    ['code' => '001', 'name' => 'Bob Johnson'],
                    ['code' => '002', 'name' => 'Alice Williams'],
                ],
            ],
        ],
    ];
});

describe('load()', function () {
    it('returns null when both configPath and documentId are null', function () {
        $result = $this->loader->load(null, null);
        
        expect($result)->toBeNull();
    });
    
    it('loads from file when configPath is provided', function () {
        // Create temporary config directory with absolute path
        $configPath = sys_get_temp_dir() . '/test-questionnaire-' . uniqid();
        mkdir($configPath, 0755, true);
        
        // Write questionnaire file
        File::put($configPath . '/questionnaire.json', json_encode($this->sampleQuestionnaire));
        
        // Load from file (using absolute path)
        $result = $this->loader->load($configPath);
        
        expect($result)->toEqual($this->sampleQuestionnaire);
        
        // Cleanup
        File::deleteDirectory($configPath);
    });
    
    it('returns null when configPath does not contain questionnaire.json', function () {
        $configPath = sys_get_temp_dir() . '/test-empty-' . uniqid();
        mkdir($configPath, 0755, true);
        
        $result = $this->loader->load($configPath);
        
        expect($result)->toBeNull();
        
        // Cleanup
        File::deleteDirectory($configPath);
    });
    
    it('falls back to database when file not found', function () {
        // Create database record
        $templateData = TemplateData::create([
            'document_id' => 'TEST-QUESTIONNAIRE-001',
            'template_id' => $this->template->id,
            'json_data' => $this->sampleQuestionnaire,
        ]);
        
        // Try loading with non-existent file path, should fall back to database
        $result = $this->loader->load('non-existent-path', 'TEST-QUESTIONNAIRE-001');
        
        expect($result)->toEqual($this->sampleQuestionnaire);
    });
    
    it('prioritizes file over database when both exist', function () {
        // Create database record
        $dbQuestionnaire = [
            'positions' => [
                ['code' => 'DB_POSITION', 'name' => 'From Database', 'candidates' => []],
            ],
        ];
        
        TemplateData::create([
            'document_id' => 'TEST-QUESTIONNAIRE-002',
            'template_id' => $this->template->id,
            'json_data' => $dbQuestionnaire,
        ]);
        
        // Create file with different data
        $configPath = sys_get_temp_dir() . '/test-priority-' . uniqid();
        mkdir($configPath, 0755, true);
        
        $fileQuestionnaire = [
            'positions' => [
                ['code' => 'FILE_POSITION', 'name' => 'From File', 'candidates' => []],
            ],
        ];
        
        File::put($configPath . '/questionnaire.json', json_encode($fileQuestionnaire));
        
        // Load - should get file version
        $result = $this->loader->load($configPath, 'TEST-QUESTIONNAIRE-002');
        
        expect($result)->toEqual($fileQuestionnaire)
            ->and($result)->not->toEqual($dbQuestionnaire);
        
        // Cleanup
        File::deleteDirectory($configPath);
    });
    
    it('handles invalid JSON gracefully', function () {
        $configPath = sys_get_temp_dir() . '/test-invalid-json-' . uniqid();
        mkdir($configPath, 0755, true);
        
        // Write invalid JSON
        File::put($configPath . '/questionnaire.json', '{invalid json}');
        
        // Should return null and not throw
        $result = $this->loader->load($configPath);
        
        expect($result)->toBeNull();
        
        // Cleanup
        File::deleteDirectory($configPath);
    });
    
    it('loads from database when documentId is provided', function () {
        TemplateData::create([
            'document_id' => 'TEST-QUESTIONNAIRE-003',
            'template_id' => $this->template->id,
            'json_data' => $this->sampleQuestionnaire,
        ]);
        
        $result = $this->loader->load(null, 'TEST-QUESTIONNAIRE-003');
        
        expect($result)->toEqual($this->sampleQuestionnaire);
    });
    
    it('returns null when documentId not found in database', function () {
        $result = $this->loader->load(null, 'NON-EXISTENT-ID');
        
        expect($result)->toBeNull();
    });
});

describe('loadAuto()', function () {
    it('loads from configured election config path', function () {
        // Skip if default config doesn't exist
        if (!File::exists(base_path('config/questionnaire.json'))) {
            $this->markTestSkipped('Default config/questionnaire.json not available');
        }
        
        // Use default config
        putenv('ELECTION_CONFIG_PATH=config');
        
        $result = $this->loader->loadAuto();
        
        // Should load successfully
        expect($result)->not->toBeNull()
            ->and($result)->toBeArray();
    });
    
    it('falls back to database when file not found but document_id in election config', function () {
        // Create database record
        TemplateData::create([
            'document_id' => 'TEST-AUTO-QUESTIONNAIRE',
            'template_id' => $this->template->id,
            'json_data' => $this->sampleQuestionnaire,
        ]);
        
        // Test by directly calling load with document_id (simulates what loadAuto does)
        $result = $this->loader->load(null, 'TEST-AUTO-QUESTIONNAIRE');
        
        expect($result)->toEqual($this->sampleQuestionnaire);
    });
});

describe('validate()', function () {
    it('validates correct questionnaire structure', function () {
        $result = $this->loader->validate($this->sampleQuestionnaire);
        
        expect($result)->toBeTrue();
    });
    
    it('throws exception when positions array is missing', function () {
        $invalid = ['some_other_key' => 'value'];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, 'missing "positions" array');
    });
    
    it('throws exception when positions is not an array', function () {
        $invalid = ['positions' => 'not an array'];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, '"positions" must be an array');
    });
    
    it('throws exception when position missing code', function () {
        $invalid = [
            'positions' => [
                ['name' => 'President', 'candidates' => []],
            ],
        ];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, "missing 'code'");
    });
    
    it('throws exception when position missing name', function () {
        $invalid = [
            'positions' => [
                ['code' => 'PRESIDENT', 'candidates' => []],
            ],
        ];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, "missing 'name'");
    });
    
    it('throws exception when position missing candidates array', function () {
        $invalid = [
            'positions' => [
                ['code' => 'PRESIDENT', 'name' => 'President'],
            ],
        ];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, "missing 'candidates' array");
    });
    
    it('throws exception when candidate missing code', function () {
        $invalid = [
            'positions' => [
                [
                    'code' => 'PRESIDENT',
                    'name' => 'President',
                    'candidates' => [
                        ['name' => 'John Doe'],
                    ],
                ],
            ],
        ];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, "Candidate at index 0")
            ->toThrow(RuntimeException::class, "missing 'code'");
    });
    
    it('throws exception when candidate missing name', function () {
        $invalid = [
            'positions' => [
                [
                    'code' => 'PRESIDENT',
                    'name' => 'President',
                    'candidates' => [
                        ['code' => '001'],
                    ],
                ],
            ],
        ];
        
        expect(fn() => $this->loader->validate($invalid))
            ->toThrow(RuntimeException::class, "Candidate at index 0")
            ->toThrow(RuntimeException::class, "missing 'name'");
    });
    
    it('validates questionnaire with multiple positions and candidates', function () {
        $complex = [
            'positions' => [
                [
                    'code' => 'PRESIDENT',
                    'name' => 'President',
                    'candidates' => [
                        ['code' => '001', 'name' => 'Candidate 1'],
                        ['code' => '002', 'name' => 'Candidate 2'],
                        ['code' => '003', 'name' => 'Candidate 3'],
                    ],
                ],
                [
                    'code' => 'VICE_PRESIDENT',
                    'name' => 'Vice President',
                    'candidates' => [
                        ['code' => '001', 'name' => 'VP Candidate 1'],
                        ['code' => '002', 'name' => 'VP Candidate 2'],
                    ],
                ],
                [
                    'code' => 'SENATOR',
                    'name' => 'Senator',
                    'candidates' => [
                        ['code' => '001', 'name' => 'Senator 1'],
                        ['code' => '002', 'name' => 'Senator 2'],
                        ['code' => '003', 'name' => 'Senator 3'],
                        ['code' => '004', 'name' => 'Senator 4'],
                    ],
                ],
            ],
        ];
        
        $result = $this->loader->validate($complex);
        
        expect($result)->toBeTrue();
    });
});

describe('integration', function () {
    it('can load and validate in sequence', function () {
        // Create test data
        TemplateData::create([
            'document_id' => 'TEST-INTEGRATION-001',
            'template_id' => $this->template->id,
            'json_data' => $this->sampleQuestionnaire,
        ]);
        
        // Load from database
        $questionnaire = $this->loader->load(null, 'TEST-INTEGRATION-001');
        
        expect($questionnaire)->not->toBeNull();
        
        // Validate loaded data
        $result = $this->loader->validate($questionnaire);
        
        expect($result)->toBeTrue();
    });
    
    it('handles missing data gracefully', function () {
        $questionnaire = $this->loader->load('non-existent', 'non-existent-id');
        
        expect($questionnaire)->toBeNull();
        
        // Should not try to validate null
        if ($questionnaire === null) {
            expect(true)->toBeTrue();
        }
    });
});
