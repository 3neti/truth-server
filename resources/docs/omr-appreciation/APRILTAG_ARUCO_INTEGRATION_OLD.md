# 🧭 Implementation Plan: AprilTag | ArUco Fiducial Integration
### for `lbhurtado/omr-appreciation` (Python/OpenCV) + `omr-templates` (PHP/TCPDF)

This document provides a **drop-in migration path** from legacy black-square fiducials to **AprilTag | ArUco** markers, along with a **ready-to-run Python sample** for live webcam detection, alignment (homography), and overlay—so you can plug it straight into your appreciation flow.

---

## 🎯 Goals

- Replace black squares with **robust fiducials** that provide:
    - ✅ Unique IDs (per corner/edge) for deterministic matching
    - ✅ Stable pose/orientation for AR overlay and OMR warping
    - ✅ Cross-language consistency (Python detector ↔ PHP generator)
- Maintain a **config-driven** layout, compatible with your template JSON/Handlebars schema.
- Provide a **real-time webcam** demo you can extend into your appreciation pipeline.

---

## 🧩 System Overview

```mermaid
flowchart TD
  A[Generate Template (TCPDF/Handlebars)] --> B[Add AprilTag/Aruco Markers]
  B --> C[Print Ballot / OMR Sheet]
  C --> D[Capture via Webcam / Scanner]
  D --> E[Python FiducialDetector]
  E --> F[Pose Estimation & Homography (ImageAligner)]
  F --> G[Warp & Map OMR Bubble ROIs]
  G --> H[Bubble Appreciation & Confidence Scoring]
  H --> I[Overlay / JSON Output / Artifacts]
```

---

## 🧠 Phase 1 — Template Schema & Generator (PHP/TCPDF)

1) **Schema extension** (example JSON embedded in template config or Handlebars data):

```json
"fiducials": {
  "mode": "aruco",
  "dictionary": "DICT_6X6_250",
  "corner_ids": [101, 102, 103, 104],  // TL, TR, BR, BL (convention)
  "corner_size_mm": 20,
  "quiet_zone_mm": 3,
  "edge_microtags": [],
  "qr": {
    "enabled": true,
    "payload": { "uuid": "<uuid>", "precinct": "<code>", "version": "v1", "checksum": "<sha1>" },
    "size_mm": 12,
    "quiet_zone_mm": 2,
    "position": "footer-right"
  }
}
```

2) **Rendering guidance**:
- Use **TCPDF** to place vector/bitmap tags at **safe margins** (corners inside trim area).
- Maintain **quiet zones** (white borders) around each tag (similar to QR quiet zones).
- Keep **consistent ID ↔ corner convention**: e.g., (TL=101, TR=102, BR=103, BL=104).

3) **AprilTag option**:
- If you want AprilTag graphics embedded by PHP, pre-build PNG/SVG tags offline, store in your `resources/` and place them just like images. (Aruco markers can be generated on-the-fly with OpenCV or pre-rendered as well.)

---

## ⚙️ Phase 2 — Python Detector (AprilTag | ArUco)

Create a module (or use the **sample script** bundled below) that supports both:

- **ArUco** (OpenCV): batteries-included, easy to ship
- **AprilTag**: use `pip install apriltag` (or `pip install pupil-apriltags`) when you want the AprilTag family and performance

**Recommended default:** `aruco` (fewer external deps). Switch to `apriltag` where needed.

---

## 📐 Phase 3 — Pose & Homography

- After detecting **≥ 3** expected corner tags, match their **IDs** to your template’s expected physical coordinates (page corners, in pixels of the “aligned” canvas).
- Compute **homography** with RANSAC; warp input image to the canonical page size (e.g., **A4 @ 300DPI = 2480×3508 px**).
- From the aligned canvas, map **predefined ROIs** for OMR bubbles and run your existing appreciation logic.

---

## 🎥 Phase 4 — Live Webcam Demo (Ready-to-Run)

A single-file Python sample is provided: **`live_fiducial_appreciation.py`**

Features:
- Webcam capture (`cv2.VideoCapture(0)`)
- ArUco (default) and AprilTag (if installed) detection
- Corner-ID to page-corner mapping
- Homography computation + real-time warped preview
- Simple overlay to visualize detection + alignment

> See the **Usage** section inside the script for CLI arguments and how to switch modes.

---

## 🧪 Phase 5 — Calibration & QA

- **Camera intrinsics**: For highest precision, pre-calibrate each webcam with a **ChArUco** board and store intrinsics (K, distCoeffs). You can then enable `cv2.aruco.estimatePoseSingleMarkers` to visualize axes and improve numerical stability.
- **Lighting & print**: Use matte paper; ensure strong contrast. Avoid glossy laminations that cause specular highlights.
- **Error handling**:
    - Require **≥ 3** detected corner tags; warn the operator if fewer.
    - If **QR/ID** (optional) doesn’t match expected precinct/template, block and alert.
- **Artifacts**: Save per-ballot **overlay PNG** and **JSON** (marks, confidences, homography, pose metrics).

---

## 📦 Deliverables (suggested structure)

```
omr-appreciation/
├─ examples/
│  └─ live_fiducial_appreciation.py   # ready-to-run demo (provided)
├─ omr_appreciation/
│  ├─ __init__.py
│  ├─ fiducials.py                    # optional: factor logic out of the demo
│  ├─ alignment.py                    # optional: factor homography tools
│  └─ appreciation.py                 # your existing bubble logic
└─ docs/
   └─ HES_AprilTag_ArUco_Fiducials_Implementation_Plan.md  # this file
```

---

## 🔧 Dependencies

- Python 3.10+
- OpenCV (`pip install opencv-python` or `pip install opencv-contrib-python`)
- AprilTag (optional)
    - `pip install apriltag` or `pip install pupil-apriltags`
- Numpy (`pip install numpy`)

---

## 🚀 Next Steps Checklist

- [ ] Render ArUco/AprilTag markers in your PHP templates (consistent IDs, sizes, quiet zones).
- [ ] Run `live_fiducial_appreciation.py` with printed sheet to confirm detection + warp.
- [ ] Plug warped output into your appreciation engine (reuse your bubble ROI mapping).
- [ ] Add calibration flow (one-time per camera) and persist intrinsics.
- [ ] Persist artifacts (PNG overlays + JSON logs) for auditability.

---

## 📎 Included: Ready-to-run Python Sample

A complete, self-contained file is included in this package:  
**`live_fiducial_appreciation.py`** — see the next file link for download.

If you prefer, copy it under `examples/` in your repo and adapt as needed.

---

© 2025 3neti R&D OPC — For internal build & evaluation under NDA.
