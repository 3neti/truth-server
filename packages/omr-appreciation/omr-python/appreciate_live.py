#!/usr/bin/env python3
# live_ar_appreciation.py
import argparse, json, time, math
from typing import List, Dict, Any
import cv2, numpy as np


def parse_args():
    ap = argparse.ArgumentParser(description='Live AR Ballot Appreciation (Webcam + ArUco)')
    ap.add_argument('--camera', type=int, default=0)
    ap.add_argument('--mode', choices=['aruco'], default='aruco')
    ap.add_argument('--dict', dest='aruco_dict', default='DICT_6X6_250')
    ap.add_argument('--size', default='2480x3508')
    ap.add_argument('--ids', default='101,102,103,104')
    ap.add_argument('--bubbles', default='')
    ap.add_argument('--radius', type=int, default=16)
    ap.add_argument('--threshold', type=float, default=0.30)
    ap.add_argument('--demo-grid', action='store_true')
    ap.add_argument('--show-warp', action='store_true')
    ap.add_argument('--no-fps', action='store_true')
    return ap.parse_args()


def get_aruco_dictionary(name: str):
    if not hasattr(cv2, 'aruco'):
        raise RuntimeError('OpenCV ArUco not available. Install opencv-contrib-python.')
    return cv2.aruco.getPredefinedDictionary(getattr(cv2.aruco, name))


def detect_aruco(gray, aruco_dict):
    params = cv2.aruco.DetectorParameters()
    detector = cv2.aruco.ArucoDetector(aruco_dict, params)
    corners, ids, _ = detector.detectMarkers(gray)
    dets = []
    if ids is not None:
        for c, i in zip(corners, ids):
            dets.append({'id': int(i[0]), 'corners': c.reshape(-1, 2)})
    return dets


def order_corners_by_ids(detections: List[Dict[str, Any]], wanted_ids: List[int]):
    found = {d['id']: d for d in detections}
    ordered = []
    for wid in wanted_ids:
        d = found.get(wid)
        ordered.append(None if d is None else d['corners'].mean(axis=0))
    return ordered


def compute_homography_from_ordered(ordered_pts, W, H):
    src, dst = [], []
    ideal = np.array([[0,0],[W-1,0],[W-1,H-1],[0,H-1]], dtype=np.float32)
    for i, p in enumerate(ordered_pts):
        if p is not None:
            src.append(p.astype(np.float32))
            dst.append(ideal[i])
    if len(src) < 3:
        return None
    src = np.array(src, dtype=np.float32)
    dst = np.array(dst, dtype=np.float32)
    Hm, _ = cv2.findHomography(src, dst, cv2.RANSAC, 5.0)
    return Hm


def project_points(points_xy: np.ndarray, invH: np.ndarray) -> np.ndarray:
    pts = points_xy.astype(np.float32).reshape(-1,1,2)
    return cv2.perspectiveTransform(pts, invH).reshape(-1,2)


def load_bubbles_json(path: str, default_radius: int):
    data = json.load(open(path))
    out = []
    for o in data:
        out.append({'x': float(o['x']), 'y': float(o['y']), 'radius': float(o.get('radius', default_radius)), 'label': str(o.get('label',''))})
    return out


def demo_grid_bubbles(W:int, H:int, r:int=18, cols:int=6, rows:int=10, mx:int=300, my:int=500, gx:int=220, gy:int=180):
    bubbles = []
    y = my
    lbl = 1
    for _ in range(rows):
        x = mx
        for _ in range(cols):
            bubbles.append({'x': x, 'y': y, 'radius': r, 'label': f'B{lbl:02d}'})
            x += gx
            lbl += 1
        y += gy
    return bubbles


def appreciation(gray: np.ndarray, centers: np.ndarray, radii: list, thresh: float):
    marks = []
    boundary = 255.0 * (1.0 - max(0.0, min(1.0, thresh)))
    for (cx, cy), r in zip(centers, radii):
        x0 = max(0, int(cx - r)); x1 = min(gray.shape[1], int(cx + r))
        y0 = max(0, int(cy - r)); y1 = min(gray.shape[0], int(cy + r))
        roi = gray[y0:y1, x0:x1]
        if roi.size == 0:
            marks.append((False, 0.0)); continue
        mean_val = float(np.mean(roi))
        filled = mean_val < boundary
        conf = abs(mean_val - boundary) / 255.0
        marks.append((filled, conf))
    return marks


def draw_overlay(frame, detections, ordered_pts, bubbles_proj, marks, radii, angle_deg=None, fps=None):
    for d in detections:
        pts = d['corners'].astype(int)
        cv2.polylines(frame, [pts], True, (255,255,0), 2)
        c = pts.mean(axis=0).astype(int)
        cv2.circle(frame, tuple(c), 4, (0,0,255), -1)
        cv2.putText(frame, str(d['id']), tuple(c + np.array([6,-6])), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255,255,255), 2, cv2.LINE_AA)
    if all(p is not None for p in ordered_pts):
        poly = np.array(ordered_pts, dtype=np.int32).reshape(-1,1,2)
        cv2.polylines(frame, [poly], True, (0,200,255), 2)
    for (pt, (filled, conf), r) in zip(bubbles_proj, marks, radii):
        color = (0,255,0) if filled else (0,0,255)
        if 0.05 < conf < 0.12: color = (0,165,255)
        cv2.circle(frame, (int(pt[0]), int(pt[1])), int(r), color, 2)
    y = 30
    if angle_deg is not None:
        cv2.putText(frame, f'Angle: {angle_deg:+.1f} deg', (20,y), cv2.FONT_HERSHEY_DUPLEX, 0.8, (255,255,255), 2, cv2.LINE_AA); y+=28
    if fps is not None:
        cv2.putText(frame, f'FPS: {fps:.1f}', (20,y), cv2.FONT_HERSHEY_DUPLEX, 0.8, (255,255,255), 2, cv2.LINE_AA)


def main():
    args = parse_args()
    W, H = map(int, args.size.lower().split('x'))
    wanted_ids = list(map(int, args.ids.split(',')))
    if len(wanted_ids) != 4:
        raise SystemExit('Provide exactly 4 IDs (TL,TR,BR,BL).')
    bubbles = load_bubbles_json(args.bubbles, args.radius) if args.bubbles else (demo_grid_bubbles(W,H,r=max(args.radius,14)) if args.demo_grid else [])
    if not bubbles:
        print('No bubbles provided (use --bubbles or --demo-grid). Proceeding with detection only.')
    points_template = np.array([[b['x'], b['y']] for b in bubbles], dtype=np.float32)
    radii = [float(b['radius']) for b in bubbles]
    cap = cv2.VideoCapture(args.camera)
    if not cap.isOpened():
        raise SystemExit('Cannot open camera')
    aruco_dict = get_aruco_dictionary(args.aruco_dict)
    prev_t = time.time(); fps = None
    while True:
        ok, frame = cap.read()
        if not ok: break
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        detections = detect_aruco(gray, aruco_dict)
        ordered = order_corners_by_ids(detections, wanted_ids)
        Hm = compute_homography_from_ordered(ordered, W, H)
        angle = None
        if ordered[0] is not None and ordered[1] is not None:
            tl, tr = ordered[0], ordered[1]
            vx, vy = (tr - tl); angle = math.degrees(math.atan2(vy, vx))
        if Hm is not None:
            invH = np.linalg.inv(Hm)
            bubbles_proj = project_points(points_template, invH) if len(bubbles)>0 else np.zeros((0,2),dtype=np.float32)
            marks = appreciation(gray, bubbles_proj, radii, args.threshold) if len(bubbles)>0 else []
            if args.show_warp:
                warp = cv2.warpPerspective(frame, Hm, (W, H))
                cv2.imshow('Warped Page (debug)', warp)
            draw_overlay(frame, detections, ordered, bubbles_proj, marks, radii, angle_deg=angle, fps=None if args.no_fps else fps)
        else:
            draw_overlay(frame, detections, ordered, np.zeros((0,2),dtype=np.float32), [], [], angle_deg=angle, fps=None if args.no_fps else fps)
        now = time.time(); dt = now - prev_t; fps = (1.0/dt) if dt>0 else fps; prev_t = now
        cv2.imshow('Live AR Ballot Appreciation', frame)
        if (cv2.waitKey(1) & 0xFF) == 27: break
    cap.release(); cv2.destroyAllWindows()

if __name__ == '__main__':
    main()
