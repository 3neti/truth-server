# OMR Mark Detection Metrics Guide

## Overview

The Python OpenCV OMR appreciation system now provides comprehensive quality metrics for each detected mark, helping you assess the reliability of the results and identify potential issues.

---

## Metrics Explained

### 1. **Fill Ratio** (0.0 to 1.0)

**What it is:** The proportion of dark pixels in the mark zone after thresholding.

**Interpretation:**
- `0.00 - 0.15`: Clearly unfilled
- `0.15 - 0.45`: Ambiguous (may need manual review)
- `0.45 - 0.70`: Clearly filled
- `> 0.70`: Overfilled (possible multiple marks or damage)

**Example:**
```json
"fill_ratio": 0.465
```
This mark has 46.5% dark pixels - clearly filled.

**Note:** Due to perspective transform interpolation, even a fully filled circle will typically show 0.40-0.50 fill ratio (not 1.0).

---

### 2. **Confidence** (0.0 to 1.0)

**What it is:** How certain the system is about the mark's status.

**Calculation factors:**
- **Clarity Score (50% weight)**: Distance from decision boundary
  - Marks near threshold (0.25-0.35) = low clarity
  - Marks far from threshold (<0.15 or >0.45) = high clarity
  
- **Uniformity (30% weight)**: How consistent the mark is
  - Low std deviation = uniform mark = high confidence
  - High std deviation = smudged/partial = low confidence
  
- **Separation (20% weight)**: Range between darkest and lightest pixels
  - Wide range (0-255) = clear distinction = high confidence
  - Narrow range = poor separation = low confidence

**Interpretation:**
- `> 0.80`: Very confident - mark is clear
- `0.60 - 0.80`: Confident - mark is acceptable
- `0.40 - 0.60`: Moderate - mark may have issues
- `< 0.40`: Low confidence - manual review recommended

**Example:**
```json
"confidence": 0.762
```
High confidence - this is a clean mark.

---

### 3. **Quality Metrics**

#### **Uniformity** (0.0 to 1.0)

How consistent the mark darkness is across the zone.

- `1.0`: Perfectly uniform (all pixels same shade)
- `0.5`: Moderate variation (acceptable)
- `< 0.4`: Non-uniform (smudged, partial, or damaged)

```json
"quality": {
  "uniformity": 0.918
}
```
Very uniform - likely a clean pencil/pen mark.

---

#### **Mean Darkness** (0.0 to 1.0)

Average darkness of the zone (inverted brightness).

- `0.0`: Pure white
- `0.5`: Medium gray
- `1.0`: Pure black

```json
"quality": {
  "mean_darkness": 0.463
}
```
Moderately dark overall.

---

#### **Standard Deviation**

Variation in pixel brightness values.

- `< 20`: Very uniform mark
- `20 - 60`: Typical filled mark
- `60 - 100`: Variable (may be partial or smudged)
- `> 100`: Highly variable (possibly bimodal - half filled/half empty)

```json
"quality": {
  "std_dev": 126.61
}
```
High variation - the mark has both very dark and very light regions (typical after perspective transform).

---

## Warning Flags

The system automatically flags potential issues:

### **ambiguous**
Fill ratio is in the uncertain range (0.15 - 0.45).

**Action:** Consider manual review or adjust threshold.

---

### **low_confidence**
Overall confidence score < 0.5.

**Causes:**
- Mark is near decision boundary
- High variability (smudge or partial mark)
- Poor image quality in that zone

**Action:** Review the mark visually or request rescan.

---

### **non_uniform**
Uniformity score < 0.4.

**Causes:**
- Partial filling
- Smudging
- Multiple marks in same zone
- Paper damage

**Action:** Visual inspection recommended.

---

### **overfilled**
Fill ratio > 0.7.

**Causes:**
- Multiple overlapping marks
- Mark extends outside zone boundaries
- Stray marks or damage

**Action:** Check for voter intent or ballot damage.

---

## Example Output

### Clean Filled Mark
```json
{
  "id": "Q1_A",
  "filled": true,
  "fill_ratio": 0.465,
  "confidence": 0.762,
  "quality": {
    "uniformity": 0.918,
    "mean_darkness": 0.463,
    "std_dev": 10.35
  },
  "warnings": null
}
```
✅ **Good**: High confidence, uniform, no warnings.

---

### Problematic Mark
```json
{
  "id": "Q2_B",
  "filled": true,
  "fill_ratio": 0.465,
  "confidence": 0.476,
  "quality": {
    "uniformity": 0.003,
    "mean_darkness": 0.463,
    "std_dev": 126.61
  },
  "warnings": ["low_confidence", "non_uniform"]
}
```
⚠️ **Caution**: Low confidence and non-uniform. Consider manual review.

---

### Unfilled Zone
```json
{
  "id": "Q1_B",
  "filled": false,
  "fill_ratio": 0.037,
  "confidence": 0.762,
  "quality": {
    "uniformity": 0.918,
    "mean_darkness": 0.008,
    "std_dev": 10.35
  },
  "warnings": null
}
```
✅ **Good**: Clearly unfilled with high confidence.

---

## Best Practices

### 1. **Set Appropriate Thresholds**

Default threshold is `0.25` (25% fill ratio).

- **Stricter** (e.g., `0.35`): Fewer false positives, may miss light marks
- **Lenient** (e.g., `0.20`): Catches more marks, may have false positives

### 2. **Use Confidence for Quality Control**

Flag ballots with average confidence < 0.6 for manual review:

```bash
jq '[.results[].confidence] | add / length' results.json
```

### 3. **Monitor Warning Patterns**

Track warning frequency across multiple ballots:
- High "ambiguous" rate → adjust threshold
- High "non_uniform" rate → check scanner or ballot quality
- High "overfilled" rate → voter education issue

### 4. **Audit High-Stakes Decisions**

For critical elections:
- Manually review all marks with confidence < 0.6
- Review all marks with warnings
- Compare results from multiple scanners

---

## Adjusting for Your Use Case

### Strict Mode (Elections)
```python
threshold = 0.30
min_confidence = 0.70
```

### Lenient Mode (Surveys)
```python
threshold = 0.20
min_confidence = 0.50
```

### Maximum Accuracy (Manual Review)
```python
threshold = 0.25
review_if_confidence < 0.60 or warnings != null
```

---

## Technical Notes

### Why is fill_ratio < 1.0 for fully filled marks?

1. **Circle in square**: A perfect circle fills only π/4 ≈ 78.5% of a square
2. **Perspective transform**: Bilinear interpolation creates gray edge pixels
3. **Otsu thresholding**: Automatically finds optimal threshold, may exclude gray pixels

Typical fill ratios:
- **Fully filled circle**: 0.40 - 0.55
- **75% filled circle**: 0.30 - 0.45
- **Partial mark**: 0.15 - 0.30
- **Stray mark**: < 0.15

### Why does confidence vary?

Confidence reflects multiple factors:
- Clean, solid marks far from threshold = high confidence
- Smudged marks near threshold = low confidence
- After perspective transform, interpolation reduces uniformity

Even correct detections may show moderate confidence (0.40-0.60) due to image processing artifacts.

---

## FAQ

**Q: Can I trust marks with low confidence?**

A: Yes, if fill_ratio is clearly above/below threshold. Low confidence just means the system is less certain, often due to image processing artifacts rather than actual ambiguity.

**Q: Should I always review marks with warnings?**

A: For high-stakes applications (elections), yes. For surveys or tests, you can usually trust the fill_ratio if it's far from the threshold.

**Q: What if all marks show "non_uniform" warnings?**

A: This is common after perspective transforms. Check a few manually - if they look correct, the warnings are due to interpolation artifacts and can be safely ignored.

**Q: How do I improve confidence scores?**

A: Better scan quality helps:
- Use higher DPI (300+ recommended)
- Ensure good lighting and contrast
- Use quality marking tools (dark pen/pencil)
- Minimize paper damage or smudging

---

## See Also

- [Python OpenCV Integration Plan](PYTHON_OPENCV_OMR_APPRECIATION_INTEGRATION_PLAN.md)
- [Calibration Guide](OPENCV_AWARE_OMR_CALIBRATION_PLAN.md)
- Test scripts: `test-omr-simple.sh`, `test-omr-workflow.sh`
