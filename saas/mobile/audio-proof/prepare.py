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
                text = path.read_text(encoding='utf-8')
                patched = (text.replace(OLD, NEW)
                           .replace('[c]om.jemcik.jemrec', '[c]om.ontrack.voiceproof')
                           .replace('jemrec-', 'ontrack-voiceproof-')
                           .replace('jemrec_rec_', 'ontrack_voiceproof_rec_'))
                if patched != text:
                    path.write_text(patched, encoding='utf-8')
                    changed.append(str(path.relative_to(root)))
    # Android Settings > Wireless debugging > Paired devices uses the ADB
    # client label, not our app launcher label. This upstream default was
    # exposing JemRec on customer phones. Rename the DISPLAY name and the
    # certificate subject for newly generated identities; NEVER reset the
    # persisted keys/certificates because that would force every installed
    # device to pair again. Source remains Apache-2.0 with attribution.
    path = root / 'app/src/main/java/com/jemcik/jemrec/adb/AdbTransport.kt'
    text = replace_once(path.read_text(),
                        'override fun getDeviceName(): String = "JemRec"',
                        'override fun getDeviceName(): String = "IO Voice"',
                        'wireless debugging client display name')
    path.write_text(text)
    path = root / 'app/src/main/java/com/jemcik/jemrec/adb/AdbIdentity.kt'
    text = replace_once(path.read_text(),
                        'private const val SUBJECT = "CN=JemRec, O=JemRec, C=US"',
                        'private const val SUBJECT = "CN=IO Voice, O=OnTrack Development, C=EG"',
                        'new ADB identity certificate subject')
    path.write_text(text)
    path = root / 'app/src/main/res/values/strings.xml'
    text = replace_once(path.read_text(),
                        '<string name="app_name">JemRec</string>',
                        '<string name="app_name">IO Voice</string>',
                        'app_name string resource')
    path.write_text(text)
    themes_path = root / 'app/src/main/res/values/themes.xml'
    if themes_path.is_file():
        t_text = themes_path.read_text()
        t_text = replace_once(
            t_text,
            '    <style name="Theme.JemRec" parent="@android:style/Theme.DeviceDefault.DayNight">\n'
            '        <item name="android:windowActionBar">false</item>\n'
            '        <item name="android:windowNoTitle">true</item>\n'
            '    </style>',
            '    <style name="Theme.JemRec" parent="@android:style/Theme.DeviceDefault.NoActionBar">\n'
            '        <item name="android:windowActionBar">false</item>\n'
            '        <item name="android:windowNoTitle">true</item>\n'
            '        <item name="android:windowBackground">#09090B</item>\n'
            '        <item name="android:colorBackground">#09090B</item>\n'
            '        <item name="android:statusBarColor">#09090B</item>\n'
            '        <item name="android:navigationBarColor">#09090B</item>\n'
            '        <item name="android:forceDarkAllowed">false</item>\n'
            '    </style>',
            'dark obsidian native theme')
        themes_path.write_text(t_text)
    path = root / 'app/build.gradle.kts'
    text = path.read_text()
    text = replace_once(text, 'versionCode = (property("jemrecVersionCode") as String).toInt()',
                        'versionCode = 71', 'versionCode')
    text = replace_once(text, 'versionName = property("jemrecVersionName") as String',
                        'versionName = "0.3.48"', 'versionName')
    text = replace_once(text, '        minSdk = 31', '        minSdk = 31\n        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"', 'instrumentation runner')
    path.write_text(text)
    path = root / 'app/build.gradle.kts'
    text = replace_once(path.read_text(), 'dependencies {', 'dependencies {\n    implementation("com.google.android.gms:play-services-code-scanner:16.1.0")\n    implementation("com.squareup.okhttp3:okhttp:4.12.0")\n    androidTestImplementation("androidx.test:runner:1.6.2")\n    androidTestImplementation("androidx.test.ext:junit:1.2.1")\n    androidTestImplementation("androidx.test:core:1.6.1")', 'mobile websocket transport')
    # Google GenAI brings several transitive Google Auth / Apache HttpClient
    # jars sharing non-executable JAR metadata. Keep services and licenses:
    # drop only duplicate indexes/dependency reports, and retain one copy
    # of legally relevant notices if their paths collide.
    text = replace_once(text,
                        '        resources {\n            // BouncyCastle',
                        '        resources {\n'
                        '            excludes += setOf("META-INF/INDEX.LIST", "META-INF/DEPENDENCIES")\n'
                        '            pickFirsts += setOf("META-INF/LICENSE", "META-INF/LICENSE.txt",\n'
                        '                "META-INF/NOTICE", "META-INF/NOTICE.txt")\n'
                        '            // BouncyCastle',
                        'google auth/apache duplicated java resource metadata')
    path.write_text(text)
    path = root / 'shellserver/src/com/jemcik/jemrec/shell/GreetingPlayer.java'
    text = path.read_text().replace('private static AudioTrack musicTrack(', 'static AudioTrack musicTrack(').replace('private static AudioTrack screeningTrack(', 'static AudioTrack screeningTrack(')
    text = replace_once(
        text,
        '        AudioTrack tr = new AudioTrack.Builder()\n'
        '                .setAudioAttributes(new AudioAttributes.Builder()\n'
        '                        .setUsage(AudioAttributes.USAGE_MEDIA)\n'
        '                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)\n'
        '                        .build())\n'
        '                .setAudioFormat(mono())\n'
        '                .setTransferMode(AudioTrack.MODE_STREAM)\n'
        '                .build();',
        '        AudioAttributes attrs = new AudioAttributes.Builder()\n'
        '                .setUsage(AudioAttributes.USAGE_MEDIA)\n'
        '                .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)\n'
        '                .build();\n'
        '        AudioTrack tr;\n'
        '        try {\n'
        '            int minBuf = AudioTrack.getMinBufferSize(RATE, AudioFormat.CHANNEL_OUT_MONO, AudioFormat.ENCODING_PCM_16BIT);\n'
        '            int targetBuf = Math.max(Math.max(minBuf, 0), framesOf(80) * 2);\n'
        '            tr = new AudioTrack.Builder()\n'
        '                    .setAudioAttributes(attrs)\n'
        '                    .setAudioFormat(mono())\n'
        '                    .setTransferMode(AudioTrack.MODE_STREAM)\n'
        '                    .setBufferSizeInBytes(targetBuf)\n'
        '                    .setPerformanceMode(AudioTrack.PERFORMANCE_MODE_LOW_LATENCY)\n'
        '                    .build();\n'
        '        } catch (Throwable ignored) {\n'
        '            tr = new AudioTrack.Builder()\n'
        '                    .setAudioAttributes(attrs)\n'
        '                    .setAudioFormat(mono())\n'
        '                    .setTransferMode(AudioTrack.MODE_STREAM)\n'
        '                    .build();\n'
        '        }',
        'low latency call uplink AudioTrack')
    path.write_text(text)
    # 2026-11-13 Audio Quality Fix (0.3.46 / v69):
    # NEVER attach AcousticEchoCanceler or NoiseSuppressor to AudioDirectCapture!
    # Main captures AudioSource.VOICE_CALL (48kHz stereo cellular carrier audio)
    # with AudioManager.setMicrophoneMute(true). Hardware AEC/NS treats carrier
    # speech & AI overlap as mic noise/echo, clipping consonants and distorting PCM.
    # Restore tenant SIM monitor + daemon recovery automatically on phone reboot.
    boot_path = root / 'app/src/main/java/com/jemcik/jemrec/capture/BootReceiver.kt'
    boot_text = replace_once(
        boot_path.read_text(),
        '                NetworkRevive.arm(context)',
        '                NetworkRevive.arm(context)\n'
        '                runCatching {\n'
        '                    if (com.ontrack.voiceproof.TenantDeviceEnrollment(context).hasAny()) {\n'
        '                        androidx.core.content.ContextCompat.startForegroundService(\n'
        '                            context,\n'
        '                            Intent(context, com.ontrack.voiceproof.MobileLinkService::class.java)\n'
        '                                .setAction(com.ontrack.voiceproof.MobileLinkService.ACTION_RESTORE)\n'
        '                        )\n'
        '                    }\n'
        '                }',
        'restore tenant SIM service after reboot')
    boot_path.write_text(boot_text)
    path = root / 'shellserver/src/com/jemcik/jemrec/shell/Main.java'
    text = replace_once(path.read_text(), '        if (command == COMMAND_RECORD) {', '''        if (command == 'B') {
            if (!callActive || activeRecording != null) throw new IOException("call not active or another capture owns audio");
            CallReport report = new CallReport("OnTrack live bridge", System::currentTimeMillis);
            LivePcm.run(client, phoneCapture(report), callAudio);
            return;
        }
        if (command == 'J') {
            // 2026-11-04 regression: Q is the old verified carrier state,
            // but on dual-SIM it carries NO SIM identity. Android must
            // bind Q to exactly one active, paired subscription; otherwise
            // refuse guessing. A returns observed CALL state, not a proof
            // that Gemini/PCM or microphone mute has started.
            int operation = client.getInputStream().read();
            if (operation == 'A') {
                onMain(() -> { if (telecom != null) telecom.acceptRingingCall(); return null; });
            } else if (operation == 'H') {
                onMain(() -> { if (telecom != null) telecom.endCall(); return null; });
            } else if (operation == 'S') {
                // Experimental telephony speaker route. Never claim success without readback.
                if (callAudio == null || phone.getCallState() != 2) throw new IOException("no active call audio");
                boolean previous = callAudio.isSpeakerphoneOn();
                onMain(() -> { callAudio.setSpeakerphoneOn(!previous); return null; });
                boolean now = callAudio.isSpeakerphoneOn();
                reply(client, "SPEAKER " + (now == previous ? "UNSUPPORTED" : now ? "ON" : "OFF"));
                return;
            } else if (operation != 'Q') {
                throw new IOException("unsupported link control");
            }
            int state = phone.getCallState();
            reply(client, "CALL " + state);
            return;
        }
        if (command == COMMAND_RECORD) {''', 'authenticated live bridge/control')
    path.write_text(text)
    path = root / 'app/src/main/java/com/jemcik/jemrec/capture/CaptureDaemon.kt'
    text = replace_once(path.read_text(), '    suspend fun connect(context: Context, timeoutMs: Int = 3_000): Socket', '''    suspend fun livePcm(context: Context, screening: Boolean): Socket = withContext(Dispatchers.IO) {
        open(context, 'B'.code.toByte(), byteArrayOf(if (screening) 'S'.code.toByte() else 'M'.code.toByte()), readMs = 15_000)
    }
    suspend fun linkControl(context: Context, operation: Char): String? =
        ask(context, 'J'.code.toByte(), byteArrayOf(operation.code.toByte()))

    suspend fun connect(context: Context, timeoutMs: Int = 3_000): Socket''', 'native live bridge API')
    path.write_text(text)
    # OnTrack Voice is a LIVE PCM bridge, never a call recorder. Keep the
    # upstream diagnostic behavior which discards transient test recordings.
    # Do not retain WAV/PCM files from user calls or test calls.
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
    # OnTrack has no call-recording feature. A legacy JemRec settings screen,
    # Quick Settings widget, or stale saved toggle must NEVER enable a recording.
    # Audio to/from the AI is handled by the separate authenticated live PCM
    # socket ('B') and remains available with recorder mode MODE_OFF.
    path = root / 'app/src/main/java/com/jemcik/jemrec/capture/RecorderSwitch.kt'
    text = replace_once(path.read_text(),
                        'Prefs.of(context).getBoolean(KEY, true)',
                        'false', 'disable all saved call-recording toggle states')
    text = replace_once(text,
                        '.edit { putBoolean(KEY, on) }',
                        '.edit { putBoolean(KEY, false) }',
                        'never persist call recording enabled')
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
    # The SIM bridge does not need notification access. Remove the inherited
    # listener capability, its grant path, and optional UI; never suppress the
    # phone's own debugging notice. Keep only a non-service compatibility API.
    path = root / 'app/src/main/java/com/jemcik/jemrec/ui/Setup.kt'
    text = path.read_text()
    start = text.index('        // Granted the same way and at the same moment as the permission above,')
    end = text.index('        // Re-assert the off-Wi-Fi shield,', start)
    text = text[:start] + text[end:]
    start = text.index('    suspend fun grantNotificationAccess(context: Context): Boolean {')
    end = text.index('\n    /**', start)
    text = text[:start] + '    suspend fun grantNotificationAccess(context: Context): Boolean = false\n' + text[end:]
    path.write_text(text)
    path = root / 'app/src/main/java/com/jemcik/jemrec/ui/SettingsScreen.kt'
    text = path.read_text()
    start = text.index("            // Setup's debugging question, answerable again.")
    end = text.index('\n        }\n\n        SettingsGroup("Troubleshooting")', start)
    text = text[:start] + text[end:]
    start = text.index('        if (state.voipEnabled.isNotEmpty()) {', text.index('SettingsGroup("Record calls in")'))
    end = text.index('\n        SettingsFooter(', start)
    text = text[:start] + text[end:]
    start = text.index('            "These are not phone calls, so JemRec does not look them up')
    end = text.index('\n        )', start)
    text = text[:start] + '            "OnTrack does not read other apps\' notifications or collect messenger caller names.",' + text[end:]
    path.write_text(text)
    # Upstream banner classifier no longer exists; remove its obsolete unit test.
    (root / 'app/src/test/java/com/jemcik/jemrec/capture/DebugNotificationCleanerTest.kt').unlink()
    # JemRec setup was designed for a call-recording archive. For OnTrack's
    # direct SIM <-> AI voice link, the ONLY mandatory Android runtime consent
    # is READ_PHONE_STATE at SIM enrollment (requested by MobileLinkActivity).
    # Notifications may be requested *later* from its own optional UI button.
    # Never require recording-library/media permissions to finish ADB pairing.
    path = root / 'app/src/main/java/com/jemcik/jemrec/ui/Setup.kt'
    text = path.read_text()
    text = replace_once(text,
                        '    val RUNTIME_PERMISSIONS: Array<String> = buildList {',
                        '    val LEGACY_RUNTIMES_UNUSED: Array<String> = buildList {',
                        'legacy recording permissions')
    text = replace_once(text,
                        '    val ASK_PERMISSIONS = RUNTIME_PERMISSIONS + AudioAccess.PERMISSIONS',
                        '    val RUNTIME_PERMISSIONS: Array<String> = emptyArray()\n'
                        '    val ASK_PERMISSIONS = RUNTIME_PERMISSIONS',
                        'remove obsolete recorder media permissions prompt')
    path.write_text(text)
    # Launcher becomes the Arabic guided connection/fault-check screen.
    path = root / 'app/src/main/AndroidManifest.xml'
    android = '{http://schemas.android.com/apk/res/android}'
    ET.register_namespace('android', 'http://schemas.android.com/apk/res/android')
    ET.register_namespace('tools', 'http://schemas.android.com/tools')
    manifest = ET.parse(path)
    app = manifest.getroot().find('application')
    for service in list(app.findall('service')):
        if service.get(android + 'permission') == 'android.permission.BIND_NOTIFICATION_LISTENER_SERVICE':
            app.remove(service)
    # Remove everything inherited solely for recording archives and contacts.
    # We still need READ_PHONE_STATE for SIM identity, WRITE_SECURE_SETTINGS for
    # optional ADB reconnect, and telephony's MANAGE_ONGOING_CALLS shell grant.
    # Keep CALL_PHONE optional: campaigns/human callback ask only when used.
    for permission in (
        'READ_MEDIA_AUDIO', 'READ_EXTERNAL_STORAGE', 'WRITE_EXTERNAL_STORAGE',
        'READ_CALL_LOG', 'READ_CONTACTS', 'USE_FULL_SCREEN_INTENT',
        'FOREGROUND_SERVICE_MEDIA_PLAYBACK',
    ):
        for node in list(manifest.getroot().findall('uses-permission')):
            if node.get(android + 'name') == 'android.permission.' + permission:
                manifest.getroot().remove(node)
        ET.SubElement(manifest.getroot(), 'uses-permission', {
            android + 'name': 'android.permission.' + permission,
            '{http://schemas.android.com/tools}node': 'remove',
        })
    # The old recording Quick Settings tile can toggle recording without user
    # ever opening OnTrack; remove it from the distributable Android manifest.
    for service in list(app.findall('service')):
        if service.get(android + 'permission') == 'android.permission.BIND_QUICK_SETTINGS_TILE':
            app.remove(service)
    # Explicitly request phone state; the upstream manifest had removed it as
    # a legacy unnecessary recorder permission, but our SIM enrollment needs it.
    for node in list(manifest.getroot().findall('uses-permission')):
        if node.get(android + 'name') == 'android.permission.READ_PHONE_STATE':
            manifest.getroot().remove(node)
    # Optional Google Play services QR camera UI; manual code remains supported.
    ET.SubElement(app, 'meta-data', {
        android + 'name': 'com.google.mlkit.vision.DEPENDENCIES',
        android + 'value': 'barcode_ui'
    })
    app.set(android + 'label', 'IO Voice')
    app.set(android + 'icon', '@mipmap/ic_launcher')
    app.set(android + 'roundIcon', '@mipmap/ic_launcher_round')
    for activity in app.findall('activity'):
        if activity.get(android + 'name') == '.MainActivity':
            for intent in list(activity.findall('intent-filter')):
                if any(x.get(android + 'name') == 'android.intent.category.LAUNCHER' for x in intent.findall('category')):
                    activity.remove(intent)
    activity = ET.SubElement(app, 'activity', {android + 'name': '.MobileLinkActivity', android + 'exported': 'true'})
    intent = ET.SubElement(activity, 'intent-filter')
    ET.SubElement(intent, 'action', {android + 'name': 'android.intent.action.MAIN'})
    ET.SubElement(intent, 'category', {android + 'name': 'android.intent.category.LAUNCHER'})
    # Camera QR scanners can open the app using this URI. It only pre-fills
    # a native confirmation dialog; no automatic redemption or browser bridge.
    qr = ET.SubElement(activity, 'intent-filter')
    ET.SubElement(qr, 'action', {android + 'name': 'android.intent.action.VIEW'})
    ET.SubElement(qr, 'category', {android + 'name': 'android.intent.category.DEFAULT'})
    ET.SubElement(qr, 'category', {android + 'name': 'android.intent.category.BROWSABLE'})
    ET.SubElement(qr, 'data', {android + 'scheme': 'ontrackvoice', android + 'host': 'pair'})
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.FOREGROUND_SERVICE_DATA_SYNC'})
    # User opt-in app overlay is unrelated to Accessibility and is never auto-granted.
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.SYSTEM_ALERT_WINDOW'})
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.ACCESS_NETWORK_STATE'})
    # Native APK updates use the platform package installer after explicit consent.
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.REQUEST_INSTALL_PACKAGES'})
    # Optional Android 13+ system notifications; requested only after user opt-in.
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.POST_NOTIFICATIONS'})
    # Tenant SIM subscription-change detection; Android runtime consent required.
    # No ICCID/IMSI/phone number is read or uploaded. Denial blocks enrollment.
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.READ_PHONE_STATE'})
    # Outbound SIM placement is never enabled unless the owner explicitly opts in.
    ET.SubElement(manifest.getroot(), 'uses-permission', {android + 'name': 'android.permission.CALL_PHONE'})
    ET.SubElement(app, 'receiver', {
        android + 'name':'.NativeInstallResultReceiver',
        android + 'exported':'false'
    })
    provider=ET.SubElement(app, 'provider', {
        android + 'name':'androidx.core.content.FileProvider',
        android + 'authorities':NEW+'.updates',
        android + 'exported':'false',
        android + 'grantUriPermissions':'true'
    })
    ET.SubElement(provider, 'meta-data', {
        android + 'name':'android.support.FILE_PROVIDER_PATHS',
        android + 'resource':'@xml/native_updates_paths'
    })
    ET.SubElement(app, 'activity', {android + 'name': '.ConsoleWebActivity', android + 'exported': 'false'})
    ET.SubElement(app, 'activity', {android + 'name': '.ProofActivity', android + 'exported': 'false'})
    ET.SubElement(app, 'service', {android + 'name': '.MobileLinkService', android + 'exported': 'false', android + 'foregroundServiceType': 'dataSync'})
    # Upstream components use relative names under the new namespace; explicit
    # intents inside shell source were rewritten above. No Accessibility added.
    manifest.write(path, encoding='utf-8', xml_declaration=True)
    for obsolete in (
        'app/src/main/res/mipmap-anydpi/ic_launcher.xml',
        'app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml',
        'app/src/main/res/mipmap-anydpi-v26/ic_launcher_round.xml',
        'app/src/main/res/drawable/ic_launcher_foreground.xml',
        'app/src/main/res/drawable/ic_launcher_monochrome.xml',
        'app/src/main/res/drawable/ontrack_icon.xml',
    ):
        (root / obsolete).unlink(missing_ok=True)
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
        'OnTrack Development, 2026. This distribution contains modified Apache-2.0 licensed software.\\n'
        'Copyright, license, and upstream notices are retained in the accompanying LICENSE and NOTICE.\\n')
    # Prove that the overlaid manifest does not request forbidden services.
    merged = (root / 'app/src/main/AndroidManifest.xml').read_text()
    if any(x in merged for x in ['BIND_ACCESSIBILITY_SERVICE', 'android.accessibilityservice',
                                  'BIND_NOTIFICATION_LISTENER_SERVICE', 'NotificationListenerService',
                                  'android.permission.READ_SMS', 'android.permission.RECEIVE_SMS']):
        raise SystemExit('Forbidden sensitive permission/service declaration')
    (root / 'ontrack-build-source.json').write_text(json.dumps({
        **LOCK, 'overlay_sha256': {str(p.relative_to(HERE)): hashlib.sha256(p.read_bytes()).hexdigest()
                                 for p in sorted((HERE / 'overlay').rglob('*')) if p.is_file()},
        'upstream_rewritten_files': changed, 'device_audio_verified': False,
    }, indent=2) + '\n')
    print(json.dumps({'status': 'PREPARED', 'application_id': NEW, 'upstream': actual}))


if __name__ == '__main__':
    main(Path(sys.argv[1]).resolve())
