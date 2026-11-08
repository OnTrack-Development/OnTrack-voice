package com.ontrack.vs;

import android.content.Context;
import android.media.AudioDeviceInfo;
import android.media.AudioTrack;
import android.media.AudioAttributes;
import android.media.AudioFormat;
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

    /**
     * Non-audible preflight of the device-selected telephony output.
     * Does NOT inject user's voice, does NOT mute the call, and never claims
     * the other party heard anything. Audio policy may reject normal app UID.
     */
    public static String probePreferredRoute(Context ctx) {
        AudioManager audio = (AudioManager)ctx.getSystemService(Context.AUDIO_SERVICE);
        if (audio == null) return "تعذّر فحص AudioManager";
        if (audio.getMode() != AudioManager.MODE_IN_CALL)
            return "افتح مكالمة شريحة أولًا؛ اختبار توجيه Telephony مش متاح بدونها.";
        AudioDeviceInfo uplink = null;
        for (AudioDeviceInfo dev : audio.getDevices(AudioManager.GET_DEVICES_OUTPUTS)) {
            if (dev.getType() == AudioDeviceInfo.TYPE_TELEPHONY) {
                uplink = dev; break;
            }
        }
        if (uplink == null)
            return "مخرج TYPE_TELEPHONY غير ظاهر لهذا التطبيق. محتاج جسر صوت بصلاحيات النظام.";
        AudioTrack tr = null;
        try {
            AudioFormat format = new AudioFormat.Builder()
                .setEncoding(AudioFormat.ENCODING_PCM_16BIT)
                .setSampleRate(48000)
                .setChannelMask(AudioFormat.CHANNEL_OUT_MONO)
                .build();
            tr = new AudioTrack.Builder()
                .setAudioAttributes(new AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_MEDIA)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                    .build())
                .setAudioFormat(format)
                .setTransferMode(AudioTrack.MODE_STREAM)
                .setBufferSizeInBytes(19200)
                .build();
            if (tr.getState() != AudioTrack.STATE_INITIALIZED)
                return "فشل فتح قناة AudioTrack على الجهاز.";
            if (!tr.setPreferredDevice(uplink))
                return "النظام رفض توجيه الصوت إلى Telephony. صلاحيات المعاينة مش كفاية.";
            tr.play();
            // 100ms PCM silence only: no audio leakage to a real phone call.
            byte[] silence = new byte[9600];
            if (tr.write(silence, 0, silence.length, AudioTrack.WRITE_BLOCKING) <= 0)
                return "قناة Telephony رفضت كتابة الاختبار الصامت.";
            AudioDeviceInfo routed = tr.getRoutedDevice();
            if (routed == null || routed.getId() != uplink.getId())
                return "التوجيه الصوتي اتطلب لكن لم يتم تأكيده؛ لا تستخدمه لبث Fish.";
            return "اختبار التوجيه الصامت مقبول مبدئيًا، لكنه لا يثبت وصول الصوت للطرف التاني. "
                + "لازم اختبار مكالمة ثانية قبل تشغيل كتم الميكروفون أو تحويل Fish.";
        } catch (SecurityException se) {
            return "Android منع توجيه صوت المكالمة: صلاحيات نظام غير متاحة للتطبيق.";
        } catch (Exception ex) {
            return "فشل اختبار توجيه الشريحة: " + ex.getClass().getSimpleName();
        } finally {
            if (tr != null) {
                try { tr.pause(); tr.flush(); tr.release(); }
                catch (Exception ignored) {}
            }
        }
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
