package com.ontrack.vs;

import android.content.Context;
import android.media.AudioDeviceInfo;
import android.media.AudioManager;

/** Non-privileged device observations only. Never claims that an uplink accepts audio. */
public final class SimAudioStatus {
    private SimAudioStatus() {}

    public static boolean callIsActive(Context ctx) {
        AudioManager a = (AudioManager) ctx.getSystemService(Context.AUDIO_SERVICE);
        if (a == null) return false;
        int mode = a.getMode();
        return mode == AudioManager.MODE_IN_CALL;
    }

    public static String report(Context ctx) {
        AudioManager a = (AudioManager) ctx.getSystemService(Context.AUDIO_SERVICE);
        if (a == null) return "تعذّر الوصول لمعلومات الصوت في الجهاز.";
        int mode = a.getMode();
        boolean active = mode == AudioManager.MODE_IN_CALL;
        boolean voip = mode == AudioManager.MODE_IN_COMMUNICATION;
        boolean telephonyOutput = false;
        try {
            for (AudioDeviceInfo device : a.getDevices(AudioManager.GET_DEVICES_OUTPUTS)) {
                if (device.getType() == AudioDeviceInfo.TYPE_TELEPHONY) {
                    telephonyOutput = true;
                    break;
                }
            }
        } catch (Exception ignored) {}
        String call = active ? "مكالمة شريحة محتملة قيد التشغيل"
                    : voip ? "اتصال صوتي عبر تطبيق آخر"
                    : "لا توجد مكالمة نشطة مرصودة";
        return call + "\n"
            + (telephonyOutput ? "مخرج Telephony مرصود" : "مخرج Telephony غير مرصود") + "\n"
            + "حقن الصوت في SIM: غير موثّق وغير مُفعّل. "
            + "الكشف عن مخرج Telephony لا يمنح صلاحية إرسال PCM للطرف الآخر. "
            + "لن نكتم مكالمتك أو نشغل صوتًا محليًا أثناءها باعتباره بثًا.";
    }
}
