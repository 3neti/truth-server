# Step 3 Implementation Plan: Update Python Scripts (NON-BREAKING)

## ⚠️ Critical Constraint

**MUST maintain backward compatibility** - Existing templates with verbose bubble IDs (e.g., `PRESIDENT_LD_001`) must continue to work alongside new simple IDs (e.g., `A1`).

## Current State Analysis

### Files to Modify

1. **`appreciate_live.py`** - 4 parsing locations
2. **`appreciate.py`** - 1 parsing location

### Current Parsing Locations

```python
# Location 1: BallotSession.finalize() - Line 165
parts = bubble_id.split('_', 1)
position = parts[0]
code = parts[1]

# Location 2: ContestValidator.validate() - Line 334
parts = bubble_id.split('_', 1)
position = parts[0]

# Location 3: get_candidate_name() - Line 503
parts = bubble_id.split('_', 1)
position_code = parts[0]
candidate_code = parts[1]

# Location 4: convert_bubbles_to_zones() - Line 531
bubble_id.rsplit('_', 1)[0]  # contest
bubble_id.rsplit('_', 1)[1]  # code

# Location 5: appreciate.py - Line 100
parts = bubble_id.rsplit('_', 1)
contest = parts[0] if len(parts) > 1 else ''
code = parts[1] if len(parts) > 1 else bubble_id
```

### Why It's Breaking (Original Plan)

❌ Removing all `split('_')` code would break:
- All existing templates with verbose IDs
- Any in-progress ballots using old format
- Test fixtures and examples

## Non-Breaking Implementation Strategy

### Phase A: Add Metadata Support (Parallel System)

**Goal:** Add new functionality WITHOUT touching existing parsing code

1. Add `bubble_metadata.py` module with metadata loading functions
2. Add `--config-path` option to Python scripts
3. Load metadata when available, use None when not

### Phase B: Auto-Detection (Dual Mode)

**Goal:** Support both formats automatically

1. Detect bubble ID format (has underscore = old, no underscore = new)
2. Use appropriate parsing strategy for each format
3. Existing code continues to work for old format

### Phase C: Graceful Deprecation (Optional)

**Goal:** Only after all templates are migrated

1. Add deprecation warnings for old format
2. Eventually remove old parsing code (separate PR, future work)

---

## Detailed Implementation Plan

### Step 3.1: Create Bubble Metadata Module

**File:** `packages/omr-appreciation/omr-python/bubble_metadata.py`

```python
#!/usr/bin/env python3
"""
Bubble metadata loading and lookup.

Supports loading metadata from election configs (mapping.yaml + election.json)
or falling back to parsing for backward compatibility.
"""

import json
import yaml
from pathlib import Path
from typing import Dict, Optional

class BubbleMetadata:
    """
    Load and provide bubble metadata from election configs.
    
    Supports both simple bubble IDs (A1, B1) and verbose IDs (PRESIDENT_LD_001).
    """
    
    def __init__(self, config_path: Optional[str] = None):
        """
        Initialize with optional config path.
        
        Args:
            config_path: Path to directory containing election.json and mapping.yaml
                        If None, metadata lookups will return None (backward compatible)
        """
        self.metadata: Dict[str, Dict] = {}
        self.available = False
        
        if config_path:
            self._load_from_configs(config_path)
    
    def _load_from_configs(self, config_path: str):
        """Load metadata from election configs."""
        try:
            config_dir = Path(config_path)
            
            # Load election.json
            election_file = config_dir / 'election.json'
            if not election_file.exists():
                return
            
            with open(election_file) as f:
                election = json.load(f)
            
            # Load mapping.yaml
            mapping_file = config_dir / 'mapping.yaml'
            if not mapping_file.exists():
                return
            
            with open(mapping_file) as f:
                mapping = yaml.safe_load(f)
            
            # Build metadata
            for mark in mapping.get('marks', []):
                bubble_id = mark['key']
                candidate_code = mark['value']
                
                # Find position and candidate details
                position_code = self._find_position(candidate_code, election)
                candidate = self._find_candidate(candidate_code, position_code, election)
                
                if position_code and candidate:
                    self.metadata[bubble_id] = {
                        'bubble_id': bubble_id,
                        'candidate_code': candidate_code,
                        'position_code': position_code,
                        'candidate_name': candidate['name'],
                        'candidate_alias': candidate.get('alias', ''),
                    }
            
            self.available = len(self.metadata) > 0
            
        except Exception as e:
            print(f"Warning: Could not load bubble metadata: {e}")
            self.available = False
    
    def _find_position(self, candidate_code: str, election: dict) -> Optional[str]:
        """Find position code for a candidate."""
        for position_code, candidates in election.get('candidates', {}).items():
            for candidate in candidates:
                if candidate.get('code') == candidate_code:
                    return position_code
        return None
    
    def _find_candidate(self, candidate_code: str, position_code: str, election: dict) -> Optional[dict]:
        """Find candidate details."""
        for candidate in election.get('candidates', {}).get(position_code, []):
            if candidate.get('code') == candidate_code:
                return candidate
        return None
    
    def get(self, bubble_id: str) -> Optional[Dict]:
        """
        Get metadata for a bubble ID.
        
        Returns None if metadata not available (backward compatible).
        """
        return self.metadata.get(bubble_id)
    
    def is_simple_id(self, bubble_id: str) -> bool:
        """
        Check if bubble ID is simple format (no underscores).
        
        Simple: A1, B1, C23
        Verbose: PRESIDENT_LD_001, SENATOR_ES_002
        """
        return '_' not in bubble_id


def load_bubble_metadata(config_path: Optional[str] = None) -> BubbleMetadata:
    """
    Load bubble metadata from configs.
    
    Returns BubbleMetadata instance (may be empty if configs not available).
    """
    return BubbleMetadata(config_path)
```

**Why Safe:**
- New module, doesn't modify existing code
- Returns None when metadata unavailable
- Existing code continues to work

---

### Step 3.2: Add Metadata Option to Scripts

**File:** `packages/omr-appreciation/omr-python/appreciate_live.py`

**Change 1: Add import (after existing imports)**

```python
from bubble_metadata import load_bubble_metadata, BubbleMetadata
```

**Change 2: Add command-line option**

```python
def parse_args():
    # ... existing options ...
    
    ap.add_argument('--config-path', type=str, default=None,
                   help='Path to election config directory (for metadata lookup)')
    
    return ap.parse_args()
```

**Change 3: Load metadata in main()**

```python
def main():
    args = parse_args()
    
    # ... existing template loading ...
    
    # Load bubble metadata if config path provided
    bubble_metadata = load_bubble_metadata(args.config_path)
    if bubble_metadata.available:
        print(f'✓ Loaded bubble metadata ({len(bubble_metadata.metadata)} bubbles)')
    else:
        print('ℹ️  No bubble metadata loaded (using legacy parsing)')
    
    # Pass metadata to functions that need it
    # ...
```

**Why Safe:**
- Optional argument, defaults to None
- Existing behavior unchanged when not provided
- Clear feedback about metadata availability

---

### Step 3.3: Update Functions with Dual-Mode Support

**Strategy:** Each function should:
1. Try metadata lookup first (if available)
2. Fall back to parsing if metadata not available or bubble ID is verbose format

#### Function 1: `get_candidate_name()`

**Current Code (Line 491-517):**
```python
def get_candidate_name(bubble_id: str, questionnaire_data: Optional[Dict]) -> Optional[str]:
    if not questionnaire_data or 'positions' not in questionnaire_data:
        return None
    
    # Parse bubble_id: PRESIDENT_LD_001 → position="PRESIDENT", code="LD_001"
    parts = bubble_id.split('_', 1)
    if len(parts) < 2:
        return None
    
    position_code = parts[0]
    candidate_code = parts[1]
    
    # Find in questionnaire
    for position in questionnaire_data['positions']:
        if position.get('code') == position_code:
            for candidate in position.get('candidates', []):
                if candidate.get('code') == candidate_code:
                    return candidate.get('name')
    
    return None
```

**New Code (Dual-Mode):**
```python
def get_candidate_name(
    bubble_id: str, 
    questionnaire_data: Optional[Dict],
    bubble_metadata: Optional[BubbleMetadata] = None
) -> Optional[str]:
    """
    Get candidate name for bubble ID.
    
    Supports both simple IDs (via metadata) and verbose IDs (via parsing).
    """
    
    # Try metadata lookup first (if available)
    if bubble_metadata and bubble_metadata.available:
        meta = bubble_metadata.get(bubble_id)
        if meta:
            return meta['candidate_name']
    
    # Fall back to legacy parsing for verbose IDs
    if not questionnaire_data or 'positions' not in questionnaire_data:
        return None
    
    # Parse bubble_id: PRESIDENT_LD_001 → position="PRESIDENT", code="LD_001"
    parts = bubble_id.split('_', 1)
    if len(parts) < 2:
        return None
    
    position_code = parts[0]
    candidate_code = parts[1]
    
    # Find in questionnaire
    for position in questionnaire_data['positions']:
        if position.get('code') == position_code:
            for candidate in position.get('candidates', []):
                if candidate.get('code') == candidate_code:
                    return candidate.get('name')
    
    return None
```

**Why Safe:**
- New parameter is optional (defaults to None)
- Tries metadata first, falls back to existing logic
- Existing callers work without modification
- Both formats supported

---

#### Function 2: `convert_bubbles_to_zones()`

**Current Code (Line 520-538):**
```python
def convert_bubbles_to_zones(bubbles: Dict, mm_to_px: float = 11.811):
    """Convert bubble dict to zones format for mark_detector."""
    zones = []
    for bubble_id, bubble in bubbles.items():
        center_x_px = bubble['center_x'] * mm_to_px
        center_y_px = bubble['center_y'] * mm_to_px
        diameter_px = bubble['diameter'] * mm_to_px
        radius_px = diameter_px / 2
        
        zones.append({
            'id': bubble_id,
            'contest': bubble_id.rsplit('_', 1)[0],  # e.g., PRESIDENT_001 -> PRESIDENT
            'code': bubble_id.rsplit('_', 1)[1] if '_' in bubble_id else bubble_id,
            'x': int(center_x_px - radius_px),
            'y': int(center_y_px - radius_px),
            'width': int(diameter_px),
            'height': int(diameter_px)
        })
    return zones
```

**New Code (Dual-Mode):**
```python
def convert_bubbles_to_zones(
    bubbles: Dict, 
    mm_to_px: float = 11.811,
    bubble_metadata: Optional[BubbleMetadata] = None
):
    """
    Convert bubble dict to zones format for mark_detector.
    
    Supports both simple IDs (via metadata) and verbose IDs (via parsing).
    """
    zones = []
    for bubble_id, bubble in bubbles.items():
        center_x_px = bubble['center_x'] * mm_to_px
        center_y_px = bubble['center_y'] * mm_to_px
        diameter_px = bubble['diameter'] * mm_to_px
        radius_px = diameter_px / 2
        
        # Determine contest and code
        if bubble_metadata and bubble_metadata.available:
            meta = bubble_metadata.get(bubble_id)
            if meta:
                # Use metadata (simple ID format)
                contest = meta['position_code']
                code = meta['candidate_code']
            else:
                # Metadata available but bubble not found - parse as fallback
                contest = bubble_id.rsplit('_', 1)[0] if '_' in bubble_id else ''
                code = bubble_id.rsplit('_', 1)[1] if '_' in bubble_id else bubble_id
        else:
            # No metadata - use legacy parsing (verbose ID format)
            contest = bubble_id.rsplit('_', 1)[0] if '_' in bubble_id else ''
            code = bubble_id.rsplit('_', 1)[1] if '_' in bubble_id else bubble_id
        
        zones.append({
            'id': bubble_id,
            'contest': contest,
            'code': code,
            'x': int(center_x_px - radius_px),
            'y': int(center_y_px - radius_px),
            'width': int(diameter_px),
            'height': int(diameter_px)
        })
    return zones
```

**Why Safe:**
- New parameter is optional
- Tries metadata, falls back to parsing
- Both formats work correctly

---

#### Function 3: `BallotSession.finalize()`

**Current Code (Line 149-183):**
```python
def finalize(self) -> str:
    self.status = 'finalized'
    self._save_metadata()
    
    # Group votes by position
    position_votes: Dict[str, List[str]] = defaultdict(list)
    
    for bubble_id, filled in self.votes.items():
        if filled:
            # Parse bubble_id: PRESIDENT_LD_001 -> position=PRESIDENT, code=LD_001
            parts = bubble_id.split('_', 1)
            if len(parts) == 2:
                position = parts[0]
                code = parts[1]
                position_votes[position].append(code)
    
    # Build compact string
    # ...
```

**New Code (Dual-Mode):**
```python
def finalize(self, bubble_metadata: Optional[BubbleMetadata] = None) -> str:
    """
    Finalize session and generate ballot string.
    
    Supports both simple and verbose bubble ID formats.
    """
    self.status = 'finalized'
    self._save_metadata()
    
    # Group votes by position
    position_votes: Dict[str, List[str]] = defaultdict(list)
    
    for bubble_id, filled in self.votes.items():
        if filled:
            # Try metadata lookup first
            if bubble_metadata and bubble_metadata.available:
                meta = bubble_metadata.get(bubble_id)
                if meta:
                    position = meta['position_code']
                    code = meta['candidate_code']
                    position_votes[position].append(code)
                    continue
            
            # Fall back to parsing for verbose IDs
            parts = bubble_id.split('_', 1)
            if len(parts) == 2:
                position = parts[0]
                code = parts[1]
                position_votes[position].append(code)
    
    # Build compact string
    # ...
```

**Why Safe:**
- Optional parameter
- Tries metadata first
- Falls back to existing logic

---

#### Function 4: `ContestValidator.validate()`

**Current Code (Line 315-352):**
```python
def validate(self, votes: Dict[str, bool]) -> Dict[str, Dict]:
    # Group votes by position
    position_counts: Dict[str, int] = defaultdict(int)
    
    for bubble_id, filled in votes.items():
        if filled:
            # Parse bubble_id: PRESIDENT_LD_001 -> position=PRESIDENT
            parts = bubble_id.split('_', 1)
            if len(parts) >= 1:
                position = parts[0]
                position_counts[position] += 1
    # ...
```

**New Code (Dual-Mode):**
```python
def __init__(self, questionnaire_data: Optional[Dict], bubble_metadata: Optional[BubbleMetadata] = None):
    self.rules: Dict[str, int] = {}
    self.bubble_metadata = bubble_metadata
    # ... existing init code ...

def validate(self, votes: Dict[str, bool]) -> Dict[str, Dict]:
    """
    Validate votes against contest rules.
    
    Supports both simple and verbose bubble ID formats.
    """
    # Group votes by position
    position_counts: Dict[str, int] = defaultdict(int)
    
    for bubble_id, filled in votes.items():
        if filled:
            # Try metadata lookup first
            if self.bubble_metadata and self.bubble_metadata.available:
                meta = self.bubble_metadata.get(bubble_id)
                if meta:
                    position = meta['position_code']
                    position_counts[position] += 1
                    continue
            
            # Fall back to parsing for verbose IDs
            parts = bubble_id.split('_', 1)
            if len(parts) >= 1:
                position = parts[0]
                position_counts[position] += 1
    # ...
```

**Why Safe:**
- Metadata passed in constructor (optional)
- Tries metadata first
- Falls back to parsing

---

### Step 3.4: Update `appreciate.py`

**Similar dual-mode approach**

Add `--config-path` option and metadata support, maintaining backward compatibility.

---

## Testing Strategy

### Test 1: Old Format (Verbose IDs)

```bash
# Should work WITHOUT --config-path
python3 appreciate_live.py \
    --template /path/to/old-template.json \
    --camera 0
```

**Expected:** Works exactly as before, uses parsing

### Test 2: New Format (Simple IDs) WITH Metadata

```bash
# WITH --config-path
python3 appreciate_live.py \
    --template resources/docs/simulation/coordinates.json \
    --config-path resources/docs/simulation/config \
    --camera 0 \
    --show-names
```

**Expected:** Uses metadata lookup, displays candidate names

### Test 3: New Format (Simple IDs) WITHOUT Metadata

```bash
# WITHOUT --config-path (graceful degradation)
python3 appreciate_live.py \
    --template resources/docs/simulation/coordinates.json \
    --camera 0
```

**Expected:** Works but candidate names not available (acceptable)

### Test 4: Mixed Usage

**Old templates continue to work**
**New templates work better with metadata**
**No breaking changes**

---

## Migration Path

### Phase 1 (This PR)
- ✅ Add metadata support (optional)
- ✅ All functions support both formats
- ✅ Existing templates continue to work
- ✅ New templates work better

### Phase 2 (Future PR - Optional)
- Add deprecation warnings for verbose IDs
- Update all templates to simple IDs
- Remove parsing code (only after all templates migrated)

---

## Summary

### ✅ Non-Breaking Changes

1. **New module:** `bubble_metadata.py` (doesn't affect existing code)
2. **Optional parameter:** `--config-path` (defaults to None)
3. **Optional function parameters:** `bubble_metadata` (defaults to None)
4. **Dual-mode logic:** Try metadata, fall back to parsing
5. **Backward compatible:** All existing templates continue to work

### ❌ NO Breaking Changes

- ❌ No removal of parsing code
- ❌ No changes to existing function signatures (only additions)
- ❌ No required parameters
- ❌ No changes to default behavior

### Testing Checklist

- [ ] Old templates work without `--config-path`
- [ ] New templates work with `--config-path`
- [ ] New templates degrade gracefully without `--config-path`
- [ ] `get_candidate_name()` works for both formats
- [ ] `convert_bubbles_to_zones()` works for both formats
- [ ] `BallotSession.finalize()` works for both formats
- [ ] `ContestValidator.validate()` works for both formats
- [ ] No existing tests break

---

## Risk Assessment

**Risk Level: LOW** ✅

**Why Safe:**
- All changes are additive
- All parameters are optional
- Existing behavior is preserved
- Parsing code remains as fallback
- Can be rolled back easily if issues found

**Worst Case:**
If metadata loading fails → falls back to parsing → existing behavior
