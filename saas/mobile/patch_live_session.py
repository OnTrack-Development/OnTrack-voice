"""Narrow opt-in adapter; preserve normal browser requests and existing key/KB."""
from pathlib import Path


def patch(source: str) -> str:
    if 'ontrackMobileRequest' in source:
        return source
    before = "parse_live_request((string)file_get_contents('php://input', false, null, 0, 32769))"
    after = "parse_live_request(isset($ontrackMobileRequest) ? json_encode($ontrackMobileRequest, JSON_THROW_ON_ERROR) : (string)file_get_contents('php://input', false, null, 0, 32769))"
    if source.count(before) != 1:
        raise ValueError('Live-session request anchor changed; inspect before patching')
    source = source.replace(before, after, 1)
    before = "$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));"
    if source.count(before) != 1:
        raise ValueError('Live-session instruction anchor changed; inspect before patching')
    return source.replace(before,
        "if (isset($ontrackMobileInstruction) && is_string($ontrackMobileInstruction)) {\n"
        "    $systemInstruction .= \"\\n\\nمهمة مكالمة الموبايل:\\n\" . $ontrackMobileInstruction;\n}\n\n" + before, 1)


if __name__ == '__main__':
    import sys
    path = Path(sys.argv[1])
    path.write_text(patch(path.read_text()))
