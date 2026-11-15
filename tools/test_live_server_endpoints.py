#!/usr/bin/env python3
import urllib.request
import urllib.error
import json
import time

def test_endpoint():
    url = 'https://app.ontrackegy.com/saas/console/device/v1/index.php'
    payload = json.dumps({"operation": "heartbeat"}).encode('utf-8')
    req = urllib.request.Request(
        url,
        data=payload,
        headers={'Content-Type': 'application/json'}
    )
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(req) as resp:
            elapsed = (time.perf_counter() - t0) * 1000
            print(f"Status: {resp.status}, Elapsed: {elapsed:.2f}ms, Body: {resp.read().decode()}")
    except urllib.error.HTTPError as e:
        elapsed = (time.perf_counter() - t0) * 1000
        headers_dict = dict(e.headers)
        print(f"HTTP {e.code}, Elapsed: {elapsed:.2f}ms, Body: {e.read().decode()[:200]}")
        print("Headers:", {k: v for k, v in headers_dict.items() if 'ontrack' in k.lower() or 'server' in k.lower()})

if __name__ == '__main__':
    test_endpoint()
