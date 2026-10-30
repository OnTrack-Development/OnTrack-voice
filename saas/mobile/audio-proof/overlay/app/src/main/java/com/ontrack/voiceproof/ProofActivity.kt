package com.ontrack.voiceproof

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.Intent
import android.content.ClipData
import android.content.ClipboardManager
import android.graphics.Color
import android.os.Build
import android.os.Bundle
import android.text.InputType
import android.view.View
import android.widget.*
import androidx.activity.ComponentActivity
import androidx.activity.result.contract.ActivityResultContracts
import androidx.lifecycle.lifecycleScope
import com.ontrack.voiceproof.capture.*
import com.ontrack.voiceproof.ui.Setup
import com.ontrack.voiceproof.adb.AdbTransport
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import org.json.JSONObject
import java.security.MessageDigest

/** Explicit one-call controls over the pinned, attributed JemRec audio daemon. */
@SuppressLint("SetTextI18n")
class ProofActivity : ComponentActivity() {
    private lateinit var status: TextView
    private lateinit var details: TextView
    private lateinit var route: RadioGroup
    private lateinit var number: EditText
    private var busy = false
    private var pending = 0
    private val operations = Mutex()
    private val controls = mutableListOf<Button>()
    private val reportPrefs by lazy { getSharedPreferences("ontrack_proof", MODE_PRIVATE) }

    private val importer = registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri != null) work("جاري تجهيز الملف الصوتي…") {
            val pcm = VoiceFile.read(this, uri)
            Greeting.save(this, pcm)
            val hash = MessageDigest.getInstance("SHA-256").digest(pcm).joinToString("") { "%02x".format(it) }
            reportPrefs.edit().putString("voice_sha256", hash).commit()
            if (CaptureDaemon.isRunning(this)) check(CaptureDaemon.uploadGreeting(this, pcm)) {
                "الملف محفوظ، لكن رفعه لمساعد الصوت فشل؛ شغّل الفحص وأعد المحاولة"
            }
            "تم تجهيز %.1f ثانية من الملف. الصوت محفوظ على الجهاز فقط.".format(pcm.size / 96000.0)
        }
    }
    private val exporter = registerForActivityResult(ActivityResultContracts.CreateDocument("application/json")) { uri ->
        if (uri != null) work("جاري حفظ التقرير…") {
            contentResolver.openOutputStream(uri)!!.use { it.write(report().toByteArray(Charsets.UTF_8)) }
            "تم حفظ التقرير. التقرير لا يحتوي أرقام تليفونات أو صوتًا مسجلًا."
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!reportPrefs.getBoolean("initialized", false)) {
            RecorderSwitch.set(this, false)
            RecordingMode.set(this, RecordingMode.ON_DEMAND)
            AnsweringMachine.setEnabled(this, false)
            reportPrefs.edit().putBoolean("initialized", true).commit()
        }
        val column = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(24, 24, 24, 32)
            setBackgroundColor(Color.rgb(248, 249, 252))
            fitsSystemWindows = true
        }
        val scroll = ScrollView(this).apply { addView(column) }
        setContentView(scroll)
        fun text(value: String, size: Float = 16f): TextView = TextView(this).apply {
            text = value; textSize = size; setTextColor(Color.rgb(25, 32, 48)); setPadding(0, 12, 0, 12)
            column.addView(this)
        }
        text("OnTrack • اختبار صوت الشريحة", 23f)
        text("نسخة اختبار 0.1.1 — تشخيص رد Android الأصلي، ثم اختبار صوت الشريحة. الاقتران وحده لا يثبت الصلاحيات أو الصوت.")
        status = text("ابدأ بالإعداد لمرة واحدة، ثم ارجع للشاشة دي.", 18f)
        fun button(label: String, tracked: Boolean = true, action: () -> Unit) = Button(this).apply {
            text = label; isAllCaps = false; setOnClickListener { action() }; column.addView(this)
            if (tracked) controls.add(this)
        }
        button("١ • إعداد الاتصال لمرة واحدة") { startActivity(Intent(this, MainActivity::class.java)) }
        button("فحص سبب رفض الصلاحية وحفظ التفاصيل") { work("جاري قراءة رد Android ومنح الصلاحية لمستخدم الهاتف الحالي…") {
            OnTrackSetupDiagnostics.collect(this)
        } }
        button("نسخ تقرير التشخيص") {
            getSystemService(ClipboardManager::class.java).setPrimaryClip(ClipData.newPlainText("OnTrack diagnostics", report()))
            status.text = "تم نسخ التقرير. يحتوي تفاصيل الصلاحية دون كود الاقتران أو مفاتيح ADB أو تسجيلات."
        }
        button("إعادة الاقتران مع الاحتفاظ بملف الصوت") {
            AlertDialog.Builder(this).setTitle("إعادة الاقتران")
                .setMessage("لو الجهاز مش ظاهر في Paired devices، سنعيد خطوة الربط فقط ونحتفظ بالصوت والإعدادات. لو ظاهر Currently connected، استخدم فحص سبب الرفض بدل إعادة الاقتران.")
                .setPositiveButton("إعادة خطوة الربط") { _, _ -> work("جاري فتح خطوة الاقتران…") {
                    AdbTransport.close()
                    Setup.forgetPairing(this)
                    withContext(Dispatchers.Main) { startActivity(DeepLink.intent(this@ProofActivity, DeepLink.Kind.PAIR_AGAIN)) }
                    "أكمل الاقتران من الإشعار، ثم ارجع لشاشة التشخيص."
                } }.setNegativeButton("إلغاء", null).show()
        }
        text("في الإعداد: فعّل خيارات المطوّر وUSB debugging وWireless debugging، وأدخل كود الاقتران في الإشعار. على شاومي قد يلزم USB debugging (Security settings). لا تطلب النسخة Accessibility أو Shizuku.")
        button("٢ • اختيار ملف صوت MP3 / M4A / WAV") { importer.launch(arrayOf("audio/*")) }
        text("يُستخدم أول ٣٠ ثانية فقط. اختر مقطعًا مميزًا يسهل التأكد منه؛ التحويل والتخزين محليان.")
        route = RadioGroup(this).apply {
            addView(RadioButton(this@ProofActivity).apply { id = View.generateViewId(); text = "المسار الأول • صوت إلى خرج المكالمة"; tag = AnsweringMachine.ROUTE_MUSIC })
            addView(RadioButton(this@ProofActivity).apply { id = View.generateViewId(); text = "المسار البديل • Call screening"; tag = AnsweringMachine.ROUTE_SCREENING })
            check(getChildAt(if (AnsweringMachine.route(this@ProofActivity) == AnsweringMachine.ROUTE_SCREENING) 1 else 0).id)
            column.addView(this)
        }
        button("٣ • فحص جاهزية الجهاز") { work("جاري فحص المساعد ومسارات الصوت…") {
            check(CaptureDaemon.isSetUp(this)) { "كمّل الإعداد الأول من الزر رقم ١" }
            check(CaptureDaemon.revive(this, request = CaptureDaemon.Request.USER)) { "مساعد الصوت لم يبدأ. راجع Wi-Fi وWireless debugging والإعداد." }
            val fields = CaptureDaemon.stageOne(this) ?: error("لم يصل رد الفحص من مساعد الصوت")
            reportPrefs.edit().putString("stage_one", JSONObject(fields).toString()).commit()
            val result = AnswerDiagnostics.stageOne(fields)
            if (result.possible) "المسار محتمل ومتاح للاختبار؛ لم يثبت الصوت بعد.\n${result.describe}"
            else "الفحص لم يجد مسارًا جاهزًا: ${result.problem ?: result.missing}"
        } }
        button("٤ • تجهيز أول مكالمة واردة للاختبار") {
            AlertDialog.Builder(this).setTitle("اختبار الوارد")
                .setMessage("سيجيب التطبيق على أول مكالمة واردة خلال ٣ دقائق، ويشغّل الملف مع كتم ميكروفون شاومي. استخدم رقم اختبار، وخلي الطرف التاني يقول عبارة مميزة بعد المقطع. الاختبار ينتهي تلقائيًا.")
                .setPositiveButton("جهّز الاختبار") { _, _ -> arm(true) }.setNegativeButton("إلغاء", null).show()
        }
        number = EditText(this).apply {
            hint = "رقم اختبار للاتصال الصادر"; inputType = InputType.TYPE_CLASS_PHONE
            textDirection = View.TEXT_DIRECTION_LTR; column.addView(this)
        }
        button("أو • اتصال صادر تجريبي") {
            val destination = number.text.toString().trim()
            if (!destination.matches(Regex("\\+?[0-9]{7,15}"))) {
                status.text = "اكتب رقم موبايل اختبار صحيح؛ أرقام الخدمات والطوارئ غير مقبولة."
            } else AlertDialog.Builder(this).setTitle("إجراء مكالمة من الشريحة")
                .setMessage("الاتصال الآن بـ $destination من شريحة المكالمات الافتراضية؟ الطرف التاني هيسمع ملف الاختبار، ثم يترك عبارة للتسجيل.")
                .setPositiveButton("اتصل الآن") { _, _ -> arm(false, destination) }.setNegativeButton("إلغاء", null).show()
        }
        button("إيقاف الاختبار وإلغاء انتظار أي مكالمة", tracked = false) { work("جاري إيقاف الاختبار…", force = true) {
            val reply = CaptureDaemon.cancelProof(this)
            AnsweringMachine.setEnabled(this, false)
            RecorderSwitch.set(this, false)
            CaptureDaemon.setAnswering(this)
            RecorderSwitch.turn(this, false)
            check(reply == "TEST cancelled") { "تم طلب الإيقاف محليًا لكن المساعد لم يؤكد الإلغاء؛ راجع المكالمة الحالية من شاشة الهاتف." }
            "تم إلغاء الاختبار وإيقاف الالتقاط."
        } }
        button("٥ • قراءة نتيجة آخر اختبار") { work("جاري قراءة النتيجة…") {
            val fields = CaptureDaemon.testResult(this) ?: error("المساعد لم يرسل نتيجة")
            reportPrefs.edit().putString("test_result", JSONObject(fields).toString())
                .putString("far_end_heard", "UNKNOWN").commit()
            "إشارات الاختبار: ${JSONObject(fields).toString(2)}\nدي إشارات فنية؛ أكّد السماع من الطرف التاني، وراجع التسجيل من زر الإعداد."
        } }
        button("الطرف التاني أكد سماع الملف") { confirmHearing("YES") }
        button("الطرف التاني لم يسمع الملف") { confirmHearing("NO") }
        button("حفظ تقرير التشخيص JSON") { exporter.launch("ontrack-sim-audio-proof.json") }
        details = text("التسجيلات محلية ويمكن مراجعتها من شاشة الإعداد. لا ربط AI أو حملات في النسخة دي. بعد إعادة تشغيل الموبايل قد يلزم أول فتح للقفل وعودة Wi-Fi.", 14f)
        text("الصوت الأساسي مبني على JemRec / scrcpy — Apache-2.0. إشعارات المصدر والتعديلات مرفقة مع البناء.", 12f)
    }

    private fun confirmHearing(value: String) {
        if (reportPrefs.getString("test_result", null) == null) { status.text = "اقرأ نتيجة الاختبار الأول."; return }
        reportPrefs.edit().putString("far_end_heard", value).apply()
        status.text = if (value == "YES") "تم تسجيل تأكيد الطرف البعيد للسماع؛ التقاط كلامه يحتاج مراجعة التسجيل بشكل مستقل." else "تم تسجيل عدم سماع الملف؛ احفظ التقرير ثم جرّب المسار البديل."
    }

    private fun arm(incoming: Boolean, destination: String = "") {
        val selected = findViewById<RadioButton>(route.checkedRadioButtonId).tag as String
        work("جاري تجهيز مكالمة الاختبار…") {
            check(CaptureDaemon.isSetUp(this)) { "كمّل الإعداد لمرة واحدة أولًا" }
            check(Greeting.present(this)) { "اختار ملف الصوت الأول" }
            check(CaptureDaemon.revive(this, request = CaptureDaemon.Request.USER)) { "مساعد الصوت غير متصل" }
            reportPrefs.edit().remove("test_result").putString("far_end_heard", "UNKNOWN")
                .putString("direction", if (incoming) "INBOUND" else "OUTBOUND")
                .putLong("armed_at_ms", System.currentTimeMillis()).commit()
            AnsweringMachine.setEnabled(this, false)
            AnsweringMachine.setRoute(this, selected)
            CallTracking.setEnabled(this, true)
            RecordingMode.set(this, RecordingMode.ON_DEMAND)
            RecorderSwitch.set(this, true)
            RecorderSwitch.turn(this, true)
            check(CaptureDaemon.setTracking(this)) { "لم يتم تفعيل تتبع المكالمة" }
            check(CaptureDaemon.setAnswering(this)) { "لم يتم تطبيق إعدادات المسار" }
            check(CaptureDaemon.uploadGreeting(this, Greeting.file(this).readBytes())) { "لم يصل ملف الصوت للمساعد" }
            val result = CaptureDaemon.armTest(this, incoming, destination)
            check(result == "TEST armed") { "لم يجهز الاختبار: ${result ?: "لا يوجد رد"}" }
            if (incoming) "جاهز لأول مكالمة واردة خلال ٣ دقائق. اتصل بالشريحة من رقم الاختبار الآن." else "تم طلب مكالمة الاختبار. نتيجة مرور الصوت لم تُحسم بعد."
        }
    }

    private fun work(message: String, force: Boolean = false, action: suspend () -> String) {
        if (busy && !force) return
        pending++; busy = true; controls.forEach { it.isEnabled = false }; status.text = message
        lifecycleScope.launch {
            val result = runCatching { withContext(Dispatchers.IO) { operations.withLock { action() } } }
            status.text = result.getOrElse { "تعذر إكمال الخطوة: ${it.message ?: it.javaClass.simpleName}" }
            pending--; busy = pending > 0; controls.forEach { it.isEnabled = !busy }
        }
    }

    private fun report(): String = JSONObject().apply {
        put("app", "OnTrack SIM Audio Proof"); put("version", "0.1.1-audio-proof")
        put("upstream_commit", "f011062adfb3a2154c4d5a9cc4394032fb4345f2")
        put("generated_at_ms", System.currentTimeMillis())
        put("manufacturer", Build.MANUFACTURER); put("model", Build.MODEL)
        put("android_api", Build.VERSION.SDK_INT); put("android_release", Build.VERSION.RELEASE)
        put("build_fingerprint", Build.FINGERPRINT)
        put("route", AnsweringMachine.route(this@ProofActivity))
        for (key in listOf("voice_sha256", "direction", "far_end_heard")) put(key, reportPrefs.getString(key, "UNKNOWN"))
        for (key in listOf("stage_one", "test_result", "setup_diagnostics")) put(key, JSONObject(reportPrefs.getString(key, "{}")!!))
        put("digital_capture_confirmed", false)
        put("full_duplex_ai_confirmed", false)
        put("note", "Source/build/route signals and manually reported hearing only. Review the remote speech recording separately. No phone numbers or audio included.")
    }.toString(2)
}
