package com.ontrack.vs;

import org.json.JSONObject;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/** Dedicated Fish Audio REST integration. No AI agent or other voice provider. */
public final class FishClient {
    private static final String BASE = "https://api.fish.audio";
    private FishClient() {}

    private static HttpURLConnection open(String path, String apiKey, String model,
                                           String contentType) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(BASE + path).openConnection();
        c.setRequestMethod("POST");
        c.setConnectTimeout(15000);
        c.setReadTimeout(90000);
        c.setDoOutput(true);
        c.setRequestProperty("Authorization", "Bearer " + apiKey);
        c.setRequestProperty("model", model);
        c.setRequestProperty("Content-Type", contentType);
        c.setRequestProperty("Accept", "*/*");
        return c;
    }
    private static byte[] read(InputStream stream, int maxBytes) throws Exception {
        try (InputStream in = stream; ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            byte[] block = new byte[8192];
            int length;
            while ((length = in.read(block)) != -1) {
                if (out.size() + length > maxBytes) throw new Exception("Fish response exceeds safe limit");
                out.write(block, 0, length);
            }
            return out.toByteArray();
        }
    }
    private static byte[] response(HttpURLConnection c) throws Exception {
        int code = c.getResponseCode();
        if (code < 200 || code >= 300) {
            byte[] error = read(c.getErrorStream() == null ?
                new java.io.ByteArrayInputStream(new byte[0]) : c.getErrorStream(), 4096);
            String detail = new String(error, StandardCharsets.UTF_8);
            throw new Exception("Fish HTTP " + code + ": " +
                detail.substring(0, Math.min(detail.length(), 250)));
        }
        return read(c.getInputStream(), 15 * 1024 * 1024);
    }
    public static String transcribe(File recording, String apiKey) throws Exception {
        String boundary = "VS" + System.nanoTime();
        HttpURLConnection c = open("/v1/asr", apiKey, "transcribe-1-pro",
            "multipart/form-data; boundary=" + boundary);
        try {
            try (OutputStream out = c.getOutputStream();
                 FileInputStream input = new FileInputStream(recording)) {
                String prefix = "--" + boundary + "\r\n"
                    + "Content-Disposition: form-data; name=\"audio\"; filename=\"speech.m4a\"\r\n"
                    + "Content-Type: audio/mp4\r\n\r\n";
                out.write(prefix.getBytes(StandardCharsets.UTF_8));
                byte[] block = new byte[8192]; int n;
                while ((n = input.read(block)) >= 0) out.write(block, 0, n);
                out.write(("\r\n--" + boundary + "\r\n"
                    + "Content-Disposition: form-data; name=\"ignore_timestamps\"\r\n\r\ntrue\r\n"
                    + "--" + boundary + "\r\n"
                    + "Content-Disposition: form-data; name=\"diarize\"\r\n\r\nfalse\r\n"
                    + "--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
            }
            return new JSONObject(new String(response(c), StandardCharsets.UTF_8))
                .optString("text", "").trim();
        } finally { c.disconnect(); }
    }
    public static byte[] synthesize(String text, String apiKey, String voiceId) throws Exception {
        if (text == null || text.trim().isEmpty()) throw new Exception("No speech/text to synthesize");
        if (apiKey == null || apiKey.isEmpty()) throw new Exception("Set Fish Audio API key first");
        if (voiceId == null || !voiceId.matches("[A-Za-z0-9_-]{4,128}"))
            throw new Exception("Invalid Voice ID");
        JSONObject json = new JSONObject();
        json.put("text", text.trim());
        json.put("reference_id", voiceId);
        json.put("format", "mp3");
        HttpURLConnection c = open("/v1/tts", apiKey, "drama-3", "application/json");
        try {
            try (OutputStream out = c.getOutputStream()) {
                out.write(json.toString().getBytes(StandardCharsets.UTF_8));
            }
            return response(c);
        } finally { c.disconnect(); }
    }
}
