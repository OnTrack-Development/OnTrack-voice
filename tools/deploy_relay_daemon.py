import os
import subprocess
from pathlib import Path

def main():
    key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    
    relay_code = r'''#!/usr/bin/env python3
"""
OnTrack Voice - Cloud WSS Audio Relay Daemon (v79 / 0.3.56)
High-performance async WebSocket proxy multiplexing:
Mobile (Raw Binary PCM) <--> Relay Daemon <--> Gemini Live + Fish Audio Key Pool
Listening locally on 127.0.0.1:8095
"""
import asyncio
import base64
import json
import logging
import os
import signal
import struct
import subprocess
import sys
import time
import urllib.parse
from typing import Dict, List, Optional, Tuple

import aiohttp
import websockets
from websockets.asyncio.server import ServerConnection

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [Relay] %(message)s"
)
logger = logging.getLogger("OnTrackRelay")

HOST = "127.0.0.1"
PORT = 8095
RESOLVE_SCRIPT = "/home/appontrack/iovoice-app/resolve_session.php"
PHP_BIN = "/usr/local/bin/ea-php82"

# In-memory token cache: token -> (timestamp, session_data)
SESSION_CACHE: Dict[str, Tuple[float, dict]] = {}
CACHE_TTL = 600.0  # 10 minutes

# Fish API Key Pool with circuit breaker and round-robin
class FishKeyPool:
    def __init__(self, initial_keys: List[str]):
        self.keys = initial_keys
        self.index = 0
        self.throttled_until: Dict[str, float] = {}

    def add_key(self, key: str):
        if key and key not in self.keys:
            self.keys.append(key)

    def get_key(self) -> Optional[str]:
        now = time.time()
        n = len(self.keys)
        if n == 0:
            return None
        for _ in range(n):
            k = self.keys[self.index % n]
            self.index += 1
            if self.throttled_until.get(k, 0) <= now:
                return k
        # If all throttled, pick the one expiring soonest
        return min(self.keys, key=lambda k: self.throttled_until.get(k, 0))

    def mark_throttled(self, key: str, duration: float = 60.0):
        self.throttled_until[key] = time.time() + duration
        logger.warning(f"Fish API Key {key[:8]}... throttled for {duration}s (429)")

FISH_POOL = FishKeyPool([])

def resolve_session_sync(token: str) -> Optional[dict]:
    now = time.time()
    if token in SESSION_CACHE:
        cached_time, cached_data = SESSION_CACHE[token]
        if now - cached_time < CACHE_TTL:
            return cached_data

    try:
        proc = subprocess.run(
            [PHP_BIN, RESOLVE_SCRIPT, token],
            capture_output=True,
            text=True,
            timeout=5
        )
        if proc.returncode == 0 and proc.stdout:
            data = json.loads(proc.stdout)
            if data.get("ok"):
                SESSION_CACHE[token] = (now, data)
                # Register fish key in pool
                fish_key = data.get("fish", {}).get("api_key")
                if fish_key:
                    FISH_POOL.add_key(fish_key)
                return data
    except Exception as e:
        logger.error(f"resolve_session error for token {token[:12]}...: {e}")
    return None

def upsample_8k_to_16k(pcm_8k: bytes) -> bytes:
    """Fast linear/duplicate upsampling for low-bitrate cellular mode (8kHz -> 16kHz)."""
    # 8kHz 16-bit mono: each 2 bytes -> duplicate to 4 bytes
    out = bytearray(len(pcm_8k) * 2)
    for i in range(0, len(pcm_8k), 2):
        s0 = pcm_8k[i:i+2]
        out[i*2 : i*2+2] = s0
        out[i*2+2 : i*2+4] = s0
    return bytes(out)

class RelaySession:
    def __init__(self, client_ws: ServerConnection, token: str, low_bitrate: bool = False):
        self.client_ws = client_ws
        self.token = token
        self.low_bitrate = low_bitrate
        self.session_data: Optional[dict] = None
        self.gemini_ws = None
        self.http_session: Optional[aiohttp.ClientSession] = None
        self.closed = False
        self.current_tts_task: Optional[asyncio.Task] = None
        self.active_turn_id = 0

    async def run(self):
        try:
            # 1. Resolve Session
            loop = asyncio.get_running_loop()
            self.session_data = await loop.run_in_executor(None, resolve_session_sync, self.token)
            if not self.session_data:
                await self.client_ws.send(json.dumps({"type": "error", "message": "unauthorized_token"}))
                return

            gemini_cfg = self.session_data["gemini"]
            fish_cfg = self.session_data.get("fish", {})
            model = gemini_cfg.get("model", "gemini-3.8-live")
            voice = gemini_cfg.get("voice", "Aoede")
            instruction = gemini_cfg.get("system_instruction", "")
            api_key = gemini_cfg.get("api_key", "")
            ephemeral = gemini_cfg.get("ephemeral_token", "")

            # 2. Connect to Gemini Live WebSocket
            if api_key:
                gemini_url = f"wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key={api_key}"
            else:
                gemini_url = f"wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?access_token={ephemeral}"

            t0_gem = time.perf_counter()
            self.gemini_ws = await websockets.connect(gemini_url, open_timeout=8)
            gem_conn_ms = (time.perf_counter() - t0_gem) * 1000
            logger.info(f"Connected to Gemini Live in {gem_conn_ms:.1f}ms")

            # 3. Send Gemini Setup
            setup_payload = {
                "setup": {
                    "model": f"models/{model}",
                    "generationConfig": {
                        "responseModalities": ["AUDIO"],
                        "speechConfig": {
                            "voiceConfig": {
                                "prebuiltVoiceConfig": {"voiceName": voice}
                            }
                        }
                    },
                    "systemInstruction": {
                        "parts": [{"text": instruction}]
                    }
                }
            }
            await self.gemini_ws.send(json.dumps(setup_payload))
            init_ack = await self.gemini_ws.recv()
            logger.info(f"Gemini setup acknowledged: {init_ack[:60]}")

            # Notify client tunnel is ready
            await self.client_ws.send(json.dumps({
                "type": "ready",
                "relay_version": "0.3.56",
                "model": model,
                "voice": voice,
                "low_bitrate": self.low_bitrate,
                "gemini_connect_ms": round(gem_conn_ms, 2)
            }))

            self.http_session = aiohttp.ClientSession(
                timeout=aiohttp.ClientTimeout(total=4.0, connect=1.0)
            )

            # 4. Launch bidirectional forwarders
            uplink_task = asyncio.create_task(self.forward_client_to_gemini())
            downlink_task = asyncio.create_task(self.forward_gemini_to_client(fish_cfg))

            done, pending = await asyncio.wait(
                [uplink_task, downlink_task],
                return_when=asyncio.FIRST_COMPLETED
            )
            for t in pending:
                t.cancel()

        except Exception as e:
            logger.error(f"RelaySession exception: {type(e)} {e}")
        finally:
            await self.close()

    async def forward_client_to_gemini(self):
        """Uplink: Raw Binary PCM frames -> Gemini Live realtimeInput."""
        try:
            async for message in self.client_ws:
                if self.closed or not self.gemini_ws:
                    break

                if isinstance(message, bytes):
                    # Raw Binary PCM Frame from mobile
                    pcm = message
                    if self.low_bitrate:
                        pcm = upsample_8k_to_16k(pcm)

                    b64_audio = base64.b64encode(pcm).decode("ascii")
                    realtime_input = {
                        "realtimeInput": {
                            "mediaChunks": [
                                {
                                    "mimeType": "audio/pcm;rate=16000",
                                    "data": b64_audio
                                }
                            ]
                        }
                    }
                    await self.gemini_ws.send(json.dumps(realtime_input))

                elif isinstance(message, str):
                    # Control message JSON
                    try:
                        ctl = json.loads(message)
                        if ctl.get("type") == "barge_in":
                            logger.info("Barge-in received from client; interrupting current TTS turn")
                            if self.current_tts_task and not self.current_tts_task.done():
                                self.current_tts_task.cancel()
                            self.active_turn_id += 1
                            # Send turn complete / barge-in to Gemini if needed
                    except Exception as err:
                        logger.warning(f"Invalid client control frame: {err}")
        except Exception as e:
            if not self.closed:
                logger.warning(f"Uplink loop terminated: {e}")

    async def forward_gemini_to_client(self, fish_cfg: dict):
        """Downlink: Gemini Live text tokens & audio -> Fish Audio Stream -> Client Binary PCM."""
        ref_id = fish_cfg.get("reference_id", "")
        fish_model = fish_cfg.get("model", "drama-3")
        text_buffer = []
        gemini_fallback_audio = bytearray()

        try:
            async for raw in self.gemini_ws:
                if self.closed:
                    break

                resp = json.loads(raw)
                server_content = resp.get("serverContent")
                if not server_content:
                    continue

                model_turn = server_content.get("modelTurn")
                if model_turn:
                    parts = model_turn.get("parts", [])
                    for part in parts:
                        # 1. Text part for Fish Audio
                        txt = part.get("text")
                        if txt:
                            text_buffer.append(txt)
                            # Check for clause / sentence boundaries
                            accumulated = "".join(text_buffer)
                            if any(p in txt for p in [".", "،", "!", "؟", "\n", "..."]) or len(accumulated) >= 28:
                                sentence = accumulated.strip()
                                text_buffer.clear()
                                if sentence:
                                    self.active_turn_id += 1
                                    turn_id = self.active_turn_id
                                    # Launch Fish Audio synthesis task with fallback
                                    asyncio.create_task(
                                        self.synthesize_and_stream(sentence, ref_id, fish_model, turn_id, gemini_fallback_audio)
                                    )

                        # 2. Native Gemini audio part (backup buffer)
                        inline_data = part.get("inlineData")
                        if inline_data and inline_data.get("mimeType", "").startswith("audio/pcm"):
                            b64 = inline_data.get("data", "")
                            if b64:
                                gemini_fallback_audio.extend(base64.b64decode(b64))

                if server_content.get("turnComplete"):
                    # Process remaining text in buffer
                    if text_buffer:
                        remaining = "".join(text_buffer).strip()
                        text_buffer.clear()
                        if remaining:
                            self.active_turn_id += 1
                            asyncio.create_task(
                                self.synthesize_and_stream(remaining, ref_id, fish_model, self.active_turn_id, gemini_fallback_audio)
                            )
                    await self.client_ws.send(json.dumps({"type": "turn_complete"}))

        except Exception as e:
            if not self.closed:
                logger.warning(f"Downlink loop terminated: {e}")

    async def synthesize_and_stream(self, text: str, ref_id: str, model: str, turn_id: int, fallback_audio: bytearray):
        """Synthesizes text via Fish Audio with key-rotation, streaming binary PCM back to client, with <15ms fallback."""
        if not ref_id or not self.http_session:
            # Direct Gemini Native fallback
            if fallback_audio:
                pcm_bytes = bytes(fallback_audio)
                fallback_audio.clear()
                await self.client_ws.send(pcm_bytes)
            return

        t0 = time.perf_counter()
        key = FISH_POOL.get_key()
        max_retries = 2
        success = False

        for attempt in range(max_retries):
            if not key or self.closed or turn_id != self.active_turn_id:
                break

            payload = {
                "text": text,
                "reference_id": ref_id,
                "format": "pcm",
                "latency": "low"
            }
            headers = {
                "Authorization": f"Bearer {key}",
                "Content-Type": "application/json",
                "model": model,
                "User-Agent": "OnTrack-WSS-Relay/0.3.56"
            }

            try:
                async with self.http_session.post("https://api.fish.audio/v1/tts", json=payload, headers=headers) as resp:
                    if resp.status == 200:
                        # Stream chunks directly as binary frames to mobile
                        first_chunk = True
                        async for chunk in resp.content.iter_chunked(2048):
                            if self.closed or turn_id != self.active_turn_id:
                                break
                            if first_chunk:
                                ttfb = (time.perf_counter() - t0) * 1000
                                logger.info(f"Fish Audio TTFB: {ttfb:.1f}ms for '{text[:20]}...'")
                                first_chunk = False
                            await self.client_ws.send(chunk)
                        success = True
                        break

                    elif resp.status == 429:
                        FISH_POOL.mark_throttled(key, duration=60.0)
                        key = FISH_POOL.get_key()
                        logger.warning(f"Fish Audio 429; retrying with key {key[:8] if key else 'None'}...")
                        continue
                    else:
                        err_text = await resp.text()
                        logger.warning(f"Fish Audio error HTTP {resp.status}: {err_text[:100]}")
                        break
            except Exception as e:
                logger.warning(f"Fish Audio request error: {e}")
                break

        if not success and turn_id == self.active_turn_id:
            # Zero-latency instant fallback to Gemini Native Audio
            logger.info("Triggering instant fallback to Gemini Native Audio")
            if fallback_audio:
                pcm_bytes = bytes(fallback_audio)
                fallback_audio.clear()
                await self.client_ws.send(pcm_bytes)
                await self.client_ws.send(json.dumps({"type": "tts_fallback", "reason": "fish_unavailable"}))

    async def close(self):
        self.closed = True
        if self.http_session:
            await self.http_session.close()
        if self.gemini_ws:
            await self.gemini_ws.close()

async def ws_handler(websocket: ServerConnection):
    path = websocket.request.path
    parsed = urllib.parse.urlparse(path)
    params = urllib.parse.parse_qs(parsed.query)

    token = params.get("token", [""])[0]
    low_bitrate = params.get("low_bitrate", ["0"])[0] in ["1", "true"]

    if not token:
        # Wait for optional init JSON frame
        try:
            init_msg = await asyncio.wait_for(websocket.recv(), timeout=3.0)
            if isinstance(init_msg, str):
                data = json.loads(init_msg)
                token = data.get("token", "")
                if data.get("low_bitrate"):
                    low_bitrate = True
        except Exception:
            pass

    if not token:
        logger.warning(f"Rejecting connection without token from {websocket.remote_address}")
        await websocket.close(1008, "token_required")
        return

    logger.info(f"Accepted client connection: token={token[:12]}... low_bitrate={low_bitrate}")
    session = RelaySession(websocket, token, low_bitrate=low_bitrate)
    await session.run()

async def main():
    logger.info(f"Starting OnTrack Cloud WSS Audio Relay on {HOST}:{PORT}...")
    server = await websockets.serve(
        ws_handler,
        HOST,
        PORT,
        max_size=2 * 1024 * 1024,  # 2MB max frame
        ping_interval=20,
        ping_timeout=20
    )
    logger.info("Relay daemon is running 24/7.")
    await server.wait_closed()

if __name__ == "__main__":
    asyncio.run(main())
'''

    # Watchdog script
    watchdog_code = r'''#!/bin/bash
# OnTrack WSS Audio Relay Watchdog
# Checks every minute via crontab that relay.py is running; restarts in <1s if stopped.
RELAY_DIR="/home/appontrack/iovoice-app/ws-relay"
LOG_FILE="$RELAY_DIR/relay.log"

if ! pgrep -f "$RELAY_DIR/relay.py" > /dev/null 2>&1; then
    echo "[$(date -u)] [Watchdog] relay.py not running; starting daemon..." >> "$LOG_FILE"
    cd "$RELAY_DIR"
    nohup /usr/bin/python3 -u "$RELAY_DIR/relay.py" >> "$LOG_FILE" 2>&1 &
    sleep 1
    if pgrep -f "$RELAY_DIR/relay.py" > /dev/null 2>&1; then
        echo "[$(date -u)] [Watchdog] relay.py successfully started." >> "$LOG_FILE"
    else
        echo "[$(date -u)] [Watchdog] ERROR: relay.py failed to start." >> "$LOG_FILE"
    fi
fi
'''

    cmd = f"""#!/bin/bash
set -e
mkdir -p /home/appontrack/iovoice-app/ws-relay
cat << 'EOF' > /home/appontrack/iovoice-app/ws-relay/relay.py
{relay_code}
EOF
chmod +x /home/appontrack/iovoice-app/ws-relay/relay.py

cat << 'EOF' > /home/appontrack/iovoice-app/ws-relay/watchdog.sh
{watchdog_code}
EOF
chmod +x /home/appontrack/iovoice-app/ws-relay/watchdog.sh

# Stop any previous test servers
pkill -f test_ws || true
pkill -f "ws-relay/relay.py" || true

# Launch watchdog to start relay
/home/appontrack/iovoice-app/ws-relay/watchdog.sh

# Ensure crontab entry exists
if ! crontab -l | grep -q "ws-relay/watchdog.sh"; then
    (crontab -l 2>/dev/null; echo "* * * * * /home/appontrack/iovoice-app/ws-relay/watchdog.sh >/dev/null 2>&1 # ontrack-ws-relay-watchdog") | crontab -
fi

sleep 2
ps aux | grep "ws-relay"
tail -n 15 /home/appontrack/iovoice-app/ws-relay/relay.log
"""
    cmd_bytes = cmd.replace("\r\n", "\n").encode("utf-8")
    res = subprocess.run(
        ["ssh", "-p", "7195", "-i", str(key), "-o", "StrictHostKeyChecking=no", "appontrack@65.21.208.232", "bash -s"],
        input=cmd_bytes,
        capture_output=True
    )
    print("STDOUT:")
    print(res.stdout.decode("utf-8", errors="replace"))
    if res.stderr:
        print("STDERR:")
        print(res.stderr.decode("utf-8", errors="replace"))

if __name__ == "__main__":
    main()
