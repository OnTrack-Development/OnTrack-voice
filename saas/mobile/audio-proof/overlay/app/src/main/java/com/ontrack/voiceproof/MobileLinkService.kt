package com.ontrack.voiceproof

import android.app.*
import android.content.Intent
import android.os.IBinder
import androidx.core.app.NotificationCompat
import com.ontrack.voiceproof.capture.*
import kotlinx.coroutines.*
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.net.URI
import java.util.concurrent.TimeUnit

class MobileLinkService:Service() {
    companion object {
        @Volatile var running=false
        @Volatile var inCall=false
        @Volatile var latestStatus="الربط متوقف"
        fun base(value:String):String {
            val uri=URI(value.trim())
            require(uri.scheme=="https" && uri.host=="app.ontrackegy.com" && uri.port in listOf(-1,443)
                && uri.userInfo==null && uri.query==null && uri.fragment==null && uri.path in listOf("","/")) {"استخدم https://app.ontrackegy.com/"}
            return "https://app.ontrackegy.com/"
        }
    }
    private val scope=CoroutineScope(SupervisorJob()+Dispatchers.IO)
    private val client=OkHttpClient.Builder().followRedirects(false).followSslRedirects(false)
        .connectTimeout(10,TimeUnit.SECONDS).readTimeout(30,TimeUnit.SECONDS).pingInterval(15,TimeUnit.SECONDS).build()
    private var call:MobileLiveCall?=null
    private var loop:Job?=null
    private fun notice(value:String){latestStatus=value;getSystemService(NotificationManager::class.java).notify(701,notification(value))}
    private fun notification(text:String):Notification {
        val open=PendingIntent.getActivity(this,0,Intent(this,MobileLinkActivity::class.java),PendingIntent.FLAG_IMMUTABLE)
        val stop=PendingIntent.getService(this,1,Intent(this,MobileLinkService::class.java).setAction("STOP"),PendingIntent.FLAG_IMMUTABLE)
        return NotificationCompat.Builder(this,"ontrack_mobile_link").setSmallIcon(android.R.drawable.sym_call_incoming)
            .setContentTitle("OnTrack — ربط الشريحة").setContentText(text).setOngoing(true).setContentIntent(open)
            .addAction(android.R.drawable.ic_menu_close_clear_cancel,"إيقاف الربط",stop).build()
    }
    override fun onCreate(){super.onCreate();getSystemService(NotificationManager::class.java).createNotificationChannel(NotificationChannel("ontrack_mobile_link","ربط المكالمات",NotificationManager.IMPORTANCE_LOW))}
    override fun onBind(intent:Intent?):IBinder?=null
    override fun onStartCommand(intent:Intent?,flags:Int,startId:Int):Int {
        if(intent?.action=="STOP"){stopSelf();return START_NOT_STICKY}
        startForeground(701,notification("جاري الاتصال بالموقع"))
        if(loop?.isActive==true)return START_NOT_STICKY
        running=true
        loop=scope.launch {
            val prefs=getSharedPreferences("ontrack_link",MODE_PRIVATE)
            try {
                val base=base(prefs.getString("base","")?:"")
                val key=prefs.getString("key","")?:""
                require(key.length>=24){"أدخل مفتاح اتصال الربط"}
                check(CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)){"مساعد الشريحة غير متصل؛ أكمل إعداد التطبيق"}
                AnsweringMachine.setEnabled(this@MobileLinkService,false)
                CaptureDaemon.setAnswering(this@MobileLinkService)
                // A single live reader owns AudioRecord; avoid competing file capture.
                RecorderSwitch.set(this@MobileLinkService,false)
                check(CaptureDaemon.setDaemonMode(this@MobileLinkService,CaptureDaemon.MODE_OFF))
                var settings=JSONObject();var lastFetch=0L;var callSeen=false
                while(isActive) {
                    if(System.currentTimeMillis()-lastFetch>=5000) {
                        settings=request(base+"saas/mobile/device.php",key,null).getJSONObject("settings")
                        lastFetch=System.currentTimeMillis()
                    }
                    val state=CaptureDaemon.linkControl(this@MobileLinkService,'Q')?.substringAfter("CALL ")?.toIntOrNull()
                        ?:error("تعذر قراءة حالة الشريحة")
                    inCall=state==2 || state==1
                    if(!settings.optBoolean("enabled")) {
                        call?.stop();call=null;notice("متصل بالموقع — الربط الصوتي مقفول من لوحة الاتصال")
                    }else{
                        if(state==1 && settings.optBoolean("auto_answer")) CaptureDaemon.linkControl(this@MobileLinkService,'A')
                        if(state==2 && !callSeen) {
                            callSeen=true
                            notice("جاري ربط المكالمة بالإيجنت")
                            try {
                                val session=request(base+"saas/mobile/session.php",key,JSONObject().put("mode","demo"))
                                val newCall=MobileLiveCall(this@MobileLinkService,client,scope,session,settings,::notice)
                                call=newCall;newCall.start()
                            }catch(t:Throwable){call?.stop();call=null;notice(t.message?:"فشل ربط المكالمة")}
                        }
                        if(state==0){call?.stop();call=null;callSeen=false;notice("متصل — جاهز لمكالمة واردة أو اتصال من الهاتف")}
                    }
                    delay(1000)
                }
            }catch(t:Throwable){if(t !is CancellationException)notice("تعذر الربط: "+(t.message?:t.javaClass.simpleName));running=false;inCall=false}
        }
        return START_NOT_STICKY
    }
    private fun request(url:String,key:String,body:JSONObject?):JSONObject {
        val builder=Request.Builder().url(url).header("Authorization","Bearer $key")
        if(body!=null)builder.post(body.toString().toRequestBody("application/json".toMediaType()))
        client.newCall(builder.build()).execute().use {
            check(it.isSuccessful){"الموقع رجع HTTP ${it.code}؛ راجع المفتاح وإعدادات الربط"}
            val text=it.body?.string()?:error("رد الموقع فارغ")
            check(text.length<=200000){"رد الموقع أكبر من المتوقع"}
            val data=JSONObject(text);check(data.optBoolean("ok")){"الموقع لم يجهز الاتصال"};return data
        }
    }
    override fun onDestroy(){call?.stop();scope.cancel();running=false;inCall=false;latestStatus="الربط متوقف";super.onDestroy()}
}
