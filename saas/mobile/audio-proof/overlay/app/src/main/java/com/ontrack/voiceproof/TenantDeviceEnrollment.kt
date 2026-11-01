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
    private val client=OkHttpClient.Builder().followRedirects(false).followSslRedirects(false)
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
    private fun local(slot:Int):JSONObject {
        val ciphertext=prefs.getString(field(slot),null)?:error("الشريحة غير مربوطة")
        return JSONObject(unseal(ciphertext))
    }
    /** Local non-secret binding identifier; never substitute for authorization. */
    fun bindingId(slot:Int):Int = local(slot).getInt("device_id")
    private fun installationId():String {
        prefs.getString("installation_id",null)?.let { return it }
        val id=UUID.randomUUID().toString()
        check(prefs.edit().putString("installation_id",id).commit()) { "تعذر حفظ هوية الهاتف" }
        return id
    }
    private fun request(body:JSONObject, token:String?=null):JSONObject {
        val builder=Request.Builder().url(TenantPairingContract.ENDPOINT)
            .header("Content-Type","application/json")
        if(token!=null)builder.header("Authorization","Bearer "+TenantPairingContract.token(token))
        val req=builder.post(body.toString().toRequestBody("application/json".toMediaType())).build()
        client.newCall(req).execute().use { response ->
            val raw=response.body?.string().orEmpty()
            check(raw.length<=16384) { "استجابة السيرفر غير صالحة" }
            if(response.code==401)throw SecurityException("تم إلغاء صلاحية ربط الشريحة")
            check(response.isSuccessful) { "السيرفر رفض الطلب HTTP ${response.code}" }
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
            .put("account_id",binding.getInt("account_id"))
            .put("agent_revision",binding.getInt("agent_revision"))
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
        check(profile.getInt("sim_slot")==slot && profile.getInt("device_id")==saved.getInt("device_id")
            && profile.getInt("site_id")==saved.getInt("site_id")
            && (saved.optInt("account_id",0)==0 ||
                profile.getInt("account_id")==saved.getInt("account_id"))) {
            "بيانات السيرفر لا تطابق الشريحة المربوطة"
        }
        val revision=profile.getInt("agent_revision")
        check(revision>=1 && revision>=saved.optInt("agent_revision",0) &&
            profile.getInt("agent_id")>0) { "وصل تعيين إيجنت قديم؛ أعد التحديث" }
        if(revision>saved.optInt("agent_revision",0) ||
            saved.optInt("agent_id")!=profile.getInt("agent_id") ||
            !saved.has("account_id")) {
            saved.put("agent_id",profile.getInt("agent_id"))
                .put("agent_revision",revision)
                .put("account_id",profile.getInt("account_id"))
            check(prefs.edit().putString(field(slot),seal(saved.toString())).commit()) {
                "تعذر حفظ تعيين الإيجنت الجديد"
            }
        }
        return profile
    }
    /**
     * Uploads a device-observed call-state event under the selected SIM bearer.
     * A fresh heartbeat checks both the physical subscription and current
     * server-side tenant/agent binding before every event. No pilot bearer.
     */
    fun submitCallEvent(slot:Int,event:JSONObject):JSONObject {
        val verified=heartbeat(slot)
        check(verified.getInt("sim_slot")==slot) { "ملف الشريحة لا يطابق الهاتف" }
        val saved=local(slot)
        check(event.optInt("device_id",-1)==saved.getInt("device_id")) {
            "حدث قديم تابع لربط مختلف؛ لن يُرسل إلى حساب آخر"
        }
        val payload=JSONObject(event.toString()).put("operation","call_event").put("sim_slot",slot)
        return request(payload,saved.getString("token")).getJSONObject("event")
    }
    /** Only the authenticated SIM binding's observations; no phone number data. */
    fun callHistory(slot:Int):org.json.JSONArray {
        heartbeat(slot)
        val saved=local(slot)
        return request(JSONObject().put("operation","call_history").put("sim_slot",slot),
            saved.getString("token")).getJSONArray("calls")
    }
    /** Human-authored report only; the phone never invents sentiment or transcript. */
    fun saveCallReport(slot:Int,callId:String,outcome:String,note:String):JSONObject {
        require(Regex("^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$").matches(callId)) {
            "معرف المكالمة غير صالح"
        }
        require(outcome in setOf("unknown","satisfied","unsatisfied","follow_up")) {
            "التقييم غير مدعوم"
        }
        require(note.length<=1000) { "الملاحظة أطول من المسموح" }
        heartbeat(slot) // Fresh subscription and server authorization for this SIM.
        val saved=local(slot)
        return request(JSONObject().put("operation","call_report")
            .put("sim_slot",slot).put("device_id",saved.getInt("device_id"))
            .put("call_id",callId).put("outcome",outcome).put("note",note),
            saved.getString("token")).getJSONObject("report")
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
