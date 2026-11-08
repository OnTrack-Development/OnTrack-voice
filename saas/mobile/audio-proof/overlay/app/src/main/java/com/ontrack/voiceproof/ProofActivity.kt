package com.ontrack.voiceproof

import android.content.Intent
import android.Manifest
import android.content.pm.PackageManager
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.activity.ComponentActivity
import com.ontrack.voiceproof.capture.*

/**
 * First-run setup wizard. Never tries to programmatically enable developer/USB
 * debugging, never requests Accessibility or silently changes system settings.
 */
class ProofActivity:ComponentActivity(){
    private val red=Color.rgb(227,48,76)
    private val bg=Color.rgb(11,16,27)
    private val card=Color.rgb(25,35,54)
    private val text=Color.rgb(245,248,252)
    private val muted=Color.rgb(168,185,208)
    private lateinit var status:TextView
    private fun dp(n:Int)=(n*resources.displayMetrics.density).toInt()
    private fun shape(color:Int,radius:Int=17)=GradientDrawable().apply{
        setColor(color);cornerRadius=dp(radius).toFloat()
    }
    private fun label(textValue:String,size:Float=15f,color:Int=text,bold:Boolean=false)=TextView(this).apply{
        text=textValue;textSize=size;setTextColor(color)
        if(bold)typeface=Typeface.create("sans-serif-medium",Typeface.BOLD)
        setPadding(0,dp(5),0,dp(7))
    }
    private fun button(title:String,onClick:()->Unit)=Button(this).apply{
        text=title;isAllCaps=false;textSize=14f
        setTextColor(Color.WHITE);background=shape(red,12)
        setPadding(dp(10),dp(10),dp(10),dp(10))
        setOnClickListener{onClick()}
    }
    private fun settings(vararg actions:String){
        for(action in actions){
            try{
                val intent=Intent(action).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                if(intent.resolveActivity(packageManager)!=null){startActivity(intent);return}
            }catch(_:Exception){}
        }
        status.text="تعذر فتح الصفحة تلقائيًا؛ افتح إعدادات الهاتف ثم ابحث عن خيارات المطوّر."
        startActivity(Intent(Settings.ACTION_SETTINGS))
    }
    private fun card(parent:LinearLayout,title:String,description:String,buttonTitle:String,onClick:()->Unit){
        val panel=LinearLayout(this).apply{
            orientation=LinearLayout.VERTICAL;setPadding(dp(18),dp(17),dp(18),dp(18))
            background=shape(card)
        }
        panel.addView(label(title,17f,text,true))
        panel.addView(label(description,13f,muted))
        panel.addView(button(buttonTitle,onClick),LinearLayout.LayoutParams(-1,dp(51)).apply{topMargin=dp(5)})
        parent.addView(panel,LinearLayout.LayoutParams(-1,-2).apply{bottomMargin=dp(13)})
    }
    override fun onCreate(savedInstanceState:Bundle?){
        super.onCreate(savedInstanceState)
        window.statusBarColor=bg;window.navigationBarColor=bg
        val stack=LinearLayout(this).apply{
            orientation=LinearLayout.VERTICAL;setPadding(dp(18),dp(30),dp(18),dp(36))
            layoutDirection=View.LAYOUT_DIRECTION_RTL;setBackgroundColor(bg)
        }
        val scroll=ScrollView(this).apply{isFillViewport=true;addView(stack)}
        setContentView(scroll)
        stack.addView(label("ONTRACK  /  VOICE",15f,red,true))
        stack.addView(label("إعداد اتصال الهاتف",26f,text,true))
        stack.addView(label("إعداد مرة واحدة فقط. هنفتح لك صفحات Android المناسبة ونخليك ترجع تكمل من نفس المكان.",14f,muted))
        status=label("",13f,muted)
        stack.addView(status)
        val diagnosticBox=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL}
        fun goFix(fix:CheckAction) {
            when(fix){
                CheckAction.NONE->Unit
                CheckAction.DEVICE_INFO->settings(Settings.ACTION_DEVICE_INFO_SETTINGS,Settings.ACTION_SETTINGS)
                CheckAction.DEVELOPER->settings(Settings.ACTION_APPLICATION_DEVELOPMENT_SETTINGS,Settings.ACTION_SETTINGS)
                CheckAction.PAIR->{
                    // Pairing is an Android-protected, owner-approved action.
                    // Never issue a factory reset or delete stored ADB identity.
                    try{startActivity(DeepLink.intent(this,DeepLink.Kind.PAIR_AGAIN))}
                    catch(_:Exception){startActivity(Intent(this,MainActivity::class.java))}
                }
                CheckAction.RECONNECT->{
                    status.text="بنجرب نستعيد اتصال ADB الموجود من غير إعادة اقتران…"
                    lifecycleScope.launch{
                        val ok=withContext(Dispatchers.IO){
                            com.ontrack.voiceproof.adb.AdbTransport
                                .autoConnect(this@ProofActivity,timeoutMs=5000).isSuccess
                        }
                        status.text=if(ok)"تم استعادة اتصال ADB الحالي. اضغط فحص للتأكد من محرك الصوت."
                            else "الاتصال السابق مش متاح دلوقتي؛ فعّل Wireless Debugging ثم جرّب فحص مجددًا."
                    }
                }
                CheckAction.PHONE_PERMISSION->requestPermissions(
                    arrayOf(Manifest.permission.READ_PHONE_STATE),401)
                CheckAction.OPEN_VOICE->startActivity(Intent(this,MobileLinkActivity::class.java))
            }
        }
        fun scan(){
            status.text="بنفحص الصلاحيات ومحرك صوت المكالمة، من غير تسجيل أو إرسال بيانات…"
            diagnosticBox.removeAllViews()
            lifecycleScope.launch{
                val results=withContext(Dispatchers.IO){
                    fun global(name:String):Boolean?=try{
                        Settings.Global.getInt(contentResolver,name)!=0
                    }catch(_:Exception){null}
                    val facts=VoiceSetupFacts(
                        developer=global(Settings.Global.DEVELOPMENT_SETTINGS_ENABLED),
                        usb=global(Settings.Global.ADB_ENABLED),
                        wireless=global("adb_wifi_enabled"),
                        adbPaired=com.ontrack.voiceproof.ui.Setup.hasPaired(this@ProofActivity),
                        adbConnected=com.ontrack.voiceproof.adb.AdbTransport.isConnected,
                        secureSettings=com.ontrack.voiceproof.ui.Setup.hasSecureSettings(this@ProofActivity),
                        setupComplete=com.ontrack.voiceproof.ui.Setup.isComplete(this@ProofActivity),
                        pcmDaemon=CaptureDaemon.isRunning(this@ProofActivity),
                        readPhone=ContextCompat.checkSelfPermission(this@ProofActivity,
                            Manifest.permission.READ_PHONE_STATE)==PackageManager.PERMISSION_GRANTED,
                        pairedSim=TenantDeviceEnrollment(this@ProofActivity).hasAny()
                    )
                    OnTrackSetupChecklist.evaluate(facts)
                }
                val needed=results.count{it.state==CheckState.ACTION}
                status.text=if(needed==0)"✅ الفحص انتهى. شغّل المساعد من مركز الهاتف؛ مفيش تسجيل مكالمات."
                    else "الفحص انتهى: $needed خطوة محتاجة إجراء. اضغط فتح بجانب الخطوة الناقصة."
                for(check in results){
                    val color=when(check.state){
                        CheckState.READY->Color.rgb(94,224,153)
                        CheckState.ACTION->red
                        CheckState.UNKNOWN->Color.rgb(255,190,93)
                        CheckState.OPTIONAL->muted
                    }
                    val prefix=when(check.state){
                        CheckState.READY->"✓ "
                        CheckState.ACTION->"✕ "
                        CheckState.UNKNOWN->"? "
                        CheckState.OPTIONAL->"ℹ "
                    }
                    val panel=LinearLayout(this@ProofActivity).apply{
                        orientation=LinearLayout.VERTICAL
                        setPadding(dp(14),dp(12),dp(14),dp(12))
                        background=shape(card,14)
                    }
                    panel.addView(label(prefix+check.title,15f,color,true))
                    panel.addView(label(check.detail,13f,muted))
                    if(check.action!=CheckAction.NONE)
                        panel.addView(button(check.actionLabel){goFix(check.action)})
                    diagnosticBox.addView(panel,LinearLayout.LayoutParams(-1,-2).apply{
                        bottomMargin=dp(9)
                    })
                }
            }
        }
        stack.addView(button("فحص وتجهيز الهاتف — بدون Root أو تسجيل"){scan()},
            LinearLayout.LayoutParams(-1,dp(56)).apply{bottomMargin=dp(12)})
        stack.addView(diagnosticBox)
        val maker=Build.MANUFACTURER.lowercase()
        val xiaomi=maker.contains("xiaomi")||maker.contains("redmi")||maker.contains("poco")
        val hint=if(xiaomi)"في شاومي / Redmi / POCO اضغط على إصدار MIUI أو HyperOS عدة مرات داخل حول الهاتف حتى يظهر تفعيل خيارات المطوّر."
                 else "افتح حول الهاتف واضغط على Build number سبع مرات تقريبًا، وقد يطلب الهاتف رمز القفل."
        // Paired users with a live PCM daemon MUST NOT see developer settings
        // as compulsory. Only show these manual instructions on demand.
        val manual=LinearLayout(this).apply{
            orientation=LinearLayout.VERTICAL
            visibility=View.GONE
        }
        stack.addView(button("عرض إعدادات Android اليدوية عند الحاجة"){
            manual.visibility=if(manual.visibility==View.VISIBLE)View.GONE else View.VISIBLE
        },LinearLayout.LayoutParams(-1,dp(52)).apply{bottomMargin=dp(12)})
        stack.addView(manual)
        card(manual,"01  تجهيز Android",
            "الخطوة الأولى تفتح صفحة إعدادات الهاتف. فعّل خيارات المطوّر بالضغط على إصدار النظام، ثم USB debugging وWireless debugging من خيارات المطوّر. " +
            (if(xiaomi)"في Xiaomi / POCO قد تحتاج USB debugging (Security settings). " else "") +
            "الإعدادات المحمية يفعّلها صاحب الجهاز بنفسه.",
            "فتح إعداد الهاتف المناسب"){
            val developerEnabled=runCatching{
                Settings.Global.getInt(contentResolver,Settings.Global.DEVELOPMENT_SETTINGS_ENABLED,0)!=0
            }.getOrDefault(false)
            if(developerEnabled)settings(Settings.ACTION_APPLICATION_DEVELOPMENT_SETTINGS,Settings.ACTION_SETTINGS)
            else settings(Settings.ACTION_DEVICE_INFO_SETTINGS,Settings.ACTION_SETTINGS)
        }
        card(manual,"02  اقتران الهاتف",
            "لما تخلص تفعيل Wireless debugging، اضغط هنا لفتح الاقتران واتبع الكود الظاهر من Android. لو الجهاز متصل بالفعل، تخطّى الخطوة دي.",
            "ربط OnTrack تلقائيًا"){
            try{startActivity(DeepLink.intent(this,DeepLink.Kind.PAIR_AGAIN))}
            catch(_:Exception){startActivity(Intent(this,MainActivity::class.java))}
        }
        val open=button("العودة إلى مركز OnTrack Voice"){
            startActivity(Intent(this,MobileLinkActivity::class.java));finish()
        }
        stack.addView(open,LinearLayout.LayoutParams(-1,dp(52)).apply{bottomMargin=dp(14)})
        card(stack,"فحص جاهزية الاتصال","فحص سريع لحالة مساعد Android. لو مكتمل، مش محتاج تدخل صفحات إعدادات المطور تاني.",
            "فحص الاتصال"){
            status.text=try{
                if(CaptureDaemon.isSetUp(this))"الإعداد الأساسي مكتمل. افتح مركز الاتصال وشغّل الإيجنت."
                else "الربط الأولي غير مكتمل؛ استخدم الخطوات السابقة."
            }catch(_:Exception){"تعذر فحص المساعد الآن؛ راجع Wireless debugging والاقتران."}
        }
        status.text="جهاز: ${Build.MANUFACTURER} ${Build.MODEL} · Android ${Build.VERSION.RELEASE}"
        scan()
    }
}
