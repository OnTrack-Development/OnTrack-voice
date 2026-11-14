package com.ontrack.voiceproof

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.security.KeyStore
import java.util.UUID
import java.util.concurrent.CancellationException
import java.util.concurrent.TimeUnit
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

/**
 * One encrypted token per physical SIM slot, never shared with WebView/pilot.
 * This is control-plane enrollment ONLY; tenant voice/telephony stays disabled.
 */
class TenantDeviceEnrollment(private val context: Context) {
    private val prefs=context.getSharedPreferences("ontrack_tenant_pairing_v1",Context.MODE_PRIVATE)
    private val client=OkHttpClient.Builder().dns(ResilientDns).followRedirects(false).followSslRedirects(false)
        .connectTimeout(8,TimeUnit.SECONDS).readTimeout(12,TimeUnit.SECONDS).build()
    private val keyAlias="ontrack_tenant_sim_aes_v1"
    private fun key():SecretKey {
        val store=KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (store.getKey(keyAlias,null) as? SecretKey)?.let { return it }
        val generator=KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES,"AndroidKeyStore")
        generator.init(KeyGenParameterSpec.Builder(keyAlias,
            KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setKeySize(256).build())
        return generator.generateKey()
    }
    private fun seal(value:String):String {
        val cipher=Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE,key())
        return Base64.encodeToString(cipher.iv+cipher.doFinal(value.toByteArray(Charsets.UTF_8)),Base64.NO_WRAP)
    }
    private fun unseal(value:String):String {
        val bytes=Base64.decode(value,Base64.NO_WRAP)
        require(bytes.size>28) { "ملف ربط الشريحة تالف؛ أعد الربط" }
        val cipher=Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE,key(),GCMParameterSpec(128,bytes.copyOfRange(0,12)))
        return String(cipher.doFinal(bytes.copyOfRange(12,bytes.size)),Charsets.UTF_8)
    }
    private fun field(slot:Int)="sim_${TenantPairingContract.slot(slot)}"
    fun hasAny():Boolean = prefs.contains(field(0)) || prefs.contains(field(1))
    fun paired(slot:Int):Boolean = prefs.contains(field(slot))
    /** Immutable identity of the original paired device/SIM. A heartbeat may refresh
     * the agent, never the device binding ID or SIM slot. Never expose the bearer. */
    fun bindingId(slot:Int):Int = local(slot).getInt("device_id")
    private fun local(slot:Int):JSONObject {
        val ciphertext=prefs.getString(field(slot),null)?:error("الشريحة غير مربوطة")
        return JSONObject(unseal(ciphertext))
    }
    private fun installationId():String {
        prefs.getString("installation_id",null)?.let { return it }
        val id=UUID.randomUUID().toString()
        check(prefs.edit().putString("installation_id",id).commit()) { "تعذر حفظ هوية الهاتف" }
        return id
    }
    private fun request(body:JSONObject, token:String?=null,maxResponse:Int=16384):JSONObject {
        val builder=Request.Builder().url(TenantPairingContract.ENDPOINT)
            .header("Content-Type","application/json")
        if(token!=null)builder.header("Authorization","Bearer "+TenantPairingContract.token(token))
        val req=builder.post(body.toString().toRequestBody("application/json".toMediaType())).build()
        // Google Live structured report generation may take well beyond the
        // default 12s device API timeout. Other pairing/call control requests
        // remain short; only post-call analysis receives the longer timeout.
        val operation=body.optString("operation")
        val transport=when(operation){
            "workspace_call_analysis" ->
                client.newBuilder().readTimeout(155,TimeUnit.SECONDS).build()
            // 3G/H/H+ radio switching during a live SIM call can exceed the
            // old 8s TCP connect limit. Stay HTTPS-only with the same account
            // authentication, but allow a bounded slower mobile handshake.
            "heartbeat","published_flow","voice_session","voice_preflight","voice_failover" ->
                client.newBuilder()
                    .connectTimeout(16,TimeUnit.SECONDS)
                    .readTimeout(22,TimeUnit.SECONDS)
                    .callTimeout(36,TimeUnit.SECONDS).build()
            else->client
        }
        transport.newCall(req).execute().use { response ->
            val raw=response.body?.string().orEmpty()
            check(raw.length<=maxResponse) { "استجابة السيرفر غير صالحة" }
            val apiError=runCatching{JSONObject(raw).optString("error","")}.getOrDefault("")
            if(response.code==401)throw SecurityException("تسجيل ربط الشريحة اتلغى أو انتهت صلاحيته. افصل الشريحة وأعد الربط.")
            if(!response.isSuccessful){
                val message=when(apiError){
                    "device_pairing_expired_or_used"->"الكود منتهي أو اتستخدم قبل كده؛ اطلب كود 6 أرقام جديد من حسابك."
                    "device_pairing_slot_mismatch"->"الكود ده مخصص للشريحة التانية؛ اختار SIM الصحيح."
                    "device_pairing_rate_limited"->"محاولات ربط كتير خلال وقت قصير؛ استنى 5 دقايق وبعدين جرب كود جديد."
                    "device_pairing_invalid"->"اكتب كود الربط المكوّن من 6 أرقام من الموقع."
                    "device_pairing_try_again"->"تعذر إنشاء الكود دلوقتي؛ اطلب كود جديد من الموقع."
                    "sim_slot_already_paired"->"الشريحة مربوطة بحساب آخر؛ لازم تفصل الربط القديم من لوحة الأجهزة."
                    "agent_flow_not_published"->"انشر Flow للإيجنت أولًا قبل تجربة المحرك."
                    "tenant_voice_not_launched"->"رسالة قديمة من السيرفر؛ تحديث المحرك لم يصل للهوست بعد."
                    "voice_subscription_inactive"->"حساب الشركة أو اشتراكها غير نشط؛ الأدمن على حساب المنصة غير مقيّد بالاشتراك."
                    "voice_provider_not_configured"->"Google Live غير متصل؛ راجع إعدادات المزوّد في لوحة الأدمن."
                    "voice_provider_unavailable"->"Google Live لم يصدر جلسة صوت حاليًا؛ تحقق من صلاحيات Live والموديل."
                    "database_error"->"قاعدة بيانات الربط غير متاحة؛ راجع سجل السيرفر."
                    else->"السيرفر أعاد HTTP ${response.code} ${apiError.take(80)}"
                }
                error(message)
            }
            val data=JSONObject(raw)
            check(data.optBoolean("ok")) { "السيرفر رفض عملية الربط" }
            return data
        }
    }
    fun enroll(rawCode:String,slot:Int,deviceName:String):JSONObject {
        TenantPairingContract.slot(slot)
        val subscriptionId=TenantSimHardware.subscriptionId(context,slot)
        check(!paired(slot)) { "الشريحة مربوطة بالفعل؛ افصلها أولًا" }
        val name=deviceName.trim().take(100)
        require(name.isNotEmpty()) { "اسم الهاتف مطلوب" }
        val result=request(JSONObject().put("operation","pair")
            .put("code",TenantPairingContract.codeForSlot(rawCode,slot))
            .put("installation_id",installationId())
            .put("sim_slot",slot).put("device_name",name))
        val binding=result.getJSONObject("binding")
        check(binding.getInt("sim_slot")==slot) { "السيرفر أعاد شريحة مختلفة" }
        val token=TenantPairingContract.token(binding.getString("device_token"))
        val saved=JSONObject().put("device_id",binding.getInt("device_id"))
            .put("sim_slot",slot).put("site_id",binding.getInt("site_id"))
            .put("agent_id",binding.getInt("agent_id"))
            .put("subscription_id",subscriptionId).put("token",token)
        check(prefs.edit().putString(field(slot),seal(saved.toString())).commit()) {
            "تعذر حفظ اعتماد الشريحة؛ افصلها من لوحة الحساب وأعد الربط"
        }
        // Pairing was redeemed and encrypted locally. A subsequent heartbeat
        // timeout must not be reported as an unsuccessful pairing (or retry
        // the already-consumed one-time code). Recheck status separately.
        return try { heartbeat(slot) } catch (cancelled:CancellationException) {
            throw cancelled
        } catch (_:Exception) {
            JSONObject().put("sim_slot",slot).put("verification_pending",true)
        }
    }
    fun heartbeat(slot:Int):JSONObject {
        val saved=local(slot)
        val currentSubscription=TenantSimHardware.subscriptionId(context,slot)
        val storedSubscription=if(saved.has("subscription_id")) saved.optInt("subscription_id",-1) else null
        val problem=TenantSimHardware.subscriptionProblem(storedSubscription,currentSubscription)
        check(problem==null) { problem ?: "تعذر التحقق من هوية الشريحة" }
        val response=request(JSONObject().put("operation","heartbeat"),saved.getString("token"))
        val profile=response.getJSONObject("profile")
        // The device identity, SIM and site are immutable. The tenant admin may
        // replace an archived agent with another published agent *in the same site*.
        // Accept that one scoped change only after an authenticated HTTPS heartbeat;
        // never accept a different device, site or physical SIM under this bearer.
        check(TenantAgentIdentity.canRefreshAgent(
            saved.getInt("device_id"), saved.getInt("site_id"), slot,
            profile.getInt("device_id"), profile.getInt("site_id"),
            profile.getInt("sim_slot"), profile.getInt("agent_id"))) {
            "بيانات السيرفر لا تطابق هوية الشريحة أو الموقع المربوط"
        }
        val serverAgent=profile.getInt("agent_id")
        check(serverAgent>0) { "معرّف الإيجنت في السيرفر غير صالح" }
        if(serverAgent!=saved.getInt("agent_id")){
            val refreshed=JSONObject(saved.toString()).put("agent_id",serverAgent)
            check(prefs.edit().putString(field(slot),seal(refreshed.toString())).commit()){
                "فشل حفظ الإيجنت الجديد للشريحة؛ لم يتم تشغيل المكالمة"
            }
        }
        return profile
    }

    /** Fetch only the immutable server-published Flow for this SIM/tenant.
     * This does not initiate calls or unlock the legacy pilot voice transport.
     */
    fun publishedFlow(slot:Int):JSONObject {
        val state=heartbeat(slot) // revalidate hardware + server authorization every time
        val saved=local(slot) // heartbeat may securely update the published agent ID
        check(state.optBoolean("flow_ready",false)) { "الإيجنت ليس لديه Flow منشور" }
        val reply=request(JSONObject().put("operation","published_flow"),
            saved.getString("token"),65536)
        val revision=reply.optInt("revision",-1)
        val digest=reply.optString("hash","")
        check(revision>0 && revision==state.optInt("flow_revision",-1)
            && Regex("^[a-f0-9]{64}$").matches(digest)
            && digest==state.optString("flow_hash","")
            && reply.optInt("site_id",-1)==saved.getInt("site_id")
            && reply.optInt("agent_id",-1)==saved.getInt("agent_id")
            && reply.optInt("sim_slot",-1)==slot) {
            "نسخة Flow لا تتطابق مع حساب الشريحة؛ لم يتم تنفيذ شيء"
        }
        val flow=reply.getJSONObject("flow")
        check(flow.getJSONArray("nodes").length() in 1..100 &&
            flow.getJSONArray("edges").length()<=250) { "Flow غير صالح" }
        return reply
    }
    /** New tenant voice-session authorization. Never uses a legacy pilot key.
     * Incident 2026-11-04: a carrier OFFHOOK call must not be mistaken for
     * an issued Gemini token. A failed SaaS authorization MUST surface its
     * actual error and leave the user's phone under manual control; it must
     * never record an ACTION_TAKEOVER that was not pressed.
     * One session is bound to the exact published agent, revision, SIM and site.
     * This only issues a short-lived token; it never answers a call itself.
     */
    fun voiceSession(slot:Int,expectedFlow:JSONObject):JSONObject {
        val live=publishedFlow(slot) // verifies current hardware, pairing and snapshot
        val saved=local(slot) // read after flow/heartbeat synchronizes a new agent
        check(live.getInt("revision")==expectedFlow.getInt("revision")
            && live.getString("hash")==expectedFlow.getString("hash")) {
            "تغيّرت نسخة السيناريو، أعد المحاولة بعد التحقق"
        }
        val response=request(JSONObject().put("operation","voice_session"),saved.getString("token"),65536)
        val session=response.getJSONObject("session")
        check(session.optInt("agent_id",-1)==saved.getInt("agent_id")
            && session.optInt("site_id",-1)==saved.getInt("site_id")
            && session.optInt("sim_slot",-1)==slot
            && session.optInt("revision",-1)==live.getInt("revision")
            && session.optString("hash","")==live.getString("hash")
            && session.optString("token","").startsWith("auth_tokens/")) {
            "بيانات جلسة الصوت لا تطابق اعتماد الشريحة؛ رفض الاتصال"
        }
        return session
    }
    /** Request the next key from the exact original device + tenant + SIM.
     * No heartbeat or republish during an ongoing call: a human may switch the
     * SIM's agent while this call keeps its original immutable revision.
     * Never expose or store a master Google API key on Android.
     */
    fun rotateVoiceSession(slot:Int,previous:JSONObject,failureCode:String):JSONObject {
        require(failureCode in setOf("429","503","timeout")) { "unsupported provider error" }
        val ticket=previous.optString("failover_ticket","")
        val generation=previous.optInt("failover_generation",-1)
        require(ticket.matches(Regex("^[a-f0-9]{48}$")) && generation in 0..2) {
            "المكالمة الحالية لا تدعم تبديل مفاتيح Google"
        }
        val saved=local(slot) // Do NOT rebind or retrieve a newer agent during a live call.
        val body=JSONObject().put("operation","voice_failover")
            .put("failover_ticket",ticket).put("failover_generation",generation)
            .put("failure_code",failureCode)
        val response=request(body,saved.getString("token"))
        val rotated=response.getJSONObject("session")
        check(rotated.optString("token","").startsWith("auth_tokens/") &&
            rotated.optString("model","").isNotBlank() &&
            rotated.optString("failover_ticket","")==ticket &&
            rotated.optInt("failover_generation",-1)==generation+1 &&
            rotated.optInt("site_id",-1)==previous.optInt("site_id",-2) &&
            rotated.optInt("agent_id",-1)==previous.optInt("agent_id",-2) &&
            rotated.optInt("sim_slot",-1)==slot &&
            rotated.optInt("revision",-1)==previous.optInt("revision",-2) &&
            rotated.optString("hash","")==previous.optString("hash","")) {
            "جلسة التبديل غير مطابقة للمكالمة أو الشريحة"
        }
        return rotated
    }
    /** Stream 44.1 kHz signed PCM from Fish without disclosing the Fish key
     * or the private voice reference. SaaS validates the CURRENT published
     * agent, exact SIM binding, voice grant, and entitlement for each phrase.
     */
    fun streamPremiumPcm(slot:Int,text:String,shouldContinue:()->Boolean,onChunk:(ByteArray)->Unit){
        val phrase=text.trim()
        require(phrase.isNotEmpty() && phrase.length<=400) { "IO Premium: عبارة طويلة" }
        val device=local(slot)
        val req=Request.Builder().url("https://app.ontrackegy.com/device/v1/tts.php")
            .header("Authorization","Bearer "+TenantPairingContract.token(device.getString("token")))
            .header("Accept","application/octet-stream")
            .post(JSONObject().put("text",phrase).toString()
                .toRequestBody("application/json".toMediaType())).build()
        // Fish PCM stream must tolerate a brief 3G/H+ TCP handshake without
        // imposing a total-call timeout on ongoing audio chunks.
        val streamClient=client.newBuilder().connectTimeout(16,TimeUnit.SECONDS)
            .readTimeout(45,TimeUnit.SECONDS).build()
        try {
            streamClient.newCall(req).execute().use{response->
                if(response.code==401)throw SecurityException("ربط الشريحة لم يعد صالحًا")
                if(!response.isSuccessful){
                    val code=runCatching{
                        JSONObject(response.body?.string().orEmpty().take(1024)).optString("error","")
                    }.getOrDefault("")
                    val reason=FishTtsFailover.httpFallbackReason(response.code,code)
                    if(reason!=null)throw FishTtsFailoverException(reason)
                    // In particular: 403 due to a revoked subscription, voice grant
                    // or changed published agent never bypasses authorization.
                    error("IO Premium HTTP " + response.code + " " + code.take(64))
                }
                val rate = response.header("X-PCM-Sample-Rate")?.toIntOrNull() ?: 48000
                if(rate !in setOf(24000, 44100, 48000) ||
                    response.header("X-PCM-Channels")!="1" ||
                    response.header("X-PCM-Format")!="s16le") {
                    throw FishTtsFailoverException("tts_failed")
                }
                val input=response.body?.byteStream()?:throw FishTtsFailoverException("tts_failed")
                val chunk=ByteArray(4096)
                var total=0
                while(shouldContinue()){
                    val size=input.read(chunk)
                    if(size<0)break
                    if(size==0)continue
                    total+=size
                    check(total<48000*2*55){"IO Premium: الصوت أطول من الحد"}
                    onChunk(chunk.copyOf(size))
                }
                if(shouldContinue()&&total==0)throw FishTtsFailoverException("tts_failed")
            }
        } catch (e: java.io.IOException) {
            throw FishTtsFailoverException("provider_unavailable")
        }
    }

    /** Dry-run for binding + immutable published Flow + ephemeral Gemini session.
     * No GSM call, audio capture, or legacy pilot session is performed.
     */
    fun testReadiness(slot:Int):String {
        val profile=heartbeat(slot)
        val who=profile.optString("agent_name","الإيجنت")
        check(profile.optBoolean("flow_ready",false)){
            "SIM ${slot+1}: الربط صحيح لكن ${who} ليس له Flow منشور"
        }
        val flow=publishedFlow(slot)
        val revision=flow.getInt("revision")
        val saved=local(slot)
        val response=request(JSONObject().put("operation","voice_preflight"),saved.getString("token"))
        val preflight=response.getJSONObject("preflight")
        check(preflight.optBoolean("ready") &&
            preflight.optInt("revision")==revision &&
            preflight.optInt("sim_slot")==slot &&
            preflight.optInt("agent_id")==saved.getInt("agent_id")) {
            "Google Live رفض إعداد الجلسة لهذه الشريحة"
        }
        return "SIM ${slot+1}: الربط صحيح • Flow v${revision} منشور • اختبار Google Live ناجح. لم تحدث مكالمة صوتية بعد."
    }
    /** Reuses the encrypted per-SIM SaaS enrollment. No second key or pilot URL. */
    fun workspace(slot:Int,operation:String,payload:JSONObject=JSONObject(),maxResponse:Int=65536):JSONObject {
        val allowed=setOf("workspace_contacts","workspace_import","workspace_contact_dnc",
            "workspace_calls","workspace_call_event","workspace_call_analysis","workspace_call_transcript",
            "workspace_handoff_claim","workspace_handoff_ack","workspace_campaigns",
            "workspace_campaign_create","workspace_campaign_action","workspace_campaign_claim",
            "workspace_campaign_verify","workspace_campaign_finish","workspace_file_import")
        require(operation in allowed){ "عملية حساب غير مدعومة" }
        heartbeat(slot) // hardware identity and tenant membership checked before each operation
        val localBinding=local(slot)
        val body=JSONObject(payload.toString()).put("operation",operation)
        return request(body,localBinding.getString("token"),maxResponse)
    }
    fun disconnect(slot:Int) {
        val saved=local(slot)
        try {
            request(JSONObject().put("operation","disconnect"),saved.getString("token"))
        } catch (revoked:SecurityException) {
            // Server already revoked this token. Remove unusable local ciphertext.
        }
        check(prefs.edit().remove(field(slot)).commit()) { "تعذر مسح اعتماد الشريحة محليًا" }
    }
}
