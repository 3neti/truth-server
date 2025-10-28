# 🎥 Live Fiducial Demo Integration Guide
## How to Use `live_fiducial_appreciation.py` with Our Implementation

This document explains how the provided `live_fiducial_appreciation.py` script fits into our AprilTag/ArUco implementation plan and how to integrate it effectively.

---

## ✅ Sanity Check: Script Quality Assessment

### Strengths
- ✅ **Well-structured**: Clean argument parsing, modular functions
- ✅ **Dual-mode support**: Both ArUco and AprilTag detection
- ✅ **Graceful fallback**: Works with ≥3 detected markers
- ✅ **Real-time visualization**: Shows detected markers and homography status
- ✅ **Production-ready**: Error handling, command-line args, save functionality
- ✅ **Minimal dependencies**: Only requires OpenCV (+ optional apriltag)

### Script Capabilities
1. ✅ Live webcam capture
2. ✅ ArUco/AprilTag detection with ID matching
3. ✅ Homography computation (perspective correction)
4. ✅ Real-time warped preview
5. ✅ Save aligned frame for inspection
6. ✅ Visual overlay of detected markers

### Integration Points
- 🔗 **Phase 3**: Python detector enhancement
- 🔗 **Phase 4**: Testing & validation
- 🔗 **Phase 5**: Live demo (THIS SCRIPT!)

**Assessment:** ✅ **EXCELLENT** - This script is production-quality and ready to integrate!

---

## 🎯 How It Fits Into Our Implementation Plan

### Current Position: Phase 5 (Live Webcam Demo)

This script **IS** the Phase 5 deliverable! It provides:

```mermaid
flowchart LR
    A[Webcam] --> B[live_fiducial_appreciation.py]
    B --> C[ArUco/AprilTag Detection]
    C --> D[Homography]
    D --> E[Warped Preview]
    E --> F[Visual Validation]
    
    style B fill:#90EE90
    style F fill:#FFD700
```

### Integration Strategy

Instead of building Phase 5 from scratch, we **adapt** this script:

1. ✅ **Use as-is** for initial testing (Phase 3/4)
2. 🔧 **Extend** with bubble detection overlay (Phase 5)
3. 🔗 **Connect** to our appreciation pipeline (Future)

---

## 📁 Where to Place the Script

### Recommended Location

Move from docs to examples:

```bash
# Move script to examples directory
mkdir -p packages/omr-appreciation/examples
mv resources/docs/omr-appreciation/live_fiducial_appreciation.py \
   packages/omr-appreciation/examples/
```

### Final Structure

```
packages/omr-appreciation/
├── omr-python/
│   ├── appreciate.py              # Main appreciation script
│   ├── image_aligner.py           # Homography logic
│   ├── fiducial_detector.py       # NEW: ArUco/AprilTag detection
│   └── bubble_detector.py         # Bubble appreciation
├── examples/
│   ├── live_fiducial_appreciation.py   # THIS SCRIPT (Phase 5)
│   └── README.md                       # Usage examples
└── tests/
    └── test_fiducial_detector.py
```

---

## 🚀 Usage Scenarios

### Scenario 1: Validate ArUco Marker Detection (Phase 3)

**Goal:** Test that printed ArUco ballots are detected correctly

```bash
cd packages/omr-appreciation/examples

# Test with printed ballot (ArUco markers 101-104)
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --dict DICT_6X6_250 \
  --ids 101,102,103,104 \
  --size 2480x3508 \
  --show-warp

# Expected: 
# - Green boxes around detected markers
# - "H: OK" indicator when ≥3 markers found
# - Warped preview shows aligned ballot
```

**Success Criteria:**
- ✅ All 4 corner markers detected
- ✅ Homography computed successfully
- ✅ Warped preview shows properly aligned ballot

---

### Scenario 2: Test Different Marker Dictionaries (Phase 3)

```bash
# Test with different ArUco dictionary
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --dict DICT_4X4_100 \
  --ids 10,11,12,13 \
  --size 2480x3508
```

---

### Scenario 3: AprilTag Mode (Phase 5+)

```bash
# Install AprilTag library first
pip3 install apriltag

# Run with AprilTag detection
python3 live_fiducial_appreciation.py \
  --mode apriltag \
  --ids 0,1,2,3 \
  --size 2480x3508 \
  --show-warp
```

---

### Scenario 4: Save Aligned Frame for Testing (Phase 4)

```bash
# Capture and save aligned ballot
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --ids 101,102,103,104 \
  --size 2480x3508 \
  --save

# Press ESC when ballot is properly aligned
# Output: aligned_last.png (ready for bubble detection)
```

---

## 🔧 Integration Steps

### Step 1: Extract Reusable Components (Phase 3)

The script has functions we can integrate into our codebase:

**Extract to `fiducial_detector.py`:**

```python
# packages/omr-appreciation/omr-python/fiducial_detector.py

def detect_aruco(gray, aruco_dict):
    """Detect ArUco markers in grayscale image."""
    # Copy from live_fiducial_appreciation.py lines 59-68
    params = cv2.aruco.DetectorParameters()
    detector = cv2.aruco.ArucoDetector(aruco_dict, params)
    corners, ids, _ = detector.detectMarkers(gray)
    out = []
    if ids is not None:
        for c, i in zip(corners, ids):
            out.append({"id": int(i[0]), "corners": c.reshape(-1, 2)})
    return out

def order_by_ids(detections, wanted_ids):
    """Match detected markers to expected corner IDs."""
    # Copy from live_fiducial_appreciation.py lines 81-93
    found = {}
    for d in detections:
        found[d["id"]] = d["corners"]
    ordered = []
    for wid in wanted_ids:
        if wid in found:
            ordered.append(found[wid].mean(axis=0))
        else:
            ordered.append(None)
    return ordered

def compute_homography(ordered_points, W, H):
    """Compute perspective transform from detected corners."""
    # Copy from live_fiducial_appreciation.py lines 96-111
    # ... (keep the implementation)
```

**Update `image_aligner.py`:**

```python
# packages/omr-appreciation/omr-python/image_aligner.py

from fiducial_detector import detect_aruco, order_by_ids, compute_homography

def detect_fiducials(image, template):
    """Detect fiducials based on configured mode."""
    mode = template.get('fiducials', {}).get('mode', 'black_square')
    
    if mode == 'aruco':
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        aruco_config = template['fiducials']['aruco']
        
        # Get dictionary and expected IDs
        dict_name = aruco_config['dictionary']
        aruco_dict = cv2.aruco.getPredefinedDictionary(
            getattr(cv2.aruco, dict_name)
        )
        
        # Detect and match to corners
        detections = detect_aruco(gray, aruco_dict)
        wanted_ids = aruco_config['corner_ids']  # [101, 102, 103, 104]
        ordered_points = order_by_ids(detections, wanted_ids)
        
        # Filter out None values for return
        return [pt for pt in ordered_points if pt is not None]
    
    else:
        # Fall back to black square detection
        return detect_fiducials_black_square(image, template)
```

---

### Step 2: Enhance Script with Bubble Overlay (Phase 5)

Add bubble detection visualization to the live demo:

```python
# packages/omr-appreciation/examples/live_fiducial_appreciation.py

def draw_bubble_regions(warped, bubble_coords):
    """Draw bubble regions on warped image."""
    for bubble_id, coords in bubble_coords.items():
        x, y = int(coords['center_x']), int(coords['center_y'])
        r = int(coords['radius'])
        cv2.circle(warped, (x, y), r, (0, 255, 255), 2)
        cv2.putText(warped, bubble_id, (x+r+5, y), 
                    cv2.FONT_HERSHEY_SIMPLEX, 0.4, (255, 255, 0), 1)

# In main loop, after warping:
if Hm is not None and bubble_coords:
    warp = cv2.warpPerspective(frame, Hm, (W, H))
    draw_bubble_regions(warp, bubble_coords)  # NEW
    last_warp = warp.copy()
```

---

### Step 3: Connect to Appreciation Pipeline (Future)

**Full integration workflow:**

```python
# packages/omr-appreciation/examples/live_appreciation_full.py

from fiducial_detector import detect_aruco, compute_homography
from bubble_detector import detect_bubbles, appreciate_marks

def process_frame(frame, template, config):
    """Full appreciation pipeline on single frame."""
    
    # 1. Detect fiducials and warp
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    detections = detect_aruco(gray, config['aruco_dict'])
    ordered = order_by_ids(detections, config['corner_ids'])
    H = compute_homography(ordered, config['W'], config['H'])
    
    if H is None:
        return None, "Insufficient fiducials"
    
    # 2. Warp to canonical size
    warped = cv2.warpPerspective(frame, H, (config['W'], config['H']))
    
    # 3. Detect and appreciate bubbles
    results = appreciate_marks(warped, template['bubble'])
    
    # 4. Create overlay
    overlay = draw_results_overlay(warped, results)
    
    return results, overlay
```

---

## 📊 Testing with the Script

### Test Checklist

Use the script to validate each phase:

#### Phase 3: ArUco Detection
- [ ] Generate ballot with ArUco markers
- [ ] Print ballot on matte paper
- [ ] Run `live_fiducial_appreciation.py`
- [ ] Verify all 4 markers detected
- [ ] Check warped preview alignment
- [ ] Save aligned frame for inspection

#### Phase 4: Robustness Testing
- [ ] Test with poor lighting
- [ ] Test with perspective distortion
- [ ] Test with partially occluded markers
- [ ] Test with ≥3 markers (one missing)
- [ ] Verify graceful degradation

#### Phase 5: Full Pipeline
- [ ] Add bubble overlay to script
- [ ] Verify bubbles align with warped coordinates
- [ ] Test real-time appreciation
- [ ] Save annotated results

---

## 🎓 Learning from the Script

### Key Takeaways

**1. Graceful Degradation:**
```python
if len(src) < 3:
    return None  # Need at least 3 points for homography
```
✅ Implement in our detector: warn but continue with ≥3 markers

**2. Visual Feedback:**
```python
cv2.putText(frame, "H: OK", (20, 40), ...)
cv2.putText(frame, "H: MISSING (need >=3 corners)", (20, 40), ...)
```
✅ Add status indicators to our overlays

**3. ID-based Matching:**
```python
ordered = order_by_ids(detections, wanted_ids)
```
✅ Use same pattern in our `fiducial_detector.py`

**4. Marker Center as Reference:**
```python
ordered.append(found[wid].mean(axis=0))  # Use center, not all 4 corners
```
✅ Simpler and more robust than corner matching

---

## 🔄 Migration Path

### From Script to Production

**Week 1: Extract & Integrate**
1. Move script to `examples/`
2. Extract reusable functions to `fiducial_detector.py`
3. Update `image_aligner.py` to use new detector
4. Add unit tests

**Week 2: Enhance & Document**
1. Add bubble overlay to script
2. Create usage examples
3. Write troubleshooting guide
4. Record demo video

**Week 3: Production Integration**
1. Connect to `appreciate.py`
2. Add to test suite
3. Performance benchmarking
4. Documentation updates

---

## 📝 Quick Start Guide

### For Developers

```bash
# 1. Move script to examples
mkdir -p packages/omr-appreciation/examples
mv resources/docs/omr-appreciation/live_fiducial_appreciation.py \
   packages/omr-appreciation/examples/

# 2. Test with webcam (no ballot needed yet)
cd packages/omr-appreciation/examples
python3 live_fiducial_appreciation.py --mode aruco

# 3. Generate ArUco markers for testing
cd ../../../scripts
python3 generate_aruco_markers.py  # From Phase 2

# 4. Print markers, tape to paper, test detection
cd ../packages/omr-appreciation/examples
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --ids 101,102,103,104 \
  --show-warp

# 5. When detection works, generate full ballot
# ... (use updated template rendering from Phase 2)
```

---

## 🎯 Success Metrics

Use the script to measure:

- **Detection Rate**: % of frames with ≥3 markers detected
- **Alignment Quality**: Visual inspection of warped preview
- **Performance**: FPS (should be >15 for real-time)
- **Robustness**: Works under various lighting conditions

---

## 🔗 Related Documents

- [AprilTag/ArUco Integration](APRILTAG_ARUCO_INTEGRATION.md) - Original proposal
- [Implementation Plan](APRILTAG_ARUCO_IMPLEMENTATION_PLAN.md) - Phased approach
- [Phase 5 Details](APRILTAG_ARUCO_IMPLEMENTATION_PLAN.md#phase-5-live-webcam-demo-optional-6-hours) - Where this script fits

---

## 🎉 Conclusion

This script is **production-ready** and should be:
1. ✅ **Adopted as-is** for Phase 5
2. 🔧 **Mined for patterns** to apply in Phase 3
3. 📚 **Used as reference** for our production code
4. 🧪 **Leveraged for testing** throughout development

It demonstrates best practices for:
- Dual-mode fiducial detection
- Real-time visualization
- Graceful error handling
- Command-line configurability

**Status:** Ready to integrate immediately! 🚀

---

*Document created: 2025-10-28*  
*Script location: `resources/docs/omr-appreciation/live_fiducial_appreciation.py`*  
*Recommended move: `packages/omr-appreciation/examples/`*
