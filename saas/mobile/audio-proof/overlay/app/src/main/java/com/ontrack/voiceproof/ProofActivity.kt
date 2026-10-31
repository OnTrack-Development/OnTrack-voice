package com.ontrack.voiceproof

import android.content.Intent
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
        val scroll=ScrollView(this).apply{fillViewport=true;addView(stack)}
        setContentView(scroll)
        stack.addView(label("ONTRACK  /  VOICE",15f,red,true))
        stack.addView(label("إعداد اتصال الهاتف",26f,text,true))
        stack.addView(label("إعداد مرة واحدة فقط. هنفتح لك صفحات Android المناسبة ونخليك ترجع تكمل من نفس المكان.",14f,muted))
        status=label("",13f,muted)
        stack.addView(status)
        val maker=Build.MANUFACTURER.lowercase()
        val xiaomi=maker.contains("xiaomi")||maker.contains("redmi")||maker.contains("poco")
        val hint=if(xiaomi)"في شاومي / Redmi / POCO اضغط على إصدار MIUI أو HyperOS عدة مرات داخل حول الهاتف حتى يظهر تفعيل خيارات المطوّر."
                 else "افتح حول الهاتف واضغط على Build number سبع مرات تقريبًا، وقد يطلب الهاتف رمز القفل."
        card(stack,"01  تجهيز Android",
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
        card(stack,"02  اقتران الهاتف",
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
        status.text=if(runCatching{CaptureDaemon.isSetUp(this)}.getOrDefault(false))
            "الجهاز مجهز بالفعل؛ اضغط تشغيل OnTrack Voice." else
            "جهاز: ${Build.MANUFACTURER} ${Build.MODEL} · Android ${Build.VERSION.RELEASE}"
    }
}
