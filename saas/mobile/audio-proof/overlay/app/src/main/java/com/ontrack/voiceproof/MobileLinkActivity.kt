package com.ontrack.voiceproof

import android.content.Intent
import android.os.Bundle
import android.text.InputType
import android.view.View
import android.widget.*
import androidx.activity.ComponentActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.*

class MobileLinkActivity:ComponentActivity(){
    override fun onCreate(savedInstanceState:Bundle?){
        super.onCreate(savedInstanceState)
        val prefs=getSharedPreferences("ontrack_link",MODE_PRIVATE)
        val root=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setPadding(24,60,24,24);layoutDirection=View.LAYOUT_DIRECTION_RTL}
        setContentView(ScrollView(this).apply{addView(root)})
        fun text(s:String)=TextView(this).apply{text=s;textSize=18f;setPadding(0,12,0,12);root.addView(this)}
        text("OnTrack — ربط الشريحة بالموقع")
        text("المحرك والمهمة من لوحة الموقع. ابدأ الربط ثم استقبل مكالمة أو اتصل من تطبيق الهاتف. لا تستخدم اختبار الملف بالتزامن مع الربط.")
        val base=EditText(this).apply{hint="رابط الموقع";setText(prefs.getString("base","https://app.ontrackegy.com/"));inputType=InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI;root.addView(this)}
        val key=EditText(this).apply{hint="مفتاح اتصال الربط";setText(prefs.getString("key",""));inputType=InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD;root.addView(this)}
        val status=text(MobileLinkService.latestStatus)
        Button(this).apply{text="حفظ وتشغيل الربط";root.addView(this);setOnClickListener{
            runCatching{
                val url=MobileLinkService.base(base.text.toString());require(key.text.length>=24){"أدخل مفتاح الربط"}
                prefs.edit().putString("base",url).putString("key",key.text.toString().trim()).commit()
                ContextCompat.startForegroundService(this@MobileLinkActivity,Intent(this@MobileLinkActivity,MobileLinkService::class.java))
            }.onFailure{status.text=it.message}
        }}
        Button(this).apply{text="إيقاف الربط";root.addView(this);setOnClickListener{stopService(Intent(this@MobileLinkActivity,MobileLinkService::class.java))}}
        text("إعدادات المهمة والرد التلقائي على: https://app.ontrackegy.com/saas/mobile/\nاختيار الصوت هنا يستخدم أصوات محرك الموقع الحالي. ملف الصوت المستورد يظل لاختبار الحقن، وليس استنساخًا حيًا لكل رد.")
        lifecycleScope.launch{while(isActive){status.text=MobileLinkService.latestStatus;delay(1000)}}
    }
}
