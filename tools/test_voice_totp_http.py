#!/usr/bin/env python3
"""Real local HTTP test: password -> TOTP setup -> recovery -> challenge -> account."""
import base64
import http.cookiejar
import html
import hmac
import hashlib
import os
from pathlib import Path
import re
import socket
import struct
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
if os.environ.get("GITHUB_ACTIONS") != "true" or Path("/home/appontrack").exists():
    raise SystemExit("TOTP HTTP fixture is CI-only and must never modify production accounts")
PASSWORD = "CI-TOTP-Only-Not-A-Real-Password-92!"
fixture = r'''
if(getenv("GITHUB_ACTIONS")!=="true" || is_dir("/home/appontrack"))exit(17);
require "saas/console/database/Seed.php";
seed_console();
$u=one("SELECT id FROM users WHERE email=?",["admin@ontrackegy.com"]);
execute("DELETE FROM user_totp WHERE user_id=?",[$u["id"]]);
execute("UPDATE users SET password_hash=? WHERE id=?",[hash_password($argv[1]),$u["id"]]);
'''
subprocess.run(["php", "-r", fixture, PASSWORD], cwd=ROOT, check=True)

with socket.socket() as sock:
    sock.bind(("127.0.0.1", 0))
    port = sock.getsockname()[1]
base = f"http://127.0.0.1:{port}/saas/console"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        return None

def client():
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def fetch(opener, path, data=None):
    url = base + path
    request = urllib.request.Request(url)
    if data is not None:
        request.data = urllib.parse.urlencode(data).encode("utf-8")
        request.add_header("Content-Type", "application/x-www-form-urlencoded")
    try:
        with opener.open(request, timeout=6) as response:
            return response.status, response.headers, response.read().decode("utf-8")
    except urllib.error.HTTPError as response:
        return response.code, response.headers, response.read().decode("utf-8")

def csrf(body):
    match = re.search(r'name="csrf" value="([a-f0-9]{64})"', body)
    assert match, "CSRF missing"
    return html.unescape(match.group(1))

def otp(secret, at=None):
    at = int(time.time() if at is None else at)
    raw = base64.b32decode(secret)
    digest = hmac.new(raw, struct.pack(">Q", at // 30), hashlib.sha1).digest()
    offset = digest[-1] & 15
    number = struct.unpack(">I", digest[offset:offset+4])[0] & 0x7fffffff
    return f"{number % 1000000:06d}"

def login(opener):
    status, _, body = fetch(opener, "/login")
    assert status == 200
    status, headers, _ = fetch(opener, "/login", {
        "csrf": csrf(body), "email": "admin@ontrackegy.com", "password": PASSWORD})
    assert status == 303 and headers.get("Location", "").endswith("/security/2fa"), (status, headers)
    status, _, body = fetch(opener, "/security/2fa")
    assert status == 200
    return body

server = subprocess.Popen(
    ["php", "-S", f"127.0.0.1:{port}", "-t", str(ROOT),
     str(ROOT / "tools/totp_http_router.php")],
    cwd=ROOT, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE,
    env={**os.environ, "GITHUB_ACTIONS": "true"})
try:
    for _ in range(60):
        try:
            status, _, _ = fetch(client(), "/login")
            if status == 200:
                break
        except (OSError, urllib.error.URLError):
            time.sleep(.1)
    else:
        raise RuntimeError("PHP test server did not start")
    first = client()
    setup = login(first)
    assert "تفعيل التحقق بخطوتين" in setup
    match = re.search(r'<code dir="ltr"[^>]*>([A-Z2-7]{32})</code>', setup)
    assert match, "secret not shown to password-verified setup only"
    secret = match.group(1)
    status, headers, _ = fetch(first, "/?tab=devices")
    assert status == 303 and headers.get("Location", "").endswith("/login"), "pending login accessed dashboard"
    status, _, _ = fetch(first, "/security/2fa", {"csrf": "0"*64, "code": otp(secret)})
    assert status == 419, "CSRF was not enforced"
    status, headers, _ = fetch(first, "/security/2fa", {"csrf": csrf(setup), "code": otp(secret)})
    assert status == 303 and headers.get("Location", "").endswith("/security/recovery")
    status, _, recovery = fetch(first, "/security/recovery")
    codes = re.findall(r'<code style="display:block;user-select:all">([A-F0-9]{24})</code>', recovery)
    assert status == 200 and len(codes) == 8, "one-time recovery codes missing"
    status, headers, _ = fetch(first, "/security/recovery")
    assert status == 303, "recovery codes displayed more than once"
    status, _, dashboard = fetch(first, "/?tab=settings")
    assert status == 200 and "التحقق بخطوتين مفعّل" in dashboard

    second = client()
    challenge = login(second)
    assert "تأكيد تسجيل الدخول" in challenge and secret not in challenge
    status, headers, _ = fetch(second, "/?tab=devices")
    assert status == 303, "password-only admin bypassed enabled TOTP"
    status, headers, _ = fetch(second, "/security/2fa", {"csrf": csrf(challenge), "code": codes[0]})
    assert status == 303 and headers.get("Location", "").endswith("/saas/console"), "recovery challenge failed"
    status, _, _ = fetch(second, "/?tab=settings")
    assert status == 200, "second-factor authenticated admin cannot access settings"

    third = client()
    challenge = login(third)
    status, _, body = fetch(third, "/security/2fa", {"csrf": csrf(challenge), "code": codes[0]})
    assert status == 200 and "تعذر التحقق" in body, "replayed recovery code accepted"
    status, headers, _ = fetch(third, "/?tab=devices")
    assert status == 303, "failed challenge accessed account"
    print("VOICE_TOTP_HTTP_PASS: password, setup, CSRF, recovery, replay, gated dashboard")
finally:
    server.terminate()
    try:
        server.communicate(timeout=4)
    except subprocess.TimeoutExpired:
        server.kill()
        server.communicate()
