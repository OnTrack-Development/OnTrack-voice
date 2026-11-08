package com.ontrack.vs;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.LocalSocket;
import android.net.LocalSocketAddress;
import android.util.Base64;
import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.security.SecureRandom;
import java.util.Arrays;

/** Local-only ADB shell bridge transport. No JemRec, no remote socket or call recording. */
public final class VsCallBridge implements AutoCloseable {
    private static final String SOCKET = "ontrack_vs_telephony_1";
    private final LocalSocket socket;
    private final DataInputStream input;
    private final DataOutputStream output;
    private boolean active;

    public static String token(Context ctx) {
        SharedPreferences p = ctx.getSharedPreferences("vs_call_bridge", Context.MODE_PRIVATE);
        String existing = p.getString("token", null);
        if (existing != null && existing.matches("[A-Za-z0-9_-]{40,100}")) return existing;
        byte[] random = new byte[32];
        new SecureRandom().nextBytes(random);
        String secret = Base64.encodeToString(random, Base64.NO_WRAP | Base64.NO_PADDING | Base64.URL_SAFE);
        Arrays.fill(random, (byte)0);
        p.edit().putString("token", secret).commit();
        return secret;
    }

    /** Export signed-in app-provided helper to app-specific shared folder readable via adb shell. */
    public static String setupCommand(Context ctx) throws Exception {
        File root = ctx.getExternalFilesDir(null);
        if (root == null) throw new IllegalStateException("App files folder unavailable");
        File target = new File(root, "vs-bridge.jar");
        try (InputStream in = ctx.getAssets().open("vs-bridge.jar");
             FileOutputStream out = new FileOutputStream(target, false)) {
            byte[] block = new byte[8192];
            int n;
            while ((n = in.read(block)) != -1) out.write(block, 0, n);
        }
        // The APK never executes system commands. Owner enables the limited
        // shell service manually via authorized Wireless/USB Debugging.
        String devicePath = target.getAbsolutePath();
        return "adb shell \"CLASSPATH=" + devicePath +
            " app_process / com.ontrack.vs.shell.VsTelephonyShell " + token(ctx) +
            " >/dev/null 2>&1 &\"";
    }

    public static VsCallBridge connect(Context ctx) throws Exception {
        LocalSocket s = new LocalSocket();
        try {
            s.connect(new LocalSocketAddress(SOCKET, LocalSocketAddress.Namespace.ABSTRACT));
            s.setSoTimeout(30000);
            DataOutputStream out = new DataOutputStream(s.getOutputStream());
            DataInputStream in = new DataInputStream(s.getInputStream());
            out.writeUTF(token(ctx));
            out.flush();
            String answer = in.readUTF();
            if (!"READY".equals(answer)) throw new SecurityException("VS shell authorization refused");
            return new VsCallBridge(s, in, out);
        } catch (Exception e) {
            try { s.close(); } catch (Exception ignored) {}
            throw e;
        }
    }

    private VsCallBridge(LocalSocket s, DataInputStream in, DataOutputStream out) {
        this.socket = s; this.input = in; this.output = out;
    }

    private synchronized String request(String cmd) throws Exception {
        output.writeUTF(cmd);
        output.flush();
        return input.readUTF();
    }

    public synchronized String probe() throws Exception { return request("PROBE"); }

    public synchronized String start() throws Exception {
        String response = request("START");
        active = "ACTIVE_MIC_MUTED_ROUTE_CANDIDATE".equals(response)
            || "ACTIVE".equals(response);
        return response;
    }

    public synchronized String play(byte[] pcm48000Mono) throws Exception {
        if (!active) throw new IllegalStateException("Bridge not active");
        if (pcm48000Mono == null || pcm48000Mono.length < 2
                || (pcm48000Mono.length & 1) != 0 || pcm48000Mono.length > 384000)
            throw new IllegalArgumentException("Invalid PCM frame");
        output.writeUTF("PLAY");
        output.writeInt(pcm48000Mono.length);
        output.write(pcm48000Mono);
        output.flush();
        return input.readUTF();
    }

    public synchronized String stop() {
        active = false;
        try { return request("STOP"); }
        catch (Exception e) { return "BRIDGE_DISCONNECTED"; }
    }

    @Override public synchronized void close() {
        stop();
        try { socket.close(); } catch (Exception ignored) {}
    }
}
