# 🧭 Implementation Plan: Coordinate-Transformed Appreciation Fix
### (For packages/omr-appreciation — Fiducial Alignment Bug)

## 🎯 Objective
Resolve the **critical bug** where bubble detection fails (0 % accuracy) after perspective alignment.  
Root cause: `cv2.warpPerspective()` moves pixels, but bubble coordinates remain in template space.

## ✅ Fix Summary
Transform bubble coordinates using the **inverse homography matrix** instead of warping the image.  
This maintains pixel fidelity, speeds up processing, and ensures alignment accuracy.

---

## 🧩 Step-by-Step Fix

### 1. `image_aligner.py`

Replace warping block with:

```python
def align_image(image, fiducials, template, verbose=False):
    src_points = np.array([f["corners"].mean(axis=0) for f in fiducials], dtype=np.float32)
    dst_points = np.array(template["corner_points"], dtype=np.float32)

    matrix, _ = cv2.findHomography(src_points, dst_points, cv2.RANSAC, 5.0)
    inv_matrix = np.linalg.inv(matrix)

    quality = compute_quality_metrics(src_points, dst_points, matrix)
    if verbose:
        print(f"[ALIGN] reprojection={quality['reproj_error']:.2f}px θ={quality['theta']:.2f}°", file=sys.stderr)
    return image, quality, inv_matrix
```

- ✅ Returns inverse homography (`inv_matrix`)
- ✅ Leaves original image unwarped

---

### 2. `appreciate.py`

Update pipeline:

```python
image, quality, inv_matrix = align_image(...)
results = detect_marks(image, coords, inv_matrix=inv_matrix)
```

---

### 3. `mark_detector.py`

Add coordinate transformer:

```python
def transform_coords(coords, inv_matrix):
    pts = np.array(coords, dtype=np.float32).reshape(-1, 1, 2)
    transformed = cv2.perspectiveTransform(pts, inv_matrix)
    return transformed.reshape(-1, 2)

def detect_marks(image, template_coords, inv_matrix=None, threshold=0.3):
    if inv_matrix is not None:
        template_coords = transform_coords(template_coords, inv_matrix)

    marks = []
    for (x, y, r, label) in template_coords:
        roi = image[int(y - r):int(y + r), int(x - r):int(x + r)]
        if roi.size == 0:
            marks.append((label, False))
            continue
        gray = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
        filled = np.mean(gray) < 255 * (1 - threshold)
        marks.append((label, filled))
    return marks
```

---

## ⚙️ Testing

### Validation Matrix

| Case | Input | Expected Accuracy |
|------|--------|------------------|
| Upright (no align) | ✅ | 100 % |
| Upright (coordinate-align) | ✅ | 100 % |
| +3° rotation | ≥ 98 % |
| +10° rotation | ≥ 95 % |
| 6° shear | ≥ 95 % |
| Perspective 0.95 | ≥ 95 % |

### Regression Script

```bash
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py "$img" "$coords"   --coordinate-align --threshold 0.3 > results/${name}_coord_align.json

python3 compare_appreciation_results.py   --result results/${name}_coord_align.json   --truth expected/filled_results.json
```

All results should match upright baseline.

---

## ✅ Deployment Checklist
- [ ] Update `align_image()` to return inverse matrix
- [ ] Update `appreciate.py` and `mark_detector.py`
- [ ] Verify all test fixtures (upright, rotated, skewed, perspective)
- [ ] Confirm ≥98 % accuracy parity
- [ ] Tag release `v1.2.0-fiducial-align-fix`

---

## 📈 Expected Outcome

| Scenario | Before Fix | After Fix |
|-----------|-------------|-----------|
| Upright | ✅ 100 % | ✅ 100 % |
| +3° Rotation | ❌ 0 % | ✅ 98 % |
| +10° Rotation | ❌ 0 % | ✅ 95 % |
| Perspective 0.95 | ❌ 0 % | ✅ 95 % |

---

© 2025 3neti R&D OPC — Internal HES OMR Documentation
