<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test directory
    $this->testDir = sys_get_temp_dir() . '/test-render-' . uniqid();
    mkdir($this->testDir, 0755, true);
});

afterEach(function () {
    // Cleanup
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('renders ballot with filled bubbles', function () {
    // Create blank ballot image
    $blankBallot = $this->testDir . '/blank.png';
    $img = imagecreatetruecolor(200, 200);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $blankBallot);
    imagedestroy($img);
    
    // Create votes file
    $votesFile = $this->testDir . '/votes.json';
    $votes = [
        'votes' => [
            'PRESIDENT' => '001',
            'VICE_PRESIDENT' => '002',
        ],
        'fill_intensity' => 1.0,
    ];
    File::put($votesFile, json_encode($votes));
    
    // Create coordinates file
    $coordsFile = $this->testDir . '/coords.json';
    $coordinates = [
        'bubble' => [
            'PRESIDENT_001' => [
                'center_x' => 50,
                'center_y' => 50,
                'diameter' => 10,
            ],
            'VICE_PRESIDENT_002' => [
                'center_x' => 100,
                'center_y' => 100,
                'diameter' => 10,
            ],
        ],
    ];
    File::put($coordsFile, json_encode($coordinates));
    
    // Output file
    $outputFile = $this->testDir . '/filled.png';
    
    // Run command
    $this->artisan('simulation:render-ballot', [
        'votes-file' => $votesFile,
        'coordinates-file' => $coordsFile,
        '--blank-ballot' => $blankBallot,
        '--output' => $outputFile,
    ])->assertExitCode(0);
    
    // Assert output file created
    expect(File::exists($outputFile))->toBeTrue();
    
    // Assert output is different from blank (has marks)
    $blankSize = filesize($blankBallot);
    $filledSize = filesize($outputFile);
    expect($filledSize)->not->toBe($blankSize);
});

it('renders ballot with multiple candidates per position', function () {
    // Create blank ballot image
    $blankBallot = $this->testDir . '/blank.png';
    $img = imagecreatetruecolor(200, 200);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $blankBallot);
    imagedestroy($img);
    
    // Create votes file with array of candidates
    $votesFile = $this->testDir . '/votes.json';
    $votes = [
        'votes' => [
            'SENATOR' => ['001', '002', '003'],
        ],
    ];
    File::put($votesFile, json_encode($votes));
    
    // Create coordinates file
    $coordsFile = $this->testDir . '/coords.json';
    $coordinates = [
        'bubble' => [
            'SENATOR_001' => ['center_x' => 50, 'center_y' => 50, 'diameter' => 10],
            'SENATOR_002' => ['center_x' => 100, 'center_y' => 50, 'diameter' => 10],
            'SENATOR_003' => ['center_x' => 150, 'center_y' => 50, 'diameter' => 10],
        ],
    ];
    File::put($coordsFile, json_encode($coordinates));
    
    // Output file
    $outputFile = $this->testDir . '/filled.png';
    
    // Run command
    $this->artisan('simulation:render-ballot', [
        'votes-file' => $votesFile,
        'coordinates-file' => $coordsFile,
        '--blank-ballot' => $blankBallot,
        '--output' => $outputFile,
    ])->assertExitCode(0);
    
    // Assert output file created
    expect(File::exists($outputFile))->toBeTrue();
});

it('fails when blank ballot is not provided', function () {
    $votesFile = $this->testDir . '/votes.json';
    $coordsFile = $this->testDir . '/coords.json';
    
    File::put($votesFile, json_encode(['votes' => []]));
    File::put($coordsFile, json_encode(['bubble' => []]));
    
    $this->artisan('simulation:render-ballot', [
        'votes-file' => $votesFile,
        'coordinates-file' => $coordsFile,
    ])->assertExitCode(1);
});

it('fails when votes file not found', function () {
    $this->artisan('simulation:render-ballot', [
        'votes-file' => '/nonexistent/votes.json',
        'coordinates-file' => '/nonexistent/coords.json',
        '--blank-ballot' => '/nonexistent/blank.png',
    ])->assertExitCode(1);
});

it('respects custom fill intensity', function () {
    // Create blank ballot image
    $blankBallot = $this->testDir . '/blank.png';
    $img = imagecreatetruecolor(200, 200);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $blankBallot);
    imagedestroy($img);
    
    // Create votes file
    $votesFile = $this->testDir . '/votes.json';
    $votes = [
        'votes' => [
            'PRESIDENT' => '001',
        ],
        'fill_intensity' => 0.5, // 50% gray
    ];
    File::put($votesFile, json_encode($votes));
    
    // Create coordinates file
    $coordsFile = $this->testDir . '/coords.json';
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
    
    // Output file
    $outputFile = $this->testDir . '/filled.png';
    
    // Run command
    $this->artisan('simulation:render-ballot', [
        'votes-file' => $votesFile,
        'coordinates-file' => $coordsFile,
        '--blank-ballot' => $blankBallot,
        '--output' => $outputFile,
    ])->assertExitCode(0);
    
    // Assert output file created
    expect(File::exists($outputFile))->toBeTrue();
});
