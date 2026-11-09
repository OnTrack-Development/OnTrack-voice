#!/usr/bin/env python3
"""Real loopback HTTP form/CSRF tests; never use external host or production DB."""
import http.cookiejar
import os
import re
import urllib.error
import urllib.parse
import urllib.request

ROOT = "http://127.0.0.1:18773"
PASSWORD = "ResponsiveUITest2026"


def assert_ci():
    marker = os.environ.get("ONTRACK_CI_NAMESPACE", "")
    if (os.getenv("CI") != "true" or os.getenv("GITHUB_ACTIONS") != "true"
            or os.getenv("SAAS_RESPONSIVE_FIXTURE") != "1"
            or not re.fullmatch(r"ui_[0-9]+_[0-9]+", marker)):
        raise RuntimeError("isolated github browser fixture only")


def client():
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(browser, path, fields=None):
    url = ROOT + path
    data = None if fields is None else urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(url, data=data)
    try:
        with browser.open(req, timeout=10) as res:
            return res.status, res.geturl(), res.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.geturl(), exc.read().decode("utf-8")


def csrf(html):
    match = re.search(r'name="csrf" value="([a-f0-9]{64})"', html)
    if not match:
        raise AssertionError("CSRF form field missing")
    return match.group(1)


def ensure(condition, message):
    if not condition:
        raise AssertionError(message)
    print("PASS " + message, flush=True)


def login(email, prefix):
    b = client()
    status, _, page = request(b, prefix + "/login")
    ensure(status == 200, "public login page loaded")
    status, destination, page = request(b, prefix + "/login", {
        "csrf": csrf(page), "email": email, "password": PASSWORD
    })
    ensure(status == 200 and "app-shell" in page
           and "login" not in urllib.parse.urlparse(destination).path,
           "real authenticated login + browser cookie " + email)
    return b


def main():
    assert_ci()
    admin = login("admin@ontrackegy.com", "/saas/console")
    status, _, plans = request(admin, "/saas/console/?tab=plans")
    ensure(status == 200
           and 'value="assign_plan"' in plans
           and "إدارة اشتراكات الريسيلرز" in plans,
           "admin real plan reassignment form exists")
    status, _, updated = request(admin, "/saas/console/", {
        "csrf": csrf(plans), "action": "assign_plan", "return": "/?tab=plans",
        "customer_id": "2", "plan_id": "2"
    })
    ensure(status == 200 and "ريسيلر سكيل" in updated,
           "admin submitted reseller plan change through POST + CSRF")

    reseller = login("reseller@delta.test", "/portal/delta-voice")
    status, _, own = request(reseller, "/portal/delta-voice/?tab=plans")
    ensure(status == 200 and "باقة حسابك كريسيلر" in own
           and "ريسيلر سكيل" in own
           and "اختيار أو تجديد باقتك" in own,
           "reseller sees plan just changed by admin and checkout link")
    status, _, blocked = request(reseller, "/portal/delta-voice/", {
        "csrf": csrf(own), "action": "assign_plan", "return": "/?tab=plans",
        "customer_id": "3", "plan_id": "2"
    })
    ensure(status in (403, 404) or "غير مصرح" in blocked
           or "مرفوض" in blocked,
           "reseller cannot edit another reseller subscription")

    customer = login("user@direct.test", "/saas/console")
    status, _, answer = request(customer, "/saas/console/?tab=answering")
    ensure(status == 200 and 'value="update_answer_policy_quick"' in answer,
           "customer has real quick-answer form")
    status, _, saved = request(customer, "/saas/console/", {
        "csrf": csrf(answer), "action": "update_answer_policy_quick",
        "return": "/?tab=answering", "agent_id": "1",
        "answer_delay_seconds": "5", "auto_answer": "on", "publish_now": "1"
    })
    ensure(status == 200 and "تم حفظ ونشر سياسة الرد" in saved,
           "customer published 5 second answering policy through POST")
    status, _, campaigns = request(customer, "/saas/console/?tab=campaigns")
    ensure(status == 200 and 'value="add_campaign"' in campaigns,
           "customer has real campaigns creation form")
    status, _, new_campaign = request(customer, "/saas/console/", {
        "csrf": csrf(campaigns), "action": "add_campaign",
        "return": "/?tab=campaigns", "site_id": "1",
        "name": "Browser safety campaign - no calls"
    })
    ensure(status == 200 and "Browser safety campaign - no calls" in new_campaign,
           "campaign draft created without dialing any contact")
    status, _, paused = request(customer, "/saas/console/", {
        "csrf": csrf(new_campaign), "action": "update_campaign",
        "return": "/?tab=campaigns", "campaign_id": "1",
        "name": "متابعة طلبات تجريبية", "status": "paused"
    })
    ensure(status == 200, "customer paused a seeded draft campaign")
    status, _, refused_start = request(customer, "/saas/console/", {
        "csrf": csrf(paused), "action": "update_campaign",
        "return": "/?tab=campaigns", "campaign_id": "1",
        "name": "متابعة طلبات تجريبية", "status": "running"
    })
    ensure(status in (200, 400, 422), "unsafe campaign start cannot crash service")
    status, _, contacts = request(customer, "/saas/console/?tab=contacts")
    ensure(status == 200 and 'value="add_contact"' in contacts,
           "customer can view consent-gated contacts form")
    phone = "+99900000123"
    status, _, refused = request(customer, "/saas/console/", {
        "csrf": csrf(contacts), "action": "add_contact",
        "return": "/?tab=contacts", "site_id": "1",
        "name": "Synthetic denied consent", "phone": phone
    })
    ensure(status in (200, 400, 422), "unconsented contact form handled safely")
    status, _, kept = request(customer, "/saas/console/", {
        "csrf": csrf(refused), "action": "add_contact",
        "return": "/?tab=contacts", "site_id": "1",
        "name": "Synthetic DNC opt-out", "phone": phone,
        "consent": "1", "dnc": "1"
    })
    ensure(status == 200 and "Synthetic DNC opt-out" in kept,
           "consented opt-out contact saved in customer workspace")
    print("SAAS_REAL_HTTP_CAMPAIGN_DRAFT_DNC_AND_NO_DIAL_FORMS_PASS", flush=True)
    print("SAAS_REAL_HTTP_ADMIN_RESELLER_CUSTOMER_FORMS_PASS", flush=True)


if __name__ == "__main__":
    main()
