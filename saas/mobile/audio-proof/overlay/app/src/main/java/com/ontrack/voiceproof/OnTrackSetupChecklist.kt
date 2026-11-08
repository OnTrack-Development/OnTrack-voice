package com.ontrack.voiceproof

/** One-button, non-root setup diagnostics. A running authenticated PCM daemon
 * is usable WITHOUT live ADB or Wireless Debugging. Do not confuse pairing
 * memory with a live ADB connection, or UNKNOWN OEM settings with OFF. */
internal enum class CheckState { READY, ACTION, UNKNOWN, OPTIONAL }
internal enum class CheckAction { NONE, DEVICE_INFO, DEVELOPER, PAIR, RECONNECT, PHONE_PERMISSION, OPEN_VOICE }
internal data class VoiceSetupCheck(
    val id:String,val title:String,val state:CheckState,
    val detail:String,val action:CheckAction=CheckAction.NONE,
    val actionLabel:String=""
)
internal data class VoiceSetupFacts(
    val developer:Boolean?,val usb:Boolean?,val wireless:Boolean?,
    val adbPaired:Boolean,val adbConnected:Boolean,
    val secureSettings:Boolean,val setupComplete:Boolean,
    val pcmDaemon:Boolean,val readPhone:Boolean,val pairedSim:Boolean
)
internal object OnTrackSetupChecklist {
    fun evaluate(f:VoiceSetupFacts):List<VoiceSetupCheck> {
        val checks=mutableListOf<VoiceSetupCheck>()
        fun add(id:String,title:String,state:CheckState,detail:String,
                action:CheckAction=CheckAction.NONE,label:String=""){
            checks+=VoiceSetupCheck(id,title,state,detail,action,label)
        }
        add("phone","حالة الهاتف والشريحة",
            if(f.readPhone)CheckState.READY else CheckState.ACTION,
            if(f.readPhone)"إذن حالة الهاتف مفعّل." else "اسمح بإذن حالة الهاتف فقط حتى نتحقق من الشريحة المرتبطة.",
            if(f.readPhone)CheckAction.NONE else CheckAction.PHONE_PERMISSION,
            if(f.readPhone)"" else "تفعيل إذن الهاتف")
        add("sim","ربط الشريحة بالحساب",
            if(f.pairedSim)CheckState.READY else CheckState.ACTION,
            if(f.pairedSim)"بيانات الشريحة محفوظة ومشفرة على الهاتف." else "اربط SIM بكود حساب OnTrack، بدون مفاتيح API أو USB.",
            if(f.pairedSim)CheckAction.NONE else CheckAction.OPEN_VOICE,
            if(f.pairedSim)"" else "فتح ربط الشريحة")
        add("pcm","محرك نقل الصوت المباشر",
            if(f.pcmDaemon)CheckState.READY else CheckState.ACTION,
            if(f.pcmDaemon)"محرك PCM المباشر بيرد، ومش محتاج تسجيل مكالمات أو ADB مفتوح طول الوقت."
            else "المحرك مش بيرد حاليًا؛ افحص الاتصال المحلي قبل تجربة المكالمات.",
            if(f.pcmDaemon)CheckAction.NONE else CheckAction.RECONNECT,
            if(f.pcmDaemon)"" else "محاولة اتصال آمنة")
        if(f.pcmDaemon) {
            add("adb","اتصال ADB",
                CheckState.OPTIONAL,
                "مش مطلوب حاليًا: محرك PCM شغال حتى لو Wireless Debugging أو Wi-Fi مقفولين.")
        } else {
            add("developer","خيارات المطوّر",
                when(f.developer){true->CheckState.READY;false->CheckState.ACTION;null->CheckState.UNKNOWN},
                when(f.developer){true->"مفعّلة.";false->"افتح حول الهاتف وفعّل خيارات المطوّر مرة واحدة."
                    null->"الهاتف مخفي حالة خيار المطوّر؛ افتح الإعدادات للتحقق."},
                if(f.developer==false)CheckAction.DEVICE_INFO else CheckAction.DEVELOPER,
                "فتح إعدادات Android")
            add("usb","USB Debugging",
                when(f.usb){true->CheckState.READY;false->CheckState.ACTION;null->CheckState.UNKNOWN},
                when(f.usb){true->"مفعّل.";false->"فعّله من خيارات المطوّر؛ مش لازم توصل كابل."
                    null->"الهاتف مخفي حالة USB Debugging؛ تحقق يدويًا."},
                if(f.usb==true)CheckAction.NONE else CheckAction.DEVELOPER,
                if(f.usb==true)"" else "فتح خيارات المطوّر")
            add("wireless","Wireless Debugging",
                when(f.wireless){true->CheckState.READY;false->CheckState.ACTION;null->CheckState.UNKNOWN},
                when(f.wireless){true->"مفعّل.";false->"فعّل Wireless Debugging على Wi-Fi موثوق وقت أول ربط."
                    null->"لا يمكن التأكد من حالة Wireless Debugging تلقائيًا على هذا الجهاز."},
                if(f.wireless==true)CheckAction.NONE else CheckAction.DEVELOPER,
                if(f.wireless==true)"" else "فتح خيارات المطوّر")
            add("adb","اتصال ADB المحلي",
                if(f.adbConnected)CheckState.READY else CheckState.ACTION,
                when{f.adbConnected->"متصل بنفس الهاتف."
                    f.adbPaired->"الجهاز مقترن سابقًا. جرّب إعادة الاتصال؛ لا تعيد إدخال كود إلا عند رفض الاقتران."
                    else->"يلزم الاقتران أول مرة بكود Android، مش بكود ربط SIM."},
                if(f.adbConnected)CheckAction.NONE else
                    if(f.adbPaired)CheckAction.RECONNECT else CheckAction.PAIR,
                if(f.adbConnected)"" else if(f.adbPaired)"إعادة اتصال" else "فتح الاقتران")
        }
        add("repair","إمكانية استعادة الاتصال بعد إعادة التشغيل",
            if(f.secureSettings && f.setupComplete)CheckState.READY else CheckState.OPTIONAL,
            if(f.secureSettings && f.setupComplete)"التجهيز السابق محفوظ؛ يمكن للمحرك محاولة الاستعادة."
            else "استعادة الصوت تلقائيًا بعد إعادة التشغيل قد تحتاج إكمال ربط ADB مرة واحدة. لا Root ولا Shizuku.",
            if(f.secureSettings && f.setupComplete)CheckAction.NONE else
                if(f.adbPaired)CheckAction.RECONNECT else CheckAction.PAIR,
            if(f.secureSettings && f.setupComplete)"" else
                if(f.adbPaired)"استكمال الاتصال" else "إكمال تجهيز الربط")
        return checks
    }
}
