package com.ontrack.voiceproof

import android.app.*
import android.content.Intent
import android.net.ConnectivityManager
import android.net.Network
import android.os.IBinder
import android.os.Handler
import android.os.Looper
import android.os.SystemClock
import androidx.core.app.NotificationCompat
import com.ontrack.voiceproof.capture.*
import kotlinx.coroutines.*
import kotlinx.coroutines.channels.Channel
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.net.URI
import java.util.concurrent.TimeUnit

/**
 * One persistent device connector, with central server desired state and
 * device-applied acknowledgment. Wireless network transitions wake a bounded
 * recovery loop; the service never quits simply because a request failed.
 */
class MobileLinkService:Service() {
    companion object {
        const val ACTION_ENABLE="com.ontrack.voiceproof.ENABLE"
        const val ACTION_DISABLE="com.ontrack.voiceproof.DISABLE"
        const val ACTION_TAKEOVER="com.ontrack.voiceproof.TAKEOVER"
        const val ACTION_SPEAKER="com.ontrack.voiceproof.SPEAKER"
        // Kept for pre-0.1.9 notifications, mapped to server disable.
        const val ACTION_PAUSE="com.ontrack.voiceproof.PAUSE"
        @Volatile var running=false
        @Volatile var agentEnabled=false
        @Volatile var serverRevision=0
        @Volatile var inCall=false
        @Volatile var humanControl=false
        @Volatile var latestStatus="الربط متوقف"
        fun base(value:String):String {
            val uri=URI(value.trim())
            require(uri.scheme=="https" && uri.host=="app.ontrackegy.com" && uri.port in listOf(-1,443)
                && uri.userInfo==null && uri.query==null && uri.fragment==null && uri.path in listOf("","/"))
            return "https://app.ontrackegy.com/"
        }
    }
    private val scope=CoroutineScope(SupervisorJob()+Dispatchers.IO)
    private val main=Handler(Looper.getMainLooper())
    private val retryWake=Channel<Unit>(Channel.CONFLATED)
    private val client=OkHttpClient.Builder().followRedirects(false).followSslRedirects(false)
        .connectTimeout(8,TimeUnit.SECONDS).readTimeout(15,TimeUnit.SECONDS).pingInterval(15,TimeUnit.SECONDS).build()
    private lateinit var overlay:CallOverlay
    private var call:MobileLiveCall?=null
    private var loop:Job?=null
    private var connectivity:ConnectivityManager?=null
    private var networkListener:ConnectivityManager.NetworkCallback?=null
    @Volatile private var takeoverRequested=false
    @Volatile private var pendingDesired:Boolean?=null
    private fun prefs()=getSharedPreferences("ontrack_link",MODE_PRIVATE)
    private fun showOverlay(){main.post{if(::overlay.isInitialized)overlay.show(running,agentEnabled,inCall,humanControl)}}
    private fun notice(value:String){
        latestStatus=value
        getSystemService(NotificationManager::class.java).notify(701,notification(value))
        showOverlay()
    }
    private fun pending(action:String,id:Int)=PendingIntent.getService(
        this,id,Intent(this,MobileLinkService::class.java).setAction(action),
        PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
    private fun notification(value:String):Notification {
        val open=PendingIntent.getActivity(this,0,Intent(this,MobileLinkActivity::class.java),PendingIntent.FLAG_IMMUTABLE)
        val item=NotificationCompat.Builder(this,"ontrack_mobile_link")
            .setSmallIcon(android.R.drawable.sym_call_incoming)
            .setContentTitle("OnTrack Voice").setContentText(value)
            .setOngoing(true).setContentIntent(open)
        if(inCall && !humanControl)item.addAction(android.R.drawable.ic_menu_view,"استلام المكالمة",pending(ACTION_TAKEOVER,1))
        if(inCall)item.addAction(android.R.drawable.ic_menu_manage,"السماعة",pending(ACTION_SPEAKER,2))
        item.addAction(android.R.drawable.ic_media_pause,if(agentEnabled)"إيقاف الإيجنت" else "تشغيل الإيجنت",
            pending(if(agentEnabled) ACTION_DISABLE else ACTION_ENABLE,3))
        return item.build()
    }
    override fun onCreate(){
        super.onCreate()
        overlay=CallOverlay(this)
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel("ontrack_mobile_link","OnTrack Voice",NotificationManager.IMPORTANCE_LOW))
        connectivity=getSystemService(ConnectivityManager::class.java)
        val callback=object:ConnectivityManager.NetworkCallback(){
            override fun onAvailable(network:Network){client.connectionPool.evictAll();retryWake.trySend(Unit)}
            override fun onLost(network:Network){retryWake.trySend(Unit)}
        }
        runCatching{connectivity?.registerDefaultNetworkCallback(callback)}
            .onSuccess{networkListener=callback}
    }
    override fun onBind(intent:Intent?):IBinder?=null
    override fun onStartCommand(intent:Intent?,flags:Int,startId:Int):Int {
        startForeground(701,notification(latestStatus))
        val action=intent?.action
        when(action){
            ACTION_TAKEOVER->{
                if(inCall && !humanControl){
                    takeoverRequested=true;humanControl=true
                    // The shell socket returns the microphone mute to its original state.
                    call?.stop();call=null
                    notice("تم استلام المكالمة الحالية • المكالمة القادمة للـAI")
                }
                return START_STICKY
            }
            ACTION_SPEAKER->{
                if(inCall)scope.launch{
                    runCatching{CaptureDaemon.linkControl(this@MobileLinkService,'S')}
                        .onSuccess{notice(if(it?.startsWith("SPEAKER ")==true)
                            "السماعة: "+it.removePrefix("SPEAKER ") else "لم يؤكد الجهاز تغيير السماعة")}
                        .onFailure{notice("السماعة غير متاحة في مسار المكالمة")}
                }
                return START_STICKY
            }
            ACTION_ENABLE,ACTION_DISABLE,ACTION_PAUSE,"STOP"->{
                val value=action==ACTION_ENABLE
                pendingDesired=value
                if(!value) {
                    agentEnabled=false
                    if(inCall){takeoverRequested=true;humanControl=true;call?.stop();call=null}
                }
                notice(if(value)"جاري مزامنة التشغيل مع السيرفر…" else "جاري إيقاف الإيجنت على السيرفر…")
                retryWake.trySend(Unit)
            }
        }
        if(loop?.isActive!=true){
            running=true
            loop=scope.launch{monitor()}
        }
        return START_STICKY
    }
    private suspend fun monitor(){
        var backoff=2000L
        // A single active monitor remains alive across Wi-Fi handoffs and server errors.
        while(scope.isActive){
            try{
                val base=base(prefs().getString("base","")?:"")
                val key=prefs().getString("key","")?:""
                require(key.length>=24){"أدخل مفتاح اتصال الربط"}
                pendingDesired?.let{desired->
                    val result=request(base+"saas/mobile/control.php",key,JSONObject().put("enabled",desired))
                    agentEnabled=result.getBoolean("enabled")
                    serverRevision=result.getInt("revision")
                    if(pendingDesired==desired) pendingDesired=null
                }
                // Re-attaches the local ADB assistant if its transport was lost on Wi-Fi switch.
                check(CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)){
                    "مساعد الاتصال يحتاج إعادة الاتصال؛ تحقق من Wireless debugging"
                }
                AnsweringMachine.setEnabled(this@MobileLinkService,false)
                CaptureDaemon.setAnswering(this@MobileLinkService)
                RecorderSwitch.set(this@MobileLinkService,false)
                check(CaptureDaemon.setDaemonMode(this@MobileLinkService,CaptureDaemon.MODE_OFF))
                backoff=2000L
                var settings=JSONObject()
                var lastFetch=0L
                var lastReport=0L
                var lastState=-1
                var callSeen=false
                var ringingAt=0L
                var answerIssued=false
                while(scope.isActive){
                    if(pendingDesired!=null){
                        val desired=pendingDesired!!
                        val result=request(base+"saas/mobile/control.php",key,JSONObject().put("enabled",desired))
                        agentEnabled=result.getBoolean("enabled")
                        serverRevision=result.getInt("revision")
                        if(pendingDesired==desired)pendingDesired=null
                        lastFetch=0
                    }
                    if(SystemClock.elapsedRealtime()-lastFetch>=3000 || lastFetch==0L){
                        settings=request(base+"saas/mobile/device.php",key,null).getJSONObject("settings")
                        serverRevision=settings.optInt("revision",0)
                        agentEnabled=settings.optBoolean("enabled",false) && pendingDesired!=false
                        lastFetch=SystemClock.elapsedRealtime()
                    }
                    val raw=CaptureDaemon.linkControl(this@MobileLinkService,'Q')?:error("مساعد الشريحة غير متصل")
                    val state=raw.substringAfter("CALL ").toIntOrNull()?:error("حالة الاتصال غير مفهومة")
                    inCall=state==1||state==2
                    if(state==1 && lastState!=1){ringingAt=SystemClock.elapsedRealtime();answerIssued=false;callSeen=false;takeoverRequested=false;humanControl=false}
                    if(state==2 && ringingAt!=0L && !answerIssued && !callSeen){
                        takeoverRequested=true;humanControl=true;callSeen=true
                    }
                    if(!agentEnabled){
                        call?.stop();call=null
                        if(state==2){takeoverRequested=true;humanControl=true;callSeen=true}
                    }else{
                        if(state==1 && settings.optBoolean("auto_answer",false) && !answerIssued && !takeoverRequested){
                            val wait=settings.optInt("answer_delay_seconds",5).coerceIn(0,30)*1000L
                            if(SystemClock.elapsedRealtime()-ringingAt>=wait){
                                answerIssued=true
                                CaptureDaemon.linkControl(this@MobileLinkService,'A')
                            }
                        }
                        if(state==2 && !callSeen && !takeoverRequested){
                            callSeen=true
                            try{
                                notice("جاري توصيل المكالمة بالإيجنت")
                                val session=request(base+"saas/mobile/session.php",key,JSONObject().put("mode","demo"))
                                if(!takeoverRequested && agentEnabled){
                                    val newCall=MobileLiveCall(this@MobileLinkService,client,scope,session,settings,::notice)
                                    call=newCall;newCall.start()
                                    if(takeoverRequested || !agentEnabled){newCall.stop();call=null}
                                }
                            }catch(t:Throwable){
                                call?.stop();call=null
                                notice("فشل جلسة الصوت، المكالمة تحت تحكم الهاتف")
                            }
                        }
                    }
                    if(state==0 && lastState!=0){
                        call?.stop();call=null;callSeen=false;ringingAt=0;answerIssued=false
                        takeoverRequested=false;humanControl=false
                    }
                    lastState=state
                    if(SystemClock.elapsedRealtime()-lastReport>4000){
                        lastReport=SystemClock.elapsedRealtime()
                        val phase=when{
                            state==0->"idle"
                            state==1->"ringing"
                            humanControl->"human"
                            call?.active==true->"ai"
                            else->"connecting"
                        }
                        val status=JSONObject().put("phase",phase)
                            .put("agent_running",agentEnabled)
                            .put("revision",serverRevision)
                        scope.launch{runCatching{request(base+"saas/mobile/status.php",key,status)}}
                    }
                    if(state==0)notice(if(agentEnabled)"متصل • الإيجنت جاهز" else "متصل • الإيجنت متوقف")
                    showOverlay()
                    delay(800)
                }
            }catch(t:Throwable){
                if(t is CancellationException)throw t
                call?.stop();call=null
                inCall=false;humanControl=false;takeoverRequested=false
                notice("إعادة الاتصال تلقائيًا • "+t.javaClass.simpleName)
                withTimeoutOrNull(backoff){retryWake.receive()}
                backoff=(backoff*2).coerceAtMost(30000L)
            }
        }
    }
    private fun request(url:String,key:String,body:JSONObject?):JSONObject{
        val builder=Request.Builder().url(url).header("Authorization","Bearer $key")
        if(body!=null)builder.post(body.toString().toRequestBody("application/json".toMediaType()))
        client.newCall(builder.build()).execute().use{
            check(it.isSuccessful){"HTTP ${it.code}"}
            val text=it.body?.string()?:error("empty response")
            check(text.length<=200000){"response limit"}
            val value=JSONObject(text);check(value.optBoolean("ok")){"server refused request"}
            return value
        }
    }
    override fun onDestroy(){
        networkListener?.let{runCatching{connectivity?.unregisterNetworkCallback(it)}}
        call?.stop();scope.cancel();running=false;inCall=false;agentEnabled=false;humanControl=false
        overlay.remove();latestStatus="الربط متوقف"
        super.onDestroy()
    }
}
