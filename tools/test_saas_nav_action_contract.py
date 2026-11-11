#!/usr/bin/env python3
"""SaaS-only route/action contract — detect dead controls without a browser.
The authenticated HTTP+Chromium matrix separately proves real interactions.
"""
import re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
view=(ROOT/"saas/console/_core/Views.php").read_text(encoding="utf-8")
actions=(ROOT/"saas/console/_core/Actions.php").read_text(encoding="utf-8")
router=(ROOT/"saas/console/index.php").read_text(encoding="utf-8")
forms=set(re.findall(r"form_start\(\s*['\"]([a-zA-Z_][a-zA-Z0-9_]*)['\"]",view))
handled=set(re.findall(r"case\s+['\"]([A-Za-z0-9_-]+)['\"]\s*:",actions))
# Logout is a deliberate special-case handled by index.php before act().
assert "($_POST['action']??'')==='logout'" in router,"logout special-case route absent"
assert forms-(handled|{"logout"})==set(),f"dead form action(s): {sorted(forms-handled-{'logout'})}"
tabs=set(re.findall(
    r"['\"]([a-z_]+)['\"]\s*=>\s*\[\s*['\"][^'\"]{1,6}['\"]\s*,",view))
rendered=set(re.findall(r"case\s+['\"]([a-z_]+)['\"]\s*:",router))
assert tabs-(rendered|{"overview"})==set(),f"unhandled nav route(s): {sorted(tabs-rendered-{'overview'})}"
assert "if(!array_key_exists($tab,nav_entries($a['kind'])))deny(404" in router, \
    "invalid/foreign tab route must fail with explicit 404"
assert "http_response_code(503);" in router and "saas_render_failure incident=" in router, \
    "render errors must get incident ref and 503"
assert "'.e($e->getMessage()).'</p></section>" not in router, \
    "raw untrusted render exceptions must not be exposed"
print(f"SAAS_NAV_AND_ACTION_CONTRACT_PASS tabs={len(tabs)} forms={len(forms)} handlers={len(handled)}")
