# Plan: Using mapping.yaml for bubble_id Generation with Dynamic Config Loading

## Overview

This plan outlines how to use the simplified `key: value` format in mapping.yaml to generate bubble IDs dynamically, while keeping bubble_id as simple grid references (A1, B1, etc.) instead of verbose concatenated strings.

## Current State

**Simulation Configs Location:**
- `resources/docs/simulation/config/election.json`
- `resources/docs/simulation/config/mapping.yaml`
- `resources/docs/simulation/config/precinct.yaml`

**Default Configs Location:**
- `config/election.json` (Philippine national election)
- `config/mapping.yaml` (Philippine ballot mapping)
- `config/precinct.yaml` (Philippine precinct)

## Architecture

### 1. Config Structure

**mapping.yaml Format:**
```yaml
code: 0102800000
location_name: 'Currimao, Ilocos Norte'
district: 2
marks:
  # PUNONG_BARANGAY-1402702011 (Row A)
  - key: A1          # bubble_id = "A1"
    value: 'LD_001'  # candidate_code
  - key: A2
    value: 'SJ_002'
  
  # MEMBER_SANGGUNIANG_BARANGAY-1402702011 (Row B)
  - key: B1
    value: 'JD_001'
  - key: B2
    value: 'ES_002'
```

**election.json Structure:**
```json
{
  "positions": [
    {"code": "PUNONG_BARANGAY-1402702011", "name": "...", "count": 1}
  ],
  "candidates": {
    "PUNONG_BARANGAY-1402702011": [
      {"code": "LD_001", "name": "Leonardo DiCaprio", "alias": "LD"}
    ],
    "MEMBER_SANGGUNIANG_BARANGAY-1402702011": [
      {"code": "JD_001", "name": "Johnny Depp", "alias": "JD"}
    ]
  }
}
```

### 2. Dynamic Config Loading Strategy

**Option A: Environment Variable (Recommended)**
```bash
# Set simulation mode
export ELECTION_CONFIG_PATH="resources/docs/simulation/config"

# Laravel will load from this path instead of default config/
php artisan election:setup-precinct
```

**Option B: Command Line Flag**
```bash
# Pass config path as option
php artisan election:setup-precinct --config-path="resources/docs/simulation/config"
```

**Option C: Config File Reference**
```yaml
# config/election.yaml
config_source: "default"  # or "simulation"

sources:
  default: "config/"
  simulation: "resources/docs/simulation/config/"
```

---

## Implementation Plan

### Phase 1: Dynamic Config Loader Service

**Create:** `app/Services/ElectionConfigLoader.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class ElectionConfigLoader
{
    protected string $configPath;
    
    public function __construct()
    {
        // Priority order:
        // 1. Environment variable
        // 2. Config setting
        // 3. Default path
        $this->configPath = env('ELECTION_CONFIG_PATH', config('election.config_path', 'config'));
    }
    
    public function getConfigPath(): string
    {
        return base_path($this->configPath);
    }
    
    public function loadElection(): array
    {
        $path = $this->getConfigPath() . '/election.json';
        
        if (!File::exists($path)) {
            throw new \RuntimeException("Election config not found: {$path}");
        }
        
        return json_decode(File::get($path), true);
    }
    
    public function loadMapping(): array
    {
        $path = $this->getConfigPath() . '/mapping.yaml';
        
        if (!File::exists($path)) {
            throw new \RuntimeException("Mapping config not found: {$path}");
        }
        
        return Yaml::parse(File::get($path));
    }
    
    public function loadPrecinct(): array
    {
        $path = $this->getConfigPath() . '/precinct.yaml';
        
        if (!File::exists($path)) {
            throw new \RuntimeException("Precinct config not found: {$path}");
        }
        
        return Yaml::parse(File::get($path));
    }
    
    /**
     * Find position code for a given candidate code
     */
    public function findPositionByCandidate(string $candidateCode): ?string
    {
        $election = $this->loadElection();
        
        foreach ($election['candidates'] as $positionCode => $candidates) {
            foreach ($candidates as $candidate) {
                if ($candidate['code'] === $candidateCode) {
                    return $positionCode;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get candidate details by code
     */
    public function getCandidateDetails(string $candidateCode): ?array
    {
        $election = $this->loadElection();
        
        foreach ($election['candidates'] as $positionCode => $candidates) {
            foreach ($candidates as $candidate) {
                if ($candidate['code'] === $candidateCode) {
                    return array_merge($candidate, ['position' => $positionCode]);
                }
            }
        }
        
        return null;
    }
}
```

---

### Phase 2: Bubble ID Generation Logic

**Key Principle:** Bubble ID = Grid Reference (simple and clean)

```php
<?php

namespace App\Services;

class BubbleIdGenerator
{
    protected ElectionConfigLoader $configLoader;
    
    public function __construct(ElectionConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }
    
    /**
     * Generate bubble metadata from mapping.yaml
     * 
     * Input: mapping.yaml with key-value pairs
     * Output: Array of bubble metadata
     */
    public function generateBubbleMetadata(): array
    {
        $mapping = $this->configLoader->loadMapping();
        $election = $this->configLoader->loadElection();
        
        $bubbles = [];
        
        foreach ($mapping['marks'] as $mark) {
            $key = $mark['key'];  // e.g., "A1"
            $candidateCode = $mark['value'];  // e.g., "LD_001"
            
            // Find position for this candidate
            $positionCode = $this->configLoader->findPositionByCandidate($candidateCode);
            
            if (!$positionCode) {
                throw new \RuntimeException("Cannot find position for candidate: {$candidateCode}");
            }
            
            // Get full candidate details
            $candidateDetails = $this->getCandidateFromElection($election, $positionCode, $candidateCode);
            
            // Store bubble metadata
            $bubbles[$key] = [
                'bubble_id' => $key,  // Simple grid reference!
                'candidate_code' => $candidateCode,
                'position_code' => $positionCode,
                'candidate_name' => $candidateDetails['name'] ?? null,
                'candidate_alias' => $candidateDetails['alias'] ?? null,
            ];
        }
        
        return $bubbles;
    }
    
    /**
     * Lookup bubble metadata by bubble_id
     */
    public function getBubbleMetadata(string $bubbleId): ?array
    {
        $metadata = $this->generateBubbleMetadata();
        return $metadata[$bubbleId] ?? null;
    }
    
    /**
     * Get all bubbles for a position
     */
    public function getBubblesByPosition(string $positionCode): array
    {
        $metadata = $this->generateBubbleMetadata();
        
        return array_filter($metadata, function($bubble) use ($positionCode) {
            return $bubble['position_code'] === $positionCode;
        });
    }
    
    protected function getCandidateFromElection(array $election, string $positionCode, string $candidateCode): ?array
    {
        foreach ($election['candidates'][$positionCode] ?? [] as $candidate) {
            if ($candidate['code'] === $candidateCode) {
                return $candidate;
            }
        }
        return null;
    }
}
```

---

### Phase 3: Update Existing Code to Use Simple bubble_id

**Before (Current):**
```python
# appreciate_live.py
bubble_id = "PRESIDENT_LD_001"  # Verbose, coupled

# Parse to get data (fragile!)
parts = bubble_id.split('_', 1)
position_code = parts[0]
candidate_code = parts[1]
```

**After (Simplified):**
```python
# appreciate_live.py
bubble_id = "A1"  # Simple grid reference

# Lookup metadata (robust!)
bubble_meta = bubble_service.get_metadata(bubble_id)
position_code = bubble_meta['position_code']
candidate_code = bubble_meta['candidate_code']
candidate_name = bubble_meta['candidate_name']
```

**Implementation:**
1. Modify `get_candidate_name()` to accept metadata instead of parsing
2. Update `convert_bubbles_to_zones()` to use lookup instead of split
3. Modify template generation to use simple keys

---

### Phase 4: Template Coordinates Generation

**coordinates.json should use simple keys:**
```json
{
  "bubble": {
    "A1": {
      "center_x": 10.5,
      "center_y": 20.3,
      "diameter": 5.0
    },
    "A2": {
      "center_x": 10.5,
      "center_y": 30.3,
      "diameter": 5.0
    },
    "B1": {
      "center_x": 10.5,
      "center_y": 50.3,
      "diameter": 5.0
    }
  }
}
```

**When generating ballots:**
```php
// Generate coordinates with simple IDs
$bubbles = [];
foreach ($mapping['marks'] as $mark) {
    $key = $mark['key'];  // "A1"
    
    $bubbles[$key] = [
        'center_x' => calculateX($key),
        'center_y' => calculateY($key),
        'diameter' => 5.0
    ];
}
```

---

## Usage Examples

### Example 1: Setup with Simulation Configs

```bash
# Set environment variable
export ELECTION_CONFIG_PATH="resources/docs/simulation/config"

# Setup precinct (loads simulation configs)
php artisan election:setup-precinct --fresh

# Run appreciation with simulation ballot
python3 packages/omr-appreciation/omr-python/appreciate_live.py \
  --template /path/to/simulation/coordinates.json \
  --show-names \
  --validate-contests
```

### Example 2: Switch Between Configs

```bash
# Use default Philippine configs
export ELECTION_CONFIG_PATH="config"
php artisan election:setup-precinct

# Use simulation Barangay configs
export ELECTION_CONFIG_PATH="resources/docs/simulation/config"
php artisan election:setup-precinct

# Use custom test configs
export ELECTION_CONFIG_PATH="tests/fixtures/election-configs"
php artisan election:setup-precinct
```

### Example 3: Lookup in Python

```python
# New helper function
def load_bubble_metadata(config_path: str) -> Dict[str, Dict]:
    """Load bubble metadata from configs"""
    import yaml
    import json
    
    # Load configs
    with open(f"{config_path}/mapping.yaml") as f:
        mapping = yaml.safe_load(f)
    
    with open(f"{config_path}/election.json") as f:
        election = json.load(f)
    
    # Build metadata
    metadata = {}
    for mark in mapping['marks']:
        key = mark['key']
        candidate_code = mark['value']
        
        # Find position
        position = find_position_by_candidate(candidate_code, election)
        candidate = find_candidate_details(candidate_code, election)
        
        metadata[key] = {
            'bubble_id': key,
            'candidate_code': candidate_code,
            'position_code': position,
            'candidate_name': candidate['name'],
            'candidate_alias': candidate['alias']
        }
    
    return metadata

# Usage
bubble_metadata = load_bubble_metadata("resources/docs/simulation/config")

# Simple lookup (no parsing!)
bubble_id = "A1"
meta = bubble_metadata[bubble_id]
print(f"Bubble {bubble_id}: {meta['candidate_name']} for {meta['position_code']}")
# Output: Bubble A1: Leonardo DiCaprio for PUNONG_BARANGAY-1402702011
```

---

## Benefits

1. ✅ **Simple bubble IDs**: `A1` instead of `PRESIDENT_LD_001`
2. ✅ **No parsing needed**: Direct lookup via metadata
3. ✅ **Dynamic config loading**: Switch between election types easily
4. ✅ **Single source of truth**: election.json has all relationships
5. ✅ **DRY principle**: No duplication of position info
6. ✅ **Flexible**: Can test different elections without code changes
7. ✅ **Clean separation**: Physical layout (coordinates) separate from semantic mapping

---

## Migration Path

### Step 1: Create Services (Week 1)
- [ ] Create `ElectionConfigLoader` service
- [ ] Create `BubbleIdGenerator` service
- [ ] Add unit tests for config loading
- [ ] Add unit tests for metadata generation

### Step 2: Update Laravel Commands (Week 1)
- [ ] Update `election:setup-precinct` to use `ElectionConfigLoader`
- [ ] Add `--config-path` option to all election commands
- [ ] Update template generation to use simple bubble IDs
- [ ] Test with both default and simulation configs

### Step 3: Update Python Scripts (Week 2)
- [ ] Add `load_bubble_metadata()` helper function
- [ ] Update `get_candidate_name()` to use metadata lookup
- [ ] Update `convert_bubbles_to_zones()` to use metadata
- [ ] Remove all `split('_')` parsing code
- [ ] Test with simulation configs

### Step 4: Generate Simulation Templates (Week 2)
- [ ] Generate `coordinates.json` with A1-A6, B1-B50 keys
- [ ] Generate test ballots with simple bubble IDs
- [ ] Update test fixtures
- [ ] Run full test suite with simulation configs

### Step 5: Documentation (Week 2)
- [ ] Document config loading strategy
- [ ] Document bubble_id simplification
- [ ] Create migration guide for existing installations
- [ ] Update developer documentation

---

## Testing Strategy

```bash
# Test 1: Default configs (Philippine election)
export ELECTION_CONFIG_PATH="config"
php artisan election:setup-precinct
bash scripts/test-omr-appreciation.sh

# Test 2: Simulation configs (Barangay election)
export ELECTION_CONFIG_PATH="resources/docs/simulation/config"
php artisan election:setup-precinct
# Generate simulation ballot and test

# Test 3: Config switching
export ELECTION_CONFIG_PATH="config"
php artisan election:setup-precinct
export ELECTION_CONFIG_PATH="resources/docs/simulation/config"
php artisan election:setup-precinct
# Verify different configs loaded correctly

# Test 4: Python appreciation with metadata
python3 -c "
from bubble_service import load_bubble_metadata
meta = load_bubble_metadata('resources/docs/simulation/config')
assert meta['A1']['candidate_name'] == 'Leonardo DiCaprio'
assert meta['B1']['candidate_name'] == 'Johnny Depp'
print('✓ All metadata lookups working')
"
```

---

## File Structure

```
config/                                    # Default Philippine configs
├── election.json
├── mapping.yaml
└── precinct.yaml

resources/docs/simulation/config/          # Simulation Barangay configs
├── election.json
├── mapping.yaml
└── precinct.yaml

app/Services/
├── ElectionConfigLoader.php              # New
└── BubbleIdGenerator.php                 # New

packages/omr-appreciation/omr-python/
├── bubble_service.py                     # New helper module
├── appreciate_live.py                    # Updated to use metadata
└── appreciate.py                         # Updated to use metadata

tests/
├── Unit/
│   ├── ElectionConfigLoaderTest.php     # New
│   └── BubbleIdGeneratorTest.php        # New
└── Feature/
    └── ElectionConfigSwitchingTest.php  # New
```

---

## Status

- ✅ Simulation configs created in `resources/docs/simulation/config/`
- ✅ Plan documented
- ⏳ Implementation pending
- ⏳ Testing pending

**Next Steps:**
1. Implement `ElectionConfigLoader` service
2. Implement `BubbleIdGenerator` service
3. Add environment variable support for `ELECTION_CONFIG_PATH`
4. Update existing code to use simple bubble IDs
5. Test with both config sets
