# HES OMR — Skew & Rotation Test Scenario (AprilTag/ArUco)

This scenario validates **orientation** and **skew** detection using your live pipeline
(`live_fiducial_appreciation.py`) and your runner script (`test-omr-appreciation.sh`).

> **Goal:** prove that the system (a) flags rotation (θ), (b) flags skew/shear, (c) quantifies perspective (“trapezoidness”), and (d) stays within acceptance thresholds **before** running appreciation.

---

## 1) Assumptions

- You have a **clean reference ballot image** with four ArUco/AprilTag corner markers.
- Use your `test-omr-appreciation.sh` script or call the detector directly.
- The viewer prints metrics (`θ`, `shear`, `ratio_tb`, `ratio_lr`, `reproj`).

---

## 2) Acceptance thresholds

| Metric | Green (Pass) | Amber (Warn) | Red (Fail) |
|--------|---------------|--------------|-------------|
| Rotation θ | |θ| ≤ 3° | 3–10° | >10° |
| Shear | ≤ 2° | 2–6° | >6° |
| ratio_tb / ratio_lr | ≥ 0.95 | 0.90–0.95 | <0.90 |
| Reproj error | <1.5 px | 1.5–3 px | >3 px |

---

## 3) Synthetic fixture generator

See `tools/synthesize_ballot_variants.py` in the main content above for creating rotated, sheared, and perspective-distorted variants of a reference ballot.

---

## 4) Test matrix

| Case ID | Input | Expect θ | Expect shear | ratio_tb | Verdict |
|---------|--------|----------|--------------|-----------|----------|
| R1 | rot_+3.png | ~+3° | 0° | ~1.00 | Amber |
| R2 | rot_+10.png | ~+10° | 0° | ~1.00 | Red |
| R3 | rot_-20.png | ~−20° | 0° | ~1.00 | Red |
| S1 | shearX_2deg.png | 0° | ~2° | ~1.00 | Green/Amber |
| S2 | shearX_6deg.png | 0° | ~6° | ~1.00 | Red |
| P1 | persp_tb_0.98.png | 0° | 0° | 0.98 | Green |
| P2 | persp_tb_0.95.png | 0° | 0° | 0.95 | Amber |
| P3 | persp_tb_0.90.png | 0° | 0° | 0.90 | Amber/Red |
| U0 | ref_upright.png | 0° | 0° | 1.00 | Green |

---

## 5) Run commands

```bash
ARGS="--mode aruco --size 2480x3508 --ids 101,102,103,104"
./test-omr-appreciation.sh fixtures/ref_upright.png $ARGS
./test-omr-appreciation.sh fixtures/rot_+10.png $ARGS
./test-omr-appreciation.sh fixtures/shearX_6deg.png $ARGS
```

---

## 6) Automated check (bash + awk)

See full parser snippet above. It checks thresholds for θ, shear, and ratios, printing PASS/FAIL.

---

## 7) Exit criteria

- Green for upright and near-perfect.
- Amber for minor misalignment.
- Red for significant skew or rotation.
