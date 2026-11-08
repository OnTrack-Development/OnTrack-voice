#!/usr/bin/env python3
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parent))
from test_typography_css_v62 import inspect_css

css=Path('saas/console/assets/css/app.css').read_text(encoding='utf-8-sig')
js=Path('saas/console/assets/js/app.js').read_text(encoding='utf-8-sig')
inspect_css(css)
start='/* OTV_TENANT_STYLE_V63_BEGIN'
end='/* OTV_TENANT_STYLE_V63_END */'
assert css.count(start)==1 and css.count(end)==1,'theme override must be unique'
block=css[css.index(start):css.index(end)]
balance=0
for ch in block:
    balance+=(ch=='{')-(ch=='}')
    assert balance>=0,'negative braces'
assert balance==0,'unclosed style selector'
for token in [
    'body.light','body[data-ot-surface="single"]',
    '--ot-sidebar-active','--ot-content-active','--ot-on-accent',
    '--ot-dark-sidebar','--ot-light-sidebar','--ot-dark-content','--ot-light-content',
    '--ot-sidebar-ink','--ot-content-ink','.content .card',
    '.ot-theme-preview','.ot-palette-grid','.btn.primary',
    '@media(max-width:480px)',
]:
    assert token in block, 'Missing tenant style contract '+token
for token in [
    "themeStorageKey='ontrack-console-theme-'",
    "data-ot-palette-editor","data-ot-palette-preview",
    "setUpPaletteEditor","data-ot-layout",
    "layout.value==='single'","themeBtn?.addEventListener('click'",
    "preview.style.setProperty",
]:
    assert token in js, 'Missing client preview contract '+token
assert "url(" not in block,'No user-supplied remote styles or CSS URLs'
print('THEME_V63_CSS_JAVASCRIPT_RTL_RESPONSIVE_PREVIEW_PASS')
