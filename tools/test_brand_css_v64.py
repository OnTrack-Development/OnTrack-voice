#!/usr/bin/env python3
"""Structural styles and upload contract, layered on v61-v63 regressions."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parent))
from test_theme_css_v63 import * # noqa: F403,F401 — executes v61-v63 contract
css=Path('saas/console/assets/css/app.css').read_text(encoding='utf-8-sig')
js=Path('saas/console/assets/js/app.js').read_text(encoding='utf-8-sig')
start='/* OTV_BRAND_LOGO_V64_BEGIN'
end='/* OTV_BRAND_LOGO_V64_END */'
assert css.count(start)==1 and css.count(end)==1,'brand logo CSS only once'
block=css[css.index(start):css.index(end)]
count=0
for ch in block:
    count+=(ch=='{')-(ch=='}')
    assert count>=0,'Unexpected CSS close bracket'
assert count==0,'Unbalanced brand CSS'
for item in [
   '.sidebar .brand[data-brand-display="both"]',
   '.sidebar .brand[data-brand-display="image"]',
   '.sidebar .brand[data-brand-display="text"]',
   '.login-panel .login-brand[data-brand-display="both"]',
   '.login-panel .login-brand[data-brand-display="text"]',
   '.ot-brand-preview[data-brand-display="image"]',
   'object-fit:contain!important',
   'border:0!important',
   'background:transparent!important',
   '@media (max-width:440px)',
   '[data-brand-upload-status]'
]:
    assert item in block,'Missing uncropped wide logo selector: '+item
for item in [
    "fetch('/saas/console/brand-upload'",
    "'X-CSRF-Token':csrf", "credentials:'same-origin'",
    "body:blob", "input.value=''", "getContext('2d')",
    "canvas.toBlob", "image/webp", "response.status===403",
]:
    assert item in js,'Missing multipart-free logo feature: '+item
print('BRAND_V64_WIDE_LIGHT_DARK_LOGO_RAW_UPLOAD_UI_PASS')
