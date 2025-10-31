# Flask Web Streaming for AR Ballot Appreciation

This document describes the **Flask Web Streaming** enhancement - an optional feature for remote monitoring and control of the live ballot appreciation system.

## Overview

Flask Web Streaming transforms the desktop AR application into a web-accessible service, enabling:
- Remote viewing from any browser
- Centralized control of multiple ballot stations
- Multi-user access with role-based permissions
- Real-time dashboards for election day operations

---

## Components

### 1. MJPEG Video Endpoint for Remote Viewing

**What it is:**
- Motion JPEG streaming: sends each video frame as a separate JPEG image
- Standard format supported by all browsers
- Endpoint like `/video_feed` that continuously streams frames

**Implementation:**
```python
from flask import Flask, Response
import cv2

def generate_frames():
    while True:
        # Get frame from camera with AR overlay
        frame = process_frame()  # Your existing logic
        
        # Encode as JPEG
        ret, buffer = cv2.imencode('.jpg', frame)
        frame_bytes = buffer.tobytes()
        
        # Yield in MJPEG format
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')

@app.route('/video_feed')
def video_feed():
    return Response(generate_frames(),
                   mimetype='multipart/x-mixed-replace; boundary=frame')
```

**Use cases:**
- Watch ballot processing from another computer
- Monitor multiple stations from central location
- Record sessions for audit purposes

---

### 2. REST API for Controls

**What it is:**
- HTTP endpoints to control the appreciation system remotely
- JSON-based request/response
- Replaces keyboard controls with API calls

**Endpoints:**

| Method | Endpoint | Action |
|--------|----------|--------|
| `POST` | `/api/freeze` | Freeze/unfreeze current frame |
| `POST` | `/api/capture` | Save current frame |
| `POST` | `/api/finalize` | Finalize and cast ballot |
| `GET` | `/api/status` | Get current session status |
| `GET` | `/api/votes` | Get current stable votes |
| `GET` | `/api/validation` | Get validation results |
| `POST` | `/api/reset` | Reset for new ballot |

**Example implementation:**
```python
@app.route('/api/freeze', methods=['POST'])
def api_freeze():
    global is_frozen
    is_frozen = not is_frozen
    return jsonify({
        'success': True,
        'frozen': is_frozen
    })

@app.route('/api/status', methods=['GET'])
def api_status():
    return jsonify({
        'session_id': session.session_id if session else None,
        'document_id': session.document_id if session else None,
        'votes': session.votes if session else {},
        'validation_errors': session.validation_errors if session else [],
        'frames_processed': session.frames_processed if session else 0,
        'status': session.status if session else 'idle'
    })
```

**Use cases:**
- Remote control from web interface
- Integration with external systems
- Automated testing and monitoring

---

### 3. Web UI Dashboard

**What it is:**
- HTML/CSS/JavaScript frontend for viewing and controlling
- Real-time updates via Server-Sent Events or WebSockets
- Interactive controls and status display

**Features:**

#### Video Display
```html
<div class="video-container">
    <img src="/video_feed" alt="Live AR Ballot View" />
    <div class="overlay">
        <div class="status-badge" id="frozen-badge">⏸ FROZEN</div>
    </div>
</div>
```

#### Control Panel
```html
<div class="controls">
    <button onclick="toggleFreeze()">⏸ Freeze</button>
    <button onclick="captureFrame()">📷 Capture</button>
    <button onclick="finalizeBallot()">✓ Finalize</button>
</div>
```

#### Status Dashboard
```html
<div class="status-panel">
    <h3>Session: <span id="session-id">-</span></h3>
    <p>Ballot: <span id="document-id">-</span></p>
    <p>Votes: <span id="vote-count">0</span></p>
    <p>Frames: <span id="frame-count">0</span></p>
    
    <div class="validation-alerts">
        <!-- Overvote warnings appear here -->
    </div>
</div>
```

#### Real-time Updates
```javascript
// Poll for status updates
setInterval(() => {
    fetch('/api/status')
        .then(r => r.json())
        .then(data => {
            document.getElementById('session-id').textContent = data.session_id || '-';
            document.getElementById('document-id').textContent = data.document_id || '-';
            document.getElementById('vote-count').textContent = Object.keys(data.votes).length;
            document.getElementById('frame-count').textContent = data.frames_processed;
        });
}, 1000);
```

#### Layout Example
```
┌─────────────────────────────────────────────┐
│  Truth AR Ballot Appreciation               │
├─────────────────┬───────────────────────────┤
│                 │  Session Info             │
│                 │  • ID: 20251030_143022    │
│   Live Video    │  • Ballot: BAL-001        │
│   [AR Overlay]  │  • Votes: 5               │
│                 │  • Status: Active         │
│                 ├───────────────────────────┤
│                 │  Controls                 │
│                 │  [Freeze] [Capture]       │
├─────────────────┤  [Finalize]               │
│  Validation     ├───────────────────────────┤
│  ⚠ PRESIDENT    │  Recent Events            │
│  Overvote (2/1) │  • Ballot detected        │
└─────────────────┴───────────────────────────┘
```

---

### 4. Multi-Client Support

**What it is:**
- Multiple users viewing/controlling the same stream simultaneously
- Thread-safe state management
- Coordinated access control

**Challenges & Solutions:**

#### A. Concurrent Video Access
**Problem:** Multiple clients requesting video feed

**Solution:** 
```python
from threading import Lock

frame_lock = Lock()
latest_frame = None

def generate_frames():
    while True:
        with frame_lock:
            if latest_frame is not None:
                yield encode_frame(latest_frame)
        time.sleep(0.033)  # ~30 FPS
```

#### B. State Synchronization
**Problem:** Multiple clients need same state

**Solution:** 
```python
from flask_socketio import SocketIO, emit

socketio = SocketIO(app)

# Broadcast state changes to all clients
def broadcast_vote_change(bubble_id, filled):
    socketio.emit('vote_change', {
        'bubble_id': bubble_id,
        'filled': filled
    }, broadcast=True)
```

#### C. Access Control
**Problem:** Prevent conflicting commands

**Solution:**
```python
# Role-based permissions
ROLES = {
    'viewer': ['view'],
    'operator': ['view', 'freeze', 'capture'],
    'supervisor': ['view', 'freeze', 'capture', 'finalize']
}

@app.route('/api/finalize', methods=['POST'])
@require_role('supervisor')
def api_finalize():
    # Only supervisors can finalize
    pass
```

#### D. Session Locking
**Problem:** Two operators trying to finalize simultaneously

**Solution:**
```python
from threading import Lock

finalize_lock = Lock()

@app.route('/api/finalize', methods=['POST'])
def api_finalize():
    if not finalize_lock.acquire(blocking=False):
        return jsonify({'error': 'Finalization in progress'}), 409
    
    try:
        # Finalize ballot
        pass
    finally:
        finalize_lock.release()
```

---

## Complete Architecture

```
┌──────────────────────────────────────────────────────┐
│                 Flask Web Server                      │
│  ┌────────────────────────────────────────────────┐  │
│  │  Routes                                        │  │
│  │  • /video_feed (MJPEG stream)                 │  │
│  │  • /api/status (JSON status)                  │  │
│  │  • /api/freeze (control endpoint)             │  │
│  │  • / (web UI dashboard)                       │  │
│  └────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────┐  │
│  │  WebSocket/SSE for real-time updates          │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
                        │
                        ↓
┌──────────────────────────────────────────────────────┐
│         Ballot Appreciation Core                      │
│  • Camera capture                                     │
│  • AR overlay rendering                               │
│  • Vote accumulator                                   │
│  • Session management                                 │
└──────────────────────────────────────────────────────┘
                        │
                        ↓
┌──────────────────────────────────────────────────────┐
│                  Multiple Clients                     │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐              │
│  │Browser 1│  │Browser 2│  │Browser 3│              │
│  │(Viewer) │  │(Operator)│ │(Super)  │              │
│  └─────────┘  └─────────┘  └─────────┘              │
└──────────────────────────────────────────────────────┘
```

---

## Implementation Guide

### Prerequisites

```bash
pip3 install flask flask-socketio flask-cors
```

### Project Structure

```
packages/omr-appreciation/
├── omr-python/
│   ├── appreciate_live.py          # Core AR logic
│   └── appreciate_web.py           # Flask wrapper (new)
├── web-ui/                          # New directory
│   ├── static/
│   │   ├── css/
│   │   │   └── dashboard.css
│   │   ├── js/
│   │   │   └── dashboard.js
│   │   └── img/
│   └── templates/
│       └── dashboard.html
└── tests/
    └── test_web_streaming.py       # New tests
```

### Basic Flask Wrapper

```python
# appreciate_web.py
from flask import Flask, render_template, Response, jsonify
from flask_socketio import SocketIO
import threading
from appreciate_live import (
    VoteAccumulator, BallotSession, ContestValidator,
    detect_fiducials, align_image, detect_marks
)

app = Flask(__name__)
socketio = SocketIO(app, cors_allowed_origins="*")

# Global state
camera = None
session = None
accumulator = None
is_frozen = False
latest_frame = None
frame_lock = threading.Lock()

@app.route('/')
def index():
    return render_template('dashboard.html')

@app.route('/video_feed')
def video_feed():
    return Response(generate_frames(),
                   mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/api/status')
def api_status():
    return jsonify({
        'session_id': session.session_id if session else None,
        'document_id': session.document_id if session else None,
        'votes': session.votes if session else {},
        'frames_processed': session.frames_processed if session else 0
    })

@app.route('/api/freeze', methods=['POST'])
def api_freeze():
    global is_frozen
    is_frozen = not is_frozen
    socketio.emit('freeze_changed', {'frozen': is_frozen})
    return jsonify({'success': True, 'frozen': is_frozen})

def generate_frames():
    while True:
        with frame_lock:
            if latest_frame is not None:
                ret, buffer = cv2.imencode('.jpg', latest_frame)
                frame_bytes = buffer.tobytes()
                yield (b'--frame\r\n'
                       b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')

def camera_thread():
    """Background thread for camera processing"""
    global latest_frame
    # Your existing appreciate_live.py logic here
    pass

if __name__ == '__main__':
    # Start camera thread
    threading.Thread(target=camera_thread, daemon=True).start()
    
    # Run Flask server
    socketio.run(app, host='0.0.0.0', port=5000, debug=False)
```

### Running the Server

```bash
# Development mode
python3 appreciate_web.py

# Production mode (with gunicorn)
gunicorn -w 4 -b 0.0.0.0:5000 appreciate_web:app
```

### Accessing the Dashboard

```bash
# Local access
http://localhost:5000

# Remote access (same network)
http://<server-ip>:5000

# Video feed only
http://localhost:5000/video_feed
```

---

## API Reference

### GET /api/status
Get current appreciation session status.

**Response:**
```json
{
  "session_id": "20251030_143022",
  "document_id": "BAL-001",
  "votes": {
    "PRESIDENT_LD_001": true,
    "SENATOR_JD_001": true
  },
  "validation_errors": [],
  "frames_processed": 245,
  "status": "active"
}
```

### POST /api/freeze
Toggle freeze state.

**Response:**
```json
{
  "success": true,
  "frozen": true
}
```

### POST /api/capture
Save current frame to session directory.

**Response:**
```json
{
  "success": true,
  "filepath": "/path/to/capture.png",
  "timestamp": "2025-10-30T14:30:45Z"
}
```

### POST /api/finalize
Finalize current ballot and cast to Laravel.

**Response:**
```json
{
  "success": true,
  "ballot_string": "BAL-001|PRESIDENT:LD_001;SENATOR:JD_001",
  "laravel_cast": true
}
```

### GET /api/votes
Get current stable votes.

**Response:**
```json
{
  "votes": {
    "PRESIDENT_LD_001": true,
    "VICE-PRESIDENT_VD_002": true,
    "SENATOR_JD_001": true
  },
  "count": 3
}
```

### GET /api/validation
Get current validation results.

**Response:**
```json
{
  "PRESIDENT": {
    "valid": true,
    "count": 1,
    "max": 1,
    "overvote": false
  },
  "SENATOR": {
    "valid": false,
    "count": 13,
    "max": 12,
    "overvote": true
  }
}
```

---

## WebSocket Events

### Client → Server

```javascript
// Request state sync
socket.emit('request_sync');

// Request finalization
socket.emit('finalize_ballot');
```

### Server → Client

```javascript
// Vote state changed
socket.on('vote_change', (data) => {
    console.log(`Vote changed: ${data.bubble_id} = ${data.filled}`);
});

// Freeze state changed
socket.on('freeze_changed', (data) => {
    console.log(`Frozen: ${data.frozen}`);
});

// New ballot detected
socket.on('ballot_detected', (data) => {
    console.log(`New ballot: ${data.document_id}`);
});

// Overvote warning
socket.on('overvote_warning', (data) => {
    console.log(`Overvote in ${data.position}: ${data.count}/${data.max}`);
});
```

---

## Security Considerations

### 1. Authentication
```python
from flask_httpauth import HTTPBasicAuth

auth = HTTPBasicAuth()

users = {
    "viewer": "view_password",
    "operator": "operator_password",
    "supervisor": "supervisor_password"
}

@auth.verify_password
def verify_password(username, password):
    if username in users and users[username] == password:
        return username

@app.route('/api/finalize', methods=['POST'])
@auth.login_required
def api_finalize():
    if auth.current_user() != 'supervisor':
        return jsonify({'error': 'Unauthorized'}), 403
    # Finalize logic
```

### 2. HTTPS/SSL
```bash
# Generate self-signed certificate
openssl req -x509 -newkey rsa:4096 -nodes \
  -out cert.pem -keyout key.pem -days 365

# Run with SSL
socketio.run(app, ssl_context=('cert.pem', 'key.pem'))
```

### 3. Rate Limiting
```python
from flask_limiter import Limiter

limiter = Limiter(app, key_func=lambda: request.remote_addr)

@app.route('/api/finalize', methods=['POST'])
@limiter.limit("5 per minute")
def api_finalize():
    pass
```

---

## Implementation Estimate

**Time:** 2-3 days

**Breakdown:**
- Flask setup: 2 hours
- MJPEG streaming: 3 hours
- REST API: 4 hours
- Web UI: 8 hours
- Multi-client support: 6 hours
- Testing: 4 hours

**Total:** ~27 hours

---

## Benefits

- 📹 **Remote monitoring** from anywhere on network
- 🎛️ **Centralized control** of multiple ballot stations
- 👥 **Multi-user access** for observers and supervisors
- 📊 **Real-time dashboards** for election day operations
- 🔒 **Access control** with role-based permissions
- 📱 **Mobile-friendly** for tablets and phones
- 🔍 **Audit trails** with logged events and captures
- 🌐 **Browser-based** - no client software installation

---

## Use Cases

### Election Day Command Center
- Monitor 10+ ballot appreciation stations from single dashboard
- Real-time statistics and progress tracking
- Quick intervention when issues detected

### Training & Demonstration
- Remote demonstrations without physical ballot access
- Training sessions viewable by multiple trainees
- Recorded sessions for review and improvement

### Audit & Verification
- Independent observers can watch remotely
- Automatic frame capture for disputed ballots
- Complete audit trail of all actions

### Mobile Monitoring
- Poll watchers can monitor from tablets
- Alerts sent to mobile devices on overvotes
- Quick approvals from authorized devices

---

## Future Enhancements

- **Multiple camera support** - Switch between different angles
- **Batch station management** - Control multiple stations from one interface
- **Real-time analytics** - Vote patterns, processing speed, error rates
- **Alert system** - Push notifications for critical events
- **Video recording** - Save full sessions to disk
- **Replay mode** - Review past sessions frame-by-frame
- **Integration with Laravel** - Direct database updates via WebSockets
- **Progressive Web App** - Installable mobile app experience

---

## Related Documentation

- [Phase 4 Enhanced AR Features](../../packages/omr-appreciation/docs/PHASE4_README.md)
- [OMR Appreciation Testing](../WARP.md)
- [Election System Architecture](../election_system.md)

---

**Status:** Planned (not yet implemented)  
**Priority:** Optional enhancement  
**Dependencies:** Phase 4 features must be complete
