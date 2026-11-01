package com.ontrack.voiceproof

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.content.pm.PackageManager
import android.os.IBinder
import android.os.SystemClock
import android.telephony.TelephonyCallback
import android.telephony.TelephonyManager
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import kotlinx.coroutines.*
import org.json.JSONArray
import org.json.JSONObject
import java.time.Instant
import java.time.temporal.ChronoUnit
import java.util.UUID

/**
 * Explicitly started foreground monitor of device-observed SIM call states.
 * Never places/answers a call or opens the old global pilot bearer.
 * No phone numbers, call audio or transcripts are collected.
 */
class TenantCallObserverService:Service() {
    companion object {
        const val ACTION_STOP="com.ontrack.voiceproof.TENANT_CALL_MONITOR_STOP"
        private const val CHANNEL="ontrack_tenant_calls"
        private const val NOTIFICATION=733
    }
    private val scope=CoroutineScope(SupervisorJob()+Dispatchers.IO)
    private val lock=Any()
    private val prefs by lazy { getSharedPreferences("ontrack_tenant_call_events_v1",MODE_PRIVATE) }
    private val enrollment by lazy { TenantDeviceEnrollment(this) }
    private val registered=mutableListOf<Triple<Int,TelephonyManager,TelephonyCallback>>()
    private var started=false
    private class Listener(private val sink:(Int)->Unit):
        TelephonyCallback(),TelephonyCallback.CallStateListener {
        override fun onCallStateChanged(state:Int)=sink(state)
    }
    override fun onBind(intent:Intent?):IBinder?=null
    override fun onCreate() {
        super.onCreate()
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(CHANNEL,"OnTrack — سجل شرائح الشركات",NotificationManager.IMPORTANCE_LOW))
    }
    private fun notification(message:String):Notification {
        val stop=PendingIntent.getService(this,4,
            Intent(this,TenantCallObserverService::class.java).setAction(ACTION_STOP),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        val open=PendingIntent.getActivity(this,5,Intent(this,MobileLinkActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        return NotificationCompat.Builder(this,CHANNEL)
            .setSmallIcon(android.R.drawable.ic_menu_call)
            .setContentTitle("OnTrack — سجل المكالمات حسب الشريحة")
            .setContentText(message).setContentIntent(open).setOngoing(true)
            .addAction(android.R.drawable.ic_media_pause,"إيقاف المراقبة",stop).build()
    }
    private fun status(message:String) {
        getSystemService(NotificationManager::class.java).notify(NOTIFICATION,notification(message))
    }
    override fun onStartCommand(intent:Intent?,flags:Int,startId:Int):Int {
        if(intent?.action==ACTION_STOP) {
            stopSelf()
            return START_NOT_STICKY
        }
        startForeground(NOTIFICATION,notification("جاري التحقق من الشريحتين والحسابات…"))
        if(started)return START_NOT_STICKY
        started=true
        scope.launch {
            var count=0
            for(slot in 0..1) {
                if(!enrollment.paired(slot))continue
                try {
                    check(ContextCompat.checkSelfPermission(this@TenantCallObserverService,
                        Manifest.permission.READ_PHONE_STATE)==PackageManager.PERMISSION_GRANTED) {
                        "إذن حالة الهاتف غير متاح"
                    }
                    val subscriptionId=TenantSimHardware.subscriptionId(this@TenantCallObserverService,slot)
                    enrollment.heartbeat(slot)
                    val manager=getSystemService(TelephonyManager::class.java)
                        ?.createForSubscriptionId(subscriptionId) ?: error("Telephony unavailable")
                    val callback=Listener { state -> onCallState(slot,state) }
                    withContext(Dispatchers.Main) {
                        manager.registerTelephonyCallback(mainExecutor,callback)
                        registered.add(Triple(slot,manager,callback))
                    }
                    count++
                } catch(e:CancellationException) { throw e }
                  catch(_:Exception) {
                    status("SIM ${slot+1}: تعذر التحقق؛ لن تُسجل أحداث هذه الشريحة")
                }
            }
            if(count==0) {
                status("لا توجد شريحة مربوطة ومؤكدة للمراقبة")
                stopSelf()
                return@launch
            }
            status("مراقبة ${count} شريحة؛ بدون أرقام أو تسجيل صوتي")
            flushLoop()
        }
        return START_NOT_STICKY
    }
    private fun onCallState(slot:Int,rawState:Int) {
        if(rawState !in 0..2)return
        synchronized(lock) {
            val old=TenantCallEventContract.State(
                prefs.getString("call_id_$slot",null),
                prefs.getLong("call_started_$slot",0L),
                prefs.getInt("last_state_$slot",0))
            val result=TenantCallEventContract.observe(old,rawState,
                SystemClock.elapsedRealtime(),UUID.randomUUID().toString(),
                Instant.now().truncatedTo(ChronoUnit.SECONDS).toString())
            if(result.state==old)return
            val events=JSONArray(prefs.getString("outbox","[]"))
            if(result.event!=null) {
                if(events.length()>=200) {
                    status("سجل الأحداث ممتلئ؛ أُوقفت المراقبة دون حذف الأحداث")
                    stopSelf()
                    return
                }
                val event=result.event
                val bindingId=try { enrollment.bindingId(slot) } catch(_:Exception) {
                    status("فُصلت الشريحة؛ أُوقفت المراقبة قبل تسجيل حدث جديد")
                    stopSelf()
                    return
                }
                events.put(JSONObject().put("sim_slot",slot).put("device_id",bindingId)
                    .put("call_id",event.callId)
                    .put("phase",event.phase).put("duration_seconds",event.durationSeconds)
                    .put("occurred_at",event.occurredAt))
            }
            val edit=prefs.edit().putString("outbox",events.toString())
                .putInt("last_state_$slot",result.state.lastState)
            if(result.state.callId==null)edit.remove("call_id_$slot").remove("call_started_$slot")
            else edit.putString("call_id_$slot",result.state.callId)
                .putLong("call_started_$slot",result.state.startedElapsedMs)
            if(!edit.commit()) {
                status("تعذر حفظ حدث المكالمة محليًا؛ أُوقفت المراقبة")
                stopSelf()
            }
        }
    }
    /** Preserve stale events locally rather than reassign them to a different tenant. */
    private fun quarantineFirst(message:String):Boolean {
        synchronized(lock) {
            val pending=JSONArray(prefs.getString("outbox","[]"))
            if(pending.length()==0)return true
            val orphaned=JSONArray(prefs.getString("orphaned","[]"))
            if(orphaned.length()>=200) {
                status("الأحداث المعزولة ممتلئة؛ أُوقفت المراقبة دون فقد البيانات")
                stopSelf()
                return false
            }
            orphaned.put(pending.get(0))
            val next=JSONArray()
            for(i in 1 until pending.length())next.put(pending.get(i))
            if(!prefs.edit().putString("orphaned",orphaned.toString())
                .putString("outbox",next.toString()).commit()) {
                status("تعذر حفظ الحدث المعزول؛ أُوقفت المراقبة")
                stopSelf()
                return false
            }
        }
        status(message)
        return true
    }
    private suspend fun flushLoop() {
        while(scope.isActive) {
            val event=synchronized(lock) {
                JSONArray(prefs.getString("outbox","[]")).optJSONObject(0)
            }
            if(event==null) { delay(8000);continue }
            // Re-pairing must never reattribute old events to a new company.
            val currentId=runCatching{enrollment.bindingId(event.getInt("sim_slot"))}.getOrNull()
            if(currentId!=event.optInt("device_id",-1)) {
                if(!quarantineFirst("عُزل حدث شريحة قديم؛ لن ينتقل إلى حساب آخر"))return
                continue
            }
            try {
                enrollment.submitCallEvent(event.getInt("sim_slot"),event)
                synchronized(lock) {
                    val pending=JSONArray(prefs.getString("outbox","[]"))
                    // Keep events appended while the network request was in flight.
                    val next=JSONArray()
                    for(i in 1 until pending.length())next.put(pending.get(i))
                    check(prefs.edit().putString("outbox",next.toString()).commit()) {
                        "تعذر حفظ تأكيد إرسال الحدث"
                    }
                }
            } catch(e:CancellationException) { throw e }
              catch(_:SecurityException) {
                val slot=event.getInt("sim_slot")
                if(!quarantineFirst("SIM ${slot+1}: أُلغي الربط؛ عُزل الحدث ولم يتأثر حساب الشريحة الأخرى"))return
                withContext(Dispatchers.Main) {
                    val matches=registered.filter { it.first==slot }
                    for((_,manager,callback) in matches)
                        runCatching { manager.unregisterTelephonyCallback(callback) }
                    registered.removeAll { it.first==slot }
                    if(registered.isEmpty())stopSelf()
                }
                continue
            } catch(_:Exception) {
                // Offline events remain on disk; retry only when explicitly running.
                delay(10000)
            }
        }
    }
    override fun onDestroy() {
        for((_,manager,callback) in registered)runCatching{manager.unregisterTelephonyCallback(callback)}
        registered.clear()
        scope.cancel()
        super.onDestroy()
    }
}
