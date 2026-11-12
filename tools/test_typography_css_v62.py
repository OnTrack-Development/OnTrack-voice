#!/usr/bin/env python3
"""Static, dependency-free typography CSS release checks."""
from pathlib import Path
import sys
sys.path.insert(0, str(Path(__file__).resolve().parent))
from test_ui_precision_v61 import assert_css

def inspect_css(css: str) -> None:
    assert_css(css)
    if css.count("/* OTV_TYPO_V62_BEGIN") != 1 or css.count("/* OTV_TYPO_V62_END */") != 1:
        raise AssertionError("Typography extension must be present exactly once")
    top = css.splitlines()[:5]
    if not top[0].startswith('@charset "UTF-8"'):
        raise AssertionError("UTF-8 charset declaration must be first")
    if not top[1].startswith('@import url("https://fonts.googleapis.com/css2?'):
        raise AssertionError("Active stylesheet must import actual Arabic font files")
    if not all(x in top[1] for x in ("Cairo", "Tajawal", "IBM+Plex+Sans+Arabic", "display=swap")):
        raise AssertionError("Missing Cairo/Tajawal/Plex loading")
    block = css[css.index("/* OTV_TYPO_V62_BEGIN"):css.index("/* OTV_TYPO_V62_END */")]
    braces = 0
    for ch in block:
        braces += (ch == "{") - (ch == "}")
        if braces < 0:
            raise AssertionError("Unexpected closing CSS brace")
    if braces != 0:
        raise AssertionError("Unbalanced typography CSS braces")
    required = (
        "body[data-ot-font=\"plex\"]", "body[data-ot-font=\"cairo\"]",
        "body[data-ot-font=\"tajawal\"]", "--ot-arabic-font",
        "body :where(button,input,select,textarea",
        ".ot-font-options", ".ot-font-option[data-font-sample=\"plex\"]",
        ".ot-font-option[data-font-sample=\"cairo\"]",
        ".ot-font-option[data-font-sample=\"tajawal\"]",
        "font-family:var(--ot-arabic-font)!important",
        "@media(max-width:560px)", "line-height:1.72",
        "font-family:ui-monospace,Consolas,Monaco,monospace!important"
    )
    for key in required:
        if key not in block:
            raise AssertionError("Typography CSS missing " + key)
    import re
    without_comments = re.sub(r"/\\*.*?\\*/", "", block, flags=re.S)
    for family in re.findall(r"font-family\\s*:[^;]*;", without_comments, flags=re.I):
        if any(re.search(r"\\b" + name + r"\\b", family, flags=re.I)
               for name in ("Droid Sans Arabic", "Tahoma", "Arial")):
            raise AssertionError("New font-family rules depend on a rejected legacy font")
    print("TYPO_V62_ARABIC_FONTS_RTL_PREVIEW_RESPONSIVE_AND_TYPE_SCALE_PASS")

if __name__ == "__main__":
    path = Path(sys.argv[1] if len(sys.argv)>1 else "saas/console/assets/css/app.css")
    inspect_css(path.read_text(encoding="utf-8-sig"))
