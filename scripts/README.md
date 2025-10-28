# Project Scripts

This directory contains utility scripts for the Truth project.

## Current Scripts

### Fiducial Marker Generation
- `generate_aruco_markers.py` - Generate ArUco marker images
- `generate_apriltag_markers.py` - Generate AprilTag marker images

### Testing & Debugging
- `debug_fiducial_detection.py` - Debug fiducial marker detection
- `test-fiducial-detection.sh` - Automated fiducial detection tests
- `test-omr-appreciation.sh` - OMR appreciation test suite

### Test Data Generation
- `synthesize_ballot_variants.py` - Generate synthetic ballot variants with geometric distortions

## TODO: Package Refactoring

**Future improvement:** Move OMR-specific scripts into their respective packages for better isolation:

```
packages/omr-appreciation/
├── omr-python/           # Python OMR code
├── scripts/              # OMR-specific scripts (TODO: create)
│   ├── synthesize_ballot_variants.py
│   ├── debug_fiducial_detection.py
│   └── ...
├── examples/             # Example usage
└── tests/                # Package tests
```

**Benefits:**
- Better package isolation
- Scripts can be vendor-published with package
- Clear ownership and dependencies
- Easier to distribute as standalone package

**Current approach:** Keep scripts at project root for simplicity during development. Refactor when stabilized.

---

*Note: This is a pragmatic "make it work now, organize later" approach. Humans have limited memory, so this README serves as a reminder!* 📝
