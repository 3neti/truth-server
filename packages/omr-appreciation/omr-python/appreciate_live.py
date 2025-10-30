#!/usr/bin/env python3
"""Live AR Ballot Appreciation - Refactored to use core OMR modules

Real-time ballot appreciation using webcam with AR visualization.
Integrates with image_aligner, mark_detector, barcode_decoder, and utils.

Phase 4: Enhanced AR Features
- Vote accumulator for stability
- Audio feedback
- Freeze frame & capture
- Multi-contest validation
- Session management
- Flask web streaming
- Laravel integration
"""
import argparse
import json
import time
import math
import sys
import subprocess
import os
from pathlib import Path
from typing import Dict, Any, Optional, List
from collections import deque, defaultdict
from datetime import datetime
import cv2
import numpy as np

# Import core OMR modules
from image_aligner import detect_fiducials, align_image
from mark_detector import detect_marks
from barcode_decoder import decode_barcode
from utils import load_template


class VoteAccumulator:
    """
    Stabilize vote detection across frames using rolling window.
    
    Only marks a vote as "filled" when it's detected consistently
    for a minimum number of frames (e.g., 8 out of last 10 frames).
    """
    
    def __init__(self, window_size: int = 10, threshold: int = 8):
        """
        Args:
            window_size: Number of frames to track
            threshold: Minimum detections needed to confirm vote
        """
        self.window_size = window_size
        self.threshold = threshold
        self.history: Dict[str, deque] = defaultdict(lambda: deque(maxlen=window_size))
        self.stable_votes: Dict[str, bool] = {}
        self.vote_change_callbacks: List[callable] = []
    
    def update(self, frame_results: Dict[str, Dict]) -> Dict[str, bool]:
        """
        Update with current frame's detection results.
        
        Returns:
            Dict mapping bubble_id -> stable filled status
        """
        # Update history for each bubble
        for bubble_id, result in frame_results.items():
            filled = result.get('filled', False)
            self.history[bubble_id].append(filled)
        
        # Update stable votes
        prev_stable = self.stable_votes.copy()
        
        for bubble_id, detections in self.history.items():
            if len(detections) >= self.threshold:
                count = sum(detections)
                is_stable = count >= self.threshold
                
                # Check for state change
                prev_state = self.stable_votes.get(bubble_id, False)
                if is_stable != prev_state:
                    self.stable_votes[bubble_id] = is_stable
                    
                    # Trigger callbacks
                    for callback in self.vote_change_callbacks:
                        callback(bubble_id, is_stable)
        
        return self.stable_votes
    
    def get_stable_votes(self) -> Dict[str, bool]:
        """Get current stable vote states."""
        return self.stable_votes.copy()
    
    def reset(self):
        """Clear all history and stable votes."""
        self.history.clear()
        self.stable_votes.clear()
    
    def on_vote_change(self, callback: callable):
        """Register callback for vote state changes."""
        self.vote_change_callbacks.append(callback)


class BallotSession:
    """
    Track ballot processing session from detection to finalization.
    """
    
    def __init__(self, document_id: str, session_dir: Path):
        self.document_id = document_id
        self.session_dir = session_dir
        self.session_id = datetime.now().strftime('%Y%m%d_%H%M%S')
        self.start_time = datetime.now()
        self.votes: Dict[str, bool] = {}
        self.frames_processed = 0
        self.status = 'active'  # active, frozen, finalized
        self.validation_errors: List[Dict] = []
        
        # Create session directory
        self.session_path = session_dir / f"{document_id}_{self.session_id}"
        self.session_path.mkdir(parents=True, exist_ok=True)
        
        # Save initial metadata
        self._save_metadata()
    
    def update_votes(self, stable_votes: Dict[str, bool]):
        """Update current vote state."""
        self.votes = stable_votes.copy()
        self.frames_processed += 1
    
    def add_validation_error(self, position_code: str, message: str, vote_count: int, max_selections: int):
        """Add validation error (e.g., overvote)."""
        self.validation_errors.append({
            'timestamp': datetime.now().isoformat(),
            'position': position_code,
            'message': message,
            'vote_count': vote_count,
            'max_selections': max_selections
        })
    
    def freeze(self):
        """Freeze session (pause processing)."""
        self.status = 'frozen'
        self._save_metadata()
    
    def unfreeze(self):
        """Resume processing."""
        self.status = 'active'
        self._save_metadata()
    
    def finalize(self) -> str:
        """
        Finalize session and generate ballot string for Laravel.
        
        Returns:
            Compact ballot string: "BAL-001|POSITION1:CODE1,CODE2;POSITION2:CODE3"
        """
        self.status = 'finalized'
        self._save_metadata()
        
        # Group votes by position
        position_votes: Dict[str, List[str]] = defaultdict(list)
        
        for bubble_id, filled in self.votes.items():
            if filled:
                # Parse bubble_id: PRESIDENT_LD_001 -> position=PRESIDENT, code=LD_001
                parts = bubble_id.split('_', 1)
                if len(parts) == 2:
                    position = parts[0]
                    code = parts[1]
                    position_votes[position].append(code)
        
        # Build compact string
        position_strings = []
        for position, codes in sorted(position_votes.items()):
            codes_str = ','.join(sorted(codes))
            position_strings.append(f"{position}:{codes_str}")
        
        ballot_string = f"{self.document_id}|{';'.join(position_strings)}"
        
        # Save ballot string
        ballot_file = self.session_path / 'ballot.txt'
        ballot_file.write_text(ballot_string)
        
        return ballot_string
    
    def save_frame(self, frame: np.ndarray, label: str = 'capture'):
        """Save annotated frame to session directory."""
        timestamp = datetime.now().strftime('%H%M%S')
        filename = f"{label}_{timestamp}.png"
        filepath = self.session_path / filename
        cv2.imwrite(str(filepath), frame)
        return filepath
    
    def _save_metadata(self):
        """Save session metadata to JSON."""
        metadata = {
            'session_id': self.session_id,
            'document_id': self.document_id,
            'start_time': self.start_time.isoformat(),
            'status': self.status,
            'frames_processed': self.frames_processed,
            'votes': {k: v for k, v in self.votes.items() if v},
            'validation_errors': self.validation_errors
        }
        
        metadata_file = self.session_path / 'session.json'
        with open(metadata_file, 'w') as f:
            json.dump(metadata, f, indent=2)
    
    @classmethod
    def load(cls, session_path: Path) -> 'BallotSession':
        """Load existing session from disk."""
        metadata_file = session_path / 'session.json'
        with open(metadata_file) as f:
            data = json.load(f)
        
        # Reconstruct session
        session = cls.__new__(cls)
        session.document_id = data['document_id']
        session.session_id = data['session_id']
        session.session_dir = session_path.parent
        session.session_path = session_path
        session.start_time = datetime.fromisoformat(data['start_time'])
        session.status = data['status']
        session.frames_processed = data['frames_processed']
        session.votes = data['votes']
        session.validation_errors = data['validation_errors']
        
        return session


class AudioFeedback:
    """
    Audio feedback for ballot events.
    Uses pygame.mixer for cross-platform audio playback.
    """
    
    def __init__(self, enabled: bool = True):
        self.enabled = enabled
        self.initialized = False
        
        if enabled:
            try:
                import pygame.mixer as mixer
                mixer.init()
                self.initialized = True
                self.mixer = mixer
            except ImportError:
                print('⚠ pygame not available, audio feedback disabled', file=sys.stderr)
                self.enabled = False
    
    def play_tone(self, frequency: int, duration_ms: int):
        """Play simple tone (fallback when no sound files available)."""
        if not self.enabled or not self.initialized:
            return
        
        try:
            # Generate sine wave
            sample_rate = 22050
            samples = int(sample_rate * duration_ms / 1000)
            wave = np.sin(2 * np.pi * frequency * np.arange(samples) / sample_rate)
            
            # Convert to 16-bit audio
            wave = (wave * 32767).astype(np.int16)
            
            # Convert to stereo
            stereo_wave = np.column_stack((wave, wave))
            
            # Play
            sound = self.mixer.Sound(buffer=stereo_wave)
            sound.play()
        except Exception as e:
            print(f'⚠ Audio playback error: {e}', file=sys.stderr)
    
    def ballot_detected(self):
        """Play sound when ballot is detected."""
        self.play_tone(800, 100)  # 800Hz for 100ms
    
    def vote_registered(self):
        """Play sound when vote is registered."""
        self.play_tone(1200, 80)  # 1200Hz for 80ms
    
    def vote_removed(self):
        """Play sound when vote is removed."""
        self.play_tone(600, 80)  # 600Hz for 80ms
    
    def overvote_warning(self):
        """Play warning sound for overvote."""
        self.play_tone(400, 300)  # Low 400Hz for 300ms
    
    def processing_complete(self):
        """Play success jingle for processing complete."""
        # Play ascending notes
        self.play_tone(800, 100)
        time.sleep(0.05)
        self.play_tone(1000, 100)
        time.sleep(0.05)
        self.play_tone(1200, 150)


class ContestValidator:
    """
    Validate vote selections against contest rules (max_selections).
    """
    
    def __init__(self, questionnaire_data: Optional[Dict]):
        self.rules: Dict[str, int] = {}  # position_code -> max_selections
        
        if questionnaire_data and 'positions' in questionnaire_data:
            for position in questionnaire_data['positions']:
                code = position.get('code')
                max_sel = position.get('max_selections', 1)
                if code:
                    self.rules[code] = max_sel
    
    def validate(self, votes: Dict[str, bool]) -> Dict[str, Dict]:
        """
        Validate current votes against contest rules.
        
        Returns:
            Dict mapping position_code -> validation result
            {
                'valid': bool,
                'count': int,
                'max': int,
                'overvote': bool
            }
        """
        # Group votes by position
        position_counts: Dict[str, int] = defaultdict(int)
        
        for bubble_id, filled in votes.items():
            if filled:
                # Parse bubble_id: PRESIDENT_LD_001 -> position=PRESIDENT
                parts = bubble_id.split('_', 1)
                if len(parts) >= 1:
                    position = parts[0]
                    position_counts[position] += 1
        
        # Validate each position
        results = {}
        for position, count in position_counts.items():
            max_sel = self.rules.get(position, 1)
            overvote = count > max_sel
            
            results[position] = {
                'valid': not overvote,
                'count': count,
                'max': max_sel,
                'overvote': overvote
            }
        
        return results
    
    def get_overvotes(self, votes: Dict[str, bool]) -> List[str]:
        """Get list of positions with overvotes."""
        validation = self.validate(votes)
        return [pos for pos, result in validation.items() if result['overvote']]


def parse_args():
    ap = argparse.ArgumentParser(
        description='Live AR Ballot Appreciation (Webcam with AR overlay)',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # With actual template
  python appreciate_live.py --template coordinates.json --threshold 0.3
  
  # With demo grid (for testing)
  python appreciate_live.py --demo-grid --size 2480x3508
        """
    )
    ap.add_argument('--camera', type=int, default=0,
                   help='Camera device index (default: 0)')
    ap.add_argument('--template', type=str, default='',
                   help='Path to coordinates.json template file')
    ap.add_argument('--threshold', type=float, default=0.30,
                   help='Fill detection threshold 0.0-1.0 (default: 0.30)')
    ap.add_argument('--demo-grid', action='store_true',
                   help='Use demo grid bubbles (for testing without template)')
    ap.add_argument('--size', default='2480x3508',
                   help='Ballot size in pixels WxH (for demo mode, default: 2480x3508)')
    ap.add_argument('--show-warp', action='store_true',
                   help='Show warped ballot view (debug)')
    ap.add_argument('--no-fps', action='store_true',
                   help='Hide FPS counter')
    ap.add_argument('--no-barcode', action='store_true',
                   help='Skip barcode decode (faster)')
    ap.add_argument('--show-names', action='store_true',
                   help='Display candidate names for filled bubbles')
    
    # Phase 4 features
    ap.add_argument('--no-audio', action='store_true',
                   help='Disable audio feedback')
    ap.add_argument('--accumulator-window', type=int, default=10,
                   help='Vote accumulator window size (default: 10 frames)')
    ap.add_argument('--accumulator-threshold', type=int, default=8,
                   help='Vote accumulator threshold (default: 8 detections)')
    ap.add_argument('--session-dir', type=str, default='storage/app/live-sessions',
                   help='Directory for session storage (default: storage/app/live-sessions)')
    ap.add_argument('--validate-contests', action='store_true',
                   help='Enable multi-contest validation (overvote detection)')
    
    return ap.parse_args()


def demo_grid_bubbles(W: int, H: int, r: int = 18, cols: int = 6, rows: int = 10, 
                      mx: int = 300, my: int = 500, gx: int = 220, gy: int = 180):
    """Generate demo grid of bubbles for testing without template."""
    bubbles = {}
    y = my
    lbl = 1
    for _ in range(rows):
        x = mx
        for _ in range(cols):
            bubble_id = f'DEMO_B{lbl:02d}'
            bubbles[bubble_id] = {
                'center_x': x / 11.811,  # Convert px to mm
                'center_y': y / 11.811,
                'diameter': (r * 2) / 11.811
            }
            x += gx
            lbl += 1
        y += gy
    return bubbles


def create_demo_template(W: int, H: int):
    """Create minimal template for demo mode."""
    template = {
        'bubble': demo_grid_bubbles(W, H),
        'fiducial': {
            'tl': {'x': 8.5, 'y': 8.5, 'marker_id': 101, 'type': 'aruco'},
            'tr': {'x': W/11.811 - 36.85, 'y': 8.5, 'marker_id': 102, 'type': 'aruco'},
            'br': {'x': W/11.811 - 36.85, 'y': H/11.811 - 36.85, 'marker_id': 103, 'type': 'aruco'},
            'bl': {'x': 8.5, 'y': H/11.811 - 36.85, 'marker_id': 104, 'type': 'aruco'}
        },
        'barcode': {},
        'document_id': 'DEMO-BALLOT'
    }
    return template


def load_questionnaire_data(document_id: str) -> Optional[Dict]:
    """
    Load candidate names from Laravel database via artisan tinker.
    
    Returns questionnaire data with positions and candidates, or None if not found.
    """
    try:
        # Find project root (3 levels up from this script)
        script_dir = Path(__file__).parent
        project_root = script_dir.parent.parent.parent
        
        # Build PHP command to query database
        php_code = f"""
$template = App\\Models\\TemplateData::where('document_id', 'LIKE', '%{document_id}%')
    ->orWhere('document_id', 'LIKE', '%QUESTIONNAIRE%')
    ->first();
if ($template && isset($template->json_data['positions'])) {{
    echo json_encode($template->json_data);
}}
        """.strip()
        
        # Run via artisan tinker
        result = subprocess.run(
            ['php', 'artisan', 'tinker', '--execute=' + php_code],
            cwd=str(project_root),
            capture_output=True,
            text=True,
            timeout=5
        )
        
        if result.returncode == 0 and result.stdout.strip():
            # Extract JSON from output (tinker adds extra text)
            output = result.stdout.strip()
            # Find JSON object in output
            json_start = output.find('{')
            if json_start >= 0:
                json_str = output[json_start:]
                data = json.loads(json_str)
                return data
        
        return None
        
    except Exception as e:
        print(f'Warning: Could not load questionnaire data: {e}', file=sys.stderr)
        return None


def get_candidate_name(bubble_id: str, questionnaire_data: Optional[Dict]) -> Optional[str]:
    """
    Convert bubble ID to candidate name.
    
    Example:
      bubble_id = "PRESIDENT_LD_001"
      returns = "Leonardo DiCaprio"
    """
    if not questionnaire_data or 'positions' not in questionnaire_data:
        return None
    
    # Parse bubble_id: PRESIDENT_LD_001 → position="PRESIDENT", code="LD_001"
    parts = bubble_id.split('_', 1)
    if len(parts) < 2:
        return None
    
    position_code = parts[0]
    candidate_code = parts[1]
    
    # Find in questionnaire
    for position in questionnaire_data['positions']:
        if position.get('code') == position_code:
            for candidate in position.get('candidates', []):
                if candidate.get('code') == candidate_code:
                    return candidate.get('name')
    
    return None


def convert_bubbles_to_zones(bubbles: Dict, mm_to_px: float = 11.811):
    """Convert bubble dict to zones format for mark_detector."""
    zones = []
    for bubble_id, bubble in bubbles.items():
        center_x_px = bubble['center_x'] * mm_to_px
        center_y_px = bubble['center_y'] * mm_to_px
        diameter_px = bubble['diameter'] * mm_to_px
        radius_px = diameter_px / 2
        
        zones.append({
            'id': bubble_id,
            'contest': bubble_id.rsplit('_', 1)[0],  # e.g., PRESIDENT_001 -> PRESIDENT
            'code': bubble_id.rsplit('_', 1)[1] if '_' in bubble_id else bubble_id,
            'x': int(center_x_px - radius_px),
            'y': int(center_y_px - radius_px),
            'width': int(diameter_px),
            'height': int(diameter_px)
        })
    return zones


def draw_overlay(frame, fiducials, results, barcode_result=None, quality=None, 
                angle_deg=None, fps=None, questionnaire_data=None, 
                validation_results=None, session=None, is_frozen=False):
    """Draw AR overlay with fiducials, bubbles, barcode info, quality, and validation warnings."""
    mm_to_px = 11.811
    
    # Draw fiducials (if detected)
    if fiducials:
        for corner_name, fid_point in fiducials.items():
            if fid_point is not None:
                pt = tuple(fid_point.astype(int))
                cv2.circle(frame, pt, 8, (255, 255, 0), -1)
                cv2.circle(frame, pt, 12, (255, 255, 0), 2)
    
    # Draw bubbles
    for bubble_id, result in results.items():
        if not result.get('center_x') or not result.get('center_y'):
            continue
            
        cx = int(result['center_x'])
        cy = int(result['center_y'])
        r = int(result.get('radius', 20))
        
        # Color based on fill status
        if result.get('filled', False):
            fill_ratio = result.get('fill_ratio', 0)
            if fill_ratio >= 0.95:
                color = (0, 255, 0)  # Green - good fill
            else:
                color = (0, 255, 255)  # Yellow - low confidence
        else:
            color = (0, 0, 255)  # Red - not filled
        
        # Draw circle
        cv2.circle(frame, (cx, cy), r, color, 2)
        
        # Draw candidate name if available and bubble is filled
        if questionnaire_data and result.get('filled', False):
            candidate_name = get_candidate_name(bubble_id, questionnaire_data)
            
            if candidate_name:
                # Draw semi-transparent background for readability
                font = cv2.FONT_HERSHEY_SIMPLEX
                font_scale = 0.5
                thickness = 1
                text_size = cv2.getTextSize(candidate_name, font, font_scale, thickness)[0]
                
                # Position text to the right of bubble
                text_x = cx + r + 8
                text_y = cy + 5
                
                # Background rectangle
                bg_x1 = text_x - 3
                bg_y1 = text_y - text_size[1] - 3
                bg_x2 = text_x + text_size[0] + 3
                bg_y2 = text_y + 3
                
                # Draw semi-transparent background
                overlay = frame.copy()
                cv2.rectangle(overlay, (bg_x1, bg_y1), (bg_x2, bg_y2), (0, 0, 0), -1)
                cv2.addWeighted(overlay, 0.7, frame, 0.3, 0, frame)
                
                # Draw text
                cv2.putText(frame, candidate_name, (text_x, text_y), 
                           font, font_scale, color, thickness, cv2.LINE_AA)
    
    # Draw info overlay (top-left corner)
    y = 30
    
    # Barcode info
    if barcode_result and barcode_result.get('decoded'):
        doc_id = barcode_result.get('document_id', 'UNKNOWN')
        source = barcode_result.get('source', 'unknown')
        decoder = barcode_result.get('decoder', 'none')
        
        color = (0, 255, 0) if source == 'visual' else (0, 165, 255)
        cv2.putText(frame, f'Ballot: {doc_id}', (20, y), 
                   cv2.FONT_HERSHEY_DUPLEX, 0.8, color, 2, cv2.LINE_AA)
        y += 28
        cv2.putText(frame, f'Decoder: {decoder}', (20, y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 1, cv2.LINE_AA)
        y += 24
    
    # Quality info
    if quality:
        overall = quality.get('overall', 'unknown')
        if overall == 'green':
            q_color = (0, 255, 0)
        elif overall == 'amber':
            q_color = (0, 165, 255)
        else:
            q_color = (0, 0, 255)
        
        cv2.putText(frame, f'Quality: {overall.upper()}', (20, y), 
                   cv2.FONT_HERSHEY_DUPLEX, 0.8, q_color, 2, cv2.LINE_AA)
        y += 28
    
    # Angle
    if angle_deg is not None:
        cv2.putText(frame, f'Angle: {angle_deg:+.1f}°', (20, y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1, cv2.LINE_AA)
        y += 24
    
    # FPS
    if fps is not None:
        cv2.putText(frame, f'FPS: {fps:.1f}', (20, y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1, cv2.LINE_AA)
        y += 24
    
    # Validation warnings (bottom-left corner)
    if validation_results:
        y_warn = frame.shape[0] - 20
        for position, result in validation_results.items():
            if result.get('overvote', False):
                count = result['count']
                max_sel = result['max']
                warn_text = f'⚠ OVERVOTE: {position} ({count}/{max_sel})'
                cv2.putText(frame, warn_text, (20, y_warn), 
                           cv2.FONT_HERSHEY_DUPLEX, 0.8, (0, 0, 255), 2, cv2.LINE_AA)
                y_warn -= 30
    
    # Session status (top-right corner)
    if session:
        status_y = 30
        status_x = frame.shape[1] - 250
        
        # Session ID
        cv2.putText(frame, f'Session: {session.session_id[-8:]}', (status_x, status_y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1, cv2.LINE_AA)
        status_y += 20
        
        # Vote count
        vote_count = sum(1 for v in session.votes.values() if v)
        cv2.putText(frame, f'Votes: {vote_count}', (status_x, status_y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1, cv2.LINE_AA)
        status_y += 20
        
        # Frames processed
        cv2.putText(frame, f'Frames: {session.frames_processed}', (status_x, status_y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1, cv2.LINE_AA)
    
    # Freeze indicator (center screen)
    if is_frozen:
        h, w = frame.shape[:2]
        freeze_text = '⏸ FROZEN'
        font = cv2.FONT_HERSHEY_DUPLEX
        font_scale = 2.0
        thickness = 4
        text_size = cv2.getTextSize(freeze_text, font, font_scale, thickness)[0]
        text_x = (w - text_size[0]) // 2
        text_y = 60
        
        # Draw semi-transparent background
        bg_x1 = text_x - 20
        bg_y1 = text_y - text_size[1] - 20
        bg_x2 = text_x + text_size[0] + 20
        bg_y2 = text_y + 20
        
        overlay = frame.copy()
        cv2.rectangle(overlay, (bg_x1, bg_y1), (bg_x2, bg_y2), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.8, frame, 0.2, 0, frame)
        
        # Draw text
        cv2.putText(frame, freeze_text, (text_x, text_y), 
                   font, font_scale, (0, 255, 255), thickness, cv2.LINE_AA)


def compute_angle(fiducials):
    """Compute ballot angle from top-left and top-right fiducials."""
    if not fiducials or 'tl' not in fiducials or 'tr' not in fiducials:
        return None
    
    tl = fiducials['tl']
    tr = fiducials['tr']
    
    if tl is None or tr is None:
        return None
    
    vx, vy = (tr - tl)
    angle = math.degrees(math.atan2(vy, vx))
    return angle


def main():
    args = parse_args()
    
    # Load or create template
    if args.template:
        try:
            template = load_template(args.template)
            print(f'✓ Loaded template: {args.template}')
        except Exception as e:
            print(f'✗ Error loading template: {e}', file=sys.stderr)
            sys.exit(1)
    elif args.demo_grid:
        W, H = map(int, args.size.lower().split('x'))
        template = create_demo_template(W, H)
        print('✓ Using demo grid template')
    else:
        print('Error: Provide --template or --demo-grid', file=sys.stderr)
        sys.exit(1)
    
    # Convert bubbles to zones for mark detector
    zones = convert_bubbles_to_zones(template['bubble'])
    print(f'✓ Loaded {len(zones)} bubbles')
    
    # Load questionnaire data for candidate names and validation
    questionnaire_data = None
    if (args.show_names or args.validate_contests) and template.get('document_id'):
        print('Loading questionnaire data...')
        questionnaire_data = load_questionnaire_data(template['document_id'])
        if questionnaire_data:
            num_positions = len(questionnaire_data.get('positions', []))
            print(f'✓ Loaded {num_positions} positions')
        else:
            print('⚠ No questionnaire data available')
    
    # Get barcode config
    barcode_config = template.get('barcode', {}).get('document_barcode', None)
    mm_to_px = 11.811
    
    # Open camera
    cap = cv2.VideoCapture(args.camera)
    if not cap.isOpened():
        print(f'✗ Cannot open camera {args.camera}', file=sys.stderr)
        sys.exit(1)
    
    print(f'✓ Camera {args.camera} opened')
    
    # Initialize Phase 4 components
    accumulator = VoteAccumulator(
        window_size=args.accumulator_window,
        threshold=args.accumulator_threshold
    )
    print(f'✓ Vote accumulator initialized ({args.accumulator_threshold}/{args.accumulator_window} frames)')
    
    audio = AudioFeedback(enabled=not args.no_audio)
    if audio.enabled:
        print('✓ Audio feedback enabled')
    
    validator = ContestValidator(questionnaire_data) if args.validate_contests else None
    if validator:
        print(f'✓ Contest validator initialized ({len(validator.rules)} positions)')
    
    session_dir = Path(args.session_dir)
    session_dir.mkdir(parents=True, exist_ok=True)
    session: Optional[BallotSession] = None
    print(f'✓ Session directory: {session_dir}')
    
    # Setup vote change callbacks for audio feedback
    def on_vote_change(bubble_id: str, is_filled: bool):
        if is_filled:
            audio.vote_registered()
        else:
            audio.vote_removed()
    
    accumulator.on_vote_change(on_vote_change)
    
    print('\nControls:')
    print('  ESC   - Exit')
    print('  W     - Toggle warped view (debug)')
    print('  SPACE - Freeze/unfreeze frame')
    print('  S     - Save current frame')
    print('  F     - Finalize ballot (cast to Laravel)')
    print('\nStarting live appreciation...\n')
    
    # State
    prev_t = time.time()
    fps = None
    show_warp = args.show_warp
    is_frozen = False
    frozen_frame = None
    last_document_id = None
    
    while True:
        # Use frozen frame if available
        if is_frozen and frozen_frame is not None:
            frame = frozen_frame.copy()
        else:
            ok, frame = cap.read()
            if not ok:
                print('✗ Failed to read frame', file=sys.stderr)
                break
        
        # Initialize results
        results = {}
        barcode_result = None
        quality = None
        angle = None
        validation_results = None
        
        # Skip processing if frozen
        if not is_frozen:
            # Detect fiducials using core module
            fiducials = detect_fiducials(frame, template)
            
            # If we have enough fiducials, do alignment and detection
            if fiducials and sum(1 for v in fiducials.values() if v is not None) >= 4:
                try:
                    # Align image using core module
                    aligned, quality, inv_matrix = align_image(frame, fiducials, template)
                    
                    # Compute angle
                    angle = compute_angle(fiducials)
                    
                    # Detect marks using core module
                    results = detect_marks(aligned, zones, threshold=args.threshold, inv_matrix=inv_matrix)
                    
                    # Decode barcode using core module (if not disabled)
                    if not args.no_barcode and barcode_config:
                        barcode_result = decode_barcode(
                            frame,
                            barcode_config,
                            mm_to_px_ratio=mm_to_px,
                            metadata_fallback=template.get('document_id')
                        )
                    
                    # Check for new ballot
                    if barcode_result and barcode_result.get('decoded'):
                        document_id = barcode_result.get('document_id')
                        if document_id != last_document_id:
                            # New ballot detected
                            last_document_id = document_id
                            audio.ballot_detected()
                            
                            # Create new session
                            if session:
                                session._save_metadata()  # Save previous session
                            session = BallotSession(document_id, session_dir)
                            accumulator.reset()
                            print(f'\n✓ New ballot session: {document_id}')
                    
                    # Update vote accumulator
                    stable_votes = accumulator.update(results)
                    
                    # Update results to show stable votes only
                    for bubble_id, result in results.items():
                        result['filled'] = stable_votes.get(bubble_id, False)
                    
                    # Update session
                    if session:
                        session.update_votes(stable_votes)
                    
                    # Validate contests
                    if validator:
                        validation_results = validator.validate(stable_votes)
                        
                        # Check for overvotes and trigger audio warning
                        overvotes = [pos for pos, res in validation_results.items() if res['overvote']]
                        if overvotes and session:
                            for position in overvotes:
                                res = validation_results[position]
                                session.add_validation_error(
                                    position,
                                    'Overvote detected',
                                    res['count'],
                                    res['max']
                                )
                            audio.overvote_warning()
                    
                    # Show warped view if enabled
                    if show_warp:
                        cv2.imshow('Warped Page (debug)', aligned)
                        
                except Exception as e:
                    print(f'Warning: Processing error: {e}', file=sys.stderr)
            else:
                # No fiducials detected - maintain last state
                fiducials = {}
        else:
            # Frozen - maintain last state
            fiducials = {}
        
        # Draw overlay
        draw_overlay(
            frame, fiducials, results, barcode_result, quality, angle,
            fps=None if args.no_fps else fps,
            questionnaire_data=questionnaire_data,
            validation_results=validation_results,
            session=session,
            is_frozen=is_frozen
        )
        
        # Update FPS
        now = time.time()
        dt = now - prev_t
        fps = (1.0 / dt) if dt > 0 else fps
        prev_t = now
        
        # Display
        cv2.imshow('Live AR Ballot Appreciation', frame)
        
        # Handle keys
        key = cv2.waitKey(1) & 0xFF
        if key == 27:  # ESC
            break
        elif key == ord('w') or key == ord('W'):
            show_warp = not show_warp
            if not show_warp:
                cv2.destroyWindow('Warped Page (debug)')
        elif key == ord(' '):  # SPACE - Freeze/unfreeze
            is_frozen = not is_frozen
            if is_frozen:
                frozen_frame = frame.copy()
                if session:
                    session.freeze()
                print('  ⏸ FROZEN')
            else:
                frozen_frame = None
                if session:
                    session.unfreeze()
                print('  ▶ RESUMED')
        elif key == ord('s') or key == ord('S'):  # Save frame
            if session:
                filepath = session.save_frame(frame, 'manual')
                print(f'  💾 Saved: {filepath}')
            else:
                # Save to temp location
                timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                filepath = session_dir / f'capture_{timestamp}.png'
                cv2.imwrite(str(filepath), frame)
                print(f'  💾 Saved: {filepath}')
        elif key == ord('f') or key == ord('F'):  # Finalize ballot
            if session and session.status == 'active':
                ballot_string = session.finalize()
                print(f'\n✓ Ballot finalized: {ballot_string}')
                
                # Call Laravel artisan command
                try:
                    script_dir = Path(__file__).parent
                    project_root = script_dir.parent.parent.parent
                    result = subprocess.run(
                        ['php', 'artisan', 'election:cast-ballot', ballot_string],
                        cwd=str(project_root),
                        capture_output=True,
                        text=True,
                        timeout=10
                    )
                    
                    if result.returncode == 0:
                        print('✓ Cast to Laravel successfully')
                        audio.processing_complete()
                    else:
                        print(f'✗ Laravel cast failed: {result.stderr}')
                except Exception as e:
                    print(f'✗ Laravel integration error: {e}')
                
                # Reset for next ballot
                session = None
                accumulator.reset()
                last_document_id = None
            else:
                print('  ⚠ No active session to finalize')
    
    # Cleanup
    cap.release()
    cv2.destroyAllWindows()
    print('\n✓ Appreciation session ended')


if __name__ == '__main__':
    main()
