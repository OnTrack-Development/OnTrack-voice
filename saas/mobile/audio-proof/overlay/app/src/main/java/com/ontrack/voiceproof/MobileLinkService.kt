package com.ontrack.voiceproof

import android.app.*
import android.content.Intent
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
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
        @Volatile var audioDiagnostic=""
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
    private val deferredCallDrains=mutableListOf<MobileLiveCall>()
    private fun stopLiveCall(){
        call?.let{ live ->
            live.stop()
            synchronized(deferredCallDrains){deferredCallDrains.add(live)}
        }
        call=null
    }
    private var loop:Job?=null
    private var callEventFlush:Job?=null
    private var callReportFlush:Job?=null
    private var callLogEnrichment:Job?=null
    private var tenantEventOutbox:CallEventOutbox?=null
    @Volatile private var liveSimSlot:Int=-1
    private var connectivity:ConnectivityManager?=null
    private var networkListener:ConnectivityManager.NetworkCallback?=null
    @Volatile private var takeoverRequested=false
    @Volatile private var pendingDesired:Boolean?=null
    @Volatile private var recoveringActiveCall=false
    private fun showOverlay(){main.post{if(::overlay.isInitialized)overlay.show(running,agentEnabled,inCall,callAnswered,humanControl,audioReady,callProblem,audioDiagnostic)}}
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
            stopLiveCall();agentEnabled=false;connected=false
            notice("ربط الشرائح قيد التنفيذ؛ الإيجنت متوقف لحين التحقق")
            stopSelf();return START_NOT_STICKY
        }
        if(TenantDeviceEnrollment(this).hasAny()){
            // Tenant mode NEVER executes the global pilot monitor or its session endpoint.
            val action=intent?.action
            when(action){
                ACTION_DISABLE,ACTION_PAUSE,"STOP"->{
                    tenantPrefs().edit().putBoolean("enabled",false).commit()
                    agentEnabled=false;stopLiveCall();audioReady=false;connected=false
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
                        stopLiveCall();audioReady=false;connected=false
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
        stopLiveCall()
        notice("اربط شريحة SIM بحساب OnTrack وإيجنت منشور قبل تشغيل المكالمات")
        stopSelf()
        return START_NOT_STICKY
    }
    private fun tenantPrefs()=getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE)
    /** Internet validity refers to Google Live's DEFAULT data path, not
     * the carrier call state. The SIM can be OFFHOOK while LTE data is
     * suspended (especially with another SIM as the data subscription).
     * Never use a Wi-Fi-only precondition for already answered SIM calls.
     */
    private fun internetValidated():Boolean = runCatching {
        val cm=connectivity?:getSystemService(ConnectivityManager::class.java)
        val network=cm?.activeNetwork?:return@runCatching false
        cm.getNetworkCapabilities(network)?.hasCapability(
            NetworkCapabilities.NET_CAPABILITY_VALIDATED)==true
    }.getOrDefault(true)
    private fun internetRoute():String = runCatching {
        val cm=connectivity?:getSystemService(ConnectivityManager::class.java)
        val caps=if(cm==null)null else cm.activeNetwork?.let { network -> cm.getNetworkCapabilities(network) }
        when {
            caps==null -> "offline"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "Wi-Fi"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "4G/5G"
            else -> "network"
        }
    }.getOrDefault("unknown")
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
        var idleSince=0L
        var lastCallLogAttempt=0L
        var lastManagerPoll=0L
        var managerClaim:Deferred<Result<JSONObject>>?=null
        var managerCallback:JSONObject?=null
        var managerSawOffhook=false
        var managerDialAt=0L
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
        var audioStart:Deferred<Result<Unit>>?=null
        var audioAttemptCount=0
        var nextAudioRetryAt=0L
        var lastError=""
        // 2026-11-04 field regression: handset OFFHOOK alone is NOT evidence
        // that an agent answered, the microphone is muted, or Gemini receives
        // caller PCM. Separately prove Q/A, signed per-SIM session, shell PCM
        // header+mute and AI readiness. Never set humanControl on errors:
        // only the explicit ACTION_TAKEOVER may record that user action.
        //
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
                        stopLiveCall();tenantSession=null;connected=false;audioReady=false
                    }
                    val states=currentSlotCalls()
                    val active=states.filter{it.state!=TelephonyManager.CALL_STATE_IDLE}
                    if(active.size>1){
                        audioStart?.cancel();audioStart=null
                        // Vendor telephony APIs can report the same call on both
                        // subscriptions. Never guess the tenant/SIM or claim a
                        // manual takeover that the user did not request.
                        stopLiveCall();tenantSession=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;connected=false
                        inCall=true;callAnswered=active.any{it.state==TelephonyManager.CALL_STATE_OFFHOOK}
                        val issue="حالة المكالمة ملتبسة بين شريحتين؛ لم يتم توصيل صوت الإيجنت"
                        if(callProblem!=issue){callProblem=issue;notice(issue)}
                        delay(900);continue
                    }
                    val current=active.singleOrNull()
                    // Never put network I/O in the 750ms telephony observer.
                    // The authenticated claim is fetched asynchronously and
                    // can be dialed ONLY after the physical SIM is fully idle.
                    if(managerCallback!=null && current?.state==TelephonyManager.CALL_STATE_OFFHOOK &&
                        current.slot==managerCallback?.optInt("sim_slot",-1)){
                        managerSawOffhook=true
                    }
                    if(managerClaim?.isCompleted==true) {
                        val claimed=managerClaim?.await()
                        managerClaim=null
                        val job=claimed?.getOrNull()?.optJSONObject("job")
                        if(job!=null){
                            val slot=job.optInt("sim_slot",-1)
                            val jobId=job.optInt("id",-1)
                            val safeToCall=current==null && managerCallback==null &&
                                agentEnabled && !tenantPairingInProgress &&
                                slot in 0..1 && enrollment.paired(slot) &&
                                runCatching{enrollment.bindingId(slot)>0}.getOrDefault(false)
                            try {
                                check(safeToCall){"مكالمة أخرى جارية أو الشريحة غير جاهزة"}
                                check(jobId>0){"معرف إبلاغ غير صالح"}
                                // Only the owner-configured phone from the server
                                // may reach the native Android dialer.
                                HumanEscalationDialer.placeOnPairedSim(this@MobileLinkService,
                                    slot,job.getString("phone"))
                                managerCallback=job
                                managerSawOffhook=false
                                managerDialAt=SystemClock.elapsedRealtime()
                                scope.launch(Dispatchers.IO){
                                    runCatching{enrollment.workspace(slot,"workspace_handoff_ack",
                                        JSONObject().put("job_id",jobId).put("stage","dial_started"))}
                                        .onFailure{notice("تعذر تحديث حالة اتصال المدير في السجل")}
                                }
                                notice("جاري الاتصال بـ"+job.optString("name","المسؤول")+
                                    " لإبلاغه بطلب الموظف وملخص المكالمة")
                            }catch(problem:Exception){
                                if(slot in 0..1 && enrollment.paired(slot) && jobId>0)
                                    scope.launch(Dispatchers.IO){
                                        runCatching{enrollment.workspace(slot,"workspace_handoff_ack",
                                            JSONObject().put("job_id",jobId).put("stage","failed"))}
                                    }
                                notice("تعذر إبلاغ المدير: "+problem.message.orEmpty().take(110))
                            }
                        }
                    }
                    if(current==null){
                        stopLiveCall()
                        // Keep the active call ID until all accepted notes are persisted,
                        // including sessions stopped earlier by manual takeover/retry.
                        val draining=synchronized(deferredCallDrains){deferredCallDrains.toList()}
                        if(draining.any{!it.awaitDeferredCallbacks()}){delay(100);continue}
                        synchronized(deferredCallDrains){deferredCallDrains.removeAll(draining.toSet())}
                    }
                    val observedBinding=current?.slot?.takeIf { enrollment.paired(it) }
                        ?.let { runCatching { enrollment.bindingId(it) }.getOrNull() }
                    tenantEvents.observe(if(observedBinding!=null)current!!.state else TelephonyManager.CALL_STATE_IDLE,
                        if(observedBinding!=null)current!!.slot else -1,observedBinding?:-1)
                    // observe() changes memory immediately; Android flushes its disk write.
                    // CallLog lookups run only after 3s idle and never hold the outbox lock.
                    val observedAt=SystemClock.elapsedRealtime()
                    if(current==null){
                        if(idleSince==0L)idleSince=observedAt
                        if(observedAt-idleSince>=3000L && observedAt-lastCallLogAttempt>=3000L &&
                            callLogEnrichment?.isActive!=true){
                            lastCallLogAttempt=observedAt
                            callLogEnrichment=scope.launch(Dispatchers.IO){
                                try{
                                    if(!inCall && !tenantPairingInProgress &&
                                        currentSlotCalls().none{it.state!=TelephonyManager.CALL_STATE_IDLE}){
                                        tenantEvents.enrichFromCallLog { !inCall && !tenantPairingInProgress }
                                    }
                                }catch(cancelled:CancellationException){throw cancelled}
                                catch(error:Exception){notice("تعذر تعريف المكالمة؛ سيُعاد عند الخمول")}
                            }
                        }
                    }else{idleSince=0L;callLogEnrichment?.cancel()}
                    if(callEventFlush?.isActive!=true)callEventFlush=scope.launch(Dispatchers.IO) {
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
                    if(current==null && callReportFlush?.isActive!=true &&
                        SystemClock.elapsedRealtime()-lastReportAttempt>=6000L){
                        lastReportAttempt=SystemClock.elapsedRealtime()
                        callReportFlush=scope.launch(Dispatchers.IO) {
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
                        val callback=managerCallback
                        if(callback!=null){
                            if(managerSawOffhook ||
                                SystemClock.elapsedRealtime()-managerDialAt>45000L) {
                                val jobId=callback.optInt("id",-1)
                                val slot=callback.optInt("sim_slot",-1)
                                val status=if(managerSawOffhook)"call_ended_unverified" else "failed"
                                if(jobId>0 && slot in 0..1 && enrollment.paired(slot))
                                    scope.launch(Dispatchers.IO){
                                        runCatching{
                                            enrollment.workspace(slot,"workspace_handoff_ack",
                                                JSONObject().put("job_id",jobId).put("stage",status))
                                        }.onFailure{notice("تعذر توثيق نتيجة مكالمة المدير؛ راجع السجل")}
                                    }
                                managerCallback=null
                                managerSawOffhook=false
                                managerDialAt=0L
                            }
                        }
                        if(managerCallback==null && managerClaim==null && agentEnabled &&
                            SystemClock.elapsedRealtime()-lastManagerPoll>=4000L){
                            lastManagerPoll=SystemClock.elapsedRealtime()
                            managerClaim=scope.async(Dispatchers.IO){
                                runCatching{
                                    var result=JSONObject().put("job",JSONObject.NULL)
                                    for(slot in 0..1){
                                        if(!enrollment.paired(slot))continue
                                        val candidate=enrollment.workspace(slot,"workspace_handoff_claim")
                                        if(candidate.optJSONObject("job")!=null){
                                            result=candidate
                                            break
                                        }
                                    }
                                    result
                                }
                            }
                        }
                        audioStart?.cancel();audioStart=null
                        flowLoad?.cancel();flowLoad=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;callProblem="";audioDiagnostic=""
                        if(selected!=null){
                            stopLiveCall();tenantSession=null;tenantFlow=null
                            selected=null;liveSimSlot=-1;answeredByAgent=false;ringAttempted=false;autoAnswerRequested=false;outgoingCall=false;humanControl=false;policy=JSONObject()
                            ringingAt=0L;startedAt=0L;audioAttemptCount=0;nextAudioRetryAt=0L
                        }
                        // The cellular call has ended. Only NOW submit the
                        // encrypted, accumulated real-time text to summarization.
                        runCatching{
                            reportOutbox.finalizeAtIdle { slot ->
                                if(enrollment.paired(slot))runCatching{enrollment.bindingId(slot)}.getOrNull()
                                else null
                            }
                        }.onFailure{notice("نص المكالمة محفوظ؛ تعذر تجهيزه للملخص وسيُعاد بعد انشغال الخط")}
                        inCall=false;callAnswered=false
                        // Running a monitor is not an authenticated voice connection.
                        connected=false
                        if(lastState!=TelephonyManager.CALL_STATE_IDLE)notice("مراقب الشرائح جاهز؛ اختبر Google والـFlow قبل المكالمة")
                        lastState=TelephonyManager.CALL_STATE_IDLE
                        delay(750);continue
                    }
                    // The observed SIM must be paired; other subscriptions remain human-only.
                    if(!enrollment.paired(current.slot)){
                        audioStart?.cancel();audioStart=null
                        if(selected!=null){stopLiveCall()}
                        selected=null;tenantSession=null;connected=false;audioReady=false
                        delay(750);continue
                    }
                    if(selected!=current.slot){
                        audioStart?.cancel();audioStart=null
                        flowLoad?.cancel();flowLoad=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;callProblem="";audioDiagnostic=""
                        selected=current.slot;liveSimSlot=current.slot;answeredByAgent=false;ringAttempted=false;autoAnswerRequested=false;outgoingCall=false;tenantFlow=null;tenantSession=null
                        ringingAt=SystemClock.elapsedRealtime();startedAt=0L;humanControl=false;policy=JSONObject()
                        audioAttemptCount=0;nextAudioRetryAt=0L
                    }
                    inCall=true;callAnswered=current.state==TelephonyManager.CALL_STATE_OFFHOOK
                    if(!agentEnabled){
                        audioStart?.cancel();audioStart=null
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
                            if(auto && current.state==TelephonyManager.CALL_STATE_RINGING &&
                                sessionLoad==null && tenantSession==null){
                                // Warm the per-SIM authorized Gemini token during
                                // ringing, so OFFHOOK doesn't wait on three HTTP
                                // requests before the audio bridge can start.
                                val slot=current.slot
                                val snapshot=flow
                                sessionLoad=scope.async(Dispatchers.IO){
                                    runCatching{
                                        Triple(snapshot,policy,enrollment.voiceSession(slot,snapshot))
                                    }
                                }
                            }
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
                    // A failed Gemini/PCM attempt on LTE must not permanently
                    // latch the OFFHOOK call into silence. Only retry after
                    // default internet VALIDATES, and never repeat phone A/Q.
                    val now=SystemClock.elapsedRealtime()
                    if(call==null && tenantSession==null && sessionLoad==null && audioStart==null &&
                       TenantCallRouting.voiceRetryDue(
                           current.state==TelephonyManager.CALL_STATE_OFFHOOK,
                           agentEnabled,humanControl,audioReady,answeredByAgent,
                           audioAttemptCount,3,internetValidated(),now,nextAudioRetryAt)){
                        answeredByAgent=false
                        notice("عاد الإنترنت (${internetRoute()})؛ إعادة ربط صوت AI للمكالمة الجارية")
                    }
                    if(TenantCallRouting.attachAfterOffhook(
                            current.state==TelephonyManager.CALL_STATE_OFFHOOK,
                            agentEnabled,humanControl,answeredByAgent)){
                        answeredByAgent=true // one attempt until a bounded, validated recovery
                        audioAttemptCount++
                        outgoingCall=!ringAttempted
                        val slot=current.slot
                        val preparedFlow=tenantFlow
                        // Call-state polling must not block on SaaS, Gemini tokens
                        // or a slow published Flow response after OFFHOOK.
                        if(sessionLoad==null && tenantSession==null){
                            sessionLoad=scope.async(Dispatchers.IO){
                                runCatching {
                                    val flow=preparedFlow?:enrollment.publishedFlow(slot)
                                    val answer=flow.optJSONObject("answer_policy")?:JSONObject()
                                    val session=enrollment.voiceSession(slot,flow)
                                    Triple(flow,answer,session)
                                }
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
                            if(current.state==TelephonyManager.CALL_STATE_OFFHOOK)
                                notice("الشريحة ${current.slot+1}: جلسة الإيجنت صالحة؛ توصيل صوت المكالمة")
                        }?.onFailure { problem ->
                            tenantSession=null
                            nextAudioRetryAt=SystemClock.elapsedRealtime()+
                                2500L*audioAttemptCount.coerceIn(1,3)
                            val message=problem.message.orEmpty().take(140)
                            callProblem="تعذر تشغيل صوت الإيجنت (${internetRoute()}): "+message
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
                        val callback=managerCallback?.takeIf{
                            it.optInt("sim_slot",-1)==current.slot
                        }
                        if(callback!=null){
                            val brief="المسؤول: "+callback.optString("name","مدير الشركة").take(100)+
                                "\nالعميل: "+callback.optString("customer_name","غير معروف").take(100)+
                                "\nرقم العميل: "+callback.optString("customer_phone","غير متاح").take(30)+
                                "\nسبب طلب الموظف: "+callback.optString("reason","غير مذكور").take(900)+
                                "\nملخص المكالمة: "+callback.optString("summary","لم يتوفر تفريغ موثق").take(1200)
                            liveSettings.put("manager_briefing",brief)
                        }
                        val reportCallId=tenantEvents.currentCallId(current.slot)
                        val reportSlot=current.slot
                        val reportBinding=enrollment.bindingId(reportSlot)
                        val currentCall=MobileLiveCall(this@MobileLinkService,client,scope,
                            sess,liveSettings,::notice,outgoingCall=outgoingCall,
                            rotateVoiceToken={ previous,failure ->
                                enrollment.rotateVoiceSession(reportSlot,previous,failure)
                            },
                            onHumanRequested={ reason,notes ->
                                if(reportCallId!=null && callback==null)
                                    tenantEvents.recordCustomerHumanRequest(reportSlot,reportBinding,reason,notes)
                                else false
                            },
                            onCallNote={ summary,notes ->
                                if(reportCallId!=null && callback==null)
                                    tenantEvents.recordAgentCallNote(reportSlot,reportBinding,summary,notes)
                                else false
                            },
                            onAudioDiagnostics={ diagnostic ->
                                // Gemini setup alone cannot prove the VoLTE modem
                                // delivered audible PCM to the other party.
                                if(inCall && liveSimSlot==reportSlot && !humanControl){
                                    audioDiagnostic=diagnostic
                                    showOverlay()
                                }
                            }) { realTranscript ->
                            if(reportCallId!=null && callback==null)
                                reportOutbox.appendLiveSegment(reportSlot,reportBinding,reportCallId,realTranscript)
                        }
                        // Regression 2026-11-04: synchronous 25s Gemini setup
                        // froze the SIM ring/OFFHOOK polling loop, so loss of a
                        // call or manual handoff was not observed promptly.
                        // A Session token is not PCM-ready and cannot enable
                        // the human-takeover button until start() succeeds.
                        call=currentCall
                        audioReady=false;audioDiagnostic="";connected=false
                        audioStart=scope.async(Dispatchers.IO){
                            try{
                                currentCall.start()
                                Result.success(Unit)
                            }catch(timeout:TimeoutCancellationException){
                                // A 25s Google setup timeout on LTE is a
                                // recoverable media error, NOT cancellation of
                                // this long-lived SIM monitor. Bubble it into
                                // the bounded voiceRetryDue path after internet
                                // revalidates. Only real job cancellation
                                // should stop the coroutine.
                                Result.failure(IllegalStateException(
                                    "انتهت مهلة Google Live على ${internetRoute()}؛ إعادة محاولة الصوت",timeout))
                            }catch(cancelled:CancellationException){throw cancelled}
                            catch(problem:Exception){Result.failure(problem)}
                        }
                        startedAt=SystemClock.elapsedRealtime()
                        notice("الشريحة ${current.slot+1}: توصيل PCM وكتم الميكروفون قبل تجهيز Google Live")
                    }
                    if(audioStart?.isCompleted==true){
                        val result=audioStart?.await()
                        audioStart=null
                        result?.onSuccess{
                            if(call?.active==true){
                                audioReady=true;connected=true;callProblem=""
                                notice("PCM وGoogle Live جاهزين على SIM ${current.slot+1}؛ اختبار صوت الجهاز مطلوب")
                            }
                        }?.onFailure{problem->
                            stopLiveCall();tenantSession=null
                            audioReady=false;connected=false
                            nextAudioRetryAt=SystemClock.elapsedRealtime()+
                                2500L*audioAttemptCount.coerceIn(1,3)
                            callProblem="فشل ربط الصوت (${internetRoute()}): "+problem.message.orEmpty().take(110)
                            notice(callProblem)
                        }
                    }
                    if(call!=null && !call!!.active){
                        audioStart?.cancel();audioStart=null
                        stopLiveCall();tenantSession=null;audioReady=false;connected=false
                        nextAudioRetryAt=SystemClock.elapsedRealtime()+
                            2500L*audioAttemptCount.coerceIn(1,3)
                        callProblem="انقطعت جلسة الصوت (${internetRoute()})؛ المكالمة على الهاتف بدون AI"
                        notice(callProblem)
                    }
                    if(call!=null && startedAt>0 &&
                        SystemClock.elapsedRealtime()-startedAt>policy.optInt("max_call_minutes",10).coerceIn(1,30)*60000L){
                        audioStart?.cancel();audioStart=null
                        stopLiveCall();audioReady=false;connected=false
                        callProblem="انتهى الوقت المحدد لصوت الإيجنت؛ الهاتف ما زال متصلًا"
                        notice(callProblem)
                    }
                    lastState=current.state
                    showOverlay()
                    delay(750)
                }catch(timeout:TimeoutCancellationException){
                    // An LTE/ADB timeout must not terminate the SIM observer.
                    // Continue monitoring the physical call and allow bounded
                    // voice reconnect when the default internet route returns.
                    audioStart?.cancel();audioStart=null
                    stopLiveCall();tenantSession=null
                    audioReady=false;connected=false
                    nextAudioRetryAt=SystemClock.elapsedRealtime()+
                        2500L*audioAttemptCount.coerceIn(1,3)
                    callProblem="انتهت مهلة ربط الصوت (${internetRoute()})؛ محاولة استعادة"
                    notice(callProblem)
                    delay(1800)
                }catch(cancelled:CancellationException){throw cancelled}
                catch(error:Exception){
                    audioStart?.cancel();audioStart=null
                    // A transport/runtime failure is NOT an explicit takeover.
                    // Preserve diagnostic UI while keeping human MIC available.
                    stopLiveCall();tenantSession=null
                    audioReady=false;connected=false
                    nextAudioRetryAt=SystemClock.elapsedRealtime()+
                        2500L*audioAttemptCount.coerceIn(1,3)
                    val msg=error.message.orEmpty().take(150)
                    callProblem="تعذر اتصال صوت المكالمة (${internetRoute()}): "+msg
                    if(msg!=lastError){lastError=msg;notice(callProblem)}
                    showOverlay()
                    delay(1800)
                }
            }
        }finally{
            stopLiveCall();running=false;connected=false;audioReady=false;audioDiagnostic=""
            inCall=false;callAnswered=false;agentEnabled=false;callProblem=""
        }
    }
    /** Never continue pilot AI/telephony after any tenant SIM starts enrollment. */
    override fun onDestroy(){
        networkListener?.let{runCatching{connectivity?.unregisterNetworkCallback(it)}}
        stopLiveCall();scope.cancel();running=false;connected=false;inCall=false;callAnswered=false;agentEnabled=false;humanControl=false;audioReady=false;callProblem="";audioDiagnostic="";audioDiagnostic=""
        liveSimSlot=-1;tenantEventOutbox=null
        overlay.remove();latestStatus="الربط متوقف"
        super.onDestroy()
    }
}
