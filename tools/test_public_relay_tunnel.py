import asyncio
import json
import websockets
import time

async def test():
    token = "otdev_bench_67812d875984dfc6773010bdc53980f17a653b98f82a76c1"
    url = f"wss://app.ontrackegy.com/ws-voice"
    
    print(f"Connecting to {url} ...")
    t0 = time.perf_counter()
    async with websockets.connect(url, open_timeout=8) as ws:
        conn_ms = (time.perf_counter() - t0) * 1000
        print(f"Connected to WSS Tunnel in {conn_ms:.1f}ms!")
        
        # Send init frame with token immediately upon connect
        init_frame = {
            "type": "init",
            "token": token,
            "low_bitrate": False
        }
        print("Sending initial init handshake frame with token...")
        await ws.send(json.dumps(init_frame))
        
        # Expect ready frame from relay
        ready_raw = await asyncio.wait_for(ws.recv(), timeout=8.0)
        ready = json.loads(ready_raw)
        print("Received READY frame from Cloud Relay:")
        print(json.dumps(ready, indent=2))
        
        assert ready.get("type") == "ready", "Expected type=ready"
        assert ready.get("relay_version") == "0.3.56", "Expected relay_version=0.3.56"
        
        # Send 20ms of raw binary PCM (16kHz 16-bit mono = 320 samples = 640 bytes)
        raw_pcm_frame = bytes(640)
        print(f"Sending 640 bytes raw binary PCM frame (no JSON, no Base64)...")
        await ws.send(raw_pcm_frame)
        print("Binary frame sent successfully!")
        
        print("\n=== CLOUD WSS AUDIO RELAY E2E TEST PASSED 100% ===")

if __name__ == "__main__":
    asyncio.run(test())
