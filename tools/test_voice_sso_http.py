#!/usr/bin/env python3
"""Local HTTP/browser-cookie SSO regression, no real WHMCS or external network."""
import base64
import hashlib
import hmac
import http.cookiejar
import json
import os
import re
import secrets
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request


def check(ok, label):
    if not ok:
        raise AssertionError(label)
    print("PASS " + label, flush=True)


def csrf(html):
    found = re.search(r'name="csrf" value="([a-f0-9]{64})"', html)
    if not found:
        raise AssertionError("missing csrf")
    return found.group(1)


def ticket(integration_id, secret, subject):
    now = int(time.time())
    claims = {
        "v": 1, "integration_id": integration_id, "iss": "whmcs",
        "aud": "ontrack-voice-console", "sub": subject,
        "jti": secrets.token_hex(16), "nonce": secrets.token_hex(16),
        "iat": now, "exp": now + 120,
    }
    body = base64.urlsafe_b64encode(json.dumps(claims, separators=(",", ":")).encode()).rstrip(b"=")
    mac = hmac.new(secret.encode(), body, hashlib.sha256).hexdigest()
    return body.decode() + "." + mac


def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(opener, url, fields=None):
    data = None if fields is None else urllib.parse.urlencode(fields).encode()
    try:
        with opener.open(urllib.request.Request(url, data=data), timeout=8) as r:
            return r.status, r.geturl(), r.read().decode("utf-8")
    except urllib.error.HTTPError as e:
        return e.code, e.geturl(), e.read().decode("utf-8")


def main():
    password = secrets.token_urlsafe(30)
    secret = "otint_" + secrets.token_hex(32)
    env = dict(os.environ, ONTRACK_TEST_MODE="1", SSO_TEST_PASSWORD=password, SSO_TEST_SECRET=secret)
    fixture = subprocess.check_output(["php", "tools/sso_http_fixture.php"], env=env, text=True)
    f = json.loads(fixture)
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    base = f"http://127.0.0.1:{port}/saas/console"
    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", "saas/console", "saas/console/index.php"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env,
    )
    try:
        for _ in range(40):
            if server.poll() is not None:
                raise RuntimeError("PHP server stopped")
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.2):
                    break
            except OSError:
                time.sleep(0.1)
        else:
            raise RuntimeError("PHP server startup timeout")

        first = browser()
        first_ticket = ticket(f["integration_id"], secret, "http-customer-001")
        status, url, page = request(first, base + "/sso", {"ticket": first_ticket})
        check(status == 200 and url.endswith("/login") and "تسجيل الدخول" in page,
              "new signed subject redirects to local login without provisioning")
        status, url, page = request(first, base + "/login", {
            "csrf": csrf(page), "email": f["email"], "password": password
        })
        check(status == 200 and url.endswith("/sso/confirm") and "ربط هويتي" in page,
              "verified local login returns to explicit SSO consent")
        status, url, page = request(first, base + "/sso/confirm", {"csrf": csrf(page)})
        check(status == 200 and url.rstrip("/").endswith("/saas/console") and "OnTrack" in page,
              "consent links immutable external subject and opens tenant dashboard")

        replay = browser()
        status, _, _ = request(replay, base + "/sso", {"ticket": first_ticket})
        check(status == 400, "HTTP replay of used assertion rejected")

        repeat = browser()
        second_ticket = ticket(f["integration_id"], secret, "http-customer-001")
        status, url, page = request(repeat, base + "/sso", {"ticket": second_ticket})
        check(status == 200 and url.endswith("/sso/confirm") and "الدخول بالحساب المرتبط" in page,
              "linked external subject reaches SSO confirmation without local password")
        status, _, _ = request(repeat, base + "/sso/confirm", {"csrf": "0" * 64})
        check(status == 419, "CSRF-protected SSO confirmation rejects forged form")
        status, url, page = request(repeat, base + "/sso/confirm", {"csrf": csrf(page)})
        check(status == 200 and url.rstrip("/").endswith("/saas/console") and "OnTrack" in page,
              "fresh signed assertion authenticates mapped identity once")

        other = browser()
        cross_ticket = ticket(f["integration_id"], secret, "http-customer-002")
        status, url, page = request(other, base + "/sso", {"ticket": cross_ticket})
        check(status == 200 and url.endswith("/login"), "unlinked foreign tenant requires login")
        status, url, page = request(other, base + "/login", {
            "csrf": csrf(page), "email": f["other_email"], "password": password
        })
        check(status == 200 and url.endswith("/sso/confirm"), "foreign tenant login does not bypass consent")
        status, _, _ = request(other, base + "/sso/confirm", {"csrf": csrf(page)})
        check(status == 403, "cross-reseller account linking denied in HTTP flow")

        invalid = browser()
        forged = ticket(f["integration_id"], "otint_" + secrets.token_hex(32), "http-customer-003")
        status, _, _ = request(invalid, base + "/sso", {"ticket": forged})
        check(status == 400, "HTTP forged signature rejected")
        print("VOICE_SSO_HTTP_PASS")
    finally:
        server.terminate()
        try:
            server.wait(timeout=3)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=3)


if __name__ == "__main__":
    main()
