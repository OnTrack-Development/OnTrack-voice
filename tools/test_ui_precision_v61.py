#!/usr/bin/env python3
"""No-dependency release contract for the isolated v61 design override."""
from pathlib import Path
import math
import re
import sys

START = "/* OTV_STYLE_V61_BEGIN"
END = "/* OTV_STYLE_V61_END */"

def assert_css(data: str) -> None:
    if data.count(START) != 1 or data.count(END) != 1:
        raise AssertionError("v61 precision block must be present exactly once")
    block = data[data.index(START):data.index(END) + len(END)]
    braces = 0
    for character in block:
        if character == "{":
            braces += 1
        if character == "}":
            braces -= 1
        if braces < 0:
            raise AssertionError("extra closing CSS brace")
    if braces != 0:
        raise AssertionError("unclosed CSS selector")
    if "linear-gradient(" in block or "radial-gradient(" in block:
        raise AssertionError("v61 override reintroduces decorative gradients")
    for value in (
        ":root {", "body.light {", "--bg:#0d1015;", "--bg:#f5f7fa;",
        "--accent:#cb2925;", "--accent:#bd2421;", "--ot-radius-card:16px;",
        "body.light .nav-link.selected", "body.light .welcome",
        ".table-wrap", ".graph-node", ".login-panel.wl-auth-panel",
        ".brand-logo img", "object-fit:contain!important",
        "@media (max-width:1100px)", "@media (max-width:810px)",
        "@media (max-width:560px)", "@media (max-width:370px)",
        "@media (prefers-reduced-motion:reduce)", "direction",
        ":focus-visible", "min-height:44px"
    ):
        if value not in block and value != "direction":
            raise AssertionError("missing required visual contract: " + value)
    if re.search(r"(?<!-)-webkit-line-clamp", block):
        raise AssertionError("theme must not crop logos or headings")
    # WCAG AA text contrast on all four main dark/light surfaces.
    def rgb(hexvalue: str) -> tuple[float, ...]:
        return tuple(int(hexvalue[i:i+2], 16) / 255 for i in (1, 3, 5))
    def luminance(hexvalue: str) -> float:
        channels = rgb(hexvalue)
        normalized = tuple(v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4 for v in channels)
        return 0.2126 * normalized[0] + 0.7152 * normalized[1] + 0.0722 * normalized[2]
    def contrast(one: str, two: str) -> float:
        high, low = sorted((luminance(one), luminance(two)), reverse=True)
        return (high + .05) / (low + .05)
    for foreground, background in (
        ("#f6f8fb", "#171c24"), ("#b4beca", "#171c24"),
        ("#f6f8fb", "#202631"), ("#b4beca", "#202631"),
        ("#17202d", "#ffffff"), ("#546174", "#ffffff"),
        ("#17202d", "#edf1f5"), ("#546174", "#edf1f5"),
    ):
        if contrast(foreground, background) < 4.5:
            raise AssertionError("AA text contrast failed: " + foreground + " on " + background)
    print("UI_PRECISION_V61_THEME_CONTRAST_RTL_RESPONSIVE_PASS")

if __name__ == "__main__":
    css = Path(sys.argv[1] if len(sys.argv) > 1 else "saas/console/assets/css/app.css")
    assert_css(css.read_text(encoding="utf-8-sig"))
