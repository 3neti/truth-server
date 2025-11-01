# Definitive Refactoring Plan - Using Existing Infrastructure

## Problem Statement

The new modular scripts are reinventing the wheel. The deprecated `test-omr-appreciation.sh` works perfectly using:
- Laravel database templates
- PHP services (CompileHandlebarsTemplate, RenderTemplateSpec)
- OMRSimulator helper class
- Python appreciate.py script
- Existing TCPDF/DomPDF renderers

We're going in circles by trying to build Python-only ballot rendering when Laravel already does this.

## Root Cause Analysis

**Current (broken) approach:**
```
Python ballot-renderer.sh → PNG → ImageMagick → PDF
```
- Only renders 1 ArUco marker
- QR codes are placeholders
- No candidate names/proper formatting
- Incomplete parity

**Working (deprecated) approach:**
```
Laravel Templates (DB) → RenderTemplateSpec → TCPDF/DomPDF → PDF
                                            → coordinates.json
PDF → OMRSimulator::pdfToPng() → PNG
PNG → OMRSimulator::fillBubbles() → filled PNG
filled PNG → appreciate.py → results
results → OMRSimulator::createOverlay() → overlay PNG
```

## Definitive Plan: Wrap Laravel Test Infrastructure

### Option A: Shell Script Wrapper (RECOMMENDED)

Create `scripts/simulation/run-simulation-laravel.sh` that:

1. **Uses existing Laravel infrastructure** - NO custom Python rendering
2. **Calls Laravel actions directly** via artisan commands or tinker
3. **Leverages OMRSimulator** methods that already work
4. **Reuses appreciate.py** Python script as-is
5. **Maintains artifact structure** for compatibility

**Implementation:**

```bash
#!/bin/bash
# Wrapper around Laravel test infrastructure for simulation runs

# Step 1: Generate ballot template using Laravel
php artisan tinker --execute="
use App\\Models\\Template;
use App\\Models\\TemplateData;
use App\\Actions\\TruthTemplates\\Compilation\\CompileHandlebarsTemplate;
use App\\Actions\\TruthTemplates\\Rendering\\RenderTemplateSpec;

\$template = Template::where('layout_variant', 'ballot-simulation')->first();
\$data = TemplateData::where('document_id', 'SIMULATION-001')->first();

\$spec = CompileHandlebarsTemplate::run(\$template->handlebars_template, \$data->json_data);
\$result = RenderTemplateSpec::run(\$spec);

echo json_encode(['pdf' => \$result['pdf'], 'coords' => \$result['coords']]);
" > template_paths.json

# Step 2: Convert PDF to PNG using OMRSimulator
php artisan simulation:pdf-to-png \
    --pdf=storage/app/templates/ballot.pdf \
    --output=storage/app/simulation/blank.png

# Step 3: Fill bubbles for each scenario using OMRSimulator
php artisan simulation:fill-bubbles \
    --blank=storage/app/simulation/blank.png \
    --bubbles=A1,B3,D2 \
    --coordinates=storage/app/templates/coordinates.json \
    --output=storage/app/simulation/filled.png

# Step 4: Run appreciation using existing Python script
python3 packages/omr-appreciation/omr-python/appreciate.py \
    storage/app/simulation/filled.png \
    storage/app/templates/coordinates.json \
    --threshold 0.3 \
    --no-align

# Step 5: Generate overlay using OMRSimulator
php artisan simulation:create-overlay \
    --ballot=storage/app/simulation/filled.png \
    --results=results.json \
    --coordinates=storage/app/templates/coordinates.json \
    --output=storage/app/simulation/overlay.png
```

### Option B: Pure Laravel Test Extraction

Extract the working test logic into a dedicated Command:

```php
// app/Console/Commands/Simulation/RunSimulationCommand.php
class RunSimulationCommand extends Command
{
    protected $signature = 'simulation:run 
        {--config-dir= : Config directory}
        {--scenarios= : Scenarios to run}
        {--output= : Output directory}';
    
    public function handle()
    {
        // 1. Load template from database or config
        $template = $this->loadTemplate();
        
        // 2. Render using existing RenderTemplateSpec
        $result = RenderTemplateSpec::run($spec);
        
        // 3-5. Use OMRSimulator methods directly
        $blankPng = OMRSimulator::pdfToPng($result['pdf']);
        $filledPng = OMRSimulator::fillBubbles($blankPng, $bubbles, $coords);
        $overlay = OMRSimulator::createOverlay($filledPng, $results, $coords);
        
        // 6. Save artifacts
        $this->saveArtifacts($result);
    }
}
```

## Immediate Action Plan

### Phase 1: Create Laravel Commands (2 hours)

**New Commands:**
1. `simulation:generate-ballot` - Uses RenderTemplateSpec
2. `simulation:pdf-to-png` - Wraps OMRSimulator::pdfToPng()
3. `simulation:fill-bubbles` - Wraps OMRSimulator::fillBubbles()
4. `simulation:appreciate` - Calls appreciate.py
5. `simulation:create-overlay` - Wraps OMRSimulator::createOverlay()

All commands already have the logic in OMRAppreciationTest.php - just extract to commands.

### Phase 2: Create Orchestration Script (1 hour)

```bash
scripts/simulation/run-simulation-laravel.sh
```

Calls the Laravel commands in sequence, handles scenarios, organizes artifacts.

### Phase 3: Update run-test-suite.sh (30 min)

Change from calling `run-simulation.sh` to calling `run-simulation-laravel.sh`.

### Phase 4: Verify Parity (1 hour)

Run side-by-side:
```bash
# Old (working)
scripts/test-omr-appreciation.sh

# New (refactored)
scripts/simulation/run-test-suite.sh
```

Compare artifacts directory structure and file contents.

## Benefits of This Approach

1. ✅ **Uses proven infrastructure** - No reinventing wheels
2. ✅ **Complete ArUco markers** - TCPDF renders all 4 corners
3. ✅ **Real QR codes** - truth-qr-php package handles this
4. ✅ **Proper ballot formatting** - Handlebars templates + candidate names
5. ✅ **Fast to implement** - Extract existing working code
6. ✅ **Full parity guaranteed** - Same code paths as deprecated script
7. ✅ **Config-independent** - Can load from config files OR database
8. ✅ **Maintainable** - Single source of truth in Laravel

## Files to Create

```
app/Console/Commands/Simulation/
├── GenerateBallotCommand.php      # NEW: RenderTemplateSpec wrapper
├── PdfToPngCommand.php             # NEW: OMRSimulator::pdfToPng wrapper
├── FillBubblesCommand.php          # EXISTS: Already has this
├── AppreciateCommand.php           # NEW: appreciate.py wrapper
└── CreateOverlayCommand.php        # EXISTS: Already has this

scripts/simulation/
└── run-simulation-laravel.sh       # NEW: Orchestration using Laravel commands
```

## Files to Modify

```
scripts/simulation/run-test-suite.sh  # Change to call run-simulation-laravel.sh
```

## Files to Deprecate (keep for reference)

```
scripts/simulation/lib/ballot-renderer.sh       # Not needed - use Laravel
scripts/simulation/lib/template-generator.sh    # Not needed - use RenderTemplateSpec
```

## Decision Point

**Should we:**
- [ ] **Option A**: Create shell wrapper calling Laravel commands (faster, cleaner)
- [ ] **Option B**: Extract to single Laravel command (more integrated)
- [ ] **Option C**: Start completely fresh with minimal wrapper

**Recommendation: Option A**
- Fastest path to parity
- Leverages all existing code
- Easy to test incrementally
- Shell script is already the orchestrator

## Next Steps

1. Create `simulation:generate-ballot` command
2. Test it produces same PDF as deprecated script
3. Create remaining wrapper commands
4. Build orchestration script
5. Test full flow
6. Compare artifacts with deprecated script
7. Achieve 100% parity

**Estimated Time: 4-5 hours total**
