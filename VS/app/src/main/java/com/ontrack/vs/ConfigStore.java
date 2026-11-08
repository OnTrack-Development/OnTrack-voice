package com.ontrack.vs;

import android.content.Context;
import android.content.SharedPreferences;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.Base64;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

/** Local-only settings. Fish API key is encrypted with a non-exportable Android Keystore key. */
public final class ConfigStore {
    public static final String DEFAULT_VOICE_ID = "e3c2911a78404bcab3c6adce3c59e1d9";
    private static final String ALIAS = "vs_fish_api_key_v1";
    private static SharedPreferences prefs(Context ctx) {
        return ctx.getApplicationContext().getSharedPreferences("vs_settings", Context.MODE_PRIVATE);
    }
    public static int selectedPersona(Context ctx) {
        return PersonaProfiles.validIndex(prefs(ctx).getInt("persona_index", 0));
    }
    public static void saveSelectedPersona(Context ctx, int index) {
        prefs(ctx).edit().putInt("persona_index", PersonaProfiles.validIndex(index)).apply();
    }
    public static String voiceId(Context ctx) {
        return voiceId(ctx, selectedPersona(ctx));
    }
    public static String voiceId(Context ctx, int index) {
        SharedPreferences data = prefs(ctx);
        return data.getString("persona_voice_id_" + PersonaProfiles.validIndex(index),
            data.getString("voice_id", DEFAULT_VOICE_ID));
    }
    public static void saveVoiceId(Context ctx, String voiceId) {
        saveVoiceId(ctx, selectedPersona(ctx), voiceId);
    }
    public static void saveVoiceId(Context ctx, int index, String voiceId) {
        prefs(ctx).edit().putString("persona_voice_id_" + PersonaProfiles.validIndex(index),
            voiceId.trim()).apply();
    }
    private static SecretKey key() throws Exception {
        KeyStore store = KeyStore.getInstance("AndroidKeyStore");
        store.load(null);
        if (store.containsAlias(ALIAS)) return (SecretKey) store.getKey(ALIAS, null);
        KeyGenerator gen = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
        gen.init(new KeyGenParameterSpec.Builder(ALIAS,
            KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setKeySize(256).build());
        return gen.generateKey();
    }
    public static void saveApiKey(Context ctx, String apiKey) throws Exception {
        if (apiKey == null || apiKey.isEmpty()) return; // empty input does not erase saved key
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, key());
        byte[] ciphertext = cipher.doFinal(apiKey.trim().getBytes(StandardCharsets.UTF_8));
        prefs(ctx).edit()
            .putString("key_iv", Base64.encodeToString(cipher.getIV(), Base64.NO_WRAP))
            .putString("key_cipher", Base64.encodeToString(ciphertext, Base64.NO_WRAP)).commit();
    }
    public static String apiKey(Context ctx) throws Exception {
        String iv = prefs(ctx).getString("key_iv", null);
        String encrypted = prefs(ctx).getString("key_cipher", null);
        if (iv == null || encrypted == null) return "";
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, key(),
            new GCMParameterSpec(128, Base64.decode(iv, Base64.DEFAULT)));
        return new String(cipher.doFinal(Base64.decode(encrypted, Base64.DEFAULT)), StandardCharsets.UTF_8);
    }
}
