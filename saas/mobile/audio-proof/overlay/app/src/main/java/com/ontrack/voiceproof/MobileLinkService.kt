package com.ontrack.voiceproof

import android.app.*
import android.content.Intent
import android.net.ConnectivityManager
import android.net.Network
import android.os.IBinder
import android.os.Handler
import android.os.Looper
import android.os.SystemClock
import android.os.Build
import android.telephony.TelephonyManager
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
        const val ACTION_CAMPAIGN_ENABLE="com.ontrack.voiceproof.CAMPAIGN_ENABLE"
        const val ACTION_CAMPAIGN_DISABLE="com.ontrack.voiceproof.CAMPAIGN_DISABLE"
        // Kept for pre-0.1.9 notifications, mapped to server disable.
        const val ACTION_PAUSE="com.ontrack.voiceproof.PAUSE"
        /** Blocks pilot telephony during a tenant enrollment, even before token persistence. */
        @Volatile var tenantPairingInProgress=false
        @Volatile var running=false
        @Volatile var agentEnabled=false
        @Volatile var connected=false
        @Volatile var serverRevision=0
        @Volatile var inCall=false
        @Volatile var callAnswered=false
        @Volatile var pendingSync=false
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
    private var callEventFlush:Job?=null
    private lateinit var callEventOutbox:CallEventOutbox
    private lateinit var campaignDialer:CampaignDialer
    private var connectivity:ConnectivityManager?=null
    private var networkListener:ConnectivityManager.NetworkCallback?=null
    @Volatile private var takeoverRequested=false
    @Volatile private var pendingDesired:Boolean?=null
    @Volatile private var recoveringActiveCall=false
    private fun prefs()=getSharedPreferences("ontrack_link",MODE_PRIVATE)
    private fun setPending(value:Boolean){
        pendingDesired=value
        pendingSync=true
        // Offline STOP persists even if the OS kills the service before reconnect.
        prefs().edit().putString("pending_agent_enabled",value.toString()).commit()
    }
    private fun confirmPending(value:Boolean) {
        if(pendingDesired==value) {
            prefs().edit().remove("pending_agent_enabled").commit()
            pendingDesired=null
            pendingSync=false
        }
    }
    private fun showOverlay(){main.post{if(::overlay.isInitialized)overlay.show(connected,agentEnabled,inCall,callAnswered,humanControl)}}
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
        val callLabel=when{
            inCall && humanControl->"أنت استلمت المكالمة من الإيجنت"
            inCall && !callAnswered->"مكالمة قيد الرنين أو الاتصال"
            inCall && agentEnabled->"مكالمة متصلة — الإيجنت قيد التشغيل"
            else->value
        }
        val item=NotificationCompat.Builder(this,"ontrack_mobile_link")
            .setSmallIcon(android.R.drawable.ic_menu_call)
            .setContentTitle("OnTrack Voice").setContentText(callLabel)
            .setOngoing(true).setContentIntent(open)
        if(inCall && callAnswered && agentEnabled && !humanControl)
            item.addAction(android.R.drawable.ic_menu_myplaces,"استلام المكالمة من الإيجنت",pending(ACTION_TAKEOVER,1))
        if(inCall && callAnswered)
            item.addAction(android.R.drawable.ic_lock_silent_mode_off,"تبديل مخرج الصوت",pending(ACTION_SPEAKER,2))
        item.addAction(
            if(agentEnabled)android.R.drawable.ic_media_pause else android.R.drawable.ic_media_play,
            if(agentEnabled)"إيقاف الإيجنت" else "تشغيل الإيجنت",
            pending(if(agentEnabled) ACTION_DISABLE else ACTION_ENABLE,3))
        return item.build()
    }
    override fun onCreate(){
        super.onCreate()
        overlay=CallOverlay(this)
        callEventOutbox=CallEventOutbox(this)
        campaignDialer=CampaignDialer(this,{payload->
            request(base(prefs().getString("base","")?:"")+"saas/mobile/campaigns_api.php",
                prefs().getString("key","")?:"",payload)
        },::notice)
        pendingDesired=when(prefs().getString("pending_agent_enabled",null)){
            "true"->true
            "false"->false
            else->null
        }
        pendingSync=pendingDesired!=null
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel("ontrack_mobile_link","OnTrack Voice",NotificationManager.IMPORTANCE_LOW))
        connectivity=getSystemService(ConnectivityManager::class.java)
        val callback=object:ConnectivityManager.NetworkCallback(){
            override fun onAvailable(network:Network){client.connectionPool.evictAll();retryWake.trySend(Unit)}
            override fun onLost(network:Network){connected=false;retryWake.trySend(Unit)}
        }
        runCatching{connectivity?.registerDefaultNetworkCallback(callback)}
            .onSuccess{networkListener=callback}
    }
    override fun onBind(intent:Intent?):IBinder?=null
    override fun onStartCommand(intent:Intent?,flags:Int,startId:Int):Int {
        startForeground(701,notification(latestStatus))
        if(tenantPairingInProgress){
            call?.stop();call=null;agentEnabled=false;connected=false
            notice("ربط الشرائح قيد التنفيذ؛ الإيجنت متوقف لحين التحقق")
            stopSelf();return START_NOT_STICKY
        }
        if(TenantDeviceEnrollment(this).hasAny()){
            // Tenant mode NEVER executes the global pilot monitor or its session endpoint.
            val action=intent?.action
            when(action){
                ACTION_DISABLE,ACTION_PAUSE,"STOP"->{
                    tenantPrefs().edit().putBoolean("enabled",false).commit()
                    agentEnabled=false;call?.stop();call=null
                    notice("إيقاف محرك الشركات على هذا الجهاز")
                }
                ACTION_ENABLE->{
                    tenantPrefs().edit().putBoolean("enabled",true).commit()
                    agentEnabled=true
                    notice("تشغيل مراقب الشرائح المستقلة — بانتظار اعتماد المحرك المنشور")
                }
                ACTION_TAKEOVER->{
                    humanControl=true;call?.stop();call=null
                    notice("استلام بشري للمكالمة الحالية")
                }
                ACTION_SPEAKER->scope.launch{
                    runCatching{CaptureDaemon.linkControl(this@MobileLinkService,'S')}
                        .onSuccess{notice("تم طلب تبديل مخرج الصوت")}
                        .onFailure{notice("تعذر تبديل الصوت على الجهاز")}
                }
            }
            if(loop?.isActive!=true){
                running=true
                loop=scope.launch{tenantMonitor()}
            }
            return START_STICKY
        }
        // Native release 0.3.4: never start the global, legacy phone pilot for
        // an unpaired device. A SIM-specific verified tenant binding is required.
        prefs().edit().putBoolean("campaign_opt_in",false).commit()
        agentEnabled=false;connected=false
        call?.stop();call=null
        notice("اربط شريحة SIM بشركتك وإيجنت منشور قبل تشغيل المحرك الجديد")
        stopSelf()
        return START_NOT_STICKY
        @Suppress("UNREACHABLE_CODE")
        val action=intent?.action
        when(action){
            ACTION_CAMPAIGN_DISABLE->{
                prefs().edit().putBoolean("campaign_opt_in",false).commit()
                notice("تم إيقاف تنفيذ الحملات على الشريحة")
                return START_STICKY
            }
            ACTION_CAMPAIGN_ENABLE->{
                val permitted=androidx.core.content.ContextCompat.checkSelfPermission(
                    this,android.Manifest.permission.CALL_PHONE)==android.content.pm.PackageManager.PERMISSION_GRANTED
                if(permitted && prefs().getString("key","").orEmpty().length>=24) {
                    prefs().edit().putBoolean("campaign_opt_in",true).commit()
                    notice("الحملات مفعلة — للجدول المعتمد والأرقام المصرح بها فقط")
                }else notice("يجب منح إذن الاتصال وربط الهاتف قبل تفعيل الحملات")
                return START_STICKY
            }
            ACTION_TAKEOVER->{
                if(callAnswered && !humanControl){
                    takeoverRequested=true;humanControl=true
                    // The shell socket returns the microphone mute to its original state.
                    call?.stop();call=null
                    notice("استلمت المكالمة من الإيجنت • التحكم بشري الآن، والمكالمة القادمة للـAI")
                }
                return START_STICKY
            }
            ACTION_SPEAKER->{
                if(callAnswered)scope.launch{
                    runCatching{CaptureDaemon.linkControl(this@MobileLinkService,'S')}
                        .onSuccess{notice(if(it?.startsWith("SPEAKER ")==true)
                            "السماعة: "+it.removePrefix("SPEAKER ") else "لم يؤكد الجهاز تغيير السماعة")}
                        .onFailure{notice("السماعة غير متاحة في مسار المكالمة")}
                }
                return START_STICKY
            }
            ACTION_ENABLE,ACTION_DISABLE,ACTION_PAUSE,"STOP"->{
                val value=action==ACTION_ENABLE
                setPending(value)
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
                if(stopPilotForTenantPairing())return
                val base=base(prefs().getString("base","")?:"")
                val key=prefs().getString("key","")?:""
                require(key.length>=24){"أدخل مفتاح اتصال الربط"}
                pendingDesired?.let{desired->
                    val result=request(base+"saas/mobile/control.php",key,JSONObject().put("enabled",desired))
                    agentEnabled=result.getBoolean("enabled")
                    serverRevision=result.getInt("revision")
                    confirmPending(desired)
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
                    if(stopPilotForTenantPairing())return
                    if(pendingDesired!=null){
                        val desired=pendingDesired!!
                        val result=request(base+"saas/mobile/control.php",key,JSONObject().put("enabled",desired))
                        agentEnabled=result.getBoolean("enabled")
                        serverRevision=result.getInt("revision")
                        confirmPending(desired)
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
                    // Persist device-observed call transitions even when server delivery retries.
                    callEventOutbox.observe(state)
                    runCatching { callEventOutbox.enrichFromCallLog() } // optional consented on-device contact matching
                    runCatching { campaignDialer.tick(state,agentEnabled) }
                        .onFailure { notice("فحص الحملات لم يكتمل: "+it.javaClass.simpleName) }
                    if(callEventFlush?.isActive!=true)callEventFlush=scope.launch {
                        runCatching {
                            val event=callEventOutbox.peek()?:return@runCatching
                            request(base+"saas/mobile/call_event.php",key,event)
                            callEventOutbox.acknowledge(event)
                        }
                    }
                    connected=true
                    inCall=state==1||state==2
                    callAnswered=state==2
                    if(state==1 && lastState!=1){
                        recoveringActiveCall=false // New call; previous manual handoff expired.
                    }
                    if(state==2 && recoveringActiveCall){
                        // Do not attach a brand-new AI voice session in the middle of an old call.
                        takeoverRequested=true;humanControl=true;callSeen=true
                    }
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
                                val prefsNow=prefs()
                                if(prefsNow.getBoolean("campaign_saw_active",false) &&
                                    prefsNow.getString("campaign_pending_attempt","").orEmpty().isNotEmpty()) {
                                    val scenario=prefsNow.getString("campaign_scenario","").orEmpty()
                                    if(scenario.isNotBlank()) {
                                        val fullName=prefsNow.getString("campaign_contact_name","").orEmpty()
                                        val phone=prefsNow.getString("campaign_contact_phone","").orEmpty()
                                        val firstName=fullName.trim().split(Regex("\\s+")).firstOrNull().orEmpty()
                                        val personalized=scenario
                                            .replace("{{contact.full_name}}",fullName.ifBlank{"حضرتك"})
                                            .replace("{{contact.first_name}}",firstName.ifBlank{"حضرتك"})
                                            .replace("{{contact.phone}}",phone)
                                        val instruction=session.getString("system_instruction")
                                        session.put("system_instruction",instruction+
                                            "\\n\\nسيناريو حملة مُعتمدة للمكالمة الصادرة الحالية فقط: \\n"+
                                            personalized.take(8000))
                                    }
                                }
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
                        takeoverRequested=false;humanControl=false;recoveringActiveCall=false
                    }
                    val telephonyStateChanged=state!=lastState
                    lastState=state
                    // Refresh notification actions when ringing turns into an answered call.
                    // Without this, takeover/speaker controls can remain stale until another notice.
                    if(telephonyStateChanged)getSystemService(NotificationManager::class.java)
                        .notify(701,notification(latestStatus))
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
                            .put("connected",connected)
                            .put("pending_sync",pendingSync)
                            .put("revision",serverRevision)
                        scope.launch{runCatching{request(base+"saas/mobile/status.php",key,status)}}
                    }
                    if(state==0)notice(if(agentEnabled)"متصل • الإيجنت جاهز" else "متصل • الإيجنت متوقف")
                    showOverlay()
                    delay(800)
                }
            }catch(t:Throwable){
                if(t is CancellationException)throw t
                if(inCall)recoveringActiveCall=true
                call?.stop();call=null
                inCall=false;callAnswered=false;connected=false
                // Keep human control of the same call after Wi-Fi recovery.
                notice("إعادة الاتصال تلقائيًا • "+t.javaClass.simpleName)
                withTimeoutOrNull(backoff){retryWake.receive()}
                backoff=(backoff*2).coerceAtMost(30000L)
            }
        }
    }
    private fun tenantPrefs()=getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE)
    /** Hardware call state is per subscription (API 31+), not aggregate getCallState().
     * If both SIMs are active/ambiguous, we NEVER bridge their audio to an agent.
     */
    private data class SlotCall(val slot:Int,val state:Int)
    private fun currentSlotCalls():List<SlotCall>{
        check(Build.VERSION.SDK_INT>=31){"Android 12 أو أحدث مطلوب لعزل حالة المكالمة لكل شريحة"}
        val telephony=getSystemService(TelephonyManager::class.java)
            ?:error("خدمة الاتصال غير متاحة")
        return (0..1).mapNotNull{slot->
            val subscription=runCatching{TenantSimHardware.subscriptionId(this,slot)}.getOrNull()
                ?:return@mapNotNull null
            SlotCall(slot,telephony.createForSubscriptionId(subscription).callStateForSubscription)
        }
    }
    private suspend fun tenantMonitor(){
        val enrollment=TenantDeviceEnrollment(this)
        val tenantEvents=CallEventOutbox(this,"ontrack_tenant_call_events")
        var selected:Int?=null
        var lastState=TelephonyManager.CALL_STATE_IDLE
        var ringingAt=0L
        var answeredByAgent=false
        var ringAttempted=false
        var startedAt=0L
        var tenantFlow:JSONObject?=null
        var tenantSession:JSONObject?=null
        var policy=JSONObject()
        var lastError=""
        try{
            while(scope.isActive && enrollment.hasAny() && !tenantPairingInProgress){
                try{
                    agentEnabled=tenantPrefs().getBoolean("enabled",false)
                    if(!agentEnabled){
                        call?.stop();call=null
                        connected=false;inCall=false;callAnswered=false
                        selected=null;tenantFlow=null;tenantSession=null
                        lastState=TelephonyManager.CALL_STATE_IDLE
                        delay(800);continue
                    }
                    val states=currentSlotCalls()
                    val active=states.filter{it.state!=TelephonyManager.CALL_STATE_IDLE}
                    if(active.size>1){
                        call?.stop();call=null;tenantSession=null
                        humanControl=true;connected=false
                        notice("أكثر من شريحة في مكالمة؛ تم منع خلط صوت الشركات")
                        delay(900);continue
                    }
                    val current=active.singleOrNull()
                    tenantEvents.observe(current?.state?:TelephonyManager.CALL_STATE_IDLE,current?.slot?:-1)
                    runCatching{tenantEvents.enrichFromCallLog()}
                    if(callEventFlush?.isActive!=true)callEventFlush=scope.launch {
                        runCatching {
                            val event=tenantEvents.peek()?:return@runCatching
                            val slot=event.optInt("sim_slot",-1)
                            if(slot !in 0..1 || !enrollment.paired(slot))return@runCatching
                            val response=withContext(Dispatchers.IO){
                                enrollment.workspace(slot,"workspace_call_event",event)
                            }
                            if(response.optBoolean("accepted"))tenantEvents.acknowledge(event)
                        }.onFailure { notice("تعذر رفع حدث مكالمة SaaS؛ سيُعاد الإرسال دون تكرار") }
                    }
                    if(current==null){
                        if(selected!=null){
                            call?.stop();call=null;tenantSession=null;tenantFlow=null
                            selected=null;answeredByAgent=false;ringAttempted=false;humanControl=false
                            ringingAt=0L;startedAt=0L
                        }
                        inCall=false;callAnswered=false
                        // Running a monitor is not an authenticated voice connection.
                        connected=false
                        if(lastState!=TelephonyManager.CALL_STATE_IDLE)notice("مراقب الشرائح جاهز؛ اختبر Google والـFlow قبل المكالمة")
                        lastState=TelephonyManager.CALL_STATE_IDLE
                        delay(750);continue
                    }
                    // The observed SIM must be paired; other subscriptions remain human-only.
                    if(!enrollment.paired(current.slot)){
                        if(selected!=null){call?.stop();call=null}
                        selected=null;tenantSession=null;connected=false
                        delay(750);continue
                    }
                    if(selected!=current.slot){
                        selected=current.slot;answeredByAgent=false;ringAttempted=false;tenantFlow=null;tenantSession=null
                        ringingAt=SystemClock.elapsedRealtime();startedAt=0L;humanControl=false
                    }
                    inCall=true;callAnswered=current.state==TelephonyManager.CALL_STATE_OFFHOOK
                    if(current.state==TelephonyManager.CALL_STATE_RINGING && !ringAttempted){
                        ringAttempted=true
                        try{
                            val flow=enrollment.publishedFlow(current.slot)
                            val answer=flow.optJSONObject("answer_policy")?:JSONObject()
                            val auto=answer.optBoolean("auto_answer",false)
                            if(!auto){humanControl=true;notice("شريحة الشركة: الرد التلقائي غير مفعّل في السيناريو المنشور")}
                            else{
                                // Preflight provider BEFORE answering, never leave callers in a silent AI call.
                                val session=enrollment.voiceSession(current.slot,flow)
                                tenantFlow=flow;tenantSession=session;policy=answer
                                notice("الشريحة ${current.slot+1}: جلسة محرك الشركات جاهزة")
                            }
                        }catch(problem:Exception){
                            humanControl=true
                            val message=problem.message.orEmpty().take(140)
                            if(message!=lastError){lastError=message;notice("تعذر تجهيز محرك الشركات قبل الرد: "+message)}
                        }
                    }
                    // A manually dialed outbound call never enters RINGING locally.
                    // Prepare exactly the published SIM agent after the user has
                    // initiated that call; do not auto-dial or bridge a foreign SIM.
                    if(current.state==TelephonyManager.CALL_STATE_OFFHOOK && !ringAttempted &&
                        !answeredByAgent && tenantSession==null && !humanControl){
                        ringAttempted=true
                        try{
                            val flow=enrollment.publishedFlow(current.slot)
                            val answer=flow.optJSONObject("answer_policy")?:JSONObject()
                            val session=enrollment.voiceSession(current.slot,flow)
                            tenantFlow=flow;tenantSession=session;policy=answer
                            answeredByAgent=true
                            notice("شريحة ${current.slot+1}: المكالمة الصادرة جاهزة للإيجنت المنشور")
                        }catch(problem:Exception){
                            humanControl=true
                            val message=problem.message.orEmpty().take(140)
                            if(message!=lastError){lastError=message;notice("تعذر تجهيز الإيجنت للمكالمة الصادرة: "+message)}
                        }
                    }
                    if(current.state==TelephonyManager.CALL_STATE_RINGING && tenantSession!=null && !answeredByAgent && !humanControl){
                        val elapsed=SystemClock.elapsedRealtime()-ringingAt
                        val ms=policy.optInt("answer_delay_seconds",5).coerceIn(0,30)*1000L
                        if(elapsed>=ms){
                            check(CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)){
                                "لا يمكن تجهيز رابط الصوت بالجهاز"
                            }
                            val daemonState=CaptureDaemon.linkControl(this@MobileLinkService,'Q')
                            check(daemonState=="CALL 1"){"تعارض حالة شريحة الاتصال مع رابط PCM"}
                            val ack=CaptureDaemon.linkControl(this@MobileLinkService,'A')
                            check(ack!=null){"الهاتف لم يؤكد طلب الرد التلقائي"}
                            answeredByAgent=true
                        }
                    }
                    if(current.state==TelephonyManager.CALL_STATE_OFFHOOK && answeredByAgent && tenantSession!=null && call==null && !humanControl){
                        val daemonState=CaptureDaemon.linkControl(this@MobileLinkService,'Q')
                        check(daemonState=="CALL 2"){"تعذر تأكيد وصول المكالمة إلى PCM"}
                        val sess=tenantSession!!
                        val voice=tenantFlow?.optString("voice","google.aoede").orEmpty().substringAfterLast('.')
                        val selectedVoice=voice.replaceFirstChar{it.uppercaseChar()}
                        val liveSettings=JSONObject().put("voice",selectedVoice)
                            .put("input_channel","right")
                            .put("response_style",policy.optString("response_style","egyptian_short"))
                        val currentCall=MobileLiveCall(this@MobileLinkService,client,scope,sess,liveSettings,::notice)
                        call=currentCall;currentCall.start();startedAt=SystemClock.elapsedRealtime()
                    }
                    if(call!=null && startedAt>0 &&
                        SystemClock.elapsedRealtime()-startedAt>policy.optInt("max_call_minutes",10).coerceIn(1,30)*60000L){
                        call?.stop();call=null;humanControl=true
                        notice("انتهى حد مدة الإيجنت؛ المكالمة متاحة للاستلام البشري")
                    }
                    lastState=current.state
                    showOverlay()
                    delay(750)
                }catch(cancelled:CancellationException){throw cancelled}
                catch(error:Exception){
                    call?.stop();call=null;tenantSession=null;humanControl=true
                    connected=false
                    val msg=error.message.orEmpty().take(150)
                    if(msg!=lastError){lastError=msg;notice("محرك الشركات: "+msg)}
                    delay(1800)
                }
            }
        }finally{
            call?.stop();call=null;running=false;connected=false
            inCall=false;callAnswered=false;agentEnabled=false
        }
    }
    /** Never continue pilot AI/telephony after any tenant SIM starts enrollment. */
    private fun stopPilotForTenantPairing():Boolean {
        if(TenantPairingContract.pilotAllowed(tenantPairingInProgress,TenantDeviceEnrollment(this).hasAny()))return false
        agentEnabled=false;connected=false
        prefs().edit().putBoolean("campaign_opt_in",false).commit()
        call?.stop();call=null
        notice("ربط شرائح الشركات نشط؛ تم إيقاف هاتف التجربة")
        stopSelf()
        return true
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
        call?.stop();scope.cancel();running=false;connected=false;inCall=false;callAnswered=false;agentEnabled=false;humanControl=false
        overlay.remove();latestStatus="الربط متوقف"
        super.onDestroy()
    }
}
