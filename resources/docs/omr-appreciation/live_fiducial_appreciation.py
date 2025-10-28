#!/usr/bin/env python3
"""
Live fiducial detection, homography alignment, and overlay demo.
Default mode is ArUco (no extra deps). Switch to AprilTag with --mode apriltag if you have `apriltag` or `pupil-apriltags` installed.

Usage:
  python live_fiducial_appreciation.py --mode aruco --dict DICT_6X6_250 \
    --size 2480x3508 --ids 101,102,103,104

Args:
  --mode {aruco,apriltag}        Detection backend (default: aruco)
  --dict <OpenCV_aruco_dict>     e.g., DICT_6X6_250, DICT_4X4_100 (aruco only)
  --size <WxH>                   Output aligned canvas size, e.g., 2480x3508
  --ids <TL,TR,BR,BL>            Expected corner IDs in reading order (default 101,102,103,104)
  --camera <index>               Camera index (default 0)
  --show-warp                    Show the warped/aligned page view
  --save                         Save last warped frame to 'aligned_last.png'

Notes:
- Requires `opencv-python` (or contrib) and `numpy`. AprilTag mode requires `apriltag` or `pupil-apriltags`.
- For best accuracy, pre-calibrate your camera and optionally extend with pose estimation.
"""
import argparse
import sys
import numpy as np
import cv2

# Try to import apriltag if available
_APRILTAG_OK = False
try:
    import apriltag  # type: ignore
    _APRILTAG_OK = True
except Exception:
    _APRILTAG_OK = False


def parse_args():
    p = argparse.ArgumentParser(description="Live fiducial detection & alignment (ArUco/AprilTag)")
    p.add_argument("--mode", choices=["aruco", "apriltag"], default="aruco")
    p.add_argument("--dict", dest="aruco_dict", default="DICT_6X6_250", help="OpenCV ArUco dictionary name")
    p.add_argument("--size", default="2480x3508", help="Aligned canvas size WxH (e.g., 2480x3508 for A4@300DPI)")
    p.add_argument("--ids", default="101,102,103,104", help="Corner IDs TL,TR,BR,BL")
    p.add_argument("--camera", type=int, default=0, help="Webcam index")
    p.add_argument("--show-warp", action="store_true", help="Show warped/aligned view")
    p.add_argument("--save", action="store_true", help="Save last warped frame as PNG")
    return p.parse_args()


def get_aruco_dictionary(name: str):
    # Map string to cv2.aruco dictionary
    if not hasattr(cv2, "aruco"):
        raise RuntimeError("OpenCV ArUco module not found. Install opencv-contrib-python.")
    try:
        return cv2.aruco.getPredefinedDictionary(getattr(cv2.aruco, name))
    except AttributeError:
        raise ValueError(f"Unknown ArUco dictionary: {name}")


def detect_aruco(gray, aruco_dict):
    # Return list of {id, corners}
    params = cv2.aruco.DetectorParameters()
    detector = cv2.aruco.ArucoDetector(aruco_dict, params)
    corners, ids, _ = detector.detectMarkers(gray)
    out = []
    if ids is not None:
        for c, i in zip(corners, ids):
            out.append({"id": int(i[0]), "corners": c.reshape(-1, 2)})
    return out


def detect_apriltag(gray):
    if not _APRILTAG_OK:
        raise RuntimeError("apriltag module not available. Install `apriltag` or `pupil-apriltags`.")
    options = apriltag.DetectorOptions(families="tag36h11")
    detector = apriltag.Detector(options)
    results = detector.detect(gray)
    out = [{"id": int(r.tag_id), "corners": r.corners.astype(np.float32)} for r in results]
    return out


def order_by_ids(detections, wanted_ids):
    # Extract detections that match IDs in wanted_ids order
    found = {}
    for d in detections:
        found[d["id"]] = d["corners"]
    ordered = []
    for wid in wanted_ids:
        if wid in found:
            # use center as single point for homography pairing
            ordered.append(found[wid].mean(axis=0))
        else:
            ordered.append(None)
    return ordered


def compute_homography(ordered_points, W, H):
    # ordered_points = [TL, TR, BR, BL] in image space (may include None)
    src = []
    dst = []
    # Destination points are ideal page corners
    ideal = np.array([[0, 0], [W-1, 0], [W-1, H-1], [0, H-1]], dtype=np.float32)
    for i, pt in enumerate(ordered_points):
        if pt is not None:
            src.append(pt.astype(np.float32))
            dst.append(ideal[i])
    if len(src) < 3:
        return None
    src = np.array(src, dtype=np.float32)
    dst = np.array(dst, dtype=np.float32)
    H, _ = cv2.findHomography(src, dst, cv2.RANSAC, 5.0)
    return H


def draw_detections(frame, detections):
    for d in detections:
        pts = d["corners"].astype(int)
        cv2.polylines(frame, [pts], True, (0, 255, 0), 2)
        c = pts.mean(axis=0).astype(int)
        cv2.circle(frame, tuple(c), 4, (0, 0, 255), -1)
        cv2.putText(frame, str(d["id"]), tuple(c + np.array([6, -6])),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2, cv2.LINE_AA)


def main():
    args = parse_args()

    W, H = map(int, args.size.lower().split("x"))
    wanted_ids = list(map(int, args.ids.split(",")))
    if len(wanted_ids) != 4:
        print("Expected exactly 4 IDs for TL,TR,BR,BL", file=sys.stderr)
        sys.exit(2)

    cap = cv2.VideoCapture(args.camera)
    if not cap.isOpened():
        print("Cannot open camera", file=sys.stderr)
        sys.exit(1)

    if args.mode == "aruco":
        aruco_dict = get_aruco_dictionary(args.aruco_dict)

    last_warp = None

    print("Press ESC to exit.")
    while True:
        ok, frame = cap.read()
        if not ok:
            break

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)

        if args.mode == "aruco":
            detections = detect_aruco(gray, aruco_dict)
        else:
            detections = detect_apriltag(gray)

        draw_detections(frame, detections)

        ordered = order_by_ids(detections, wanted_ids)
        Hm = compute_homography(ordered, W, H)

        if Hm is not None:
            warp = cv2.warpPerspective(frame, Hm, (W, H))
            last_warp = warp.copy()
            # Visual hint on original frame
            cv2.putText(frame, "H: OK", (20, 40), cv2.FONT_HERSHEY_DUPLEX, 1.0, (0, 255, 0), 2, cv2.LINE_AA)
            if args.show_warp:
                cv2.imshow("Aligned / Warped", warp)
        else:
            cv2.putText(frame, "H: MISSING (need >=3 corners)", (20, 40), cv2.FONT_HERSHEY_DUPLEX, 1.0, (0, 128, 255), 2, cv2.LINE_AA)

        cv2.imshow("Live Feed", frame)
        key = cv2.waitKey(1) & 0xFF
        if key == 27:  # ESC
            break

    cap.release()
    cv2.destroyAllWindows()

    if args.save and last_warp is not None:
        cv2.imwrite("aligned_last.png", last_warp)
        print("Saved aligned_last.png")


if __name__ == "__main__":
    main()
