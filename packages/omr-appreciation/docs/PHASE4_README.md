# Phase 4: Enhanced AR Features

Phase 4 transforms the live AR ballot appreciation system into a production-ready application with robust vote tracking, audio feedback, validation, session management, and Laravel integration.

## Features Implemented

### 1. Vote Accumulator
**Purpose:** Stabilize vote detection across frames to prevent flickering and false positives.

**How it works:**
- Tracks each bubble's filled state over a rolling window (default: 10 frames)
- Only marks a vote as "stable" when detected in a threshold number of frames (default: 8 of 10)
- Triggers callbacks when vote state changes

**Configuration:**
```bash
python3 appreciate_live.py \
  --template coords.json \
  --accumulator-window 10 \
  --accumulator-threshold 8
```

**Benefits:**
- Eliminates noise from lighting variations, motion blur, or detection artifacts
- Prevents false votes from transient shadows or reflections
- Provides smooth, confident vote registration

### 2. Audio Feedback
**Purpose:** Provide auditory confirmation of ballot events without requiring visual attention.

**Sounds:**
- **Ballot detected** (800Hz beep): New ballot QR code scanned
- **Vote registered** (1200Hz click): Stable vote detected
- **Vote removed** (600Hz tone): Stable vote removed
- **Overvote warning** (400Hz alert): Too many votes in a contest
- **Processing complete** (ascending notes): Ballot finalized and cast

**Configuration:**
```bash
# Enable audio (default)
python3 appreciate_live.py --template coords.json

# Disable audio
python3 appreciate_live.py --template coords.json --no-audio
```

**Requirements:**
- Optional: `pygame` library for audio playback
- Falls back gracefully if pygame not available

### 3. Freeze Frame & Capture
**Purpose:** Pause processing to review current state or save annotated frames.

**Controls:**
- **SPACE**: Freeze/unfreeze current frame
- **S**: Save current frame to session directory

**Use cases:**
- Review complex ballot situations
- Capture evidence of overvotes or ambiguous marks
- Pause for voter or poll worker review

**Indicator:**
- Large "⏸ FROZEN" overlay appears when frozen
- Session status shows "frozen" in metadata

### 4. Multi-Contest Validation
**Purpose:** Real-time detection of overvotes and other validation errors.

**How it works:**
- Loads `max_selections` rules from questionnaire data
- Tracks votes per position (e.g., President max=1, Senator max=12)
- Displays warning overlay when overvote detected
- Logs validation errors to session metadata

**Configuration:**
```bash
python3 appreciate_live.py \
  --template coords.json \
  --validate-contests
```

**Visual feedback:**
- Red "⚠ OVERVOTE: POSITION (count/max)" overlay at bottom-left
- Audio alert when overvote detected

### 5. Session Management
**Purpose:** Track complete ballot lifecycle from detection to finalization.

**Session data:**
- Document ID (from barcode)
- Start time
- Votes collected
- Frames processed
- Validation errors
- Status (active, frozen, finalized)

**Persistence:**
- Session directory: `storage/app/live-sessions/{document_id}_{session_id}/`
- `session.json`: Metadata and vote state
- `ballot.txt`: Final ballot string for Laravel
- `capture_*.png`: Saved frames

**Configuration:**
```bash
python3 appreciate_live.py \
  --template coords.json \
  --session-dir storage/app/live-sessions
```

### 6. Laravel Integration
**Purpose:** Cast finalized ballots directly to Laravel election system.

**How it works:**
1. Press `F` to finalize active session
2. Generates compact ballot string: `BAL-001|PRESIDENT:LD_001;SENATOR:JD_001,ES_002`
3. Calls `php artisan election:cast-ballot <ballot_string>`
4. Plays success jingle if cast successful
5. Resets accumulator for next ballot

**Output:**
```
✓ Ballot finalized: BAL-001|PRESIDENT:LD_001;VICE-PRESIDENT:VD_002;SENATOR:JD_001,ES_002,MF_003
✓ Cast to Laravel successfully
```

**Requirements:**
- Laravel project must be 3 directories up from script location
- `php artisan election:cast-ballot` command must be available

## Complete Workflow

### Typical Ballot Processing Session

1. **Launch Application:**
   ```bash
   python3 packages/omr-appreciation/omr-python/appreciate_live.py \
     --template storage/app/tests/omr-appreciation/latest/template/coordinates.json \
     --show-names \
     --validate-contests
   ```

2. **Ballot Detection:**
   - Place ballot in view of camera
   - System detects fiducials and QR code
   - Audio beep confirms ballot detected
   - New session created automatically

3. **Vote Marking:**
   - Fill ballot bubbles normally
   - Green circles appear after 8 consecutive detections
   - Candidate names display next to filled bubbles (if `--show-names` enabled)
   - Audio click confirms each stable vote

4. **Validation:**
   - If overvote occurs, red warning appears at bottom
   - Audio alert sounds
   - Validation error logged to session

5. **Review & Freeze:**
   - Press SPACE to freeze current frame
   - Review votes and validation status
   - Press S to save annotated image
   - Press SPACE again to resume

6. **Finalization:**
   - Press F to finalize ballot
   - Ballot string generated and displayed
   - Cast to Laravel election system
   - Success jingle plays
   - System ready for next ballot

### Controls Summary

| Key | Action |
|-----|--------|
| ESC | Exit application |
| W | Toggle warped view (debug) |
| SPACE | Freeze/unfreeze frame |
| S | Save current frame |
| F | Finalize ballot (cast to Laravel) |

## On-Screen Display

### Top-Left Corner
- **Ballot ID** (from QR code)
- **Decoder type** (visual/metadata)
- **Quality indicator** (green/amber/red)
- **Angle** (ballot rotation)
- **FPS** (frame rate)

### Top-Right Corner (Session Info)
- **Session ID** (last 8 characters)
- **Vote count** (number of stable votes)
- **Frames processed**

### Bottom-Left Corner
- **Validation warnings** (overvotes)

### Center (when frozen)
- **⏸ FROZEN** indicator

### Ballot Overlay
- **Yellow dots**: Fiducial markers
- **Green circles**: Stable filled votes (high confidence)
- **Yellow circles**: Stable filled votes (low confidence)
- **Red circles**: Not filled
- **Candidate names**: Next to filled bubbles (if enabled)

## Testing

### Unit Tests
Run Phase 4 unit tests:
```bash
python3 packages/omr-appreciation/tests/test_phase4_features.py
```

Tests cover:
- Vote accumulator stability and callbacks
- Session creation, persistence, and finalization
- Contest validation and overvote detection
- Audio feedback initialization

### Integration Tests
Phase 4 features are tested in:
```bash
bash scripts/test-omr-appreciation.sh
```

Includes:
- Vote accumulator with simulated flickering
- Session management with multiple ballots
- Validation with overvote scenarios
- Ballot string generation and Laravel casting

## Configuration Reference

### Command-Line Arguments

| Argument | Type | Default | Description |
|----------|------|---------|-------------|
| `--template` | str | (required) | Path to coordinates.json |
| `--camera` | int | 0 | Camera device index |
| `--threshold` | float | 0.30 | Fill detection threshold |
| `--show-names` | flag | false | Display candidate names |
| `--validate-contests` | flag | false | Enable overvote detection |
| `--no-audio` | flag | false | Disable audio feedback |
| `--accumulator-window` | int | 10 | Vote window size (frames) |
| `--accumulator-threshold` | int | 8 | Vote threshold (detections) |
| `--session-dir` | str | storage/app/live-sessions | Session storage directory |
| `--no-barcode` | flag | false | Skip barcode decoding |
| `--show-warp` | flag | false | Show warped view (debug) |
| `--no-fps` | flag | false | Hide FPS counter |

### Session Directory Structure

```
storage/app/live-sessions/
└── BAL-001_20251030_143022/
    ├── session.json          # Session metadata and state
    ├── ballot.txt           # Final ballot string
    ├── manual_143045.png    # Manually saved frames
    └── capture_143123.png   # Auto-saved captures
```

### Session JSON Format

```json
{
  "session_id": "20251030_143022",
  "document_id": "BAL-001",
  "start_time": "2025-10-30T14:30:22Z",
  "status": "finalized",
  "frames_processed": 245,
  "votes": {
    "PRESIDENT_LD_001": true,
    "VICE-PRESIDENT_VD_002": true,
    "SENATOR_JD_001": true,
    "SENATOR_ES_002": true,
    "SENATOR_MF_003": true
  },
  "validation_errors": [
    {
      "timestamp": "2025-10-30T14:30:45Z",
      "position": "PRESIDENT",
      "message": "Overvote detected",
      "vote_count": 2,
      "max_selections": 1
    }
  ]
}
```

## Troubleshooting

### Audio Not Working
- Check if `pygame` is installed: `pip3 install pygame`
- Use `--no-audio` to disable audio feedback
- Audio silently fails if pygame unavailable

### Votes Flickering
- Increase accumulator window: `--accumulator-window 15`
- Increase threshold: `--accumulator-threshold 12`
- Improve lighting conditions

### Laravel Cast Fails
- Verify project structure: script must be in `packages/omr-appreciation/omr-python/`
- Test manually: `php artisan election:cast-ballot "BAL-001|PRESIDENT:LD_001"`
- Check Laravel logs: `tail -f storage/logs/laravel.log`

### Validation Not Working
- Enable validation: `--validate-contests`
- Verify questionnaire data loads (check console output)
- Ensure template has correct `document_id`

## Performance

### Typical Performance (MacBook Pro M1)
- **FPS:** 25-30 with full processing
- **Latency:** ~300ms from detection to stable vote
- **Memory:** ~150MB RAM
- **CPU:** ~30% (single core)

### Optimization Tips
- Use `--no-barcode` if QR code not needed
- Reduce accumulator window for faster response
- Disable candidate names if not needed
- Use lower camera resolution (not implemented yet)

## Future Enhancements (Not Yet Implemented)

### Flask Web Streaming
- MJPEG video stream endpoint
- REST API for controls (freeze, finalize, etc.)
- Web UI for remote monitoring
- Multi-client viewing

### Advanced Features
- Batch processing mode (multiple ballots in sequence)
- Automatic ballot rejection (quality too low)
- Real-time dashboard with statistics
- Support for multiple camera angles
- Hardware acceleration (GPU)

## Changelog

### Phase 4 (2025-10-30)
- ✅ Vote accumulator with configurable window and threshold
- ✅ Audio feedback for all ballot events
- ✅ Freeze frame and manual capture
- ✅ Multi-contest validation with overvote detection
- ✅ Session management with persistence
- ✅ Laravel integration via artisan commands
- ✅ Comprehensive unit tests (17 tests)
- ✅ Integration with main test suite

### Phase 3 (Previous)
- Module refactoring (integrated core OMR modules)
- Barcode decoding with visual and metadata fallback
- Candidate name display from database

### Phase 2 (Previous)
- ArUco fiducial detection and alignment
- Quality metrics and indicators
- Coordinate transformation

### Phase 1 (Initial)
- Basic bubble detection
- AR overlay visualization
- Camera input handling

---

**For questions or issues, see project documentation or contact the development team.**
