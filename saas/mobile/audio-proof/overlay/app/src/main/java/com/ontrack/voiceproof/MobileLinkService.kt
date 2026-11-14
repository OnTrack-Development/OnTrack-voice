package com.ontrack.voiceproof

import android.app.*
import android.content.Intent
import android.graphics.BitmapFactory
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
        const val ACTION_CAMPAIGN_PULL_NOW="com.ontrack.voiceproof.CAMPAIGN_PULL_NOW"
        const val ACTION_REVIVE_DAEMON="com.ontrack.voiceproof.REVIVE_DAEMON"
        // Kept for pre-0.1.9 notifications, mapped to server disable.
        const val ACTION_PAUSE="com.ontrack.voiceproof.PAUSE"
        /** Blocks pilot telephony during a tenant enrollment, even before token persistence. */
        @Volatile var tenantPairingInProgress=false
        @Volatile var running=false
        @Volatile var daemonReady=false
        @Volatile var daemonIssue=""
        @Volatile var agentEnabled=false
        @Volatile var connected=false
        @Volatile var serverRevision=0
        @Volatile var inCall=false
        @Volatile var callAnswered=false
        @Volatile var pendingSync=false
        @Volatile var humanControl=false
        @Volatile var audioReady=false
        @Volatile var audioDiagnostic=""
        @Volatile var stageCallerPcm=false
        @Volatile var stageCallerVoice=false
        @Volatile var stageAiReceived=false
        @Volatile var stageAiPlayed=false
        @Volatile var audioTelemetryText=""
        @Volatile var callProblem=""
        @Volatile var lastError=""
        @Volatile var latestStatus="الربط متوقف"
        @Volatile var campaignAutoEnabled=false
        @Volatile var campaignReadyCount=0
        @Volatile var campaignActiveName=""
        @Volatile var campaignId=""
        @Volatile var campaignStatus="idle"
        @Volatile var campaignTotalContacts=0
        @Volatile var campaignCompletedCalls=0
        @Volatile var campaignRemainingContacts=0
        @Volatile var campaignUncertainPending=0
        @Volatile var campaignNextName=""
        @Volatile var campaignNextPhone=""
        @Volatile var campaignScheduledAtCairo=""
        @Volatile var campaignSecondsUntilDial=0
        @Volatile var campaignQueueSyncedAtMs=0L
        @Volatile var campaignLastError=""
        @Volatile var lastDialRequestEpoch=0L
        @Volatile var dialNextRequested=false
        @Volatile var managerCallbackActive=false
        @Volatile var managerCallbackCountdownSeconds=0
        @Volatile var managerCallbackTargetMasked=""
        fun updateCampaignSummary(ready:Int,name:String){
            campaignReadyCount=ready
            campaignActiveName=name
        }
        fun updateCampaignQueue(queue:JSONObject?){
            if(queue==null)return
            campaignId=queue.optString("campaign_id","")
            campaignStatus=queue.optString("campaign_status","idle")
            val qName=queue.optString("campaign_name","").trim()
            if(qName.isNotBlank())campaignActiveName=qName
            campaignTotalContacts=queue.optInt("total_contacts",0)
            campaignCompletedCalls=queue.optInt("completed_calls",0)
            campaignRemainingContacts=queue.optInt("remaining_contacts",0)
            campaignUncertainPending=queue.optInt("uncertain_pending",0)
            val next=queue.optJSONObject("next_contact")
            if(next!=null){
                campaignNextName=next.optString("name","")
                campaignNextPhone=next.optString("phone","")
                campaignScheduledAtCairo=next.optString("scheduled_at_cairo","")
                campaignSecondsUntilDial=next.optInt("seconds_until_dial",0)
            }else{
                campaignNextName=""
                campaignNextPhone=""
                campaignScheduledAtCairo=""
                campaignSecondsUntilDial=0
            }
            campaignQueueSyncedAtMs=System.currentTimeMillis()
            if(campaignUncertainPending==0 && campaignStatus=="running" && campaignLastError.startsWith("uncertain"))campaignLastError=""
        }
        fun base(value:String):String {
            val uri=URI(value.trim())
            require(uri.scheme=="https" && uri.host=="app.ontrackegy.com" && uri.port in listOf(-1,443)
                && uri.userInfo==null && uri.query==null && uri.fragment==null && uri.path in listOf("","/"))
            return "https://app.ontrackegy.com/"
        }
    }
    private val serviceGuard=CoroutineExceptionHandler { _, error ->
        if(error !is CancellationException){
            val msg=error.message.orEmpty().ifBlank{error.javaClass.simpleName}.take(140)
            Companion.lastError=msg
            callProblem="خطأ مؤقت في خدمة الصوت: $msg"
            runCatching{notice(callProblem)}
        }
    }
    private val scope=CoroutineScope(SupervisorJob()+Dispatchers.IO+serviceGuard)
    private val main=Handler(Looper.getMainLooper())
    private val retryWake=Channel<Unit>(Channel.CONFLATED)
    private val client=OkHttpClient.Builder().dns(ResilientDns).followRedirects(false).followSslRedirects(false)
        .connectTimeout(2500,TimeUnit.MILLISECONDS).readTimeout(15,TimeUnit.SECONDS).pingInterval(15,TimeUnit.SECONDS).build()
    private lateinit var overlay:CallOverlay
    private var call:MobileLiveCall?=null
    private var loop:Job?=null
    private var callEventFlush:Job?=null
    private var callReportFlush:Job?=null
    private var callLogLookup:Job?=null
    private var tenantEventOutbox:CallEventOutbox?=null
    @Volatile private var liveSimSlot:Int=-1
    private var connectivity:ConnectivityManager?=null
    private var networkListener:ConnectivityManager.NetworkCallback?=null
    // A cellular radio can drop its packet network when a 3G/H+ call starts.
    // Track real default-network reconnects, not a guessed 4G-only state.
    @Volatile private var dataNetworkEpoch=0L
    @Volatile private var takeoverRequested=false
    @Volatile private var pendingDesired:Boolean?=null
    @Volatile private var recoveringActiveCall=false
    @Volatile private var triggerCampaignPollNow=false
    @Volatile private var forceDaemonCheck=true
    private fun hasPermission(permission:String)=androidx.core.content.ContextCompat.checkSelfPermission(
        this,permission)==android.content.pm.PackageManager.PERMISSION_GRANTED
    private fun syncCampaignOptIn(){
        val linkPrefs=getSharedPreferences("ontrack_link",MODE_PRIVATE)
        val userDisabled=linkPrefs.getBoolean("campaign_user_disabled",false)
        val paired=runCatching{TenantDeviceEnrollment(this).hasAny()}.getOrDefault(false)
        val enabled=TenantCampaignDialer.shouldDefaultOptIn(
            paired=paired,
            hasCallPhone=hasPermission(android.Manifest.permission.CALL_PHONE),
            hasReadPhone=hasPermission(android.Manifest.permission.READ_PHONE_STATE),
            agentEnabled=agentEnabled,
            userExplicitlyDisabled=userDisabled
        )
        linkPrefs.edit().putBoolean("campaign_opt_in",enabled).apply()
        campaignAutoEnabled=enabled
    }
    private fun showOverlay(){main.post{runCatching{if(::overlay.isInitialized)overlay.show(running,agentEnabled,inCall,callAnswered,humanControl,audioReady,callProblem,audioDiagnostic,daemonReady,daemonIssue)}}}
    private fun notice(value:String){
        latestStatus=value
        runCatching{getSystemService(NotificationManager::class.java)?.notify(701,notification(value))}
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
            managerCallbackActive && managerCallbackCountdownSeconds>0->"سيتم معاودة المدير خلال $managerCallbackCountdownSeconds ثانية"
            else->value
        }
        val item=NotificationCompat.Builder(this,"ontrack_mobile_link")
            .setSmallIcon(R.drawable.ic_stat_io_voice)
            .setLargeIcon(BitmapFactory.decodeResource(resources,R.mipmap.ic_launcher))
            .setContentTitle("IO Voice").setContentText(callLabel)
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
        agentEnabled=tenantPrefs().getBoolean("enabled",false)
        syncCampaignOptIn()
        pendingDesired=null
        pendingSync=false
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel("ontrack_mobile_link","IO Voice",NotificationManager.IMPORTANCE_LOW))
        connectivity=getSystemService(ConnectivityManager::class.java)
        val callback=object:ConnectivityManager.NetworkCallback(){
            override fun onAvailable(network:Network){
                dataNetworkEpoch++
                forceDaemonCheck=true
                ResilientDns.clearCache()
                client.connectionPool.evictAll()
                call?.onNetworkRouteChanged()
                retryWake.trySend(Unit)
            }
            override fun onCapabilitiesChanged(network:Network,caps:NetworkCapabilities){
                if(caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED))forceDaemonCheck=true
                retryWake.trySend(Unit)
            }
            override fun onLost(network:Network){
                connected=false
                forceDaemonCheck=true
                ResilientDns.clearCache()
                call?.onNetworkLost()
                retryWake.trySend(Unit)
            }
        }
        runCatching{connectivity?.registerDefaultNetworkCallback(callback)}
            .onSuccess{networkListener=callback}
    }
    private fun prewarmGreetingForSlot(slot: Int) {
        val enrollment = TenantDeviceEnrollment(this)
        if (slot !in 0..1 || !enrollment.paired(slot)) return
        scope.launch(Dispatchers.IO) {
            runCatching {
                val flow = enrollment.cachedPublishedFlow(slot) ?: enrollment.publishedFlow(slot)
                val pol = flow.optJSONObject("answer_policy") ?: JSONObject()
                val ivrGreeting = pol.optString("ivr_greeting", "").trim()
                val agentGreeting = pol.optString("greeting", "").trim()
                val greetingText = if (pol.optBoolean("ivr_enabled", false) && ivrGreeting.isNotBlank()) ivrGreeting
                    else if (agentGreeting.isNotBlank()) agentGreeting else ""
                val agentId = flow.optString("agent_id", "")
                val voice = flow.optString("voice", "")
                if (greetingText.isNotBlank()) {
                    GreetingPcmCache.prewarm(this@MobileLinkService, slot, agentId, voice, greetingText) { text, shouldContinue, onChunk ->
                        enrollment.streamPremiumPcm(slot, text, shouldContinue, onChunk)
                    }
                }
            }
        }
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
                    agentEnabled=false;call?.stop();call=null;audioReady=false;connected=false;clearCallAudioStages()
                    campaignAutoEnabled=false
                    notice("إيقاف محرك الشركات على هذا الجهاز")
                }
                ACTION_ENABLE->{
                    check(tenantPrefs().edit().putBoolean("enabled",true).commit()){
                        "تعذر حفظ حالة تشغيل محرك الشركات"
                    }
                    agentEnabled=true
                    forceDaemonCheck=true
                    retryWake.trySend(Unit)
                    syncCampaignOptIn()
                    notice("مراقبة الشريحة مفعّلة؛ جلسة Google Live تبدأ عند المكالمة")
                }
                ACTION_RESTORE->{
                    // Honor the last explicit user selection after process death/update.
                    // Never auto-enable a SIM the user deliberately switched off.
                    agentEnabled=tenantPrefs().getBoolean("enabled",false)
                    forceDaemonCheck=true
                    retryWake.trySend(Unit)
                    syncCampaignOptIn()
                    notice(if(agentEnabled)
                        "استُعيد تشغيل مراقبة الشرائح بعد فتح التطبيق"
                    else "الرد الآلي متوقف بقرار المستخدم؛ اضغط تشغيل لتفعيله")
                }
                ACTION_REVIVE_DAEMON->{
                    forceDaemonCheck=true
                    retryWake.trySend(Unit)
                    notice("جاري فحص وتنشيط محرك الصوت المحلي…")
                }
                ACTION_TAKEOVER->{
                    val slot=liveSimSlot
                    if(slot in 0..1 && inCall && callAnswered && audioReady && call?.active==true){
                        val bindingId=runCatching{TenantDeviceEnrollment(this).bindingId(slot)}.getOrNull()
                        if(bindingId!=null)scope.launch(Dispatchers.IO) {
                            runCatching{
                                tenantEventOutbox?.recordHumanTakeover(slot,bindingId)
                            }.onFailure{notice("تعذر حفظ حدث الاستلام؛ تابع سجل المكالمة")}
                        }
                        humanControl=true
                        call?.stop();call=null;audioReady=false;connected=false;clearCallAudioStages()
                        callProblem=""
                        notice("استلام بشري للمكالمة الحالية")
                    }else{
                        notice("لم تتصل جلسة صوت الإيجنت؛ لا يوجد صوت لاستلامه")
                    }
                }
                ACTION_CAMPAIGN_ENABLE->{
                    val permission=hasPermission(android.Manifest.permission.CALL_PHONE)
                    val readPhone=hasPermission(android.Manifest.permission.READ_PHONE_STATE)
                    val safe=permission && readPhone && agentEnabled && !inCall
                    if(safe){
                        check(getSharedPreferences("ontrack_link",MODE_PRIVATE).edit()
                            .putBoolean("campaign_user_disabled",false)
                            .putBoolean("campaign_opt_in",true).commit()){
                            "تعذر حفظ تفعيل الحملات"
                        }
                        campaignAutoEnabled=true
                        triggerCampaignPollNow=true
                        retryWake.trySend(Unit)
                        notice("تم تفعيل الاتصال التلقائي للحملات (Auto-Dialer)")
                    }else notice("الحملات متوقفة: شغل الإيجنت، امنح إذن الاتصال والشريحة، وأنهِ المكالمة الحالية")
                }
                ACTION_CAMPAIGN_DISABLE->{
                    getSharedPreferences("ontrack_link",MODE_PRIVATE).edit()
                        .putBoolean("campaign_user_disabled",true)
                        .putBoolean("campaign_opt_in",false).commit()
                    campaignAutoEnabled=false
                    notice("تم إيقاف طلب مكالمات حملات جديدة؛ لا نقطع مكالمة جارية")
                }
                ACTION_CAMPAIGN_PULL_NOW->{
                    val permission=hasPermission(android.Manifest.permission.CALL_PHONE)
                    val readPhone=hasPermission(android.Manifest.permission.READ_PHONE_STATE)
                    if(permission && readPhone && agentEnabled && !inCall){
                        getSharedPreferences("ontrack_link",MODE_PRIVATE).edit()
                            .putBoolean("campaign_user_disabled",false)
                            .putBoolean("campaign_opt_in",true).apply()
                        campaignAutoEnabled=true
                        dialNextRequested=true
                        campaignLastError=""
                        triggerCampaignPollNow=true
                        retryWake.trySend(Unit)
                        notice("جاري سحب الرقم التالي من الحملة الآن...")
                    }else{
                        val msg=when{
                            inCall->"أنهِ المكالمة الحالية أولاً"
                            !agentEnabled->"شغّل المساعد (الإيجنت) أولاً"
                            !permission->"امنح إذن إجراء المكالمات (CALL_PHONE)"
                            else->"امنح إذن قراءة الشريحة (READ_PHONE_STATE)"
                        }
                        campaignLastError=msg
                        notice("تعذر سحب رقم الحملة: $msg")
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
                prewarmGreetingForSlot(0)
                prewarmGreetingForSlot(1)
            }
            // Google Live stays idle until a call; show the confirmed SIM-monitor state.
            showOverlay()
            return START_STICKY
        }
        // Native release 0.3.4: never start the global, legacy phone pilot for
        // an unpaired device. A SIM-specific verified tenant binding is required.
        agentEnabled=false;connected=false
        call?.stop();call=null
        notice("اربط شريحة SIM بحساب IO Voice وإيجنت منشور قبل تشغيل المكالمات")
        stopSelf()
        return START_NOT_STICKY
    }
    private fun tenantPrefs()=getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE)
    /** Internet validity refers to Google Live's DEFAULT data path, not
     * the carrier call state. The SIM can be OFFHOOK while LTE data is
     * suspended (especially with another SIM as the data subscription).
     * Never use a Wi-Fi-only precondition for already answered SIM calls.
     */
    private fun internetAvailableForRetry():Boolean = runCatching {
        val cm=connectivity?:getSystemService(ConnectivityManager::class.java)
        val caps=cm?.activeNetwork?.let { cm.getNetworkCapabilities(it) }
        CellularCallRecoveryPolicy.mayRetryLive(
            defaultNetworkPresent=caps!=null,
            internetCapability=caps?.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)==true,
            validated=caps?.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)==true,
            cellularTransport=caps?.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)==true
        )
    }.getOrDefault(false)
    private fun clearCallAudioStages() {
        stageCallerPcm=false
        stageCallerVoice=false
        stageAiReceived=false
        stageAiPlayed=false
        audioTelemetryText=""
    }
    private fun syncLiveAudioStages() {
        val activeCall=call
        if(activeCall==null || !activeCall.active){
            clearCallAudioStages()
            return
        }
        stageCallerPcm=activeCall.hasCallerPcm()
        stageCallerVoice=activeCall.hasCallerVoice()
        stageAiReceived=activeCall.hasAiAudioReceived()
        stageAiPlayed=activeCall.hasAiAudioPlayed()
        val snap=activeCall.metricsSnapshot()
        audioTelemetryText="PCM ${snap.pcmFirstFrameMs}ms (فجوات:${snap.captureGapCount}/سقوط:${snap.pcmDroppedPackets}) · شبكة ${snap.networkConnectMs}ms (AI:${snap.aiFirstAudioMs}ms/تجاوز:${snap.networkSkippedUplinkPackets})"
        if(audioDiagnostic.isBlank() || audioDiagnostic.startsWith("ADB/5555:")){
            audioDiagnostic=buildString {
                append("ADB/5555:").append(if(daemonReady)"جاهز" else "متوقف")
                append(" | المتصل:").append(when{
                    stageCallerVoice->"صوت مسموع"
                    stageCallerPcm->"PCM واصل"
                    else->"لا يوجد PCM"
                })
                append(" | AI:").append(when{
                    stageAiPlayed->"يُشغَّل"
                    stageAiReceived->"مستلَم"
                    else->"بانتظار الرد"
                })
            }
        }
    }
    private fun dataRadioLabel():String = CellularRadioStatus.safeDataRadioLabel(this)
    private fun cellularCallNetworkHint(slot:Int):String = runCatching {
        if(NetworkRevive.onWifi(this) || internetAvailableForRetry())return@runCatching ""
        val callSubscription=runCatching{TenantSimHardware.subscriptionId(this,slot)}.getOrDefault(-1)
        val dataSubscription=runCatching{SubscriptionManager.getDefaultDataSubscriptionId()}.getOrDefault(-1)
        val support=CellularRadioStatus.safeConcurrentVoiceAndData(this,callSubscription)
        CellularRadioStatus.voiceDataHint(
            dataInternetAvailable=false,
            simultaneousVoiceData=support,
            callSimEqualsDataSim=dataSubscription<0 || dataSubscription==callSubscription)
    }.getOrDefault("الداتا غير متاحة أثناء المكالمة؛ إعادة ربط الإيجنت عند عودتها")
    private fun internetRoute():String = CellularRadioStatus.safeInternetRoute(this)
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
        if(nativeSlot!=null && daemonReady){
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
    private suspend fun checkDaemonReadiness(allowRevive: Boolean): Pair<Boolean, String> {
        var up = false
        // Warm-up retry loop: allow daemon socket up to 3 attempts x 400ms before declaring failure
        for (attempt in 1..3) {
            up = runCatching { CaptureDaemon.isRunning(this) }.getOrDefault(false) ||
                 runCatching { CaptureDaemon.isRunningConfirmed(this) }.getOrDefault(false)
            if (up) break
            if (attempt < 3) {
                delay(400L)
            }
        }
        val onWifiNow = NetworkRevive.onWifi(this)
        val wifiFreeNow = runCatching { StartWithoutWifi.ready(this) }.getOrDefault(false)
        val setupOk = runCatching { com.ontrack.voiceproof.ui.Setup.isComplete(this) }.getOrDefault(false)
        if (!up && allowRevive && (onWifiNow || wifiFreeNow)) {
            up = runCatching {
                CaptureDaemon.revive(this, request = CaptureDaemon.Request.USER)
            }.getOrDefault(false)
            if (!up) {
                delay(400L)
                up = runCatching { CaptureDaemon.isRunning(this) }.getOrDefault(false)
            }
        }
        if (up) {
            runCatching {
                AnsweringMachine.setEnabled(this, false)
                CaptureDaemon.setAnswering(this)
                RecorderSwitch.set(this, false)
                CaptureDaemon.setDaemonMode(this, CaptureDaemon.MODE_OFF)
            }
            return true to ""
        }
        val wirelessOn = runCatching {
            android.provider.Settings.Global.getInt(contentResolver, "adb_wifi_enabled", 0) != 0
        }.getOrDefault(false)
        val wifiRefused = onWifiNow && runCatching { NetworkRevive.stillRefusedHere(this, wirelessOn) }.getOrDefault(false)
        return false to CellularDaemonDiagnostics.diagnose(false, setupOk, onWifiNow, wifiFreeNow, wifiRefused)
    }
    private suspend fun tenantMonitor(){
        val enrollment=TenantDeviceEnrollment(this)
        val tenantEvents=CallEventOutbox(this,"ontrack_tenant_call_events")
        tenantEventOutbox=tenantEvents
        val reportOutbox=CallReportOutbox(this)
        var lastReportAttempt=0L
        var lastManagerPoll=0L
        var managerClaim:Deferred<Result<JSONObject>>?=null
        var managerCallback:JSONObject?=null
        var managerSawOffhook=false
        var managerDialAt=0L
        val callbackScheduler=ManagerCallbackScheduler()
        var dueManagerDial:Deferred<Result<JSONObject>>?=null
        var pendingEventCbSlot=-1
        var pendingEventCbPhone=""
        var ivrTransferPhone=""
        var ivrTransferLabel=""
        var lastCountdownNotified=0
        var campaignClaim:Deferred<Result<Pair<Int,JSONObject>?>>?=null
        var campaignDial:Deferred<Result<String>>?=null
        var campaignInProgress:JSONObject?=null
        var campaignSawOffhook=false
        var campaignDialAt=0L
        var campaignPhone=""
        var lastCampaignPoll=0L
        var selected:Int?=null
        var lastState=TelephonyManager.CALL_STATE_IDLE
        var ringingAt=0L
        var answeredByAgent=false
        var ringAttempted=false
        var autoAnswerRequested=false
        var autoAnswerAttempts=0
        var nextAutoAnswerRetryAt=0L
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
        var mobileDataLostDuringCall=false
        var lastDefaultNetworkEpoch=dataNetworkEpoch
        var lastObservedRadio=internetRoute()
        var perCallNetworkResets=0
        var daemonCheckJob:Deferred<Pair<Boolean,String>>?=null
        var lastDaemonCheckAt=0L
        var lastDaemonNetworkEpoch=dataNetworkEpoch
        var lastDaemonOnWifi=NetworkRevive.onWifi(this@MobileLinkService)
        var lastDaemonWifiFree=runCatching{StartWithoutWifi.ready(this@MobileLinkService)}.getOrDefault(false)
        var nextLocalBridgeRetryAt=0L
        runCatching{
            val initial=checkDaemonReadiness(allowRevive=true)
            daemonReady=initial.first
            daemonIssue=initial.second
            lastDaemonCheckAt=SystemClock.elapsedRealtime()
            forceDaemonCheck=false
            if(!daemonReady && daemonIssue.isNotBlank())notice(daemonIssue)
        }.onFailure{
            daemonReady=false
            daemonIssue="تعذر فحص محرك الصوت المحلي"
        }
        try{
            while(scope.isActive && enrollment.hasAny() && !tenantPairingInProgress){
                try{
                    agentEnabled=tenantPrefs().getBoolean("enabled",false)
                    if(!agentEnabled){
                        // Continue observing the SIM while the AI is disabled.
                        call?.stop();call=null;tenantSession=null;connected=false;audioReady=false
                    }
                    if(daemonCheckJob?.isCompleted==true){
                        val res=runCatching{daemonCheckJob?.await()}.getOrNull()
                        daemonCheckJob=null
                        if(res!=null){
                            val changed=res.first!=daemonReady || res.second!=daemonIssue
                            daemonReady=res.first
                            daemonIssue=res.second
                            if(changed){
                                if(res.first)notice("محرك الصوت المحلي جاهز")
                                else if(res.second.isNotBlank())notice(res.second)
                                showOverlay()
                            }
                        }
                    }
                    val curOnWifi=NetworkRevive.onWifi(this@MobileLinkService)
                    val curWifiFree=runCatching{StartWithoutWifi.ready(this@MobileLinkService)}.getOrDefault(false)
                    if(forceDaemonCheck || curOnWifi!=lastDaemonOnWifi || curWifiFree!=lastDaemonWifiFree ||
                        dataNetworkEpoch!=lastDaemonNetworkEpoch){
                        forceDaemonCheck=false
                        lastDaemonOnWifi=curOnWifi
                        lastDaemonWifiFree=curWifiFree
                        lastDaemonNetworkEpoch=dataNetworkEpoch
                        lastDaemonCheckAt=0L
                        nextLocalBridgeRetryAt=0L
                    }
                    val states=currentSlotCalls()
                    val active=states.filter{it.state!=TelephonyManager.CALL_STATE_IDLE}
                    if(active.isEmpty() && daemonCheckJob==null){
                        val daemonNow=SystemClock.elapsedRealtime()
                        val checkEveryMs=if(daemonReady)15000L else if(curOnWifi || curWifiFree)10000L else 18000L
                        if(lastDaemonCheckAt==0L || daemonNow-lastDaemonCheckAt>=checkEveryMs){
                            lastDaemonCheckAt=daemonNow
                            daemonCheckJob=scope.async(Dispatchers.IO){
                                runCatching{checkDaemonReadiness(allowRevive=true)}.getOrElse{
                                    false to "تعذر فحص محرك الصوت المحلي"
                                }
                            }
                        }
                    }
                    if(active.size>1){
                        audioStart?.cancel();audioStart=null
                        // Vendor telephony APIs can report the same call on both
                        // subscriptions. Never guess the tenant/SIM or claim a
                        // manual takeover that the user did not request.
                        call?.stop();call=null;tenantSession=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;connected=false;clearCallAudioStages()
                        inCall=true;callAnswered=active.any{it.state==TelephonyManager.CALL_STATE_OFFHOOK}
                        val issue="حالة المكالمة ملتبسة بين شريحتين؛ لم يتم توصيل صوت الإيجنت"
                        if(callProblem!=issue){callProblem=issue;notice(issue)}
                        delay(900);continue
                    }
                    val current=active.singleOrNull()
                    if(campaignInProgress!=null && current?.state==TelephonyManager.CALL_STATE_OFFHOOK &&
                       current.slot==campaignInProgress?.optInt("sim_slot",-1)){
                        campaignSawOffhook=true
                    }
                    if(campaignClaim?.isCompleted==true){
                        val proposed=campaignClaim?.await()?.getOrNull()
                        campaignClaim=null
                        if(proposed!=null){
                            val slot=proposed.first
                            val claim=proposed.second
                            val attempt=claim.optString("id","")
                            try{
                                check(slot in 0..1 && enrollment.paired(slot) &&
                                    getSharedPreferences("ontrack_link",MODE_PRIVATE)
                                        .getBoolean("campaign_opt_in",false) &&
                                    agentEnabled && current==null && managerCallback==null &&
                                    campaignInProgress==null && !tenantPairingInProgress){
                                    "توقفت صلاحية الاتصال قبل التحقق النهائي"
                                }
                                check(attempt.matches(Regex("^[a-f0-9]{32}$"))){
                                    "معرف محاولة الحملة غير صالح"
                                }
                                // Reserve this one attempt before starting network I/O.
                                // The telephony observer MUST NEVER block on LTE.
                                campaignInProgress=JSONObject(claim.toString()).put("sim_slot",slot)
                                campaignDialAt=0L;campaignSawOffhook=false
                                campaignDial=scope.async(Dispatchers.IO){
                                    runCatching{
                                        val verified=enrollment.workspace(slot,"workspace_campaign_verify",
                                            JSONObject().put("attempt_id",attempt))
                                            .getJSONObject("verification")
                                        check(verified.optBoolean("allowed",false) &&
                                            verified.optInt("sim_slot",-1)==slot &&
                                            verified.optInt("must_dial_within_seconds",0) in 1..10){
                                            "سيرفر الحملات رفض الموافقة الأخيرة"
                                        }
                                        check(getSharedPreferences("ontrack_link",MODE_PRIVATE)
                                            .getBoolean("campaign_opt_in",false) &&
                                            agentEnabled && enrollment.paired(slot) &&
                                            currentSlotCalls().all{
                                                it.state==TelephonyManager.CALL_STATE_IDLE
                                            }){
                                            "اتصال آخر بدأ أو تم إيقاف الحملة"
                                        }
                                        val phone=TenantCampaignDialer.e164(claim.getString("phone"))
                                        TenantCampaignDialer.place(this@MobileLinkService,slot,phone)
                                        phone
                                    }
                                }
                            }catch(problem:Exception){
                                val errMsg=problem.message.orEmpty().ifBlank{problem.javaClass.simpleName}.take(120)
                                campaignLastError=errMsg
                                if(slot in 0..1 && enrollment.paired(slot) &&
                                   attempt.matches(Regex("^[a-f0-9]{32}$"))){
                                    scope.launch(Dispatchers.IO){
                                        runCatching{enrollment.workspace(slot,"workspace_campaign_finish",
                                            JSONObject().put("attempt_id",attempt)
                                                .put("result","failed"))}
                                    }
                                }
                                notice("اتمنعت محاولة الحملة: "+errMsg)
                            }
                        }
                    }
                    if(campaignDial?.isCompleted==true){
                        val result=campaignDial?.await()
                        campaignDial=null
                        if(result?.isSuccess==true){
                            campaignLastError=""
                            campaignPhone=result.getOrThrow()
                            campaignDialAt=SystemClock.elapsedRealtime()
                            campaignInProgress?.put("dial_started_wall_ms",System.currentTimeMillis())
                            notice("تم بدء اتصال الحملة عبر الشريحة؛ انتهاء المكالمة لم يتأكد بعد")
                        }else{
                            val err=result?.exceptionOrNull()
                            val errMsg=err?.message.orEmpty().ifBlank{err?.javaClass?.simpleName ?: "فشل بدء المكالمة"}.take(120)
                            campaignLastError=errMsg
                            val failed=campaignInProgress
                            campaignInProgress=null
                            campaignDialAt=0L;campaignSawOffhook=false;campaignPhone=""
                            val slot=failed?.optInt("sim_slot",-1)?:-1
                            val attempt=failed?.optString("id","").orEmpty()
                            if(slot in 0..1 && enrollment.paired(slot) &&
                               attempt.matches(Regex("^[a-f0-9]{32}$"))){
                                scope.launch(Dispatchers.IO){
                                    runCatching{enrollment.workspace(slot,"workspace_campaign_finish",
                                        JSONObject().put("attempt_id",attempt)
                                            .put("result","failed"))}
                                }
                            }
                            notice("فشل بدء مكالمة الحملة: $errMsg")
                        }
                    }
                    if(triggerCampaignPollNow){
                        triggerCampaignPollNow=false
                        lastCampaignPoll=0L
                    }
                    syncCampaignOptIn()
                    if(current==null && campaignInProgress==null && campaignClaim==null &&
                       managerCallback==null && managerClaim==null &&
                       !callbackScheduler.active && dueManagerDial==null &&
                       agentEnabled && getSharedPreferences("ontrack_link",MODE_PRIVATE)
                           .getBoolean("campaign_opt_in",false) &&
                       SystemClock.elapsedRealtime()-lastCampaignPoll>=10000L){
                        lastCampaignPoll=SystemClock.elapsedRealtime()
                        val forceDialNext=dialNextRequested
                        dialNextRequested=false
                        campaignClaim=scope.async(Dispatchers.IO){
                            runCatching{
                                var found:Pair<Int,JSONObject>?=null
                                for(slot in 0..1){
                                    if(!enrollment.paired(slot))continue
                                    if(forceDialNext){
                                        runCatching{
                                            val dialRes=enrollment.workspace(slot,"workspace_campaign_dial_next")
                                            updateCampaignQueue(dialRes.optJSONObject("queue"))
                                        }
                                    }
                                    val reply=enrollment.workspace(slot,"workspace_campaign_claim")
                                    val claim=reply.optJSONObject("claim")
                                    if(claim!=null){found=slot to claim;break}
                                    val reason=reply.optString("reason","").trim()
                                    if(reason.isNotBlank() && forceDialNext){
                                        campaignLastError=reason
                                    }
                                }
                                found
                            }.onFailure{err->
                                campaignLastError=err.message.orEmpty().take(120)
                            }
                        }
                    }
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
                                campaignInProgress==null && campaignClaim==null &&
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
                    val observedBinding=current?.slot?.takeIf { enrollment.paired(it) }
                        ?.let { runCatching { enrollment.bindingId(it) }.getOrNull() }
                    tenantEvents.observe(if(observedBinding!=null)current!!.state else TelephonyManager.CALL_STATE_IDLE,
                        if(observedBinding!=null)current!!.slot else -1,observedBinding?:-1)
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
                            val srvCb=response.optJSONObject("manager_callback")
                            if(srvCb!=null && srvCb.optBoolean("enabled",false)){
                                val srvPhone=srvCb.optString("phone","").trim()
                                if(srvPhone.isNotBlank()){
                                    pendingEventCbSlot=slot
                                    pendingEventCbPhone=srvPhone
                                }
                            }
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
                        val attempt=campaignInProgress
                        if(attempt!=null && campaignDial==null && campaignDialAt>0L &&
                           (campaignSawOffhook ||
                            SystemClock.elapsedRealtime()-campaignDialAt>=45000L)){
                            val slot=attempt.optInt("sim_slot",-1)
                            val attemptId=attempt.optString("id","")
                            val hadOffhook=campaignSawOffhook
                            val phone=campaignPhone
                            val placedAt=attempt.optLong("dial_started_wall_ms",0L)
                            campaignInProgress=null;campaignSawOffhook=false
                            campaignPhone="";campaignDialAt=0L
                            if(slot in 0..1 && enrollment.paired(slot) &&
                               attemptId.matches(Regex("^[a-f0-9]{32}$"))){
                                scope.launch(Dispatchers.IO){
                                    delay(2000L) // CallLog may lag behind carrier idle.
                                    val outcome=if(hadOffhook && placedAt>0L)
                                        TenantCampaignDialer.finishResult(this@MobileLinkService,phone,placedAt)
                                        else "unknown"
                                    runCatching{enrollment.workspace(slot,"workspace_campaign_finish",
                                        JSONObject().put("attempt_id",attemptId).put("result",outcome))}
                                        .onFailure{notice("نتيجة مكالمة الحملة غير مؤكدة؛ يلزم مراجعة قبل تكرارها")}
                                }
                            }
                        }
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
                        audioStart?.cancel();audioStart=null
                        flowLoad?.cancel();flowLoad=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;callProblem="";audioDiagnostic="";clearCallAudioStages()
                        if(selected!=null){
                            val endedSlot=selected!!
                            val hadMgr=managerCallback!=null
                            val selectedTransferPhone=ivrTransferPhone.trim()
                            val selectedTransferLabel=ivrTransferLabel.trim()
                            ivrTransferPhone="";ivrTransferLabel=""
                            val cbEnabled=(tenantSession?.optBoolean("manager_callback_enabled",false)==true) ||
                                policy.optBoolean("manager_callback_enabled",false)
                            val cbPhone=tenantSession?.optString("manager_callback_phone","")?.trim()
                                ?.ifBlank{policy.optString("manager_callback_phone","").trim()}.orEmpty()
                            val effectiveCbEnabled=selectedTransferPhone.isNotBlank() || cbEnabled
                            val effectiveCbPhone=selectedTransferPhone.ifBlank{cbPhone}
                            val effectiveCbName=if(selectedTransferPhone.isNotBlank()){
                                if(selectedTransferLabel.isNotBlank())"قسم: $selectedTransferLabel" else "القسم / المدير"
                            } else "المدير المناوب"
                            val wasAgentAnswered=answeredByAgent
                            val endedCall=call;call=null
                            endedCall?.stop()
                            // Flush accumulated transcript segment into CallReportOutbox before idle finalization.
                            endedCall?.awaitReports()
                            tenantSession=null;tenantFlow=null
                            selected=null;liveSimSlot=-1;answeredByAgent=false;ringAttempted=false;autoAnswerRequested=false;autoAnswerAttempts=0;nextAutoAnswerRetryAt=0L;nextLocalBridgeRetryAt=0L;outgoingCall=false;humanControl=false;policy=JSONObject()
                            ringingAt=0L;startedAt=0L;audioAttemptCount=0;nextAudioRetryAt=0L;lastDaemonCheckAt=0L
                            if(!hadMgr && effectiveCbEnabled && effectiveCbPhone.isNotBlank() && agentEnabled && wasAgentAnswered){
                                callbackScheduler.schedule(endedSlot,effectiveCbPhone,effectiveCbName,"",SystemClock.elapsedRealtime())
                                pendingEventCbSlot=-1;pendingEventCbPhone=""
                                notice("ستتم معاودة $effectiveCbName خلال 10 ثوانٍ على شريحة SIM ${endedSlot+1}")
                            }
                        }
                        if(pendingEventCbSlot in 0..1 && pendingEventCbPhone.isNotBlank()){
                            if(!callbackScheduler.active && managerCallback==null && agentEnabled){
                                callbackScheduler.schedule(pendingEventCbSlot,pendingEventCbPhone,"المدير المناوب","",SystemClock.elapsedRealtime())
                                notice("ستتم معاودة المدير خلال 10 ثوانٍ على شريحة SIM ${pendingEventCbSlot+1}")
                            }
                            pendingEventCbSlot=-1;pendingEventCbPhone=""
                        }
                        val cbNow=SystemClock.elapsedRealtime()
                        val cbRem=callbackScheduler.remainingSeconds(cbNow)
                        managerCallbackActive=callbackScheduler.active
                        managerCallbackCountdownSeconds=cbRem
                        managerCallbackTargetMasked=if(callbackScheduler.active) callbackScheduler.maskedPhone() else ""
                        if(callbackScheduler.active && cbRem>0 && cbRem!=lastCountdownNotified){
                            lastCountdownNotified=cbRem
                            notice("معاودة المدير خلال $cbRem ثانية (SIM ${callbackScheduler.pendingSlot+1})")
                        }else if(!callbackScheduler.active){
                            lastCountdownNotified=0
                        }
                        if(dueManagerDial?.isCompleted==true){
                            val fetched=dueManagerDial?.await()
                            dueManagerDial=null
                            val job=fetched?.getOrNull()
                            if(job!=null){
                                val slot=job.optInt("sim_slot",-1)
                                val jobId=job.optInt("id",0)
                                val safeToCall=current==null && managerCallback==null &&
                                    campaignInProgress==null && agentEnabled && !tenantPairingInProgress &&
                                    slot in 0..1 && enrollment.paired(slot)
                                try{
                                    check(safeToCall){"مكالمة أخرى جارية أو الشريحة غير جاهزة"}
                                    HumanEscalationDialer.placeOnPairedSim(this@MobileLinkService,slot,job.getString("phone"))
                                    managerCallback=job
                                    managerSawOffhook=false
                                    managerDialAt=SystemClock.elapsedRealtime()
                                    if(jobId>0)scope.launch(Dispatchers.IO){
                                        runCatching{enrollment.workspace(slot,"workspace_handoff_ack",
                                            JSONObject().put("job_id",jobId).put("stage","dial_started"))}
                                    }
                                    notice("جاري معاودة المدير ("+job.optString("name","المسؤول")+") على SIM ${slot+1}")
                                }catch(problem:Exception){
                                    if(jobId>0 && slot in 0..1 && enrollment.paired(slot))scope.launch(Dispatchers.IO){
                                        runCatching{enrollment.workspace(slot,"workspace_handoff_ack",
                                            JSONObject().put("job_id",jobId).put("stage","failed"))}
                                    }
                                    notice("تعذر معاودة المدير تلقائيًا: "+problem.message.orEmpty().take(110))
                                }
                            }
                        }
                        val dueNow=callbackScheduler.consumeDue(SystemClock.elapsedRealtime())
                        if(dueNow!=null){
                            managerCallbackActive=false
                            managerCallbackCountdownSeconds=0
                            managerCallbackTargetMasked=""
                            lastCountdownNotified=0
                            if(managerCallback==null && campaignInProgress==null && agentEnabled && !tenantPairingInProgress){
                                dueManagerDial=scope.async(Dispatchers.IO){
                                    runCatching{
                                        val claimed=runCatching{enrollment.workspace(dueNow.slot,"workspace_handoff_claim")}.getOrNull()
                                        val srvJob=claimed?.optJSONObject("job")
                                        if(srvJob!=null){
                                            if(dueNow.name.startsWith("قسم:") && dueNow.phone.isNotBlank()){
                                                srvJob.put("phone",dueNow.phone).put("name",dueNow.name)
                                            }
                                            srvJob
                                        }else JSONObject()
                                            .put("id",0)
                                            .put("sim_slot",dueNow.slot)
                                            .put("phone",dueNow.phone)
                                            .put("name",dueNow.name)
                                    }
                                }
                            }
                        }
                        if(managerCallback==null && managerClaim==null &&
                            !callbackScheduler.active && dueManagerDial==null &&
                            campaignInProgress==null && campaignClaim==null && agentEnabled &&
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
                        // The cellular call has ended. Only NOW submit the
                        // encrypted, accumulated real-time text to summarization.
                        runCatching{
                            reportOutbox.finalizeAtIdle { slot ->
                                if(enrollment.paired(slot))runCatching{enrollment.bindingId(slot)}.getOrNull()
                                else null
                            }
                        }.onFailure{notice("نص المكالمة محفوظ؛ تعذر تجهيزه للملخص وسيُعاد بعد انشغال الخط")}
                        inCall=false;callAnswered=false
                        if(callLogLookup?.isActive!=true)callLogLookup=scope.launch(Dispatchers.IO) {
                            delay(2600)
                            if(!inCall)runCatching{tenantEvents.enrichFromCallLog { !inCall }}
                        }
                        // Running a monitor is not an authenticated voice connection.
                        connected=false
                        if(lastState!=TelephonyManager.CALL_STATE_IDLE && !callbackScheduler.active)notice("مراقب الشرائح جاهز؛ اختبر Google والـFlow قبل المكالمة")
                        lastState=TelephonyManager.CALL_STATE_IDLE
                        delay(750);continue
                    }
                    // The observed SIM must be paired; other subscriptions remain human-only.
                    if(!enrollment.paired(current.slot)){
                        audioStart?.cancel();audioStart=null
                        if(selected!=null){call?.stop();call=null}
                        selected=null;tenantSession=null;connected=false;audioReady=false
                        delay(750);continue
                    }
                    if(selected!=current.slot){
                        callbackScheduler.cancel()
                        dueManagerDial?.cancel();dueManagerDial=null
                        pendingEventCbSlot=-1;pendingEventCbPhone=""
                        ivrTransferPhone="";ivrTransferLabel=""
                        managerCallbackActive=false;managerCallbackCountdownSeconds=0;managerCallbackTargetMasked=""
                        lastCountdownNotified=0
                        audioStart?.cancel();audioStart=null
                        flowLoad?.cancel();flowLoad=null
                        sessionLoad?.cancel();sessionLoad=null
                        audioReady=false;callProblem="";audioDiagnostic="";clearCallAudioStages()
                        selected=current.slot;liveSimSlot=current.slot;answeredByAgent=false;ringAttempted=false;autoAnswerRequested=false;autoAnswerAttempts=0;nextAutoAnswerRetryAt=0L;outgoingCall=false;tenantFlow=null;tenantSession=null
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
                        val cachedFlow=enrollment.cachedPublishedFlow(slot)
                        if(cachedFlow!=null){
                            tenantFlow=cachedFlow
                            policy=enrollment.cachedPolicy(slot)
                        }
                        // Load signed Flow/policy without pausing shell ring polling.
                        flowLoad=scope.async(Dispatchers.IO){
                            runCatching{enrollment.publishedFlow(slot)}
                        }
                        notice("الشريحة ${slot+1}: تم رصد الرنين؛ سياسة الإيجنت جاهزة محلياً")
                    }
                    if(flowLoad?.isCompleted==true){
                        val result=flowLoad?.await()
                        flowLoad=null
                        result?.onSuccess { flow->
                            tenantFlow=flow
                            policy=flow.optJSONObject("answer_policy")?:JSONObject()
                            prewarmGreetingForSlot(current.slot)
                            val auto=policy.optBoolean("auto_answer",false)
                            callProblem=if(auto) "" else "الرد التلقائي مغلق في إعدادات الإيجنت"
                            if(current.state==TelephonyManager.CALL_STATE_RINGING &&
                                sessionLoad==null && tenantSession==null && agentEnabled){
                                // Warm the per-SIM authorized Gemini token during
                                // ringing, so OFFHOOK doesn't wait on three HTTP
                                // requests before the audio bridge can start.
                                val slot=current.slot
                                val snapshot=flow
                                sessionLoad=scope.async(Dispatchers.IO){
                                    runCatching{
                                        val triple=Triple(snapshot,policy,enrollment.voiceSession(slot,snapshot))
                                        retryWake.trySend(Unit)
                                        triple
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
                            autoAnswerRequested,humanControl) &&
                        CellularCallRecoveryPolicy.autoAnswerRetryAllowed(
                            ringing=true,alreadyAcknowledged=autoAnswerRequested,
                            attempts=autoAnswerAttempts,nowMs=SystemClock.elapsedRealtime(),
                            nextRetryAtMs=nextAutoAnswerRetryAt)){
                        // Retry temporary LTE bridge failures while ringing;
                        // acknowledged answers and OFFHOOK are never retried.
                        autoAnswerAttempts++
                        try{
                            val onWifiNow=NetworkRevive.onWifi(this@MobileLinkService)
                            val wifiFreeNow=runCatching{StartWithoutWifi.ready(this@MobileLinkService)}.getOrDefault(false)
                            var bridgeUp=runCatching{CaptureDaemon.isRunning(this@MobileLinkService)}.getOrDefault(false) ||
                                     runCatching{CaptureDaemon.isRunningConfirmed(this@MobileLinkService)}.getOrDefault(false)
                            if(!bridgeUp && (onWifiNow || wifiFreeNow)){
                                bridgeUp=runCatching{CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)}.getOrDefault(false)
                            }
                            if(!bridgeUp){
                                val setupOk=runCatching{com.ontrack.voiceproof.ui.Setup.isComplete(this@MobileLinkService)}.getOrDefault(false)
                                val wirelessOn=runCatching{android.provider.Settings.Global.getInt(contentResolver,"adb_wifi_enabled",0)!=0}.getOrDefault(false)
                                val wifiRefused=onWifiNow && runCatching{NetworkRevive.stillRefusedHere(this@MobileLinkService,wirelessOn)}.getOrDefault(false)
                                val diag=CellularDaemonDiagnostics.diagnose(false,setupOk,onWifiNow,wifiFreeNow,wifiRefused)
                                daemonReady=false
                                daemonIssue=diag
                                nextAutoAnswerRetryAt=SystemClock.elapsedRealtime()+
                                    CellularCallRecoveryPolicy.answerRetryDelayMs(autoAnswerAttempts)
                                callProblem="تعذر الرد التلقائي ("+internetRoute()+"): "+diag
                                notice(callProblem+" — الرد اليدوي ما زال متاحًا للإيجنت")
                            }else{
                                daemonReady=true
                                daemonIssue=""
                                val daemonState=CaptureDaemon.linkControl(this@MobileLinkService,'Q')
                                if(daemonState!="CALL 1"){
                                    nextAutoAnswerRetryAt=SystemClock.elapsedRealtime()+
                                        CellularCallRecoveryPolicy.answerRetryDelayMs(autoAnswerAttempts)
                                    callProblem="رابط الرد الآلي لم يؤكد الرنين: "+(daemonState?:"لا توجد استجابة")
                                    notice(callProblem+" — الرد اليدوي ما زال متاحًا للإيجنت")
                                }else{
                                    val ack=CaptureDaemon.linkControl(this@MobileLinkService,'A')
                                    if(ack==null){
                                        nextAutoAnswerRetryAt=SystemClock.elapsedRealtime()+
                                            CellularCallRecoveryPolicy.answerRetryDelayMs(autoAnswerAttempts)
                                        callProblem="لم يؤكد رابط الهاتف أمر الرد التلقائي"
                                        notice(callProblem+" — الرد اليدوي ما زال متاحًا للإيجنت")
                                    }else{
                                        autoAnswerRequested=true
                                        callProblem=""
                                        notice("أُرسل أمر الرد للشريحة ${current.slot+1}؛ انتظار تأكيد OFFHOOK")
                                    }
                                }
                            }
                        }catch(problem:Throwable){
                            nextAutoAnswerRetryAt=SystemClock.elapsedRealtime()+
                                CellularCallRecoveryPolicy.answerRetryDelayMs(autoAnswerAttempts)
                            callProblem="تعذر الرد التلقائي ("+internetRoute()+"): "+
                                problem.message.orEmpty().take(110)
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
                    // 3G/H+ voice calls can suspend packet data, then return it.
                    // Reset a bounded exhausted AI-start budget only after a genuine
                    // default network transition or an observed offline->online change.
                    // Never answer the carrier call a second time or override takeover.
                    if(current.state==TelephonyManager.CALL_STATE_OFFHOOK &&
                        !humanControl && !audioReady){
                        val dataReady=internetAvailableForRetry()
                        if(!dataReady)mobileDataLostDuringCall=true
                        val networkChanged=dataNetworkEpoch!=lastDefaultNetworkEpoch
                        val currentRadio=internetRoute()
                        // 4G -> H+ can keep the same Android Network object;
                        // compare actual radio as well as onAvailable callbacks.
                        val radioChanged=lastObservedRadio!=currentRadio &&
                            lastObservedRadio.isNotBlank()
                        lastObservedRadio=currentRadio
                        if(dataReady && (mobileDataLostDuringCall || networkChanged || radioChanged) &&
                            perCallNetworkResets<2 &&
                            CellularCallRecoveryPolicy.canRearmAfterNetworkReturn(
                                current.state==TelephonyManager.CALL_STATE_OFFHOOK,
                                agentEnabled,humanControl,audioReady,audioAttemptCount)){
                            mobileDataLostDuringCall=false
                            perCallNetworkResets++
                            audioAttemptCount=audioAttemptCount.coerceAtMost(1)
                            nextAudioRetryAt=now+1000L
                        }
                        if(dataReady)lastDefaultNetworkEpoch=dataNetworkEpoch
                    }
                    if(call==null && tenantSession==null && sessionLoad==null && audioStart==null &&
                       TenantCallRouting.voiceRetryDue(
                           current.state==TelephonyManager.CALL_STATE_OFFHOOK,
                           agentEnabled,humanControl,audioReady,answeredByAgent,
                           audioAttemptCount,CellularCallRecoveryPolicy.MAX_VOICE_START_ATTEMPTS,
                            internetAvailableForRetry(),now,nextAudioRetryAt)){
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
                        val preparedFlow=tenantFlow ?: enrollment.cachedPublishedFlow(slot)
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
                            if(current.state==TelephonyManager.CALL_STATE_OFFHOOK){
                                notice("الشريحة ${current.slot+1}: جلسة الإيجنت صالحة؛ توصيل صوت المكالمة")
                                retryWake.trySend(Unit)
                            }
                        }?.onFailure { problem ->
                            tenantSession=null
                            val isSlowCellular = runCatching { CellularRadioStatus.isSlowCellularLink(this@MobileLinkService) }.getOrDefault(false)
                            val retryDelay = if (isSlowCellular) 400L else CellularCallRecoveryPolicy.retryDelayMs(audioAttemptCount)
                            nextAudioRetryAt=SystemClock.elapsedRealtime()+retryDelay
                            val message=problem.message.orEmpty().take(140)
                            val hint=cellularCallNetworkHint(current.slot)
                            callProblem="تعذر تشغيل صوت الإيجنت ("+internetRoute()+"): "+message+
                                (if(hint.isNotEmpty()) "؛ "+hint else "")
                            if(message!=lastError){lastError=message;notice(callProblem)}
                        }
                    }
                    if(current.state==TelephonyManager.CALL_STATE_OFFHOOK &&
                        answeredByAgent && tenantSession!=null && call==null && !humanControl &&
                        now>=nextLocalBridgeRetryAt){
                        val onWifiNow=NetworkRevive.onWifi(this@MobileLinkService)
                        val wifiFreeNow=runCatching{StartWithoutWifi.ready(this@MobileLinkService)}.getOrDefault(false)
                        var bridgeUp=runCatching{CaptureDaemon.isRunning(this@MobileLinkService)}.getOrDefault(false) ||
                                     runCatching{CaptureDaemon.isRunningConfirmed(this@MobileLinkService)}.getOrDefault(false)
                        if(!bridgeUp && (onWifiNow || wifiFreeNow)){
                            bridgeUp=runCatching{CaptureDaemon.revive(this@MobileLinkService,request=CaptureDaemon.Request.USER)}.getOrDefault(false)
                        }
                        if(!bridgeUp){
                            val setupOk=runCatching{com.ontrack.voiceproof.ui.Setup.isComplete(this@MobileLinkService)}.getOrDefault(false)
                            val wirelessOn=runCatching{android.provider.Settings.Global.getInt(contentResolver,"adb_wifi_enabled",0)!=0}.getOrDefault(false)
                            val wifiRefused=onWifiNow && runCatching{NetworkRevive.stillRefusedHere(this@MobileLinkService,wirelessOn)}.getOrDefault(false)
                            val diag=CellularDaemonDiagnostics.diagnose(false,setupOk,onWifiNow,wifiFreeNow,wifiRefused)
                            daemonReady=false
                            daemonIssue=diag
                            callProblem=diag
                            nextLocalBridgeRetryAt=now+if(onWifiNow || wifiFreeNow)4000L else 12000L
                            notice(callProblem)
                            showOverlay()
                            withTimeoutOrNull(750L){retryWake.receiveCatching()}
                            continue
                        }
                        daemonReady=true
                        daemonIssue=""
                        val daemonState=runCatching{CaptureDaemon.linkControl(this@MobileLinkService,'Q')}.getOrNull()
                        if(daemonState!="CALL 2"){
                            callProblem="تعذر تأكيد وصول المكالمة إلى PCM: "+(daemonState?:"لا توجد استجابة")
                            nextLocalBridgeRetryAt=now+2500L
                            notice(callProblem)
                            showOverlay()
                            withTimeoutOrNull(750L){retryWake.receiveCatching()}
                            continue
                        }
                        val sess=tenantSession!!
                        val selectedCode=sess.optString("voice",
                            tenantFlow?.optString("voice","google.aoede").orEmpty())
                        val isFishVoice=selectedCode=="io.premium" || selectedCode.startsWith("fish.")
                        if(isFishVoice)check(sess.optString("voice_engine")=="official_google_live_fish_pcm"){
                            "اختيار صوت Fish Audio غير مدعوم في نسخة السيرفر الحالية"
                        }
                        val voice=if(isFishVoice)"Aoede"
                            else selectedCode.substringAfterLast('.')
                                .replaceFirstChar{it.uppercaseChar()}
                        val voiceLabel=sess.optString("voice_label","").trim().ifBlank{
                            when {
                                selectedCode=="io.premium"->"IO Premium"
                                selectedCode.startsWith("fish.")->"Fish Audio"
                                else->voice
                            }
                        }
                        val liveSettings=JSONObject().put("voice",voice)
                            .put("voice_label",voiceLabel)
                            .put("input_channel","mix")
                            .put("scenario_mode",policy.optString("scenario_mode","builder"))
                            .put("response_style",policy.optString("response_style","egyptian_short"))
                            .put("ivr_enabled",sess.optBoolean("ivr_enabled",policy.optBoolean("ivr_enabled",false)))
                            .put("ivr_greeting",sess.optString("ivr_greeting",policy.optString("ivr_greeting","")))
                            .put("ivr_routes",sess.optJSONArray("ivr_routes")?:policy.optJSONArray("ivr_routes")?:org.json.JSONArray())
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
                            premiumPcm=if(isFishVoice) { text,keepRunning,chunk ->
                                enrollment.streamPremiumPcm(reportSlot,text,keepRunning,chunk)
                            } else null,
                            onIvrRouteSelected={ digit, label, transferPhone ->
                                scope.launch(Dispatchers.Main) {
                                    runCatching {
                                        if(inCall && liveSimSlot==reportSlot && !humanControl && transferPhone.isNotBlank()){
                                            ivrTransferPhone=transferPhone
                                            ivrTransferLabel=label
                                            val maskedTarget=transferPhone.trim().let{ if(it.length<=6) it else it.take(4)+"****"+it.takeLast(3) }
                                            notice("IVR: قسم $label (زر $digit) — سيتم الاتصال بـ $maskedTarget بعد المكالمة بـ 10 ثوانٍ")
                                            showOverlay()
                                        }
                                    }
                                }
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
                                runCatching {
                                    if(inCall && liveSimSlot==reportSlot && !humanControl){
                                        audioDiagnostic=diagnostic
                                        showOverlay()
                                    }
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
                        audioReady=false;audioDiagnostic="";connected=false;clearCallAudioStages()
                        audioStart=scope.async(Dispatchers.IO){
                            try{
                                currentCall.start()
                                retryWake.trySend(Unit)
                                Result.success(Unit)
                            }catch(timeout:TimeoutCancellationException){
                                // A 25s Google setup timeout on LTE is a
                                // recoverable media error, NOT cancellation of
                                // this long-lived SIM monitor. Bubble it into
                                // the bounded voiceRetryDue path after internet
                                // revalidates. Only real job cancellation
                                // should stop the coroutine.
                                runCatching{currentCall.stop()}
                                Result.failure(IllegalStateException(
                                    "انتهت مهلة Google Live على ${internetRoute()}؛ إعادة محاولة الصوت",timeout))
                            }catch(cancelled:CancellationException){throw cancelled}
                            catch(problem:Throwable){
                                runCatching{currentCall.stop()}
                                Result.failure(problem)
                            }
                        }
                        startedAt=SystemClock.elapsedRealtime()
                        notice("الشريحة ${current.slot+1}: توصيل PCM وكتم الميكروفون قبل تجهيز Google Live")
                    }
                    if(audioStart?.isCompleted==true){
                        val result=audioStart?.await()
                        audioStart=null
                        result?.onSuccess{
                            if(call?.active==true){
                                syncLiveAudioStages()
                                audioReady=true;connected=true;callProblem=""
                                notice("PCM وGoogle Live جاهزين على SIM ${current.slot+1}؛ اختبار صوت الجهاز مطلوب")
                            }
                        }?.onFailure{problem->
                            call?.stop();call=null;tenantSession=null
                            audioReady=false;connected=false;clearCallAudioStages()
                            val isSlowCellular = runCatching { CellularRadioStatus.isSlowCellularLink(this@MobileLinkService) }.getOrDefault(false)
                            val retryDelay = if (isSlowCellular) 400L else CellularCallRecoveryPolicy.retryDelayMs(audioAttemptCount)
                            nextAudioRetryAt=SystemClock.elapsedRealtime()+retryDelay
                            callProblem="فشل ربط الصوت (${internetRoute()}): "+problem.message.orEmpty().take(110)
                            notice(callProblem)
                        }
                    }
                    if(call!=null && !call!!.active && !call!!.isRecovering){
                        audioStart?.cancel();audioStart=null
                        call=null;tenantSession=null;audioReady=false;connected=false;clearCallAudioStages()
                        nextAudioRetryAt=SystemClock.elapsedRealtime()+
                            CellularCallRecoveryPolicy.retryDelayMs(audioAttemptCount)
                        callProblem="انقطعت جلسة الصوت (${internetRoute()})؛ المكالمة على الهاتف بدون AI"
                        notice(callProblem)
                    } else if(call!=null && call!!.isRecovering){
                        audioReady=false
                        callProblem="انقطع اتصال خدمة الصوت؛ جاري استعادة الصوت تلقائيًا على ${internetRoute()}…"
                    }
                    if(call!=null && startedAt>0 &&
                        SystemClock.elapsedRealtime()-startedAt>policy.optInt("max_call_minutes",10).coerceIn(1,30)*60000L){
                        audioStart?.cancel();audioStart=null
                        call?.stop();call=null;audioReady=false;connected=false;clearCallAudioStages()
                        callProblem="انتهى الوقت المحدد لصوت الإيجنت؛ الهاتف ما زال متصلًا"
                        notice(callProblem)
                    }
                    syncLiveAudioStages()
                    lastState=current.state
                    showOverlay()
                    withTimeoutOrNull(750L){retryWake.receiveCatching()}
                }catch(timeout:TimeoutCancellationException){
                    // An LTE/ADB timeout must not terminate the SIM observer.
                    // Continue monitoring the physical call and allow bounded
                    // voice reconnect when the default internet route returns.
                    audioStart?.cancel();audioStart=null
                    call?.stop();call=null;tenantSession=null
                    audioReady=false;connected=false;clearCallAudioStages()
                    nextAudioRetryAt=SystemClock.elapsedRealtime()+
                        CellularCallRecoveryPolicy.retryDelayMs(audioAttemptCount)
                    callProblem="انتهت مهلة ربط الصوت (${internetRoute()})؛ محاولة استعادة"
                    notice(callProblem)
                    delay(1800)
                }catch(cancelled:CancellationException){throw cancelled}
                catch(error:Throwable){
                    runCatching{audioStart?.cancel()};audioStart=null
                    // A transport/runtime failure is NOT an explicit takeover.
                    // Preserve diagnostic UI while keeping human MIC available.
                    runCatching{call?.stop()};call=null;tenantSession=null
                    audioReady=false;connected=false;clearCallAudioStages()
                    nextAudioRetryAt=SystemClock.elapsedRealtime()+
                        CellularCallRecoveryPolicy.retryDelayMs(audioAttemptCount)
                    val msg=error.message.orEmpty().ifBlank{error.javaClass.simpleName}.take(150)
                    val route=runCatching{internetRoute()}.getOrDefault("شبكة")
                    callProblem="تعذر اتصال صوت المكالمة ($route): "+msg
                    Companion.lastError=msg
                    if(msg!=lastError){lastError=msg;runCatching{notice(callProblem)}}
                    runCatching{showOverlay()}
                    delay(1800)
                }
            }
        }finally{
            runCatching{call?.stop()};call=null;running=false;connected=false;audioReady=false;audioDiagnostic="";clearCallAudioStages()
            inCall=false;callAnswered=false;agentEnabled=false;callProblem=""
        }
    }
    /** Never continue pilot AI/telephony after any tenant SIM starts enrollment. */
    override fun onDestroy(){
        networkListener?.let{runCatching{connectivity?.unregisterNetworkCallback(it)}}
        runCatching{call?.stop()};scope.cancel();running=false;daemonReady=false;daemonIssue="";connected=false;inCall=false;callAnswered=false;agentEnabled=false;humanControl=false;audioReady=false;callProblem="";audioDiagnostic="";clearCallAudioStages()
        liveSimSlot=-1;tenantEventOutbox=null
        overlay.remove();latestStatus="الربط متوقف"
        super.onDestroy()
    }
}
