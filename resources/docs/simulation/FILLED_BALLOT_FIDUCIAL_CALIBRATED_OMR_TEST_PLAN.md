# 🧭 HES OMR — Filled Ballot Appreciation Under Skew & Rotation  
### Version 1.0 — Test Plan for Fiducial-Calibrated OMR Appreciation

## 🎯 Objective
Verify that **bubbles (OMR marks)** are still accurately appreciated (detected and classified) even when the **ballot is rotated, skewed, or warped** — as long as fiducial alignment can correct it.

---

## 🧩 Scope

| Stage | Purpose |
|-------|----------|
| 1️⃣ Fiducial detection | Detect 3–4 tags; estimate homography and rotation |
| 2️⃣ Image alignment | Warp input to canonical coordinates |
| 3️⃣ ROI mapping | Overlay bubble masks from template definition |
| 4️⃣ Bubble thresholding | Detect filled vs. unfilled bubbles |
| 5️⃣ Appreciation scoring | Produce JSON with mark confidence |
| 6️⃣ Validation | Compare against expected marks |

---

## 🧠 Methodology Overview

```mermaid
flowchart TD
  A[Base Template (unfilled)] --> B[Generate Filled Ballots]
  B --> C[Apply Rotation/Skew/Perspective]
  C --> D[Run test-omr-appreciation.sh]
  D --> E[Fiducial Align & Warp]
  E --> F[Bubble Appreciation Engine]
  F --> G[Compare Against Expected JSON]
  G --> H[Accuracy Report]
```

---

## 🧾 Test Assets

| File/Folder | Purpose |
|--------------|----------|
| `templates/ballot_template.json` | Defines fiducials + bubble ROIs |
| `assets/ballot_blank.png` | Clean, unfilled ballot (A4 with 4 ArUco markers) |
| `fixtures/filled_ballots/` | Synthetic filled + distorted test images |
| `expected/filled_results.json` | Ground truth (expected marked/unmarked bubbles) |
| `2025-10-29_084311.zip` | Captured artifact set for calibration |

---

## 🧰 Step 1 — Generate Filled Ballot Fixtures

A Python script (`tools/generate_filled_variants.py`) should:
- Draw synthetic “filled” bubbles at ROI coordinates.
- Create skewed, rotated, and perspective-distorted copies.
- Save images in `fixtures/filled/`.

Example distortions:
- Rotation: ±3°, ±10°
- Shear: 2°, 6°
- Perspective: 0.98×, 0.93× vertical compression.

---

## ⚙️ Step 2 — Run Appreciation Pipeline

Each variant is processed through your existing wrapper:

```bash
ARGS="--mode aruco --size 2480x3508 --ids 101,102,103,104"

for img in fixtures/filled/*.png; do
  echo "Testing $img"
  ./test-omr-appreciation.sh "$img" $ARGS --expect expected/filled_results.json --save
done
```

Expected output:
- JSON result with appreciated marks  
- Saved overlays in `artifacts/overlays/`

---

## 📊 Step 3 — Evaluation Metrics

| Metric | Definition | Pass Threshold |
|---------|-------------|----------------|
| **Mark accuracy** | Correct vs. expected marks | ≥ 98% |
| **False positive rate** | Blank bubble detected as filled | ≤ 1% |
| **False negative rate** | Missed filled bubble | ≤ 2% |
| **Confidence stability** | Δ mean(confidence) (upright vs. 10° skew) | ≤ 0.05 |
| **Alignment residual** | Mean reprojection error post-homography | ≤ 2.0 px |

---

## 🔬 Step 4 — Comparison Script

Example: `tools/compare_appreciation.py`

```python
import json, sys

pred, truth = json.load(open(sys.argv[1])), json.load(open(sys.argv[2]))
tp=fp=fn=0
for k, v in truth.items():
    got = pred.get(k, False)
    if v and got: tp += 1
    elif v and not got: fn += 1
    elif not v and got: fp += 1
acc = tp / (tp + fp + fn)
print(f"Accuracy: {acc*100:.2f}% (TP={tp} FP={fp} FN={fn})")
if acc < 0.98: sys.exit(1)
```

---

## 🧮 Step 5 — Overlay Heatmap

Visualize detection accuracy:

```python
for bubble in bubbles:
    color = (0,255,0) if correct else (0,0,255)
    cv2.circle(image, (int(bx), int(by)), int(r), color, 2)
cv2.imwrite("artifacts/overlay_accuracy.png", image)
```

---

## 🧾 Step 6 — Example Results

| File | Rotation | Shear | Perspective | Accuracy | Avg Conf | Verdict |
|------|-----------|--------|--------------|-----------|----------|----------|
| ballot_filled_upright.png | 0° | 0° | 1.00 | 100% | 0.98 | ✅ |
| ballot_rot_+10.png | 10° | 0° | 1.00 | 98.5% | 0.96 | ⚠️ |
| ballot_shear_6deg.png | 0° | 6° | 1.00 | 96.2% | 0.93 | ❌ |
| ballot_persp_0.93.png | 0° | 0° | 0.93 | 95.7% | 0.90 | ❌ |

---

## ✅ Exit Criteria

- ≥98% accuracy for skew ≤6° or rotation ≤10°  
- Graceful degradation (no ROI drift)  
- System blocks ballots with skew/rotation ≥15° or <0.9 ratio
