<?php

use App\Models\Template;
use App\Models\TemplateData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test directory
    $this->testDir = sys_get_temp_dir() . '/test-overlay-' . uniqid();
    mkdir($this->testDir, 0755, true);
    
    // Create template for database loading
    $this->template = Template::factory()->create();
});

afterEach(function () {
    // Cleanup
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('creates overlay with file-based questionnaire', function () {
    // Create test files
    $ballotImage = $this->testDir . '/ballot.png';
    $resultsFile = $this->testDir . '/results.json';
    $coordsFile = $this->testDir . '/coords.json';
    $outputFile = $this->testDir . '/overlay.png';
    $configDir = $this->testDir . '/config';
    
    mkdir($configDir, 0755, true);
    
    // Create mock ballot image (1x1 white PNG)
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $ballotImage);
    imagedestroy($img);
    
    // Create mock results
    $results = [
        'document_id' => 'TEST-001',
        'results' => [
            ['id' => 'PRESIDENT_001', 'filled' => true, 'fill_ratio' => 0.95],
        ],
    ];
    File::put($resultsFile, json_encode($results));
    
    // Create mock coordinates
    $coordinates = [
        'bubble' => [
            'PRESIDENT_001' => [
                'center_x' => 50,
                'center_y' => 50,
                'diameter' => 10,
            ],
        ],
    ];
    File::put($coordsFile, json_encode($coordinates));
    
    // Create questionnaire
    $questionnaire = [
        'positions' => [
            [
                'code' => 'PRESIDENT',
                'name' => 'President',
                'candidates' => [
                    ['code' => '001', 'name' => 'John Doe'],
                ],
            ],
        ],
    ];
    File::put($configDir . '/questionnaire.json', json_encode($questionnaire));
    
    // Run command
    $this->artisan('simulation:create-overlay', [
        'ballot-image' => $ballotImage,
        'results-file' => $resultsFile,
        'coordinates-file' => $coordsFile,
        'output' => $outputFile,
        '--config-dir' => $configDir,
        '--show-legend' => true,
    ])->assertExitCode(0);
    
    // Assert output file created
    expect(File::exists($outputFile))->toBeTrue();
});

it('creates overlay with database questionnaire', function () {
    // Create test files
    $ballotImage = $this->testDir . '/ballot.png';
    $resultsFile = $this->testDir . '/results.json';
    $coordsFile = $this->testDir . '/coords.json';
    $outputFile = $this->testDir . '/overlay.png';
    
    // Create mock ballot image
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $ballotImage);
    imagedestroy($img);
    
    // Create mock results
    $results = [
        'document_id' => 'TEST-002',
        'results' => [
            ['id' => 'PRESIDENT_001', 'filled' => true, 'fill_ratio' => 0.95],
        ],
    ];
    File::put($resultsFile, json_encode($results));
    
    // Create mock coordinates
    $coordinates = [
        'bubble' => [
            'PRESIDENT_001' => [
                'center_x' => 50,
                'center_y' => 50,
                'diameter' => 10,
            ],
        ],
    ];
    File::put($coordsFile, json_encode($coordinates));
    
    // Create questionnaire in database
    $questionnaire = [
        'positions' => [
            [
                'code' => 'PRESIDENT',
                'name' => 'President',
                'candidates' => [
                    ['code' => '001', 'name' => 'Jane Smith'],
                ],
            ],
        ],
    ];
    
    TemplateData::create([
        'document_id' => 'TEST-QUESTIONNAIRE',
        'template_id' => $this->template->id,
        'json_data' => $questionnaire,
    ]);
    
    // Run command
    $this->artisan('simulation:create-overlay', [
        'ballot-image' => $ballotImage,
        'results-file' => $resultsFile,
        'coordinates-file' => $coordsFile,
        'output' => $outputFile,
        '--document-id' => 'TEST-QUESTIONNAIRE',
        '--show-legend' => true,
    ])->assertExitCode(0);
    
    // Assert output file created
    expect(File::exists($outputFile))->toBeTrue();
});

it('fails when ballot image not found', function () {
    $this->artisan('simulation:create-overlay', [
        'ballot-image' => '/nonexistent/ballot.png',
        'results-file' => '/nonexistent/results.json',
        'coordinates-file' => '/nonexistent/coords.json',
        'output' => '/tmp/output.png',
    ])->assertExitCode(1);
});

it('works without questionnaire data', function () {
    // Create test files
    $ballotImage = $this->testDir . '/ballot.png';
    $resultsFile = $this->testDir . '/results.json';
    $coordsFile = $this->testDir . '/coords.json';
    $outputFile = $this->testDir . '/overlay.png';
    
    // Create mock ballot image
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $ballotImage);
    imagedestroy($img);
    
    // Create mock results
    $results = [
        'document_id' => 'TEST-003',
        'results' => [
            ['id' => 'PRESIDENT_001', 'filled' => true, 'fill_ratio' => 0.95],
        ],
    ];
    File::put($resultsFile, json_encode($results));
    
    // Create mock coordinates
    $coordinates = [
        'bubble' => [
            'PRESIDENT_001' => [
                'center_x' => 50,
                'center_y' => 50,
                'diameter' => 10,
            ],
        ],
    ];
    File::put($coordsFile, json_encode($coordinates));
    
    // Run command without questionnaire
    $this->artisan('simulation:create-overlay', [
        'ballot-image' => $ballotImage,
        'results-file' => $resultsFile,
        'coordinates-file' => $coordsFile,
        'output' => $outputFile,
    ])->assertExitCode(0);
    
    // Assert output file created
    expect(File::exists($outputFile))->toBeTrue();
});
