#!/usr/bin/env python3
"""Apply an auditable overlay to exactly the pinned JemRec revision.

Usage: python3 prepare.py /absolute/path/to/checked-out-JemRec
The caller obtains upstream; this script never fetches or executes network code.
"""
import hashlib
import json
import shutil
import subprocess
import sys
from pathlib import Path
from xml.etree import ElementTree as ET

HERE = Path(__file__).resolve().parent
LOCK = json.loads((HERE / 'UPSTREAM.lock.json').read_text())
OLD = 'com.jemcik.jemrec'
NEW = LOCK['application_id']


def replace_once(text, before, after, name):
    if text.count(before) != 1:
        raise ValueError(f'{name}: expected exactly one patch anchor')
    return text.replace(before, after, 1)


def main(root):
    actual = subprocess.check_output(['git', '-C', str(root), 'rev-parse', 'HEAD'], text=True).strip()
    if actual != LOCK['commit']:
        raise SystemExit('Upstream ref mismatch; refusing to patch a different revision')
    if subprocess.check_output(['git', '-C', str(root), 'status', '--porcelain'], text=True).strip():
        raise SystemExit('Upstream checkout must be clean; create a fresh checkout')
    # Keep package namespaces and hardcoded shell-service targets consistent.
    # Directory names need not match Kotlin/Java package names; paths remain
    # intact so the upstream Gradle source sets continue to select the files.
    suffixes = {'.kt', '.java', '.xml', '.kts', '.sh', '.pro', '.properties'}
    changed = []
    for parent in ['app', 'shellserver', 'gradle']:
        for path in (root / parent).rglob('*'):
            if path.is_file() and path.suffix in suffixes:
                text = path.read_text()
                patched = (text.replace(OLD, NEW)
                           .replace('[c]om.jemcik.jemrec', '[c]om.ontrack.voiceproof')
                           .replace('jemrec-', 'ontrack-voiceproof-')
                           .replace('jemrec_rec_', 'ontrack_voiceproof_rec_'))
                if patched != text:
                    path.write_text(patched)
                    changed.append(str(path.relative_to(root)))
    path = root / 'app/build.gradle.kts'
    text = path.read_text()
    text = replace_once(text, 'versionCode = (property("jemrecVersionCode") as String).toInt()',
                        'versionCode = 2', 'versionCode')
    text = replace_once(text, 'versionName = property("jemrecVersionName") as String',
                        'versionName = "0.1.1-audio-proof"', 'versionName')
    path.write_text(text)
    path = root / 'shellserver/tests/src/com/jemcik/jemrec/shell/AnswerMachineTest.java'
    text = replace_once(path.read_text(), '    private void testCallGreeting() {',
                        '    @Test public void cancelledProofNeverAnswersTheNextRing() {\n'
                        '        machine.armTest(true, 180000L);\n'
                        '        machine.cancelProof();\n'
                        '        machine.ringing();\n'
                        '        world.advance(10000L);\n'
                        '        assertFalse(world.did.contains("answer"));\n    }\n\n'
                        '    @Test public void cancelledArmedProofCancelsTheAnswerTimer() {\n'
                        '        machine.armTest(true, 180000L);\n'
                        '        machine.ringing();\n'
                        '        machine.cancelProof();\n'
                        '        world.advance(10000L);\n'
                        '        assertFalse(world.did.contains("answer"));\n    }\n\n'
                        '    @Test public void cancelledActiveProofStopsAudioAndHangsUp() {\n'
                        '        testCallGreeting();\n'
                        '        world.did.clear();\n'
                        '        machine.cancelProof();\n'
                        '        assertTrue(world.did.contains("stop greeting"));\n'
                        '        assertTrue(world.did.contains("hang up"));\n    }\n\n'
                        '    private void testCallGreeting() {', 'proof cancel regression tests')
    path.write_text(text)
    # Recording and automatic answering are off until an explicit one-call test.
    path = root / 'app/src/main/java/com/jemcik/jemrec/capture/RecorderSwitch.kt'
    text = replace_once(path.read_text(), 'getBoolean(KEY, true)', 'getBoolean(KEY, false)', 'recorder default')
    path.write_text(text)
    # Add a bounded cancel command; existing protocol authentication is retained.
    path = root / 'app/src/main/java/com/jemcik/jemrec/capture/CaptureDaemon.kt'
    text = replace_once(path.read_text(), '    /** What the last test call measured;',
                        '    suspend fun cancelProof(context: Context): String? =\n'
                        "        ask(context, COMMAND_DIAGNOSE, byteArrayOf('Z'.code.toByte()))\n\n"
                        '    /** What the last test call measured;', 'app cancel command')
    path.write_text(text)
    path = root / 'shellserver/src/com/jemcik/jemrec/shell/AnswerMachine.java'
    text = replace_once(path.read_text(), '    void armTest(boolean incoming, long windowMs) {',
                        '    void cancelProof() {\n'
                        '        testUntil = 0;\n        testIncoming = false;\n'
                        '        if (testCall) {\n'
                        '            effects.stopGreeting();\n'
                        '            if (state == State.ARMED) disarm("proof cancelled");\n'
                        '            else hangUpNow();\n        }\n    }\n\n'
                        '    void armTest(boolean incoming, long windowMs) {', 'shell cancel')
    path.write_text(text)
    path = root / 'shellserver/src/com/jemcik/jemrec/shell/Main.java'
    text = replace_once(path.read_text(), "            case 'R':\n                reply(client, \"RESULT 1 state=\"",
                        "            case 'Z':\n"
                        '                onMain(() -> {\n'
                        '                    if (answerMachine != null) answerMachine.cancelProof();\n'
                        '                    return null;\n                });\n'
                        '                testState = "cancelled";\n'
                        '                reply(client, "TEST cancelled");\n                break;\n'
                        "            case 'R':\n                reply(client, \"RESULT 1 state=\"", 'shell cancel protocol')
    path.write_text(text)
    # Preserve the actual grant/read-back response; generic vendor advice was
    # misleading once Xiaomi's security switch was already visibly enabled.
    path = root / 'app/src/main/java/com/jemcik/jemrec/ui/Setup.kt'
    text = replace_once(path.read_text(),
                        '            val outcome = ShellGrant.outcome(pkg, PERMISSION, grant, readBack)',
                        '            com.ontrack.voiceproof.OnTrackSetupDiagnostics.recordGrant(context, grant, readBack)\n'
                        '            val outcome = ShellGrant.outcome(pkg, PERMISSION, grant, readBack)',
                        'capture raw setup grant response')
    text = replace_once(text,
                        'error(ShellGrant.advice(outcome, DeviceSkin.current(), ShellGrant.monitoring(property)))',
                        'error(ShellGrant.advice(outcome, DeviceSkin.current(), ShellGrant.monitoring(property)) + '
                        '"\\n\\nAndroid response:\\n" + grant.take(3000))', 'display raw grant refusal')
    path.write_text(text)
    # Launcher becomes the Arabic proof screen; upstream setup remains available.
    path = root / 'app/src/main/AndroidManifest.xml'
    android = '{http://schemas.android.com/apk/res/android}'
    ET.register_namespace('android', 'http://schemas.android.com/apk/res/android')
    ET.register_namespace('tools', 'http://schemas.android.com/tools')
    manifest = ET.parse(path)
    app = manifest.getroot().find('application')
    app.set(android + 'label', 'OnTrack SIM Audio Test')
    app.set(android + 'icon', '@drawable/ontrack_icon')
    app.set(android + 'roundIcon', '@drawable/ontrack_icon')
    for activity in app.findall('activity'):
        if activity.get(android + 'name') == '.MainActivity':
            for intent in list(activity.findall('intent-filter')):
                if any(x.get(android + 'name') == 'android.intent.category.LAUNCHER' for x in intent.findall('category')):
                    activity.remove(intent)
    activity = ET.SubElement(app, 'activity', {android + 'name': '.ProofActivity', android + 'exported': 'true'})
    intent = ET.SubElement(activity, 'intent-filter')
    ET.SubElement(intent, 'action', {android + 'name': 'android.intent.action.MAIN'})
    ET.SubElement(intent, 'category', {android + 'name': 'android.intent.category.LAUNCHER'})
    # Upstream components use relative names under the new namespace; explicit
    # intents inside shell source were rewritten above. No Accessibility added.
    manifest.write(path, encoding='utf-8', xml_declaration=True)
    for source in (HERE / 'overlay').rglob('*'):
        if source.is_file():
            target = root / source.relative_to(HERE / 'overlay')
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source, target)
    assets = root / 'app/src/main/assets'
    assets.mkdir(parents=True, exist_ok=True)
    for name in ['LICENSE', 'NOTICE.md']:
        shutil.copyfile(root / name, assets / ('UPSTREAM-' + name))
    (assets / 'ONTRACK-MODIFICATIONS.txt').write_text(
        'OnTrack Development, 2026-10-30. Experimental derivative of JemRec at ' + LOCK['commit'] + '\n'
        'Apache-2.0. Original attribution and notices retained.\n'
        'Changes: separate application namespace/socket/files; Arabic proof launcher; local audio import;\n'
        'one-call test cancellation; no automatic recording by default; explicit diagnostics export.\n'
        'This build does not establish compatibility or full duplex AI conversation on any device.\n')
    # Prove that the overlaid manifest does not request forbidden services.
    merged = (root / 'app/src/main/AndroidManifest.xml').read_text()
    if 'BIND_ACCESSIBILITY_SERVICE' in merged or 'android.accessibilityservice' in merged:
        raise SystemExit('Forbidden Accessibility declaration')
    (root / 'ontrack-build-source.json').write_text(json.dumps({
        **LOCK, 'overlay_sha256': {str(p.relative_to(HERE)): hashlib.sha256(p.read_bytes()).hexdigest()
                                 for p in sorted((HERE / 'overlay').rglob('*')) if p.is_file()},
        'upstream_rewritten_files': changed, 'device_audio_verified': False,
    }, indent=2) + '\n')
    print(json.dumps({'status': 'PREPARED', 'application_id': NEW, 'upstream': actual}))


if __name__ == '__main__':
    main(Path(sys.argv[1]).resolve())
