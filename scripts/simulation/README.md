# Ballot Appreciation Simulation Framework

A comprehensive testing framework for ballot appreciation (optical mark recognition) that generates synthetic test scenarios, renders ballot images, runs detection algorithms, and validates results.

## Overview

This simulation framework provides end-to-end testing capabilities for the ballot appreciation system. It bridges the gap between election configuration and Computer Vision validation by:

1. **Generating** ballot templates from election config files
2. **Creating** diverse test scenarios (normal, overvote, faint marks, etc.)
3. **Rendering** realistic ballot images with voted bubbles
4. **Running** ballot appreciation/detection algorithms
5. **Visualizing** results with overlays
6. **Validating** detection accuracy

## Architecture

### Modular Library Design

The framework uses a modular architecture with specialized library scripts:

```
scripts/simulation/
├── run-simulation.sh           # Main orchestration script
├── README.md                    # This file
└── lib/
    ├── common.sh                # Shared utilities (logging, validation)
    ├── template-generator.sh    # Generate coordinates from config
    ├── scenario-generator.sh    # Create test scenarios
    ├── ballot-renderer.sh       # Render ballot images
    ├── aruco-generator.sh       # Generate ArUco fiducial markers
    ├── ballot-appreciator.sh    # Run detection algorithms
    └── overlay-generator.sh     # Generate visual overlays
```

### Library Components

#### `common.sh`
Shared utilities used by all other libraries:
- Colored logging functions (info, success, warning, error)
- Path validation and file size utilities
- Test result tracking
- Python module availability checks
- Project root detection

#### `template-generator.sh`
Generates `coordinates.json` from election configuration:
- Parses election.json, precinct.yaml, mapping.yaml
- Calls Laravel command to generate bubble coordinates
- Validates coordinate bounds and structure
- Counts bubbles and fiducials

#### `scenario-generator.sh`
Creates diverse test scenarios:
- 9 scenario types: normal, overvote, undervote, faint, stray, damaged, rotated, skewed, mixed
- Generates scenario metadata with expected issues
- Creates scenario-specific vote patterns
- Configures test parameters per scenario type

#### `ballot-renderer.sh`
Renders ballot images from votes and coordinates:
- Draws ballot outline and structure
- Renders voted bubbles (filled/unfilled)
- Supports variable fill ratios (for faint marks)
- Generates ArUco markers at corners
- Outputs PNG ballot images

#### `aruco-generator.sh`
Generates ArUco fiducial markers:
- Uses cv2.aruco.generateImageMarker()
- DICT_4X4_100 dictionary
- 10mm markers at ballot corners (101, 102, 103, 104)
- Graceful fallback if OpenCV unavailable

#### `ballot-appreciator.sh`
Runs ballot appreciation/detection:
- Detects ArUco markers for alignment
- Applies perspective correction
- Extracts bubble regions
- Calculates fill ratios and confidence scores
- Outputs appreciation_results.json

#### `overlay-generator.sh`
Generates visual overlays for inspection:
- Overlays colored circles on ballot image
- Green: Filled/detected bubbles
- Red: Unfilled/undetected bubbles
- Displays fill_ratio and confidence scores
- Adds legend with counts

### Data Flow

```
Election Config Files
        ↓
  coordinates.json ────┐
        ↓              │
  Scenario Metadata    │
        ↓              │
    votes.json         │
        ↓              │
    ballot.png ←───────┘
        ↓
appreciation_results.json
        ↓
    overlay.png
```

## Usage

### Quick Start

Run the simulation with default scenarios (normal, overvote, faint):

```bash
./scripts/simulation/run-simulation.sh
```

### Command-Line Options

```bash
./scripts/simulation/run-simulation.sh [OPTIONS]

OPTIONS:
    -o, --output DIR        Output directory (default: storage/simulation)
    -c, --config DIR        Config directory (default: config)
    -s, --scenarios LIST    Comma-separated scenario types
    -l, --list-scenarios    List available scenario types
    -f, --fresh             Start fresh (remove existing output)
    -v, --verbose           Enable verbose logging
    -h, --help              Show help message
```

### Examples

```bash
# List available scenario types
./scripts/simulation/run-simulation.sh --list-scenarios

# Run specific scenarios
./scripts/simulation/run-simulation.sh \
  --scenarios normal,overvote,undervote,faint,stray

# Fresh run with custom output directory
./scripts/simulation/run-simulation.sh \
  --fresh \
  --output /tmp/ballot-test

# Verbose mode for debugging
./scripts/simulation/run-simulation.sh --verbose
```

## Scenario Types

The framework supports 9 scenario types:

| Type | Description | Expected Issues |
|------|-------------|----------------|
| `normal` | Clean ballot with clear marks | None |
| `overvote` | Ballot with overvoted positions | Overvote violation |
| `undervote` | Ballot with some positions unmarked | None (valid partial vote) |
| `faint` | Ballot with faint/light marks | Low confidence, detection failure |
| `stray` | Ballot with stray marks and noise | False positives, noise |
| `damaged` | Ballot with torn edges or damage | Fiducial detection failure |
| `rotated` | Ballot scanned at slight rotation | Alignment issues |
| `skewed` | Ballot with perspective distortion | Perspective correction needed |
| `mixed` | Combination of various issues | Multiple issues |

### Scenario Parameters

Each scenario type has specific test parameters:

```json
{
  "fill_threshold": 0.3,           // Minimum fill ratio to consider filled
  "confidence_threshold": 0.7,     // Minimum confidence for valid detection
  "expected_pass": true,           // Whether scenario should pass validation
  "expected_violations": []        // Expected validation violations
}
```

## Output Structure

The simulation generates a structured output directory:

```
storage/simulation/
├── template/
│   ├── coordinates.json          # Ballot template coordinates
│   └── generate.log              # Template generation log
├── scenarios/
│   ├── scenario-1-normal/
│   │   ├── scenario.json         # Scenario metadata
│   │   ├── votes.json            # Vote marks (filled bubbles)
│   │   ├── coordinates.json      # Ballot coordinates
│   │   ├── ballot.png            # Rendered ballot image
│   │   ├── appreciation_results.json  # Detection results
│   │   ├── overlay.png           # Visual overlay
│   │   ├── render.log            # Rendering log
│   │   ├── appreciate.log        # Appreciation log
│   │   └── overlay.log           # Overlay generation log
│   ├── scenario-2-overvote/
│   └── scenario-3-faint/
└── summary.txt                   # Summary report
```

### Output Files

#### `scenario.json`
Scenario metadata and parameters:
```json
{
  "scenario_name": "scenario-1-normal",
  "scenario_type": "normal",
  "description": "Clean ballot with clear marks",
  "created_at": "2025-10-31T15:44:44Z",
  "expected_issues": ["none"],
  "test_parameters": {
    "fill_threshold": 0.3,
    "confidence_threshold": 0.7,
    "expected_pass": true
  }
}
```

#### `votes.json`
Vote marks with fill ratios:
```json
{
  "A1": {"filled": true, "fill_ratio": 0.7},
  "B2": {"filled": true, "fill_ratio": 0.7},
  "C3": {"filled": false, "fill_ratio": 0.05}
}
```

#### `appreciation_results.json`
Detection results:
```json
{
  "bubbles": [
    {
      "key": "A1",
      "filled": true,
      "fill_ratio": 0.68,
      "confidence": 0.95
    }
  ],
  "fiducials_detected": true,
  "perspective_corrected": true
}
```

## Development

### Adding New Scenario Types

1. Add scenario type to `SCENARIO_TYPES` in `scenario-generator.sh`:
```bash
declare -A SCENARIO_TYPES=(
    ["mytype"]="Description of my scenario type"
)
```

2. Add expected issues in `get_expected_issues()`:
```bash
mytype)
    echo '["my_issue_type"]'
    ;;
```

3. Add test parameters in `get_test_parameters()`:
```bash
mytype)
    cat << 'EOF'
{
  "fill_threshold": 0.25,
  "confidence_threshold": 0.5,
  "expected_pass": false
}
EOF
    ;;
```

4. Add vote generation logic in `generate_scenario_votes()`:
```python
elif scenario_type == 'mytype':
    # Custom vote generation logic
    for position, keys in bubbles_by_position.items():
        # ... generate votes
```

### Testing Individual Components

Each library can be sourced and tested independently:

```bash
# Test template generation
source scripts/simulation/lib/common.sh
source scripts/simulation/lib/template-generator.sh
generate_template config storage/test/coordinates.json

# Test scenario generation
source scripts/simulation/lib/scenario-generator.sh
create_scenario "test" "normal" storage/test/scenario coordinates.json

# Test ballot rendering
source scripts/simulation/lib/ballot-renderer.sh
render_ballot votes.json coordinates.json ballot.png
```

## Requirements

### System Dependencies
- Bash 4.0+
- Python 3.7+
- jq (JSON processor)

### Python Packages
- **PIL/Pillow**: Image manipulation
- **opencv-python**: ArUco markers and perspective correction
- **numpy**: Numerical operations

Install Python dependencies:
```bash
pip3 install pillow opencv-python numpy
```

### Laravel Commands
The framework integrates with Laravel commands:
- `php artisan election:generate-template`: Generate coordinates from config

## Integration with Truth System

This simulation framework integrates with the Truth election system:

1. **Configuration**: Uses election.json, precinct.yaml, mapping.yaml
2. **Laravel Commands**: Calls Laravel artisan commands for template generation
3. **Data Structures**: Outputs match TruthElection package formats
4. **Validation**: Tests match real ballot appreciation workflows

## Troubleshooting

### Template Generation Fails
```bash
# Check if config files exist
ls -l config/election.json config/precinct.yaml config/mapping.yaml

# Check Laravel command
php artisan election:generate-template --help

# Run with verbose logging
./scripts/simulation/run-simulation.sh --verbose
```

### Python Module Not Found
```bash
# Install missing modules
pip3 install pillow opencv-python numpy

# Check module availability
python3 -c "import PIL; import cv2; import numpy"
```

### Ballot Rendering Issues
```bash
# Check if Pillow is installed
python3 -c "from PIL import Image"

# Check coordinates file
jq . storage/simulation/template/coordinates.json

# Review render log
cat storage/simulation/scenarios/scenario-1-normal/render.log
```

## Performance

Typical execution times (M1 Mac):
- Template generation: ~2 seconds
- Scenario generation: <1 second per scenario
- Ballot rendering: ~1 second per ballot
- Ballot appreciation: ~2-3 seconds per ballot
- Overlay generation: ~1 second per overlay

Total time for 3 scenarios: ~20-30 seconds

## Future Enhancements

- [ ] Support for multi-page ballots
- [ ] Batch processing with parallel execution
- [ ] More sophisticated damage/distortion simulation
- [ ] Integration with CI/CD pipelines
- [ ] Performance benchmarking and profiling
- [ ] Web-based visualization dashboard
- [ ] Automated regression testing
- [ ] Support for different ballot sizes and layouts

## Related Documentation

- [Election Setup Guide](../../resources/docs/election-setup.md)
- [Ballot Appreciation Documentation](../../packages/truth-election-php/docs/ballot-appreciation.md)
- [WARP.md](../../WARP.md) - Project overview and development guide

## License

Part of the Truth Augmented Reality Ballot Appreciation System.
