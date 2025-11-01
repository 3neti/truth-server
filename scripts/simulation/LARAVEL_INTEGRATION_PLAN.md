# Laravel Integration Plan for Simulation Script

## Overview

Integrate Laravel test infrastructure (`OMRSimulator`, `Template`, `TemplateData`) into `scripts/simulation/run-simulation.sh` to leverage existing PHP classes while supporting **both** file-based config and database sources.

## Goals

1. Replace bash/Python-only ballot generation with Laravel test infrastructure
2. Support dual-mode config loading: **files OR database** (not instead, but in addition)
3. Maintain existing bash script structure for modularity
4. Enable rich overlay generation with candidate names from either source

## Architecture Principles

### Config Loading Priority
All Laravel services should follow this precedence:
1. **Explicit config path parameter** (if provided)
2. **Database** (if available and no config path specified)
3. **Fallback to default config/** directory

### Data Flow
```
Config Source (Files OR DB)
  ↓
Laravel Actions/Services
  ↓
Artisan Commands (CLI interface)
  ↓
Bash Script (orchestration)
  ↓
Test Artifacts
```

## Implementation Phases

### Phase 1: Update Core Services for Dual-Source Support

#### 1.1 Update `scripts/generate-overlay.php`
**Current Behavior**: Hardcodes database lookup
```php
$data = TemplateData::where('document_id', 'PH-2025-QUESTIONNAIRE-CURRIMAO-001')->first();
```

**New Behavior**: Support both sources
```php
// Priority:
// 1. Load from file if --config-path provided
// 2. Fall back to database
// 3. Continue without if neither available

$configPath = $argv[5] ?? null; // Optional 5th argument
$questionnaireData = null;

if ($configPath && file_exists("$configPath/questionnaire.json")) {
    // Load from file
    $questionnaireData = json_decode(
        file_get_contents("$configPath/questionnaire.json"), 
        true
    );
} else {
    // Fall back to database
    try {
        $data = TemplateData::where('document_id', 'PH-2025-QUESTIONNAIRE-CURRIMAO-001')->first();
        if ($data) {
            $questionnaireData = $data->json_data;
        }
    } catch (Exception $e) {
        fwrite(STDERR, "Warning: Could not load questionnaire: {$e->getMessage()}\n");
    }
}
```

**Changes Required**:
- Add optional 5th parameter `$configPath`
- Implement file-based questionnaire loading
- Keep database fallback intact
- Update usage message

#### 1.2 Create `app/Services/QuestionnaireLoader.php`
**Purpose**: Centralize questionnaire loading logic with dual-source support

```php
<?php

namespace App\Services;

use App\Models\TemplateData;
use Illuminate\Support\Facades\File;

class QuestionnaireLoader
{
    /**
     * Load questionnaire data from file or database
     * 
     * @param string|null $configPath Optional config directory path
     * @param string|null $documentId Database document ID (fallback)
     * @return array|null Questionnaire data or null
     */
    public function load(?string $configPath = null, ?string $documentId = null): ?array
    {
        // Try file-based loading first
        if ($configPath) {
            $questionnaireFile = base_path($configPath . '/questionnaire.json');
            
            if (File::exists($questionnaireFile)) {
                $json = File::get($questionnaireFile);
                $data = json_decode($json, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        }
        
        // Fall back to database
        if ($documentId) {
            try {
                $template = TemplateData::where('document_id', $documentId)->first();
                if ($template) {
                    return $template->json_data;
                }
            } catch (\Exception $e) {
                // Database not available or query failed
            }
        }
        
        return null;
    }
    
    /**
     * Load questionnaire with auto-detection
     * Uses ElectionConfigLoader to determine config path
     */
    public function loadAuto(): ?array
    {
        $configLoader = app(ElectionConfigLoader::class);
        $configPath = $configLoader->getConfigPath();
        
        // Try to extract document_id from config if it exists
        $documentId = null;
        try {
            $election = $configLoader->loadElection();
            $documentId = $election['questionnaire_document_id'] ?? null;
        } catch (\Exception $e) {
            // Config not available
        }
        
        return $this->load($configPath, $documentId);
    }
}
```

**Benefits**:
- Single source of truth for questionnaire loading
- Reusable across all scripts and commands
- Clear precedence order
- Graceful degradation

### Phase 2: Create Artisan Commands

#### 2.1 `app/Console/Commands/Simulation/GenerateTemplateCommand.php`
**Purpose**: Generate ballot template from config

```php
<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use App\Services\ElectionConfigLoader;
use App\Services\BubbleIdGenerator;
// ... other imports

class GenerateTemplateCommand extends Command
{
    protected $signature = 'simulation:generate-template 
                            {--config-dir= : Config directory path}
                            {--output= : Output coordinates file path}';
    
    protected $description = 'Generate ballot template from election config';
    
    public function handle(ElectionConfigLoader $configLoader, BubbleIdGenerator $bubbleIdGen)
    {
        // Set config path if provided
        if ($configDir = $this->option('config-dir')) {
            putenv("ELECTION_CONFIG_PATH={$configDir}");
        }
        
        // Load election config
        $election = $configLoader->loadElection();
        $mapping = $configLoader->loadMapping();
        
        // Generate bubble IDs
        $bubbles = $bubbleIdGen->generateFromElectionConfig($election, $mapping);
        
        // Create coordinates structure
        $coordinates = [
            'document_id' => $election['document_id'] ?? 'SIMULATION',
            'template_id' => $election['template_id'] ?? '',
            'bubble' => $bubbles,
            'fiducial' => $this->generateFiducialCoordinates(),
        ];
        
        // Write output
        $outputPath = $this->option('output') ?? 'storage/app/private/simulation/coordinates.json';
        file_put_contents($outputPath, json_encode($coordinates, JSON_PRETTY_PRINT));
        
        $this->info("Template generated: {$outputPath}");
        return 0;
    }
}
```

**Note**: This may require actual Template rendering. If so, integrate with:
- `CompileHandlebarsTemplate::run()`
- `RenderTemplateSpec::run()`
- Load template from database or file-based template system

#### 2.2 `app/Console/Commands/Simulation/RenderBallotCommand.php`
**Purpose**: Render ballot with simulated marks

```php
<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Tests\Helpers\OMRSimulator;

class RenderBallotCommand extends Command
{
    protected $signature = 'simulation:render-ballot
                            {votes-file : JSON file with votes to fill}
                            {coordinates-file : Coordinates JSON file}
                            {--blank-ballot= : Path to blank ballot PNG}
                            {--output= : Output filled ballot path}';
    
    protected $description = 'Render ballot with simulated filled bubbles';
    
    public function handle()
    {
        $votesFile = $this->argument('votes-file');
        $coordsFile = $this->argument('coordinates-file');
        $blankBallot = $this->option('blank-ballot');
        $output = $this->option('output');
        
        // Load data
        $votes = json_decode(file_get_contents($votesFile), true);
        $coordinates = json_decode(file_get_contents($coordsFile), true);
        
        // Extract bubble IDs to fill
        $bubblesToFill = [];
        foreach ($votes['votes'] as $positionCode => $candidateCodes) {
            if (!is_array($candidateCodes)) {
                $candidateCodes = [$candidateCodes];
            }
            
            foreach ($candidateCodes as $candidateCode) {
                // Construct bubble ID: POSITION_CODE_CANDIDATE_CODE
                $bubblesToFill[] = "{$positionCode}_{$candidateCode}";
            }
        }
        
        // Fill bubbles
        $filledPath = OMRSimulator::fillBubbles(
            $blankBallot,
            $bubblesToFill,
            $coordinates,
            300, // DPI
            $votes['fill_intensity'] ?? 1.0
        );
        
        // Copy to output location
        if ($output) {
            copy($filledPath, $output);
            $this->info("Ballot rendered: {$output}");
        } else {
            $this->info("Ballot rendered: {$filledPath}");
        }
        
        return 0;
    }
}
```

#### 2.3 `app/Console/Commands/Simulation/CreateOverlayCommand.php`
**Purpose**: Generate visual overlay using OMRSimulator

```php
<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Tests\Helpers\OMRSimulator;
use App\Services\QuestionnaireLoader;

class CreateOverlayCommand extends Command
{
    protected $signature = 'simulation:create-overlay
                            {ballot-image : Path to ballot image}
                            {results-file : Appreciation results JSON}
                            {coordinates-file : Coordinates JSON}
                            {output : Output overlay path}
                            {--config-dir= : Config directory for questionnaire}
                            {--document-id= : Database document ID (fallback)}
                            {--show-legend : Show legend in overlay}';
    
    protected $description = 'Create visual overlay from appreciation results';
    
    public function handle(QuestionnaireLoader $questionnaireLoader)
    {
        $ballotImage = $this->argument('ballot-image');
        $resultsFile = $this->argument('results-file');
        $coordsFile = $this->argument('coordinates-file');
        $output = $this->argument('output');
        
        // Load data
        $results = json_decode(file_get_contents($resultsFile), true);
        $coordinates = json_decode(file_get_contents($coordsFile), true);
        
        // Load questionnaire (dual-source)
        $questionnaireData = $questionnaireLoader->load(
            $this->option('config-dir'),
            $this->option('document-id')
        );
        
        // Generate overlay
        $overlayPath = OMRSimulator::createOverlay(
            $ballotImage,
            $results['results'],
            $coordinates,
            [
                'scenario' => 'simulation',
                'show_legend' => $this->option('show-legend'),
                'show_unfilled' => false,
                'output_path' => $output,
                'questionnaire' => $questionnaireData,
            ]
        );
        
        if ($overlayPath && file_exists($overlayPath)) {
            $this->info("Overlay created: {$overlayPath}");
            return 0;
        } else {
            $this->error("Overlay generation failed");
            return 1;
        }
    }
}
```

### Phase 3: Update Bash Script Libraries

#### 3.1 Update `scripts/simulation/lib/template-generator.sh`
**Current**: Uses PHP inline code or complex bash
**New**: Calls artisan command

```bash
generate_template() {
    local config_dir="$1"
    local output_file="$2"
    local log_file="${3:-/dev/null}"
    
    log_debug "Generating template from config: $config_dir" >> "$log_file" 2>&1
    
    # Call Laravel artisan command
    if php artisan simulation:generate-template \
        --config-dir="$config_dir" \
        --output="$output_file" \
        >> "$log_file" 2>&1; then
        return 0
    else
        log_error "Template generation failed" >> "$log_file" 2>&1
        return 1
    fi
}
```

#### 3.2 Update `scripts/simulation/lib/ballot-renderer.sh`
**Current**: Uses Python inline code
**New**: Calls artisan command

```bash
render_ballot() {
    local votes_file="$1"
    local coords_file="$2"
    local output_file="$3"
    local log_file="${4:-/dev/null}"
    
    # Need blank ballot - may need to generate from template first
    local blank_ballot="${TEMPLATE_DIR}/blank.png"
    
    log_debug "Rendering ballot: $votes_file" >> "$log_file" 2>&1
    
    # Call Laravel artisan command
    if php artisan simulation:render-ballot \
        "$votes_file" \
        "$coords_file" \
        --blank-ballot="$blank_ballot" \
        --output="$output_file" \
        >> "$log_file" 2>&1; then
        return 0
    else
        log_error "Ballot rendering failed" >> "$log_file" 2>&1
        return 1
    fi
}
```

#### 3.3 Update `scripts/simulation/lib/overlay-generator.sh`
**Current**: May use standalone PHP script or Python
**New**: Calls artisan command

```bash
generate_overlay() {
    local ballot_image="$1"
    local results_file="$2"
    local output_file="$3"
    local log_file="${4:-/dev/null}"
    
    log_debug "Generating overlay: $ballot_image" >> "$log_file" 2>&1
    
    # Use config dir if available
    local config_args=""
    if [ -n "${CONFIG_DIR:-}" ]; then
        config_args="--config-dir=${CONFIG_DIR}"
    fi
    
    # Call Laravel artisan command
    if php artisan simulation:create-overlay \
        "$ballot_image" \
        "$results_file" \
        "${COORDINATES_FILE}" \
        "$output_file" \
        $config_args \
        --show-legend \
        >> "$log_file" 2>&1; then
        return 0
    else
        log_error "Overlay generation failed" >> "$log_file" 2>&1
        return 1
    fi
}
```

### Phase 4: Update Main Script Configuration

#### 4.1 Update `scripts/simulation/run-simulation.sh`
Add config path propagation:

```bash
# After parsing arguments
export CONFIG_DIR="$CONFIG_DIR"
export ELECTION_CONFIG_PATH="$CONFIG_DIR"

# Make available to child processes
export TEMPLATE_DIR="${OUTPUT_DIR}/template"
export COORDINATES_FILE="${TEMPLATE_DIR}/coordinates.json"
```

### Phase 5: Maintain Backward Compatibility

#### 5.1 Keep `scripts/generate-overlay.php` Working
Update to support both calling patterns:

```bash
# Old pattern (for deprecated script)
php scripts/generate-overlay.php image.png results.json coords.json output.png

# New pattern (with config)
php scripts/generate-overlay.php image.png results.json coords.json output.png config-dir
```

#### 5.2 Support Both Appreciation Paths
The bash script should still support calling Python directly OR using Laravel when appropriate.

## Testing Strategy

### Unit Tests
- `tests/Unit/Services/QuestionnaireLoaderTest.php` - Test dual-source loading
- Test file precedence over database
- Test graceful fallback

### Feature Tests
- `tests/Feature/Console/Commands/Simulation/*Test.php` - Test each command
- Test with file-based config
- Test with database config
- Test with neither (graceful failure)

### Integration Tests
- Run full `scripts/simulation/run-simulation.sh` with file config
- Run with database-seeded config
- Verify identical outputs

## Migration Path

### Step 1: Non-Breaking Changes
1. Create `QuestionnaireLoader` service
2. Create artisan commands (new, no conflicts)
3. Update `generate-overlay.php` to support optional 5th parameter

### Step 2: Bash Library Updates
1. Update library functions one at a time
2. Keep old implementations commented for reference
3. Test each change independently

### Step 3: Integration
1. Update main `run-simulation.sh` to use new flow
2. Run side-by-side comparison with old approach
3. Validate output artifacts match

### Step 4: Cleanup
1. Remove old inline Python/PHP code from bash scripts
2. Update documentation
3. Archive deprecated patterns

## Benefits

### Immediate
- **Reusability**: Laravel commands callable from any context
- **Testability**: Full unit/feature test coverage
- **Maintainability**: Centralized logic in PHP classes
- **Type Safety**: PHP strict typing vs bash strings

### Long-term
- **API Foundation**: Commands can become HTTP endpoints
- **Job Queue**: Heavy operations can be queued
- **Event System**: Laravel events for pipeline hooks
- **Extensibility**: Easy to add new simulation scenarios

## File Structure After Implementation

```
app/
├── Console/Commands/Simulation/
│   ├── GenerateTemplateCommand.php       [NEW]
│   ├── RenderBallotCommand.php           [NEW]
│   └── CreateOverlayCommand.php          [NEW]
└── Services/
    ├── QuestionnaireLoader.php           [NEW]
    ├── ElectionConfigLoader.php          [EXISTS - no changes]
    └── BubbleIdGenerator.php             [EXISTS - may need updates]

scripts/
├── generate-overlay.php                  [UPDATED - add config param]
└── simulation/
    ├── run-simulation.sh                 [UPDATED - use artisan]
    └── lib/
        ├── template-generator.sh         [UPDATED - call artisan]
        ├── ballot-renderer.sh            [UPDATED - call artisan]
        └── overlay-generator.sh          [UPDATED - call artisan]

tests/
├── Unit/Services/
│   └── QuestionnaireLoaderTest.php       [NEW]
└── Feature/Console/Commands/Simulation/
    ├── GenerateTemplateCommandTest.php   [NEW]
    ├── RenderBallotCommandTest.php       [NEW]
    └── CreateOverlayCommandTest.php      [NEW]
```

## Configuration Examples

### File-based (config/questionnaire.json)
```json
{
  "positions": [
    {
      "code": "PRESIDENT",
      "name": "President",
      "candidates": [
        {"code": "001", "name": "John Doe"},
        {"code": "002", "name": "Jane Smith"}
      ]
    }
  ]
}
```

### Database-based (template_data table)
```sql
SELECT json_data FROM template_data 
WHERE document_id = 'PH-2025-QUESTIONNAIRE-CURRIMAO-001';
```

### Priority Resolution
1. CLI flag: `--config-dir=resources/docs/simulation/config`
2. Environment: `ELECTION_CONFIG_PATH=config`
3. Database: `TemplateData::where(...)`
4. Default: `config/questionnaire.json`

## Success Criteria

- [ ] All existing tests pass
- [ ] New artisan commands work with file config
- [ ] New artisan commands work with database config
- [ ] Bash script produces identical artifacts
- [ ] No breaking changes to existing scripts
- [ ] Full test coverage for new services/commands
- [ ] Documentation updated

## Timeline Estimate

- **Phase 1**: 2-3 hours (Services)
- **Phase 2**: 3-4 hours (Commands)
- **Phase 3**: 2-3 hours (Bash updates)
- **Phase 4**: 1 hour (Main script)
- **Phase 5**: 1 hour (Compatibility)
- **Testing**: 2-3 hours
- **Total**: 11-15 hours

## Next Steps

1. Review and approve this plan
2. Start with Phase 1: Create `QuestionnaireLoader`
3. Incrementally implement each phase
4. Test at each milestone
5. Commit granularly for easy rollback
