<?php

use Tests\Helpers\OMRSimulator;
use App\Models\Template;
use App\Models\TemplateData;
use App\Actions\TruthTemplates\Compilation\CompileHandlebarsTemplate;
use App\Actions\TruthTemplates\Rendering\RenderTemplateSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\InstructionalDataSeeder;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed database with templates and data
    // Create admin user required by seeders
    $admin = User::factory()->create([
        'email' => 'admin@disburse.cash',
        'name' => 'Admin User',
    ]);
    
    $this->seed(TemplateSeeder::class);
    $this->seed(InstructionalDataSeeder::class);
    
    $this->artifactsDir = storage_path('app/tests/artifacts/appreciation');
    if (!is_dir($this->artifactsDir)) {
        mkdir($this->artifactsDir, 0755, true);
    }
});

it('appreciates simulated Philippine ballot correctly', function () {
    // 1. Load template and data
    $template = Template::where('layout_variant', 'answer-sheet')->first();
    $data = TemplateData::where('document_id', 'PH-2025-BALLOT-CURRIMAO-001')->first();
    
    expect($template)->not->toBeNull('Template with layout_variant "answer-sheet" not found');
    expect($data)->not->toBeNull('Template data with document_id "PH-2025-BALLOT-CURRIMAO-001" not found');
    
    // 2. Compile and render
    $spec = CompileHandlebarsTemplate::run(
        $template->handlebars_template, 
        $data->json_data
    );
    
    $result = RenderTemplateSpec::run($spec);
    $pdfPath = $result['pdf'];
    $coordsPath = $result['coords'];
    
    expect($pdfPath)->toBeFile();
    expect($coordsPath)->toBeFile();
    
    // $this->info("Generated PDF: $pdfPath");
    // $this->info("Coordinates: $coordsPath");
    
    // 3. Load coordinates
    $coordinates = json_decode(file_get_contents($coordsPath), true);
    expect($coordinates)->not->toBeNull()
        ->and($coordinates)->toBeArray();
    
    // 4. Convert PDF to PNG
    $blankPng = OMRSimulator::pdfToPng($pdfPath);
    copy($blankPng, "{$this->artifactsDir}/blank_sheet.png");
    
    expect($blankPng)->toBeFile();
    // $this->info("Converted to PNG: $blankPng");
    
    // 5. Simulate answers (vote for President #1, Vice President #2, Senator #1,2,3)
    $selectedBubbles = [
        'PRESIDENT_LD_001',      // President: Leonardo DiCaprio (#1)
        'VICE-PRESIDENT_VD_002', // VP: Viola Davis (#2)
        'SENATOR_JD_001',        // Senator: Johnny Depp (#1)
        'SENATOR_ES_002',        // Senator: Emma Stone (#2)
        'SENATOR_MF_003',        // Senator: Morgan Freeman (#3)
    ];
    
    $filledPng = OMRSimulator::fillBubbles($blankPng, $selectedBubbles, $coordinates);
    copy($filledPng, "{$this->artifactsDir}/filled_sheet.png");
    
    expect($filledPng)->toBeFile();
    // $this->info("Simulated filled bubbles: $filledPng");
    
    // 6. Run appreciation
    $appreciateScript = base_path('packages/omr-appreciation/omr-python/appreciate.py');
    expect($appreciateScript)->toBeFile('appreciate.py script not found');
    
    $command = sprintf(
        'python3 %s %s %s --threshold 0.3 2>&1',
        escapeshellarg($appreciateScript),
        escapeshellarg($filledPng),
        escapeshellarg($coordsPath)
    );
    
    // $this->info("Running appreciation: $command");
    $output = shell_exec($command);
    // $this->info("Appreciation output: $output");
    
    $appreciationResult = json_decode($output, true);
    
    // Handle appreciation errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->fail("Appreciation script returned invalid JSON: $output");
    }
    
    expect($appreciationResult)->not->toBeNull()
        ->and($appreciationResult)->toBeArray()
        ->and($appreciationResult)->toHaveKey('results', 'Appreciation result missing "results" key');
    
    // 7. Assert correct marks detected
    $marks = collect($appreciationResult['results']);
    
    // $this->info(sprintf('Detected %d marks', $marks->count()));
    
    $detectedBubbles = $marks->pluck('bubble_id')->toArray();
    
    expect($marks)->toHaveCount(5, sprintf(
        'Expected 5 marks, got %d. Detected: %s', 
        $marks->count(),
        implode(', ', $detectedBubbles)
    ));
    
    // Check that all selected bubbles were detected
    foreach ($selectedBubbles as $expectedBubble) {
        expect($detectedBubbles)->toContain(
            $expectedBubble,
            "Expected bubble '$expectedBubble' was not detected"
        );
    }
    
    // 8. Generate overlay
    $overlayPath = OMRSimulator::createOverlay(
        $filledPng, 
        $appreciationResult['results'], 
        $coordinates
    );
    copy($overlayPath, "{$this->artifactsDir}/appreciation_overlay.png");
    
    expect($overlayPath)->toBeFile();
    // $this->info("Generated overlay: $overlayPath");
    
    // 9. Save report
    $reportPath = "{$this->artifactsDir}/appreciation_report.json";
    file_put_contents(
        $reportPath, 
        json_encode($appreciationResult, JSON_PRETTY_PRINT)
    );
    
    // $this->info("✓ Appreciation test passed!");
    // $this->info("Artifacts saved to: {$this->artifactsDir}");
    // $this->line('');
    // $this->line('View results:');
    // $this->line("  open {$this->artifactsDir}/appreciation_overlay.png");
    // $this->line("  cat {$this->artifactsDir}/appreciation_report.json");
})->group('appreciation', 'omr');

it('handles overvote scenario for President', function () {
    $template = Template::where('layout_variant', 'answer-sheet')->first();
    $data = TemplateData::where('document_id', 'PH-2025-BALLOT-CURRIMAO-001')->first();
    
    expect($template)->not->toBeNull();
    expect($data)->not->toBeNull();
    
    $spec = CompileHandlebarsTemplate::run($template->handlebars_template, $data->json_data);
    $result = RenderTemplateSpec::run($spec);
    
    $coordinates = json_decode(file_get_contents($result['coords']), true);
    $blankPng = OMRSimulator::pdfToPng($result['pdf']);
    
    // Fill TWO bubbles for President (max is 1 = overvote)
    $overvoteBubbles = [
        'PRESIDENT_LD_001',  // Leonardo DiCaprio
        'PRESIDENT_SJ_002',  // Scarlett Johansson - OVERVOTE!
    ];
    
    $filledPng = OMRSimulator::fillBubbles($blankPng, $overvoteBubbles, $coordinates);
    copy($filledPng, "{$this->artifactsDir}/overvote_filled.png");
    
    $appreciateScript = base_path('packages/omr-appreciation/omr-python/appreciate.py');
    $command = sprintf(
        'python3 %s %s %s --threshold 0.3 2>&1',
        escapeshellarg($appreciateScript),
        escapeshellarg($filledPng),
        escapeshellarg($result['coords'])
    );
    
    $output = shell_exec($command);
    $appreciationResult = json_decode($output, true);
    
    expect($appreciationResult)->not->toBeNull();
    
    $marks = collect($appreciationResult['results']);
    $presidentMarks = $marks->filter(fn($m) => str_starts_with($m['bubble_id'], 'PRESIDENT_'));
    
    // Should detect 2 marks for President (overvote condition)
    expect($presidentMarks)->toHaveCount(2);
    
    // $this->info("✓ Overvote correctly detected: " . $presidentMarks->count() . " marks for President");
    
    // Save overvote report
    file_put_contents(
        "{$this->artifactsDir}/overvote_report.json",
        json_encode($appreciationResult, JSON_PRETTY_PRINT)
    );
})->group('appreciation', 'omr', 'overvote');

it('handles faint marks with lower threshold', function () {
    $template = Template::where('layout_variant', 'answer-sheet')->first();
    $data = TemplateData::where('document_id', 'PH-2025-BALLOT-CURRIMAO-001')->first();
    
    expect($template)->not->toBeNull();
    expect($data)->not->toBeNull();
    
    $spec = CompileHandlebarsTemplate::run($template->handlebars_template, $data->json_data);
    $result = RenderTemplateSpec::run($spec);
    
    $coordinates = json_decode(file_get_contents($result['coords']), true);
    $blankPng = OMRSimulator::pdfToPng($result['pdf']);
    
    // Fill bubbles with 50% intensity (faint marks)
    $faintBubbles = [
        'PRESIDENT_LD_001',
    ];
    
    $filledPng = OMRSimulator::fillBubbles(
        $blankPng, 
        $faintBubbles, 
        $coordinates,
        300, // DPI
        0.5  // 50% fill intensity
    );
    copy($filledPng, "{$this->artifactsDir}/faint_filled.png");
    
    $appreciateScript = base_path('packages/omr-appreciation/omr-python/appreciate.py');
    $command = sprintf(
        'python3 %s %s %s --threshold 0.2 2>&1',  // Lower threshold for faint marks
        escapeshellarg($appreciateScript),
        escapeshellarg($filledPng),
        escapeshellarg($result['coords'])
    );
    
    $output = shell_exec($command);
    $appreciationResult = json_decode($output, true);
    
    expect($appreciationResult)->not->toBeNull();
    
    $marks = collect($appreciationResult['results']);
    
    // Should still detect the faint mark with lower threshold
    expect($marks)->toHaveCount(1);
    
    // Confidence should be lower than full mark
    if (isset($marks[0]['confidence'])) {
        $confidence = $marks[0]['confidence'];
        expect($confidence)->toBeLessThan(1.0)
            ->and($confidence)->toBeGreaterThan(0.2);
        
        // $this->info(sprintf('✓ Faint mark detected with confidence: %.2f', $confidence));
    }
    
    file_put_contents(
        "{$this->artifactsDir}/faint_report.json",
        json_encode($appreciationResult, JSON_PRETTY_PRINT)
    );
})->group('appreciation', 'omr', 'faint');
