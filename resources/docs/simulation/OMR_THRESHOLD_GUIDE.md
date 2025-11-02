# OMR Threshold Configuration Guide

**Last Updated:** 2025-11-02  
**System Version:** Laravel-based OMR Simulation

---

## Table of Contents

1. [Overview](#overview)
2. [Threshold Types](#threshold-types)
3. [Configuration](#configuration)
4. [Threshold Tuning Process](#threshold-tuning-process)
5. [Interpreting Results](#interpreting-results)
6. [Presets](#presets)
7. [Troubleshooting](#troubleshooting)
8. [Best Practices](#best-practices)

---

## Overview

OMR (Optical Mark Recognition) thresholds determine how the system classifies marks on ballots as "filled" or "unfilled". Proper threshold configuration is critical for accurate vote detection.

### Key Concepts

- **Fill Ratio**: Percentage of dark pixels in a bubble (0.0 = empty, 1.0 = completely filled)
- **Detection Threshold**: Minimum fill ratio to consider a mark as "filled"
- **Classification Thresholds**: Ranges that categorize mark quality (valid, ambiguous, faint)

### Why Thresholds Matter

- **Too Low** → False positives (unmarked bubbles detected as filled)
- **Too High** → False negatives (valid marks missed)
- **Just Right** → Accurate detection with appropriate warnings

---

## Threshold Types

### 1. Detection Threshold (`detection_threshold`)

**Purpose:** Primary threshold for determining if a bubble is filled.

**Default:** `0.3` (30%)

**Range:** 0.0 to 1.0

**Usage:**
```bash
# Environment variable
OMR_DETECTION_THRESHOLD=0.3

# Config file
'detection_threshold' => 0.3
```

**Recommendations:**
- **0.20-0.25**: Very sensitive, catches faint marks (risk of false positives)
- **0.30-0.35**: Balanced, good for most conditions ✅ **Recommended**
- **0.40-0.50**: Conservative, requires darker marks (fewer false positives)

---

### 2. Classification Thresholds

#### Valid Mark (`valid_mark`)

**Purpose:** Threshold for high-quality marks (displayed green in overlays).

**Default:** `0.95` (95%)

**Typical Range:** 0.90-0.98

**Example:**
```php
'valid_mark' => 0.95
```

Marks with fill_ratio ≥ 0.95 are considered high-quality valid votes.

---

#### Ambiguous Range (`ambiguous_min`, `ambiguous_max`)

**Purpose:** Range for marks that are visible but unclear.

**Defaults:** 
- `ambiguous_min`: `0.15` (15%)
- `ambiguous_max`: `0.45` (45%)

**Usage:**

Marks between 0.15 and 0.45 trigger "ambiguous" warnings (displayed orange).

---

#### Faint Mark (`faint_mark`)

**Purpose:** Minimum threshold to distinguish faint marks from background noise.

**Default:** `0.16` (16%)

**Context:** Background noise typically measures 0.13-0.15, so 0.16+ indicates actual marking.

---

#### Overfilled (`overfilled`)

**Purpose:** Threshold for detecting excessively dark marks.

**Default:** `0.7` (70%)

**Usage:** Marks with fill_ratio > 0.7 may indicate heavy marking or possible tampering.

---

### 3. Confidence Thresholds

Used internally for confidence scoring:

- `reference` (0.3): Reference point for distance calculations
- `perfect_fill` (0.5): Target normalization for filled marks
- `noise_threshold` (0.15): Baseline for unfilled marks
- `low_confidence` (0.5): Warning threshold for confidence scores

---

### 4. Quality Thresholds

- `min_uniformity` (0.4): Minimum uniformity before warning
- `high_std_dev` (60): Expected standard deviation for filled marks after perspective transform

---

## Configuration

### Method 1: Environment Variables (Recommended for Deployment)

Add to `.env`:

```bash
# Primary detection threshold
OMR_DETECTION_THRESHOLD=0.3

# Classification thresholds
OMR_THRESHOLD_VALID=0.95
OMR_THRESHOLD_AMBIGUOUS_MIN=0.15
OMR_THRESHOLD_AMBIGUOUS_MAX=0.45
OMR_THRESHOLD_FAINT=0.16
OMR_THRESHOLD_OVERFILLED=0.7

# Confidence calculation
OMR_CONFIDENCE_REFERENCE=0.3
OMR_CONFIDENCE_PERFECT=0.5
OMR_CONFIDENCE_NOISE=0.15
OMR_CONFIDENCE_LOW=0.5

# Quality metrics
OMR_QUALITY_MIN_UNIFORMITY=0.4
OMR_QUALITY_HIGH_STD_DEV=60
```

Then:
```bash
php artisan config:cache
```

---

### Method 2: Direct Config File

Edit `config/omr-thresholds.php`:

```php
return [
    'detection_threshold' => 0.35,  // Custom value
    'classification' => [
        'valid_mark' => 0.90,
        // ... other values
    ],
];
```

---

### Method 3: Use Presets

Config file includes three presets:

```php
// For faint marks or poor scan quality
return config('omr-thresholds.presets.sensitive');

// For normal conditions (default)
return config('omr-thresholds.presets.balanced');

// For high-quality ballots with dark marks
return config('omr-thresholds.presets.conservative');
```

---

## Threshold Tuning Process

### Step 1: Run Threshold Tuning Test

```bash
php artisan simulation:tune-threshold \
  --config-dir=resources/docs/simulation/config \
  --bubbles=ROW_A_A1,ROW_B_B1,ROW_B_B2,ROW_B_B3,ROW_B_B4
```

This generates ballots at different intensities (100%, 85%, 70%, 55%, 40%, 25%) and measures detection accuracy.

**Output:**
```
storage/app/private/threshold-tuning/latest/
├── summary.json          # Accuracy metrics per intensity
├── REPORT.md            # Human-readable report
└── intensity-XXX/       # Results per intensity
    ├── ballot_filled.png
    ├── appreciation_results.json
    └── overlay.png
```

---

### Step 2: Review Results

Check `REPORT.md`:

```markdown
| Intensity | Detected | Accuracy | Avg Fill Ratio | Min Fill Ratio | Max Fill Ratio |
|-----------|----------|----------|----------------|----------------|----------------|
| 100%      | 5/5      | 100.0%   | 1.000          | 1.000          | 1.000          |
| 85%       | 0/5      | 0.0%     | 0.208          | 0.203          | 0.210          |
| 70%       | 0/5      | 0.0%     | 0.207          | 0.202          | 0.209          |
```

---

### Step 3: Interpret Recommendations

The tuning report includes automatic recommendations:

**Example 1: Safe Threshold**
```json
{
  "type": "safe_threshold",
  "message": "All marks at 100% intensity were detected correctly",
  "suggested_threshold": 0.9,
  "rationale": "90% of minimum fill_ratio provides safety margin"
}
```

**Example 2: False Negatives Detected**
```json
{
  "type": "false_negatives",
  "message": "Current threshold (0.3) is missing marks at 25% intensity",
  "suggested_threshold": 0.2,
  "rationale": "Lower threshold to 95% of max fill_ratio at failed intensity"
}
```

---

### Step 4: Test Finer Granularity

If there's a sharp drop-off between intensities, test narrower ranges:

```bash
php artisan simulation:tune-threshold \
  --intensities=1.0,0.95,0.90,0.88,0.85,0.80 \
  --bubbles=ROW_A_A1,ROW_B_B1
```

---

## Interpreting Results

### Understanding Fill Ratios

| Fill Ratio | Classification | Meaning |
|-----------|---------------|---------|
| 0.00-0.15 | Unfilled (gray) | Background noise, no mark |
| 0.16-0.29 | Faint (orange) | Visible but below threshold |
| 0.30-0.94 | Low Conf (yellow) | Filled but not high quality |
| 0.95-1.00 | Valid (green) | High-quality mark |
| > 0.70 | Overfilled (warning) | Unusually dark, check for issues |

---

### Real-World Example: Barangay Ballot

**Test Results:**
- **100% intensity**: Perfect detection (1.000 fill_ratio, 100% accuracy)
- **85% intensity**: All failed (0.207 fill_ratio - background noise level)

**Analysis:**
- Sharp threshold boundary between 100% and 85%
- Fill ratio of 1.000 indicates perfect marks
- Fill ratio of 0.207 is close to background noise (0.13-0.15)
- Suggests voter marks are either full or absent (binary behavior)

**Recommendation:**
- Keep `detection_threshold` at 0.3 for this ballot type
- Consider testing 90-95% intensity range to find actual threshold
- Current config appropriate for strong voter marking

---

### Common Patterns

#### Pattern 1: Gradual Decline
```
100% → 1.00 (✓)
90%  → 0.85 (✓)
80%  → 0.72 (✓)
70%  → 0.58 (✓)
60%  → 0.45 (✓)
50%  → 0.32 (✓)
40%  → 0.22 (✗)
```
**Interpretation:** Good dynamic range, threshold of 0.3 appropriate.

---

#### Pattern 2: Sharp Threshold
```
100% → 1.00 (✓)
90%  → 0.95 (✓)
85%  → 0.28 (✗)  ← Sharp drop
```
**Interpretation:** Binary behavior, need finer testing between 90-85%.

---

#### Pattern 3: Noisy Detection
```
100% → 1.00 (✓)
90%  → 0.42 (✓)
80%  → 0.38 (✗)  ← Just below threshold
70%  → 0.35 (✗)
```
**Interpretation:** Consider lowering threshold to 0.35 to catch faint marks.

---

## Presets

### Sensitive Preset

**Use When:**
- Poor scan quality
- Faint marking (pencil vs. pen)
- Low-contrast ballots
- Testing/acceptance of marginal marks

**Settings:**
```php
'detection_threshold' => 0.20,
'classification' => [
    'valid_mark' => 0.90,
    'ambiguous_min' => 0.12,
    'ambiguous_max' => 0.40,
    'faint_mark' => 0.12,
    'overfilled' => 0.65,
]
```

**Trade-offs:**
- ✅ Catches more faint marks
- ⚠️ Higher risk of false positives (smudges, fingerprints)

---

### Balanced Preset (Default)

**Use When:**
- Normal ballot conditions
- Standard pen marking
- Good scanner quality
- Production elections

**Settings:**
```php
'detection_threshold' => 0.30,
'classification' => [
    'valid_mark' => 0.95,
    'ambiguous_min' => 0.15,
    'ambiguous_max' => 0.45,
    'faint_mark' => 0.16,
    'overfilled' => 0.70,
]
```

**Trade-offs:**
- ✅ Good balance of sensitivity and accuracy
- ✅ Appropriate for most conditions
- ⚠️ May miss very faint marks

---

### Conservative Preset

**Use When:**
- High-quality ballot printing
- Dark, clear marks expected
- Minimizing false positives is critical
- Audit or recount scenarios

**Settings:**
```php
'detection_threshold' => 0.45,
'classification' => [
    'valid_mark' => 0.98,
    'ambiguous_min' => 0.20,
    'ambiguous_max' => 0.50,
    'faint_mark' => 0.20,
    'overfilled' => 0.75,
]
```

**Trade-offs:**
- ✅ Fewer false positives
- ⚠️ May miss legitimate faint marks
- ⚠️ Higher rejection rate

---

## Troubleshooting

### Problem: False Positives (Unmarked bubbles detected as filled)

**Symptoms:**
- Unfilled bubbles show as "filled" in overlays
- Unexpected votes in results

**Diagnosis:**
```bash
# Check if fill_ratios for "unfilled" are high
cat scenario-X-normal/votes.json | jq '.detected_votes[] | select(.fill_ratio < 0.4)'
```

**Solutions:**
1. **Increase detection threshold:**
   ```bash
   OMR_DETECTION_THRESHOLD=0.35  # or 0.4
   ```

2. **Check for scanning artifacts:**
   - Smudges, fingerprints
   - Paper texture showing as marks
   - Scanner noise

3. **Use conservative preset:**
   ```php
   return config('omr-thresholds.presets.conservative');
   ```

---

### Problem: False Negatives (Valid marks not detected)

**Symptoms:**
- Filled bubbles show as "unfilled" in overlays
- Missing votes in results
- Fill ratios between 0.15-0.45 (ambiguous range)

**Diagnosis:**
```bash
# Check fill_ratios of marks that should be detected
cat scenario-X-faint/votes.json | jq '.summary'
```

**Solutions:**
1. **Lower detection threshold:**
   ```bash
   OMR_DETECTION_THRESHOLD=0.25  # or 0.20
   ```

2. **Check mark intensity:**
   - Are voters using pencil vs. pen?
   - Is marking too light?

3. **Use sensitive preset:**
   ```php
   return config('omr-thresholds.presets.sensitive');
   ```

4. **Run threshold tuning with faint scenario:**
   ```bash
   ./scripts/simulation/run-simulation-laravel.sh --scenarios faint
   ```

---

### Problem: Inconsistent Detection Across Ballots

**Symptoms:**
- Some ballots detect perfectly, others fail
- Wide variance in fill_ratios for similar marks

**Diagnosis:**
```bash
# Compare fill_ratios across multiple test runs
php artisan simulation:tune-threshold --intensities=1.0,0.9,0.8,0.7
```

**Solutions:**
1. **Check scanner consistency:**
   - Calibrate scanner
   - Ensure consistent lighting
   - Verify scan resolution (300 DPI recommended)

2. **Test with real ballots:**
   - Print test ballots
   - Have multiple people mark them
   - Scan and test with actual equipment

3. **Adjust classification thresholds:**
   - Widen ambiguous range
   - Lower valid_mark threshold to accept more variance

---

### Problem: Too Many "Overfilled" Warnings

**Symptoms:**
- Most valid marks flagged with "overfilled" warning
- Fill ratios consistently 0.7-1.0

**Diagnosis:**
- Check overlay visualizations
- Review fill_ratio distribution

**Solutions:**
1. **Adjust overfilled threshold:**
   ```bash
   OMR_THRESHOLD_OVERFILLED=0.85  # Raise from 0.7
   ```

2. **This is often normal:**
   - Simulation fills at 100% intensity
   - Real voter marks may vary more
   - "Overfilled" is informational, not an error

---

## Best Practices

### 1. Always Test with Real Ballots

Simulated ballots use perfect circles. Real ballots have:
- Varying mark pressure
- Different pen types
- Scanning artifacts
- Paper texture

**Action:** Print, mark, scan, and test with actual equipment.

---

### 2. Document Your Configuration

Keep a record of threshold values used:

```bash
# Save configuration with test results
php artisan tinker --execute="echo json_encode(config('omr-thresholds'));" \
  > storage/app/private/simulation/config-snapshot.json
```

---

### 3. Run Threshold Tuning Before Production

**Recommended Schedule:**
- Initial deployment: Full tuning (all intensities)
- After scanner changes: Full tuning
- Quarterly: Quick validation test (1-2 intensities)
- Before each election: Validation with printed test ballots

---

### 4. Monitor Detection in Production

Track metrics:
- **Detection rate**: % of ballots with valid marks detected
- **Ambiguous rate**: % of marks with warnings
- **Manual review rate**: % requiring human review

If rates deviate from baseline, recalibrate thresholds.

---

### 5. Use Version Control for Config

Track threshold changes:

```bash
# Commit threshold configuration
git add config/omr-thresholds.php .env
git commit -m "chore: adjust detection threshold to 0.35 for faint marks"
```

---

### 6. Test Edge Cases

Always test scenarios:
- ✅ Normal marking (baseline)
- ✅ Faint marks (pencil, light pressure)
- ✅ Overvotes (multiple marks in single-choice)
- ✅ Partial marks (incomplete fills)
- ✅ Smudges (accidental marks)

---

### 7. Balance Sensitivity vs. Accuracy

**For Most Elections:**
- Favor **accuracy** (fewer false positives)
- Accept some **manual review** of ambiguous marks
- Use **balanced preset** as starting point

**For High-Stakes:**
- Favor **sensitivity** (catch all valid marks)
- Accept **higher false positive rate**
- Plan for **manual verification** workflow

---

## Summary

### Quick Reference Card

| Ballot Condition | Recommended Threshold | Preset |
|-----------------|----------------------|---------|
| High-quality, dark marks | 0.40-0.45 | Conservative |
| Normal conditions | 0.30-0.35 | Balanced ✅ |
| Faint marks, poor scans | 0.20-0.25 | Sensitive |

### Key Commands

```bash
# Run threshold tuning
php artisan simulation:tune-threshold --config-dir=path/to/config

# Test specific scenario
./scripts/simulation/run-simulation-laravel.sh --scenarios faint

# View current config
php artisan tinker --execute="print_r(config('omr-thresholds'));"

# Apply config changes
php artisan config:cache
```

---

## Further Reading

- `config/omr-thresholds.php` - Full configuration file with comments
- `storage/app/private/threshold-tuning/latest/REPORT.md` - Latest tuning results
- `packages/omr-appreciation/omr-python/mark_detector.py` - Detection algorithm source

---

**Questions?** Review threshold tuning reports or run simulation tests with different intensity ranges.
