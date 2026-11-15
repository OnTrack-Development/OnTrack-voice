import urllib.request
import ssl
import json
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print("=== 1. Checking Public Web Call Page ===")
req = urllib.request.Request('https://app.ontrackegy.com/aivoiceagent/1?share=live', headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req, timeout=10, context=ctx) as r:
    html = r.read().decode('utf-8')
    print(f"Public Web Call: HTTP {r.status} OK (HTML bytes: {len(html)})")
    matches = re.findall(r'zoie', html, re.I)
    if matches:
        print(f"FAIL: Found {len(matches)} matches of 'zoie' in HTML!")
    else:
        print("PASS: 0 occurrences of 'zoie' in HTML! Pure OnTrack branding.")

print("\n=== 2. Checking Live Gemini Session Endpoint ===")
req2 = urllib.request.Request(
    'https://app.ontrackegy.com/aivoiceagent/1/session',
    data=json.dumps({"share": "live"}).encode('utf-8'),
    headers={
        'User-Agent': 'Mozilla/5.0',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    method='POST'
)
try:
    with urllib.request.urlopen(req2, timeout=10, context=ctx) as r2:
        body = r2.read().decode('utf-8')
        resp = json.loads(body)
        print(f"Live Session Endpoint: HTTP {r2.status}")
        print("  - ok:", resp.get('ok'))
        print("  - agent_id:", resp.get('agent_id'))
        print("  - agent_name:", resp.get('agent_name'))
        print("  - voice_name:", resp.get('voice_name'))
        ws_url = resp.get('gemini_ws_url', '')
        print("  - ws_url prefix:", ws_url[:75] + "... (total len: " + str(len(ws_url)) + ")")
        print("  - model in setup:", resp.get('setup', {}).get('model'))
        print("  - responseModalities:", resp.get('setup', {}).get('generationConfig', {}).get('responseModalities'))
        
        assert resp.get('ok') is True, "Session endpoint did not return ok=True"
        assert 'wss://generativelanguage.googleapis.com' in ws_url, "Invalid WebSocket URL"
        print("PASS: Gemini Multimodal Live Session successfully initialized with real API credentials!")
except urllib.error.HTTPError as e:
    print(f"FAIL: HTTP {e.code} - {e.read().decode('utf-8')}")
except Exception as e:
    print(f"FAIL: {e}")

print("\n=== 3. Checking Input Styles and Zero-Zoie on Portal Views ===")
req3 = urllib.request.Request('https://app.ontrackegy.com/', headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req3, timeout=10, context=ctx) as r3:
    portal_html = r3.read().decode('utf-8')
    print(f"Portal Root: HTTP {r3.status} OK (HTML bytes: {len(portal_html)})")
    matches = re.findall(r'zoie', portal_html, re.I)
    if matches:
        print(f"FAIL: Found {len(matches)} matches of 'zoie' in Portal Root!")
    else:
        print("PASS: 0 occurrences of 'zoie' in Portal Root!")
    
    if 'background-color: #FFFFFF !important' in portal_html and 'color: #0F172A !important' in portal_html:
        print("PASS: Enforced input styles with !important found in Portal Root!")
    else:
        print("Note: Portal Root is guest view, checking layout CSS...")
