import urllib.request
import urllib.parse
import ssl
import json
import time

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print("=== 1. Testing Public Endpoints ===")
tests = [
    ("Live Web Call Page", "https://app.ontrackegy.com/aivoiceagent/1?share=live", "GET", None, None),
    ("WHMCS Addon Download", "https://app.ontrackegy.com/downloads/ontrack_bridge.zip", "GET", None, None),
    ("Zoie Widget Script", "https://app.ontrackegy.com/widget/ontrack-widget.js", "GET", None, None),
    ("Dashboard Login/Home", "https://app.ontrackegy.com/?tab=overview", "GET", None, None),
]

for name, url, method, headers, data in tests:
    req = urllib.request.Request(url, method=method, headers=headers or {"User-Agent": "Mozilla/5.0"})
    try:
        with urllib.request.urlopen(req, timeout=10, context=ctx) as r:
            body = r.read()
            print(f"  [PASS] {name}: HTTP {r.status} OK | Size: {len(body)} bytes")
    except Exception as e:
        print(f"  [FAIL] {name}: {e}")

print("\n=== 2. Testing Authenticated Bridge APIs ===")
now_ts = str(int(time.time()))

# Test authenticated status
req_status = urllib.request.Request("https://app.ontrackegy.com/api/v1/bridge/status", headers={
    "User-Agent": "Mozilla/5.0",
    "X-Ontrack-Key": "1",
    "X-Ontrack-Timestamp": now_ts,
})
try:
    with urllib.request.urlopen(req_status, timeout=10, context=ctx) as r:
        res = json.loads(r.read().decode("utf-8"))
        conn = res.get("connected")
        pname = res.get("plan_name")
        print(f"  [PASS] /api/v1/bridge/status: connected={conn} | plan={pname}")
except Exception as e:
    print(f"  [FAIL] /api/v1/bridge/status: {e}")

# Test widget interactive reply
widget_payload = json.dumps({"message": "عايز أعرف تفاصيل باقات الاستضافة والسيرفرات"}).encode("utf-8")
req_widget = urllib.request.Request("https://app.ontrackegy.com/api/v1/bridge/widget", data=widget_payload, headers={
    "User-Agent": "Mozilla/5.0",
    "Content-Type": "application/json",
    "X-Ontrack-Key": "1",
    "X-Ontrack-Timestamp": now_ts,
})
try:
    with urllib.request.urlopen(req_widget, timeout=10, context=ctx) as r:
        res = json.loads(r.read().decode("utf-8"))
        ok = res.get("ok")
        btn_count = len(res.get("buttons", []))
        reply = res.get("reply", "")[:60]
        print(f"  [PASS] /api/v1/bridge/widget: ok={ok} | buttons={btn_count}")
        print(f"         reply: {reply}...")
except Exception as e:
    print(f"  [FAIL] /api/v1/bridge/widget: {e}")

# Test ticket event with zero_retention_mode
ticket_payload = json.dumps({
    "event_type": "ticket.opened",
    "ticket_id": "WHMCS-LIVE-901",
    "client_id": "77",
    "subject": "استفسار عن خطة الاستضافة",
    "message": "أريد معرفة هل تدعمون لغة Node.js وقواعد بيانات MySQL؟",
    "zero_retention_mode": True
}).encode("utf-8")

req_event = urllib.request.Request("https://app.ontrackegy.com/api/v1/bridge/events", data=ticket_payload, headers={
    "User-Agent": "Mozilla/5.0",
    "Content-Type": "application/json",
    "X-Ontrack-Key": "1",
    "X-Ontrack-Timestamp": now_ts,
})
try:
    with urllib.request.urlopen(req_event, timeout=10, context=ctx) as r:
        res = json.loads(r.read().decode("utf-8"))
        ok = res.get("ok")
        ev = res.get("event")
        orch = res.get("orchestration", {})
        tid = orch.get("ticket_id")
        verdict = orch.get("reviewer_verdict")
        print(f"  [PASS] /api/v1/bridge/events (Zero Retention): ok={ok} | event={ev}")
        print(f"         ticket_id: {tid} | verdict: {verdict}")
except Exception as e:
    print(f"  [FAIL] /api/v1/bridge/events: {e}")
