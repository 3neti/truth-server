# 🔧 AprilTag/ArUco Implementation Plan
## Sanity Check & Phased Implementation Strategy

This document provides a practical, phased approach to implementing AprilTag/ArUco fiducials as outlined in `APRILTAG_ARUCO_INTEGRATION.md`.

---

## ✅ Sanity Check Summary

### Current System Analysis
- ✅ **Existing fiducial detection works** (black squares via contour detection)
- ✅ **Homography pipeline is solid** (perspective correction working)
- ✅ **Configuration is flexible** (`config/omr-template.php` already has fiducials section)
- ✅ **Tests are comprehensive** (OMRAppreciationTest covers scenarios)
- ⚠️ **Limitations**: No unique IDs, orientation ambiguity, lighting sensitivity

### Proposed Enhancement Assessment
- ✅ **Well-scoped**: Builds on existing system without breaking changes
- ✅ **Backward compatible**: Can support both black squares and ArUco/AprilTag
- ✅ **Practical**: Uses OpenCV features (ArUco) already available
- ✅ **Testable**: Can validate with existing test infrastructure
- ⚠️ **Complexity**: Requires Python library updates and PHP marker generation

### Risk Assessment
- **Low Risk**: Configuration extension, backward compatibility
- **Medium Risk**: PHP marker generation (new rendering code)
- **Medium Risk**: Python detector changes (requires testing)
- **Low Risk**: Live webcam demo (optional, for validation)

### Recommendation: ✅ PROCEED with phased approach

---

## 📋 Implementation Phases

### Phase 1: Configuration Extension (2 hours)
**Status:** Ready to implement  
**Risk:** Low  
**Dependencies:** None

#### Tasks:
1. Extend `config/omr-template.php` fiducials section:
   ```php
   'fiducials' => [
       'mode' => env('OMR_FIDUCIAL_MODE', 'black_square'),  // 'black_square', 'aruco', 'apriltag'
       'aruco' => [
           'enabled' => false,
           'dictionary' => 'DICT_6X6_250',
           'corner_ids' => [101, 102, 103, 104],  // TL, TR, BR, BL
           'size_mm' => 20,
           'quiet_zone_mm' => 3,
       ],
       'apriltag' => [
           'enabled' => false,
           'family' => 'tag36h11',
           'corner_ids' => [0, 1, 2, 3],
           'size_mm' => 20,
           'quiet_zone_mm' => 3,
       ],
       // Keep existing black square config
       'default' => [
           'top_left' => ['x' => 10, 'y' => 10],
           'top_right' => ['x' => 190, 'y' => 10],
           'bottom_left' => ['x' => 10, 'y' => 277],
           'bottom_right' => ['x' => 190, 'y' => 277],
       ],
   ],
   ```

2. Add environment variables to `.env.example`:
   ```env
   OMR_FIDUCIAL_MODE=black_square
   OMR_ARUCO_DICTIONARY=DICT_6X6_250
   ```

3. Test: Verify config loads correctly, existing tests still pass

**Deliverables:**
- Updated `config/omr-template.php`
- Updated `.env.example`
- Config validation test

---

### Phase 2: Pre-generate ArUco Marker Images (4 hours)
**Status:** Ready to implement  
**Risk:** Low  
**Dependencies:** Phase 1

#### Approach:
Instead of generating ArUco markers in PHP (complex), **pre-generate them** as PNG files and include them as static assets.

#### Tasks:
1. Create Python script to generate ArUco marker images:
   ```python
   # scripts/generate_aruco_markers.py
   import cv2
   import numpy as np
   
   dictionary = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)
   marker_ids = [101, 102, 103, 104]
   marker_size_px = 200  # High resolution
   
   for marker_id in marker_ids:
       marker_image = cv2.aruco.generateImageMarker(dictionary, marker_id, marker_size_px)
       cv2.imwrite(f'resources/fiducials/aruco/marker_{marker_id}.png', marker_image)
   ```

2. Store markers in `resources/fiducials/aruco/`

3. Update TCPDF rendering code to place marker images:
   ```php
   if ($fiducialMode === 'aruco') {
       $markerPath = resource_path("fiducials/aruco/marker_{$cornerId}.png");
       $pdf->Image($markerPath, $x, $y, $size, $size);
   }
   ```

4. Test: Generate ballot with ArUco markers, verify they render correctly

**Deliverables:**
- `scripts/generate_aruco_markers.py`
- `resources/fiducials/aruco/marker_*.png` (4 files)
- Updated template rendering code
- Visual verification test

---

### Phase 3: Python Detector Enhancement (6 hours)
**Status:** Ready to implement  
**Risk:** Medium  
**Dependencies:** Phase 2

#### Tasks:
1. Create new detector module: `packages/omr-appreciation/omr-python/fiducial_detector.py`
   ```python
   def detect_fiducials_aruco(image, template):
       """Detect ArUco markers and return corners with IDs."""
       dictionary = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)
       parameters = cv2.aruco.DetectorParameters()
       corners, ids, rejected = cv2.aruco.detectMarkers(image, dictionary, parameters=parameters)
       
       # Match detected IDs to expected corner IDs
       expected_ids = template['fiducials']['aruco']['corner_ids']  # [101, 102, 103, 104]
       corner_map = {}
       
       for i, marker_id in enumerate(ids):
           if marker_id[0] in expected_ids:
               idx = expected_ids.index(marker_id[0])
               # Get center of marker
               center = corners[i][0].mean(axis=0)
               corner_map[idx] = tuple(center)
       
       if len(corner_map) >= 3:  # Need at least 3 corners for homography
           return [corner_map.get(i) for i in range(4)]
       return None
   ```

2. Update `image_aligner.py` to support multiple fiducial modes:
   ```python
   def detect_fiducials(image, template):
       mode = template.get('fiducials', {}).get('mode', 'black_square')
       
       if mode == 'aruco':
           from fiducial_detector import detect_fiducials_aruco
           return detect_fiducials_aruco(image, template)
       elif mode == 'apriltag':
           from fiducial_detector import detect_fiducials_apriltag
           return detect_fiducials_apriltag(image, template)
       else:
           # Existing black square detection
           return detect_fiducials_black_square(image, template)
   ```

3. Update `appreciate.py` to pass fiducial mode from config

4. Test with printed ArUco ballots

**Deliverables:**
- `fiducial_detector.py` module
- Updated `image_aligner.py`
- Unit tests for ArUco detection
- Integration test with real ballot

---

### Phase 4: Automated Tests (4 hours)
**Status:** Ready to implement  
**Risk:** Low  
**Dependencies:** Phase 3

#### Tasks:
1. Create test scenario in `tests/Feature/OMRAppreciationTest.php`:
   ```php
   it('appreciates ballot with ArUco markers', function () {
       // Similar to existing test but with ArUco config
       config(['omr-template.fiducials.mode' => 'aruco']);
       // ... generate ballot, detect, verify
   });
   ```

2. Add comparison test (black square vs ArUco):
   - Same ballot content
   - Different fiducial modes
   - Compare detection success rates

3. Add noise/distortion tolerance test

**Deliverables:**
- ArUco test scenarios
- Comparison benchmarks
- Test documentation

---

### Phase 5: Live Webcam Demo (Optional, 6 hours)
**Status:** Nice to have  
**Risk:** Low  
**Dependencies:** Phase 3

#### Tasks:
1. Create `examples/live_fiducial_appreciation.py`:
   ```python
   import cv2
   from fiducial_detector import detect_fiducials_aruco
   
   cap = cv2.VideoCapture(0)
   while True:
       ret, frame = cap.read()
       corners = detect_fiducials_aruco(frame, config)
       if corners:
           # Draw detected markers
           # Show warped preview
       cv2.imshow('Live Detection', frame)
   ```

2. Add CLI arguments for mode switching

3. Document usage in README

**Deliverables:**
- `examples/live_fiducial_appreciation.py`
- Usage documentation
- Demo video/screenshots

---

### Phase 6: Documentation & Training (2 hours)
**Status:** Ready to implement  
**Risk:** Low  
**Dependencies:** All phases

#### Tasks:
1. Update `resources/docs/simulation/HOW_TO_RUN_APPRECIATION_TESTS.md`
2. Create migration guide (black square → ArUco)
3. Document configuration options
4. Add troubleshooting section

**Deliverables:**
- Updated documentation
- Migration guide
- Configuration examples

---

## 📊 Timeline Estimate

| Phase | Duration | Start After | Status |
|-------|----------|-------------|--------|
| Phase 1: Config | 2 hours | - | Ready |
| Phase 2: Marker Generation | 4 hours | Phase 1 | Ready |
| Phase 3: Python Detector | 6 hours | Phase 2 | Ready |
| Phase 4: Tests | 4 hours | Phase 3 | Ready |
| Phase 5: Live Demo | 6 hours | Phase 3 | Optional |
| Phase 6: Docs | 2 hours | All | Ready |

**Total Core Implementation:** 16-18 hours  
**With Optional Demo:** 22-24 hours

---

## 🎯 Success Criteria

### MVP (Minimum Viable Product)
- ✅ ArUco markers render in ballots
- ✅ Python detector identifies ArUco markers
- ✅ Homography works with detected markers
- ✅ Existing tests pass
- ✅ New ArUco test passes

### Full Feature Set
- ✅ All MVP criteria
- ✅ AprilTag support (in addition to ArUco)
- ✅ Live webcam demo works
- ✅ Comprehensive documentation
- ✅ Performance benchmarks
- ✅ Migration guide

---

## 🔍 Key Design Decisions

### 1. Pre-generated vs Dynamic Markers
**Decision:** Pre-generate marker images  
**Rationale:**
- ✅ Simpler PHP code (just place images)
- ✅ Consistent quality (generated once at high resolution)
- ✅ No OpenCV/ArUco PHP library needed
- ⚠️ Less flexible (need to regenerate for new IDs)

### 2. Backward Compatibility
**Decision:** Keep black square support  
**Rationale:**
- ✅ Existing ballots continue to work
- ✅ Gradual migration possible
- ✅ Fallback if ArUco has issues
- ✅ A/B testing capability

### 3. Detection Strategy
**Decision:** ID-based corner matching  
**Rationale:**
- ✅ Unambiguous (ID 101 = top-left)
- ✅ Rotation invariant
- ✅ Partial detection support (≥3 markers)
- ✅ Future-proof (can add edge markers)

### 4. AprilTag vs ArUco
**Decision:** Start with ArUco, add AprilTag later  
**Rationale:**
- ✅ ArUco is built into OpenCV
- ✅ No additional dependencies
- ✅ Simpler to implement
- ✅ Can add AprilTag in Phase 5+

---

## ⚠️ Risks & Mitigations

### Risk 1: Marker Detection Failure
**Mitigation:**
- Require ≥3 detected markers (graceful degradation)
- Add debug visualization mode
- Provide marker quality checker tool
- Document lighting/print requirements

### Risk 2: PHP Marker Generation Complexity
**Mitigation:**
- Use pre-generated images (avoids PHP implementation)
- Provide Python script for regeneration
- Store high-res markers (scalable)

### Risk 3: Backward Compatibility Issues
**Mitigation:**
- Keep existing black square code
- Use feature flag (`OMR_FIDUCIAL_MODE`)
- Add tests for both modes
- Document migration path

### Risk 4: Performance Degradation
**Mitigation:**
- Benchmark ArUco vs black square detection
- Optimize detector parameters
- Cache marker dictionary
- Profile critical paths

---

## 🧪 Testing Strategy

### Unit Tests
- ✅ Config validation
- ✅ Marker ID mapping
- ✅ Corner detection logic
- ✅ Homography computation

### Integration Tests
- ✅ End-to-end ballot generation
- ✅ ArUco detection with test images
- ✅ Comparison with black square
- ✅ Edge cases (missing markers, partial detection)

### Visual Tests
- ✅ Render markers in ballot
- ✅ Print and scan verification
- ✅ Overlay visualization
- ✅ Live webcam demo

### Performance Tests
- ✅ Detection speed comparison
- ✅ Memory usage
- ✅ Multiple markers handling
- ✅ Distortion tolerance

---

## 📚 References

- [ArUco Documentation](https://docs.opencv.org/4.x/d5/dae/tutorial_aruco_detection.html)
- [AprilTag Library](https://github.com/AprilRobotics/apriltag)
- Current Implementation: `packages/omr-appreciation/omr-python/image_aligner.py`
- Config: `config/omr-template.php`

---

## 🚀 Next Steps

1. ✅ Review this plan with team
2. ⏭️ Implement Phase 1 (Config)
3. ⏭️ Generate marker images (Phase 2)
4. ⏭️ Update Python detector (Phase 3)
5. ⏭️ Add tests (Phase 4)
6. ⏭️ Documentation (Phase 6)
7. 🎯 Optional: Live demo (Phase 5)

---

*Document created: 2025-10-28*  
*Implementation strategy: Phased, backward-compatible, testable*
