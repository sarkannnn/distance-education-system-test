# LiveKit & Egress — Full Technical Guide

This document explains exactly how LiveKit real-time video and Egress recording work inside this project, from environment setup to file storage.

---

## Table of Contents

1. [What Is LiveKit and Egress?](#1-what-is-livekit-and-egress)
2. [Environment Configuration](#2-environment-configuration)
3. [JWT Token System](#3-jwt-token-system)
4. [Live Class Flow — Teacher Studio](#4-live-class-flow--teacher-studio)
5. [Student Live View](#5-student-live-view)
6. [Egress Recording — How It Works](#6-egress-recording--how-it-works)
7. [Browser-Side Recording (Fallback)](#7-browser-side-recording-fallback)
8. [End of Class — File Storage](#8-end-of-class--file-storage)
9. [Data Signaling (DataChannel)](#9-data-signaling-datachannel)
10. [File Reference Map](#10-file-reference-map)
11. [Database Schema](#11-database-schema)
12. [Common Errors and Fixes](#12-common-errors-and-fixes)
13. [Deployment Checklist](#13-deployment-checklist)

---

## 1. What Is LiveKit and Egress?

**LiveKit** is an open-source WebRTC infrastructure server. Instead of peers connecting directly to each other (which fails on mobile/LTE), all video/audio streams go through the LiveKit server as an SFU (Selective Forwarding Unit).

**LiveKit Egress** is a service that sits alongside the LiveKit server. When activated, it launches a headless Chromium browser, loads a special recording page from your web app (`live-record_view.php`), connects it to the room, and records everything to an MP4 file on the LiveKit server.

```
Teacher Browser ──┐
                   ├──► LiveKit SFU Server ──► Egress Recorder ──► .mp4 file
Student Browsers ──┘
```

---

## 2. Environment Configuration

All LiveKit settings are loaded from the `.env` file in the project root.

```env
# Required
LIVEKIT_API_KEY=devkey
LIVEKIT_API_SECRET=secret
LIVEKIT_HOST=https://distant-l.ndu.edu.az     # LiveKit server URL (HTTP/HTTPS, not wss://)

# Optional
LIVEKIT_VERIFY_SSL=true                        # Set to false for self-signed certs (dev only)
LIVEKIT_CONNECT_TIMEOUT=10                     # cURL connect timeout in seconds
LIVEKIT_REQUEST_TIMEOUT=30                     # cURL total request timeout in seconds
PUBLIC_BASE_URL=https://distant.ndu.edu.az     # Public URL used to build the Egress template URL
```

**Important:** The `.env` file must be readable by the web server process:
```bash
chmod 644 /var/www/distance-education-system-test/.env
chown root:www-data /var/www/distance-education-system-test/.env
```

The `.env` is loaded in `student/config/database.php`, `teacher/config/database.php`, and all PHP files that use `getenv()` or `$_ENV`.

---

## 3. JWT Token System

All participants (teacher, student, recorder) must authenticate with LiveKit using a JWT token. Tokens are generated server-side by PHP — no external library is used.

### How tokens are generated

**File:** `api/livekit_helper.php`

```
Header: { "typ": "JWT", "alg": "HS256" }

Payload:
  iss    = LIVEKIT_API_KEY
  sub    = participant identity (e.g. "teacher_12", "student_7", "EgressRecorder")
  name   = display name
  exp    = now + 14400 seconds (4 hours)
  nbf    = now - 30 seconds (tolerance)
  video  = {
    room: "<room_name>",
    roomJoin: true,
    canPublish: true,
    canSubscribe: true,
    canPublishData: true,
    canUpdateMetadata: true,
    roomRecord: true   ← only for egress token
  }

Signature: HMAC-SHA256(header + "." + payload, LIVEKIT_API_SECRET)
```

### Token endpoint

**File:** `api/livekit_token.php`

Called by the browser via:
```
GET /api/livekit_token.php?room=<lessonId>
```

Role detection logic:
- Checks `DISTANT_T_SESSION_V4` cookie → instructor role
- Checks `DISTANT_STUDENT_SESSION` cookie → student role
- Checks `?guest_token=` parameter → guest role (future use)

Returns:
```json
{
  "success": true,
  "token": "<JWT>",
  "identity": "teacher_12",
  "name": "Rauf Əliyev",
  "serverUrl": "wss://distant-l.ndu.edu.az"
}
```

For the Egress recorder, the token is fetched from the same endpoint with `identity=EgressRecorder` inside `live-record_view.php`.

---

## 4. Live Class Flow — Teacher Studio

**File:** `teacher/live-studio_livekit.php`

This is the teacher's production interface. It has three columns:
- **Left:** Live attendance list
- **Center:** Video compositor canvas + controls
- **Right:** Student camera grid + chat

### Startup sequence

1. Teacher clicks **"YAYIMI BAŞLAT"** (Start Broadcast) on the overlay
2. `startProductionNow()` is called
3. `init()` runs:
   - Fetches TURN credentials from `api/get_turn_credentials.php`
   - Requests camera + microphone (`getUserMedia`)
   - Falls back to a black canvas stream if camera is unavailable
   - Calls `startCanvasCompositing()` — composites cam + screen share onto a single `<canvas>` at 30 FPS
   - Connects to LiveKit: fetches JWT from `api/livekit_token.php?room=<lessonId>`
   - Publishes the canvas stream as the teacher's video track
4. `startEgressRecording()` is called automatically → calls `api/start_egress.php`

### Teacher controls

| Button | Function |
|--------|----------|
| MİKROFON | Toggle microphone on/off |
| KAMERA | Toggle camera on/off |
| EKRAN | Toggle screen share |
| LÖVHƏ | Open/close whiteboard overlay |
| NORMAL / ECO | Switch video quality mode |
| LOGLAR | Show/hide the system event log |
| Dərsi Bitir | End the class → `stopAndUpload()` |

### Canvas compositing

The teacher's `<canvas>` renders at 30 FPS. The compositor `drawToCanvas()` stacks layers in this priority:

1. **Student Spotlight** (if active) — student's screen share fills the frame; teacher cam appears as PIP in corner
2. **Teacher Screen Share** — if sharing, fills the main frame
3. **Whiteboard** — if active, replaces the video content
4. **Teacher Camera** — default view

This composite canvas stream is what gets published to LiveKit and recorded by Egress.

### Maximum session duration

The studio has a built-in timer. At 10800 seconds (3 hours), a 5-minute warning is shown and then `stopAndUpload(true)` is called automatically.

---

## 5. Student Live View

**File:** `student/live-view_livekit.php`

Students join the same LiveKit room using `identity=student_<userId>`. Before entering:
- Local enrollment is checked
- TMIS API subject list is checked as fallback
- Stream target course IDs are checked

Students can:
- Subscribe to the teacher's video/audio
- Send chat messages (via DataChannel)
- Request microphone / whiteboard / screen share access (teacher must approve)

When the teacher ends the class, a `lesson_ended` DataChannel message is broadcast to all students and they are redirected.

---

## 6. Egress Recording — How It Works

The Egress system records what Chromium renders on `live-record_view.php` to an MP4 file directly on the LiveKit server.

### Step 1 — Start Recording

**Triggered by:** `startEgressRecording()` in `live-studio_livekit.php` (called automatically after room connection)

**HTTP call:** `POST ../api/start_egress.php`
- Auth: instructor session required
- Parameters: `lesson_id`, `room_name`

**File:** `api/start_egress.php` → delegates to `api/livekit_egress_service.php`

**`LiveKitEgressService::startRecording()`** does:

1. Builds the template URL:
   ```
   https://<PUBLIC_BASE_URL>/teacher/live-record_view.php?id=<lessonId>&secret=L6k_Rec_2024
   ```

2. Generates an admin JWT token with `roomRecord: true` permission

3. Sends a Twirp API request to LiveKit:
   ```
   POST https://distant-l.ndu.edu.az/twirp/livekit.Egress/StartRoomCompositeEgress
   Authorization: Bearer <JWT>
   Content-Type: application/json

   {
     "room_name": "<lessonId>",
     "layout": "custom",
     "custom_base_url": "<template_url>",
     "file": {
       "filepath": "recordings/lesson_<id>_<timestamp>.mp4",
       "disable_manifest": true
     }
   }
   ```

4. On success (HTTP 200), saves the returned `egress_id` into `live_classes.egress_id` in the database

### Step 2 — Egress Compositor (live-record_view.php)

**File:** `teacher/live-record_view.php`

This page is opened by the LiveKit Egress headless browser. It:

1. Fetches its own LiveKit JWT token with identity `EgressRecorder`
2. Connects to the room as a hidden participant
3. Renders a `<canvas>` at 1920×1080 at 30 FPS
4. Subscribes to video tracks:
   - Teacher camera → `<video id="teacherCam">`
   - Teacher screen share → `<video id="teacherScreen">`
   - Student screen share → `<video id="studentSpotlight">`
5. Listens for DataChannel signals:
   - `whiteboard_started` / `whiteboard_stopped` → switch to whiteboard layer
   - `screen_share_started` / `screen_share_stopped` → switch to student spotlight
   - `wb_draw` → renders whiteboard strokes on a secondary canvas
   - `wb_clear` → clears whiteboard canvas

The `drawFrame()` function runs at 30 FPS and composes layers in this order:
1. Background fill (`#0f172a`)
2. Whiteboard (if active) — full frame white canvas with drawings on top
3. Teacher screen share (if active) — full frame
4. Teacher camera (default) — full frame
5. Student spotlight overlay (if active) — replaces main + teacher PIP in corner
6. NSU branding badge (top-left overlay)

**Security:** Page requires `?secret=L6k_Rec_2024`. The `die()` is currently commented out — hardening this for production is recommended.

### Step 3 — Stop Recording

**Triggered by:** `stopAndUpload()` when teacher ends the class

**HTTP call:** `POST ../api/stop_egress.php`
- Auth: instructor session required
- Parameters: `lesson_id`

**`LiveKitEgressService::stopRecording()`** does:

1. Fetches `egress_id` from `live_classes` table
2. Sends:
   ```
   POST https://distant-l.ndu.edu.az/twirp/livekit.Egress/StopEgress
   Body: { "egress_id": "<id>" }
   ```
3. On success, clears `egress_id` in the database

**Output file location (on LiveKit server):**
```
recordings/lesson_<lessonId>_<unixTimestamp>.mp4
```

---

## 7. Browser-Side Recording (Fallback)

In parallel with Egress, the browser also records locally using `MediaRecorder` on the composite canvas stream. This is a fallback in case Egress fails.

**Periodic chunk upload every ~30 seconds:**

Function: `flushChunksToServer()` in `live-studio_livekit.php`

```
POST ../api/live/upload_chunk.php
  lesson_id     = <lessonId>
  video_blob    = <Blob: video/webm chunk>
  is_first_chunk = 1 (first) or 0 (subsequent)
  session_id    = <random session identifier>
```

**File:** `api/live/upload_chunk.php`
- Auth: instructor or admin session required
- Appends chunks to: `uploads/live_recordings/lesson_<lessonId>.webm`
- If `is_first_chunk=1` and a file already exists (teacher refreshed), strips the WebM header from the new chunk (to avoid duplicate EBML headers) and only appends the Cluster data

On page unload, `flushChunksBeacon()` uses `navigator.sendBeacon()` to send any remaining chunks.

---

## 8. End of Class — File Storage

When the teacher clicks "Dərsi Bitir", the `stopAndUpload()` function runs a sequential chain:

```
1. broadcastData({ type: 'lesson_ended' })   → notifies all students

2. POST teacher/api/end_live_class.php        → sets status = 'ended' in DB
   (lesson_id)

3. POST api/stop_egress.php                  → stops Egress recording on LiveKit server
   (lesson_id)

4. POST teacher/api/upload_recording.php     → finalizes the local browser recording
   (lesson_id, course_id, video blob, has_chunks, duration_ms)

5. Redirect → live-lessons.php?ended=1
```

### upload_recording.php Logic

**File:** `teacher/api/upload_recording.php`

1. Checks for a pre-saved chunk file: `uploads/live_recordings/lesson_<id>.webm`
2. Checks for a final video blob in `$_FILES['video']`

**Merge scenarios:**

| Chunk file exists | Final blob | Action |
|-------------------|------------|--------|
| ✅ | ✅ | Copy chunk file to final path, strip header from blob, append blob's Cluster data |
| ✅ | ❌ | Use chunk file directly as final video |
| ❌ | ✅ | `move_uploaded_file()` the blob directly |
| ❌ | ❌ | Mark lesson ended without a video recording |

3. After saving, calls `patchWebmDuration()` — writes the actual duration as EBML metadata so the browser player shows a correct seekbar
4. Updates the database:
   ```sql
   UPDATE live_classes SET recording_path = ?, status = 'pending_approval',
     end_time = NOW(), duration_minutes = ? WHERE id = ?
   UPDATE schedule SET status = 'completed' WHERE live_class_id = ?
   ```
5. Deletes the temporary chunk file

### Final file paths

| Type | Path | Format |
|------|------|--------|
| LiveKit Egress output | `recordings/lesson_<id>_<ts>.mp4` on LiveKit server | MP4 |
| Temporary chunks | `uploads/live_recordings/lesson_<id>.webm` | WebM (deleted after finalization) |
| **Final browser recording** | **`uploads/videos/lesson_<id>_<ts>.webm`** | **WebM** |
| Upload debug log | `uploads/upload_debug.log` | Text |
| Upload error log | `uploads/upload_error.log` | Text |

---

## 9. Data Signaling (DataChannel)

LiveKit's DataChannel (not WebSocket) is used for all real-time signaling between teacher and students. All messages are JSON, encoded as `Uint8Array`.

**Send (teacher):**
```js
room.localParticipant.publishData(payload, { reliable: true })
// Private: { reliable: true, destinationIdentities: ['student_X'] }
```

### Message Types

| `type` | Direction | Payload | Effect |
|--------|-----------|---------|--------|
| `lesson_ended` | Teacher → All | — | Students redirect to lessons page |
| `chat` | Both ways | `message`, `sender`, `isPrivate?` | Displays in chat box |
| `file` | Both ways | `fileData` (URL), `fileName`, `sender` | Shows download link in chat |
| `whiteboard_started` | Teacher → All | — | Students show whiteboard overlay |
| `whiteboard_stopped` | Teacher → All | — | Students close whiteboard |
| `whiteboard_force_stop` | Teacher → All | — | Immediate close (e.g. teacher toggles off) |
| `whiteboard_approved` | Teacher → Student | — | Grants student whiteboard drawing rights |
| `whiteboard_rejected` | Teacher → Student | — | Denies whiteboard request |
| `wb_draw` | Teacher → All | `tool`, `mode`, `x1`,`y1`,`x2`,`y2`, `color`, `size` | Renders stroke on student + egress canvas |
| `wb_clear` | Teacher → All | — | Clears whiteboard canvas |
| `screen_share_started` | Student → Teacher | `sender` | Teacher sees student screen |
| `screen_share_stopped` | Student → Teacher | — | Stops student spotlight |
| `screen_share_approved` | Teacher → Student | — | Student may start sharing |
| `screen_share_rejected` | Teacher → Student | — | Denied |
| `mic_request` | Student → Teacher | `sender`, identity | Shows approval modal |
| `mic_approved` | Teacher → Student | — | Student mic is unmuted |
| `mic_rejected` | Teacher → Student | — | Denied |

---

## 10. File Reference Map

```
project-root/
│
├── api/
│   ├── livekit_token.php          ← JWT token generator (HTTP endpoint)
│   ├── livekit_helper.php         ← JWT signing logic (no external lib)
│   ├── livekit_egress_service.php ← Core Egress service class
│   ├── start_egress.php           ← HTTP: start server recording
│   ├── stop_egress.php            ← HTTP: stop server recording
│   ├── get_turn_credentials.php   ← HTTP: fetch TURN server config
│   ├── upload_chat_file.php       ← HTTP: chat file upload
│   └── live/
│       └── upload_chunk.php       ← HTTP: periodic browser recording chunks
│
├── teacher/
│   ├── live-studio_livekit.php    ← Teacher production UI (LiveKit)
│   ├── live-record_view.php       ← Egress compositor page (headless Chromium)
│   └── api/
│       └── upload_recording.php   ← HTTP: finalize + save browser recording
│
├── student/
│   └── live-view_livekit.php      ← Student live viewer (LiveKit)
│
└── uploads/
    ├── live_recordings/           ← Temporary WebM chunks (deleted after class ends)
    └── videos/                    ← Final browser recordings (permanent)
```

---

## 11. Database Schema

### `live_classes` table (relevant columns)

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key, used as room name in LiveKit |
| `course_id` | INT | Linked course |
| `tmis_session_id` | VARCHAR | External TMIS system ID (fallback lookup) |
| `egress_id` | VARCHAR(255) | LiveKit Egress ID; set on start, cleared on stop |
| `recording_path` | VARCHAR(255) | Filename of final video in `uploads/videos/` |
| `status` | ENUM | `live` → `ended` → `pending_approval` → `archived` |
| `start_time` | DATETIME | When class started |
| `end_time` | DATETIME | When class ended |
| `duration_minutes` | INT | Computed from start/end times |

---

## 12. Common Errors and Fixes

### Permission denied on .env
```
PHP Warning: file(): Failed to open stream: Permission denied
```
The web server cannot read `.env`. Database credentials are not loaded.
```bash
chmod 644 /var/www/distance-education-system-test/.env
chown root:www-data /var/www/distance-education-system-test/.env
```

### Egress HTTP 503
```
LiveKit API Error (HTTP 503): Server unavailable
```
LiveKit server is not running or wrong URL.
```bash
docker ps | grep livekit       # Is the container running?
curl https://distant-l.ndu.edu.az/   # Is the host reachable?
```
Also verify `LIVEKIT_API_KEY` and `LIVEKIT_API_SECRET` in `.env`.

### Egress HTTP 401
```
Authentication failed. Invalid LIVEKIT_API_KEY or LIVEKIT_API_SECRET
```
Credentials in `.env` do not match what the LiveKit server was started with.

### Egress timeout / cURL error
```
cURL Error: Operation timed out
```
Increase timeouts in `.env`:
```env
LIVEKIT_CONNECT_TIMEOUT=20
LIVEKIT_REQUEST_TIMEOUT=60
```

### No egress_id found when stopping
```
Egress ID tapılmadı.
```
`startRecording()` either failed or the `egress_id` was never saved to the database. Check `live_classes.egress_id` for the lesson row.

### Video has no seekbar / duration shows 0
The WebM duration metadata was not written. `patchWebmDuration()` ran but may have failed if the file header structure was unexpected. Check `uploads/upload_debug.log`.

### Chunk upload 403 Unauthorized
The instructor's PHP session expired during the live class. The session name is `DISTANT_T_SESSION_V4`. Ensure `session.gc_maxlifetime` in `php.ini` is at least 4 hours (14400).

### uploads/ directory not writable
```
Failed to write file to: ../../uploads/live_recordings/lesson_X.webm
```
```bash
chmod 775 /var/www/distance-education-system-test/uploads
chown root:www-data /var/www/distance-education-system-test/uploads
```

---

## 13. Deployment Checklist

- [ ] `.env` file exists with all `LIVEKIT_*` variables set
- [ ] `.env` readable by web server: `chmod 644`, owned by `root:www-data`
- [ ] LiveKit server deployed and running (Docker or binary)
- [ ] `LIVEKIT_HOST` uses `https://` not `wss://`
- [ ] `PUBLIC_BASE_URL` is set to the public-facing domain
- [ ] `uploads/` directory writable by web server (`chmod 775`)
- [ ] `uploads/videos/` and `uploads/live_recordings/` exist or can be auto-created
- [ ] PHP `post_max_size` and `upload_max_filesize` are large enough (e.g. `512M`)
- [ ] PHP `max_execution_time` set to `3600` for `upload_recording.php`
- [ ] PHP session lifetime (`session.gc_maxlifetime`) is at least `14400` (4 hours)
- [ ] SSL certificate valid on LiveKit host (or `LIVEKIT_VERIFY_SSL=false` for dev)
- [ ] `live-record_view.php` secret key `L6k_Rec_2024` changed for production
- [ ] Nginx allows large POST bodies: `client_max_body_size 512M;`
- [ ] Nginx FastCGI timeouts are high enough: `fastcgi_read_timeout 3600;`
