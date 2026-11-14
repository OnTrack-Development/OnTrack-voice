#!/usr/bin/env python3
"""
Live 50-Call Concurrent API Stress Tester for OnTrack Voice Production Server.
Fires 50 simultaneous HTTP requests across synchronized threads on the exact same millisecond.
"""
import concurrent.futures
import json
import statistics
import threading
import time
import urllib.request
import urllib.error

TOKEN = 'otdev_bench_67812d875984dfc6773010bdc53980f17a653b98f82a76c1'
ENDPOINT = 'https://app.ontrackegy.com/saas/console/device/v1/index.php'
CONCURRENCY = 50

def run_single_request(barrier, request_id):
    payload = json.dumps({'operation': 'voice_session'}).encode('utf-8')
    req = urllib.request.Request(
        ENDPOINT,
        data=payload,
        headers={
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {TOKEN}',
            'User-Agent': f'OnTrack-StressTester/0.3.55 (Call #{request_id})',
            'Connection': 'keep-alive'
        }
    )

    # Wait at barrier for synchronized launch
    barrier.wait()
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            elapsed_ms = (time.perf_counter() - t0) * 1000
            headers = dict(resp.headers)
            body = resp.read()
            return {
                'id': request_id,
                'status': resp.status,
                'elapsed_ms': elapsed_ms,
                'success': True,
                'bytes': len(body),
                'auth_ms': float(headers.get('X-OnTrack-Auth-Ms', 0.0)),
                'vault_ms': float(headers.get('X-OnTrack-Vault-Ms', 0.0)),
                'internal_ms': float(headers.get('X-OnTrack-Internal-Ms', 0.0)),
                'error': None
            }
    except urllib.error.HTTPError as e:
        elapsed_ms = (time.perf_counter() - t0) * 1000
        return {
            'id': request_id,
            'status': e.code,
            'elapsed_ms': elapsed_ms,
            'success': False,
            'bytes': 0,
            'auth_ms': 0.0,
            'vault_ms': 0.0,
            'internal_ms': 0.0,
            'error': f'HTTP_{e.code}'
        }
    except Exception as e:
        elapsed_ms = (time.perf_counter() - t0) * 1000
        return {
            'id': request_id,
            'status': 0,
            'elapsed_ms': elapsed_ms,
            'success': False,
            'bytes': 0,
            'auth_ms': 0.0,
            'vault_ms': 0.0,
            'internal_ms': 0.0,
            'error': e.__class__.__name__
        }

def main():
    print(f"============================================================")
    print(f"🔥 STARTING 50-CALL CONCURRENT API STRESS TEST")
    print(f"Target: {ENDPOINT}")
    print(f"Concurrency: {CONCURRENCY} simultaneous connections")
    print(f"Launch synchronization: threading.Barrier({CONCURRENCY})")
    print(f"============================================================")

    barrier = threading.Barrier(CONCURRENCY)
    overall_start = time.perf_counter()

    with concurrent.futures.ThreadPoolExecutor(max_workers=CONCURRENCY) as executor:
        futures = [executor.submit(run_single_request, barrier, i + 1) for i in range(CONCURRENCY)]
        results = [f.result() for f in concurrent.futures.as_completed(futures)]

    overall_elapsed_sec = time.perf_counter() - overall_start

    durations = [r['elapsed_ms'] for r in results]
    successes = [r for r in results if r['success']]
    failures = [r for r in results if not r['success']]
    success_rate = (len(successes) / CONCURRENCY) * 100.0

    sorted_durations = sorted(durations)
    min_ms = sorted_durations[0]
    max_ms = sorted_durations[-1]
    avg_ms = statistics.mean(sorted_durations)
    p50_ms = statistics.median(sorted_durations)
    p95_ms = sorted_durations[int(len(sorted_durations) * 0.95)]
    p99_ms = sorted_durations[int(len(sorted_durations) * 0.99)]
    rps = CONCURRENCY / overall_elapsed_sec

    auth_times = [r['auth_ms'] for r in successes if r['auth_ms'] > 0]
    vault_times = [r['vault_ms'] for r in successes if r['vault_ms'] > 0]
    internal_times = [r['internal_ms'] for r in successes if r['internal_ms'] > 0]

    report = {
        'concurrency': CONCURRENCY,
        'total_requests': CONCURRENCY,
        'success_count': len(successes),
        'failure_count': len(failures),
        'success_rate_percent': round(success_rate, 2),
        'overall_elapsed_sec': round(overall_elapsed_sec, 3),
        'requests_per_second': round(rps, 2),
        'latency_ms': {
            'min': round(min_ms, 2),
            'avg': round(avg_ms, 2),
            'p50': round(p50_ms, 2),
            'p95': round(p95_ms, 2),
            'p99': round(p99_ms, 2),
            'max': round(max_ms, 2)
        },
        'server_telemetry_avg_ms': {
            'auth_ms': round(statistics.mean(auth_times), 2) if auth_times else 0.0,
            'vault_ms': round(statistics.mean(vault_times), 2) if vault_times else 0.0,
            'internal_ms': round(statistics.mean(internal_times), 2) if internal_times else 0.0
        },
        'failures_breakdown': {}
    }

    for f in failures:
        err = f['error'] or f"HTTP_{f['status']}"
        report['failures_breakdown'][err] = report['failures_breakdown'].get(err, 0) + 1

    print("\n================== 📊 OFFICIAL STRESS TEST REPORT ==================")
    print(json.dumps(report, indent=2, ensure_ascii=False))
    print("====================================================================\n")

    with open("C:/OnTrack-voice/stress_test_report_50_calls.json", "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)

if __name__ == '__main__':
    main()
