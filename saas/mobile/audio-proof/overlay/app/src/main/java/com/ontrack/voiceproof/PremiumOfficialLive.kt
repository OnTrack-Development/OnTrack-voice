package com.ontrack.voiceproof

import android.util.Base64
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.withTimeout
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import okio.ByteString
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.atomic.AtomicBoolean

/**
 * Secure short-lived Gemini Live socket for IO Premium.
 *
 * The Google Gen AI Kotlin Android client intentionally throws SECURITY FATAL
 * on Client(apiKey=...), even for an ephemeral Live token. Use the same proven
 * token-only v1beta WebSocket transport as our standard SIM Live path instead.
 * No permanent Google/Fish API key is ever stored on the device.
 *
 * Google audio is intentionally discarded. Only verified server-side Fish PCM
 * reaches the phone call through MobileLiveCall's existing muted conduit.
 */
internal class PremiumOfficialLive(
    private val scope:CoroutineScope,
    private val client:OkHttpClient,
    private val token:String,
    private val model:String,
    private val instruction:String,
    private val caller:(String)->Unit,
    private val assistant:(String)->Unit,
    private val phrase:(String)->Unit,
    private val interrupted:()->Unit,
    private val disconnected:(String)->Unit,
) {
    private val stopped=AtomicBoolean(false)
    private val ready=CompletableDeferred<Unit>()
    @Volatile private var socket:WebSocket?=null
    private var fullOutput=""
    private var spoken=""
    private fun failure(message:String) {
        if(!ready.isCompleted)ready.completeExceptionally(IllegalStateException(message))
        if(!stopped.get())disconnected(message)
    }
    fun start() {
        require(token.startsWith("auth_tokens/") && token.length>20){"invalid Live ephemeral token"}
        val url=HttpUrl.Builder().scheme("https").host("generativelanguage.googleapis.com")
            .addPathSegments("ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained")
            .addQueryParameter("access_token",token).build().toString().replaceFirst("https:","wss:")
        socket=client.newWebSocket(Request.Builder().url(url).build(),object:WebSocketListener(){
            override fun onOpen(webSocket:WebSocket,response:Response) {
                if(stopped.get())return
                val setup=JSONObject().put("model","models/"+model.removePrefix("models/"))
                    .put("generationConfig",JSONObject()
                        .put("responseModalities",JSONArray().put("AUDIO")))
                    .put("systemInstruction",JSONObject().put("parts",
                        JSONArray().put(JSONObject().put("text",instruction))))
                    .put("inputAudioTranscription",JSONObject())
                    .put("outputAudioTranscription",JSONObject())
                if(!webSocket.send(JSONObject().put("setup",setup).toString()))
                    failure("premium_live_setup_send_failed")
            }
            override fun onMessage(webSocket:WebSocket,text:String) {
                if(stopped.get() || webSocket!==socket)return
                try {
                    val message=JSONObject(text)
                    if(message.has("error")){
                        val error=message.optJSONObject("error")
                        failure("premium_live_provider_"+(error?.optInt("code",0)?:0))
                        webSocket.close(1000,"provider error")
                        return
                    }
                    if(message.has("setupComplete") && !ready.isCompleted)ready.complete(Unit)
                    val content=message.optJSONObject("serverContent")?:return
                    content.optJSONObject("inputTranscription")?.optString("text","")
                        ?.takeIf{it.isNotBlank()}?.let(caller)
                    content.optJSONObject("outputTranscription")?.optString("text","")
                        ?.takeIf{it.isNotBlank()}?.let { text->
                            fullOutput=PremiumPhrase.merge(fullOutput,text)
                            assistant(text)
                            if(spoken.isEmpty()){
                                val prefix=PremiumPhrase.prefix(fullOutput)
                                if(prefix.isNotEmpty()){spoken=prefix;phrase(prefix)}
                            }
                        }
                    if(content.optBoolean("interrupted")){
                        fullOutput="";spoken=""
                        interrupted()
                    }else if(content.optBoolean("turnComplete")){
                        val remainder=if(spoken.isNotEmpty()&&fullOutput.startsWith(spoken))
                            fullOutput.substring(spoken.length).trim() else fullOutput.trim()
                        if(remainder.isNotEmpty())phrase(remainder)
                        fullOutput="";spoken=""
                    }
                    // Deliberately discard modelTurn.inlineData: audible voice is Fish PCM.
                }catch(_:Exception){failure("premium_live_invalid_message")}
            }
            override fun onMessage(webSocket:WebSocket,bytes:ByteString){
                onMessage(webSocket,bytes.utf8())
            }
            override fun onFailure(webSocket:WebSocket,t:Throwable,response:Response?){
                if(!stopped.get())failure("premium_live_network_"+(response?.code?:0))
            }
            override fun onClosed(webSocket:WebSocket,code:Int,reason:String){
                if(!stopped.get())failure("premium_live_closed_"+code)
            }
        })
    }
    // A live 3G/HSPA call may be slower than the initial 25-second LTE path.
    suspend fun awaitConnected(){withTimeout(35000){ready.await()}}
    suspend fun pushPcm(bytes:ByteArray){
        if(stopped.get() || !ready.isCompleted || bytes.isEmpty())return
        val audio=JSONObject().put("mimeType","audio/pcm;rate=16000")
            .put("data",Base64.encodeToString(bytes,Base64.NO_WRAP))
        if(socket?.send(JSONObject().put("realtimeInput",JSONObject().put("audio",audio)).toString())!=true)
            failure("premium_live_audio_send_failed")
    }
    suspend fun prompt(text:String){
        if(stopped.get() || !ready.isCompleted || text.isBlank())return
        val input=JSONObject().put("text",text)
        if(socket?.send(JSONObject().put("realtimeInput",input).toString())!=true)
            failure("premium_live_text_send_failed")
    }
    fun stop(){
        if(stopped.compareAndSet(false,true)){
            socket?.close(1000,"call ended")
            socket=null
        }
    }
}
