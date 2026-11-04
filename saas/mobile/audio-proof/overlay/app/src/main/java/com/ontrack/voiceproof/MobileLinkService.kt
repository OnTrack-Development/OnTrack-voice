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
import android.telephony.SubscriptionManager
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
        const val ACTION_RESTORE="com.ontrack.voiceproof.RESTORE_PREVIOUS_ENABLE"
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
        @Volatile var audioReady=false
        @Volatile var callProblem=""
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
    private var callReportFlush:Job?=null
    private var tenantEventOutbox:CallEventOutbox?=null
    @Volatile private var liveSimSlot:Int=-1
    private var connectivity:ConnectivityManager?=null
    private var networkListener:ConnectivityManager.NetworkCallback?=null
    @Volatile private var takeoverRequested=false
    @Volatile private var pendingDesired:Boolean?=null
    @Volatile private var recoveringActiveCall=false
    private fun showOverlay(){main.post{if(::overlay.isInitialized)overlay.show(running,agentEnabled,inCall,callAnswered,humanControl,audioReady,callProblem)}}
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
            inCall && callProblem.isNotBlank()->callProblem
            inCall && !callAnswered->"مكالمة قيد الرنين أو الاتصال"
            inCall && audioReady->"المكالمة متصلة — صوت الإيجنت يعمل"
            inCall && agentEnabled->"المكالمة متصلة — جاري تجهيز صوت الإيجنت"
            else->value
        }
        val item=NotificationCompat.Builder(this,"ontrack_mobile_link")
            .setSmallIcon(android.R.drawable.ic_menu_call)
            .setContentTitle("OnTrack Voice").setContentText(callLabel)
            .setOngoing(true).setContentIntent(open)
        if(inCall && callAnswered && audioReady && agentEnabled && !humanControl)
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
        pendingDesired=null
        pendingSync=false
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
                    agentEnabled=false;call?.stop();call=null;audioReady=false;connected=false
                    notice("إيقاف محرك الشركات على هذا الجهاز")
                }
                ACTION_ENABLE->{
                    check(tenantPrefs().edit().putBoolean("enabled",true).commit()){
                        "تعذر حفظ حالة تشغيل محرك الشركات"
                    }
                    agentEnabled=true
                    notice("مراقبة الشريحة مفعّلة؛ جلسة Google Live تبدأ عند المكالمة")
                }
                ACTION_RESTORE->{
                    // Honor the last explicit user selection after process death/update.
                    // Never auto-enable a SIM the user deliberately switched off.
                    agentEnabled=tenantPrefs().getBoolean("enabled",false)
                    notice(if(agentEnabled)
                        "استُعيد تشغيل مراقبة الشرائح بعد فتح التطبيق"
                    else "الرد الآلي متوقف بقرار المستخدم؛ اضغط تشغيل لتفعيله")
                }
                ACTION_TAKEOVER->{
                    val slot=liveSimSlot
                    if(slot in 0..1 && inCall && callAnswered && audioReady && call?.active==true){
                        val bindingId=runCatching{TenantDeviceEnrollment(this).bindingId(slot)}.getOrNull()
                        if(bindingId!=null)runCatching{
                            tenantEventOutbox?.recordHumanTakeover(slot,bindingId)
                        }.onFailure{notice("تعذر حفظ حدث الاستلام؛ تابع سجل المكالمة")}
                        humanControl=true
                        call?.stop();call=null;audioReady=false;connected=false
                        callProblem=""
                        notice("استلام بشري للمكالمة الحالية")
                    }else{
                        notice("لم تتصل جلسة صوت الإيجنت؛ لا يوجد صوت لاستلامه")
                    }
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
            // Google Live stays idle until a call; show the confirmed SIM-monitor state.
            showOverlay()
            return START_STICKY
        }
        // Native release 0.3.4: never start the global, legacy phone pilot for
        // an unpaired device. A SIM-specific verified tenant binding is required.
        agentEnabled=false;connected=false
        call?.stop();call=null
        notice("اربط شريحة SIM بحساب OnTrack وإيجنت منشور قبل تشغيل المكالمات")
        stopSelf()
        return START_NOT_STICKY
    }
    private fun tenantPrefs()=getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE)
    /** 
     * Single-active-SIM devices use the established shell CALL Q source.
     * Q is global and cannot identify a SIM, so dual-active-SIM devices
     * continue to use subscription-scoped Android call states only.
     */
    private data class SlotCall(val slot:Int,val state:Int)
    private suspend fun currentSlotCalls():List<SlotCall>{
        check(Build.VERSION.SDK_INT>=31){"Android 12 أو أحدث مطلوب لعزل حالة المكالمة لكل شريحة"}
        val enrollment=TenantDeviceEnrollment(this)
        val activeSlots=runCatching{
            getSystemService(SubscriptionManager::class.java)
                ?.activeSubscriptionInfoList.orEmpty().map{it.simSlotIndex}
        }.getOrDefault(emptyList())
        val boundSlots=(0..1).filter{enrollment.paired(it)}.toSet()
        val nativeSlot=TenantCallRouting.nativeSingleSimSlot(activeSlots,boundSlots)
        if(nativeSlot!=null){
            val nativeState=runCatching{
                CaptureDaemon.linkControl(this,'Q')?.trim()
                    ?.let{Regex("^CALL ([012])$").matchEntire(it)?.groupValues?.get(1)?.toInt()}
            }.getOrNull()
            if(nativeState!=null)return listOf(SlotCall(nativeSlot,nativeState))
        }
        // If Q is unavailable, fall back to OS telephony with explicit errors.
        val telephony=getSystemService(TelephonyManager::class.java)
            ?:error("خدمة الاتصال غير متاحة")
        return (0..1).mapNotNull{slot->
            val subscription=try{
                TenantSimHardware.subscriptionId(this,slot)
            }catch(error:Exception){
                if(enrollment.paired(slot))throw IllegalStateException(
                    "لا يمكن قراءة حالة SIM ${slot+1}: "+error.message.orEmpty().take(85),error)
                return@mapNotNull null
            }
            val state=try{
                telephony.createForSubscriptionId(subscription).callStateForSubscription
            }catch(error:Exception){
                if(enrollment.paired(slot))throw IllegalStateException(
                    "حالة المكالمات غير متاحة لـ SIM ${slot+1}: "+error.message.orEmpty().take(85),error)
                return@mapNotNull null
            }
            SlotCall(slot,state)
        }
    }
    private suspend fun tenantMonitor(){
        val enrollment=TenantDeviceEnrollment(this)
        val tenantEvents=CallEventOutbox(this,"ontrack_tenant_call_events")
        tenantEventOutbox=tenantEvents
        val reportOutbox=CallReportOutbox(this)
        var lastReportAttempt=0L
        var selected:Int?=null
        var lastState=TelephonyManager.CALL_STATE_IDLE
        var ringingAt=0L
        var answeredByAgent=false
        var ringAttempted=false
        var autoAnswerRequested=false
        var outgoingCall=false
        var startedAt=0L
        var tenantFlow:JSONObject?=null
        var tenantSession:JSONObject?=null
        var policy=JSONObject()
        var flowLoad:Deferred<Result<JSONObject>>?=null
        var sessionLoad:Deferred<Result<Triple<JSONObject,JSONObject,JSONObject>>>?=null
        var lastError=""
        // The working Pilot prepared and authenticated the local shell bridge
        // BEFORE a phone rang. Restoring that readiness avoids a slow ADB
        // handshake when the first incoming call needs an immediate answer.
        runCatching {
            check(CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)){
                "الربط المحلي للصوت غير جاهز؛ أعد تشغيل Wireless debugging"
            }
            AnsweringMachine.setEnabled(this@MobileLinkService,false)
            CaptureDaemon.setAnswering(this@MobileLinkService)
            RecorderSwitch.set(this@MobileLinkService,false)
            check(CaptureDaemon.setDaemonMode(this@MobileLinkService,CaptureDaemon.MODE_OFF)){
                "تعذر وضع محرك الصوت في وضع التحكم المباشر"
            }
        }.onFailure {
            callProblem="الربط المحلي للصوت غير جاهز: "+it.message.orEmpty().take(85)
            notice(callProblem)
        }
        try{
            while(scope.isActive && enrollment.hasAny() && !tenantPairingInProgress){
                try{
                    agentEnabled=tenantPrefs().getBoolean("enabled",false)
                    if(!agentEnabled){
                        // Continue observing the SIM while the AI is disabled.
                        call?.stop();call=null;tenantSession=null;connected=false;audioReady=false
                    }
                    val states=currentSlotCalls()
                    val active=states.filter{it.state!=TelephonyManager.CALL_STATE_IDLE}
                    if(active.size>1){
                        // Vendor telephony APIs can report the same call on both
                        // subscriptions. Never guess the tenant/SIM or claim a
                        // manual takeover that the user did not request.
                        call?.stop();call=null;tenantSession=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;connected=false
                        inCall=true;callAnswered=active.any{it.state==TelephonyManager.CALL_STATE_OFFHOOK}
                        val issue="حالة المكالمة ملتبسة بين شريحتين؛ لم يتم توصيل صوت الإيجنت"
                        if(callProblem!=issue){callProblem=issue;notice(issue)}
                        delay(900);continue
                    }
                    val current=active.singleOrNull()
                    val observedBinding=current?.slot?.takeIf { enrollment.paired(it) }
                        ?.let { runCatching { enrollment.bindingId(it) }.getOrNull() }
                    tenantEvents.observe(if(observedBinding!=null)current!!.state else TelephonyManager.CALL_STATE_IDLE,
                        if(observedBinding!=null)current!!.slot else -1,observedBinding?:-1)
                    runCatching{tenantEvents.enrichFromCallLog()}
                    if(callEventFlush?.isActive!=true)callEventFlush=scope.launch {
                        runCatching {
                            val event=tenantEvents.peekEligible { slot ->
                                if(enrollment.paired(slot))runCatching { enrollment.bindingId(slot) }.getOrNull() else null
                            }?:return@runCatching
                            val slot=event.optInt("sim_slot",-1)
                            if(slot !in 0..1 || !enrollment.paired(slot) ||
                                event.optInt("device_id",-1)!=enrollment.bindingId(slot))return@runCatching
                            val response=withContext(Dispatchers.IO){
                                enrollment.workspace(slot,"workspace_call_event",event)
                            }
                            if(response.optBoolean("accepted"))tenantEvents.acknowledge(event)
                        }.onFailure { notice("تعذر رفع حدث مكالمة SaaS؛ سيُعاد الإرسال دون تكرار") }
                    }
                    // A report is retried separately from call-state events. The
                    // original event may not have arrived yet; keep encrypted
                    // evidence until the server explicitly acknowledges saving.
                    if(callReportFlush?.isActive!=true &&
                        SystemClock.elapsedRealtime()-lastReportAttempt>=6000L){
                        lastReportAttempt=SystemClock.elapsedRealtime()
                        callReportFlush=scope.launch {
                            runCatching {
                                val report=reportOutbox.peekEligible { slot ->
                                    if(enrollment.paired(slot))runCatching { enrollment.bindingId(slot) }.getOrNull() else null
                                }?:return@runCatching
                                val slot=report.optInt("sim_slot",-1)
                                if(slot !in 0..1 || !enrollment.paired(slot) ||
                                    report.optInt("device_id",-1)!=enrollment.bindingId(slot))return@runCatching
                                val response=enrollment.workspace(slot,"workspace_call_analysis",report)
                                if(response.optBoolean("saved"))reportOutbox.acknowledge(report)
                            }.onFailure {
                                notice("تعذر حفظ تحليل المكالمة؛ محفوظ مشفرًا وسيُعاد الإرسال")
                            }
                        }
                    }
                    if(current==null){
                        flowLoad?.cancel();flowLoad=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;callProblem=""
                        if(selected!=null){
                            call?.stop();call=null;tenantSession=null;tenantFlow=null
                            selected=null;liveSimSlot=-1;answeredByAgent=false;ringAttempted=false;autoAnswerRequested=false;outgoingCall=false;humanControl=false;policy=JSONObject()
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
                        selected=null;tenantSession=null;connected=false;audioReady=false
                        delay(750);continue
                    }
                    if(selected!=current.slot){
                        flowLoad?.cancel();flowLoad=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;callProblem=""
                        selected=current.slot;liveSimSlot=current.slot;answeredByAgent=false;ringAttempted=false;autoAnswerRequested=false;outgoingCall=false;tenantFlow=null;tenantSession=null
                        ringingAt=SystemClock.elapsedRealtime();startedAt=0L;humanControl=false;policy=JSONObject()
                    }
                    inCall=true;callAnswered=current.state==TelephonyManager.CALL_STATE_OFFHOOK
                    if(!agentEnabled){
                        // Agent-off is NOT the same as an explicit human takeover.
                        tenantFlow=null;tenantSession=null;audioReady=false;connected=false
                        sessionLoad?.cancel();sessionLoad=null
                        callProblem=""
                        if(lastState!=current.state)notice("تم رصد مكالمة على SIM ${current.slot+1} — الإيجنت متوقف")
                        lastState=current.state
                        showOverlay()
                        delay(750);continue
                    }
                    if(current.state==TelephonyManager.CALL_STATE_RINGING && !ringAttempted){
                        ringAttempted=true
                        outgoingCall=false
                        val slot=current.slot
                        // Load signed Flow/policy without pausing shell ring polling.
                        flowLoad=scope.async(Dispatchers.IO){
                            runCatching{enrollment.publishedFlow(slot)}
                        }
                        notice("الشريحة ${slot+1}: تم رصد الرنين؛ جاري تحميل سياسة الإيجنت")
                    }
                    if(flowLoad?.isCompleted==true){
                        val result=flowLoad?.await()
                        flowLoad=null
                        result?.onSuccess { flow->
                            tenantFlow=flow
                            policy=flow.optJSONObject("answer_policy")?:JSONObject()
                            val auto=policy.optBoolean("auto_answer",false)
                            callProblem=if(auto) "" else "الرد التلقائي مغلق في إعدادات الإيجنت"
                            notice(if(auto) "سيتم الرد تلقائيًا على SIM ${current.slot+1} بعد المهلة المحددة"
                                else "الرنين مرصود لكن الرد التلقائي مغلق في سياسة الإيجنت")
                        }?.onFailure { problem->
                            val message=problem.message.orEmpty().take(140)
                            callProblem="تعذر تحميل سياسة الإيجنت: "+message
                            if(message!=lastError){lastError=message;notice(callProblem)}
                        }
                    }
                    if(TenantCallRouting.autoAnswerDue(
                            current.state==TelephonyManager.CALL_STATE_RINGING,
                            policy.optBoolean("auto_answer",false) && tenantFlow!=null && agentEnabled,
                            SystemClock.elapsedRealtime()-ringingAt,
                            policy.optInt("answer_delay_seconds",5),
                            autoAnswerRequested,humanControl)){
                        // A failed auto-answer command must NOT disable the
                        // manually answered AI path for the same phone call.
                        autoAnswerRequested=true // bounded one attempt
                        try{
                            check(CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)){
                                "رابط الرد التلقائي ADB غير متصل"
                            }
                            val daemonState=CaptureDaemon.linkControl(this@MobileLinkService,'Q')
                            check(daemonState=="CALL 1"){"رابط الرد الآلي لم يؤكد الرنين: "+(daemonState?:"لا توجد استجابة")}
                            val ack=CaptureDaemon.linkControl(this@MobileLinkService,'A')
                            check(ack!=null){"لم يؤكد رابط الهاتف أمر الرد التلقائي"}
                            notice("أُرسل أمر الرد للشريحة ${current.slot+1}؛ انتظار تأكيد OFFHOOK")
                        }catch(problem:Exception){
                            callProblem="تعذر الرد التلقائي: "+problem.message.orEmpty().take(110)
                            notice(callProblem+" — الرد اليدوي ما زال متاحًا للإيجنت")
                        }
                    }
                    // Both incoming (automatic OR answered manually) and
                    // outgoing calls enter here ONLY after the bound SIM has
                    // moved to OFFHOOK. The original Pilot also issued its Live
                    // session after answering instead of delaying the answer.
                    if(TenantCallRouting.attachAfterOffhook(
                            current.state==TelephonyManager.CALL_STATE_OFFHOOK,
                            agentEnabled,humanControl,answeredByAgent)){
                        answeredByAgent=true // at most one authorization attempt per call
                        outgoingCall=!ringAttempted
                        val slot=current.slot
                        val preparedFlow=tenantFlow
                        // Call-state polling must not block on SaaS, Gemini tokens
                        // or a slow published Flow response after OFFHOOK.
                        sessionLoad=scope.async(Dispatchers.IO){
                            runCatching {
                                val flow=preparedFlow?:enrollment.publishedFlow(slot)
                                val answer=flow.optJSONObject("answer_policy")?:JSONObject()
                                val session=enrollment.voiceSession(slot,flow)
                                Triple(flow,answer,session)
                            }
                        }
                        callProblem=""
                        notice("الشريحة ${slot+1}: تم توصيل المكالمة؛ تجهيز تفويض صوت الإيجنت")
                    }
                    if(sessionLoad?.isCompleted==true){
                        val result=sessionLoad?.await()
                        sessionLoad=null
                        result?.onSuccess { (flow,answer,session)->
                            tenantFlow=flow;tenantSession=session;policy=answer
                            callProblem=""
                            notice("الشريحة ${current.slot+1}: جلسة الإيجنت صالحة؛ توصيل صوت المكالمة")
                        }?.onFailure { problem ->
                            tenantSession=null
                            val message=problem.message.orEmpty().take(140)
                            callProblem="تعذر تشغيل صوت الإيجنت: "+message
                            if(message!=lastError){lastError=message;notice(callProblem)}
                        }
                    }
                    if(current.state==TelephonyManager.CALL_STATE_OFFHOOK &&
                        answeredByAgent && tenantSession!=null && call==null && !humanControl){
                        check(CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)){
                            "رابط الصوت المحلي لا يعمل بعد الرد"
                        }
                        val daemonState=CaptureDaemon.linkControl(this@MobileLinkService,'Q')
                        check(daemonState=="CALL 2"){"تعذر تأكيد وصول المكالمة إلى PCM: "+(daemonState?:"لا توجد استجابة")}
                        val sess=tenantSession!!
                        val voice=tenantFlow?.optString("voice","google.aoede").orEmpty().substringAfterLast('.')
                        val selectedVoice=voice.replaceFirstChar{it.uppercaseChar()}
                        val liveSettings=JSONObject().put("voice",selectedVoice)
                            .put("input_channel","mix")
                            .put("response_style",policy.optString("response_style","egyptian_short"))
                        val reportCallId=tenantEvents.currentCallId(current.slot)
                        val reportSlot=current.slot
                        val reportBinding=enrollment.bindingId(reportSlot)
                        val currentCall=MobileLiveCall(this@MobileLinkService,client,scope,
                            sess,liveSettings,::notice,outgoingCall=outgoingCall) { realTranscript ->
                            if(reportCallId!=null)
                                reportOutbox.enqueue(reportSlot,reportBinding,reportCallId,realTranscript)
                        }
                        call=currentCall;currentCall.start();startedAt=SystemClock.elapsedRealtime()
                        audioReady=currentCall.active;connected=audioReady;callProblem=""
                        notice(if(audioReady)"صوت الإيجنت يعمل على SIM ${current.slot+1}" else "صوت الإيجنت لم يبدأ")
                    }
                    if(call!=null && !call!!.active){
                        call=null;audioReady=false;connected=false
                        callProblem="انقطعت جلسة صوت الإيجنت؛ المكالمة على الهاتف بدون AI"
                        notice(callProblem)
                    }
                    if(call!=null && startedAt>0 &&
                        SystemClock.elapsedRealtime()-startedAt>policy.optInt("max_call_minutes",10).coerceIn(1,30)*60000L){
                        call?.stop();call=null;audioReady=false;connected=false
                        callProblem="انتهى الوقت المحدد لصوت الإيجنت؛ الهاتف ما زال متصلًا"
                        notice(callProblem)
                    }
                    lastState=current.state
                    showOverlay()
                    delay(750)
                }catch(cancelled:CancellationException){throw cancelled}
                catch(error:Exception){
                    // A transport/runtime failure is NOT an explicit takeover.
                    // Preserve diagnostic UI while keeping human MIC available.
                    call?.stop();call=null;tenantSession=null
                    audioReady=false;connected=false
                    val msg=error.message.orEmpty().take(150)
                    callProblem="تعذر اتصال صوت المكالمة: "+msg
                    if(msg!=lastError){lastError=msg;notice(callProblem)}
                    showOverlay()
                    delay(1800)
                }
            }
        }finally{
            call?.stop();call=null;running=false;connected=false;audioReady=false
            inCall=false;callAnswered=false;agentEnabled=false;callProblem=""
        }
    }
    /** Never continue pilot AI/telephony after any tenant SIM starts enrollment. */
    override fun onDestroy(){
        networkListener?.let{runCatching{connectivity?.unregisterNetworkCallback(it)}}
        call?.stop();scope.cancel();running=false;connected=false;inCall=false;callAnswered=false;agentEnabled=false;humanControl=false;audioReady=false;callProblem=""
        liveSimSlot=-1;tenantEventOutbox=null
        overlay.remove();latestStatus="الربط متوقف"
        super.onDestroy()
    }
}
