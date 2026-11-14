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
import java.util.concurrent.atomic.AtomicInteger

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
    private val googleAudio:(ByteArray,Int)->Unit,
    private val interrupted:()->Unit,
    private val disconnected:(String)->Unit,
    private val maxOutputTokens:Int=LiveVadConfig.DEFAULT_MAX_OUTPUT_TOKENS,
) {
    @Volatile var lastError:String=""
        private set
    private val stopped=AtomicBoolean(false)
    private val ready=CompletableDeferred<Unit>()
    private val turnGeneration=AtomicInteger(0)
    @Volatile private var socket:WebSocket?=null
    private var fullOutput=""
    private var spokenOffset=0
    // Google's AUDIO stream is generated in the SAME authorized Gemini Live
    // session as Fish-backed output. Buffer ONLY a few seconds of its latest
    // turn so quota errors can switch voices without new Google credentials,
    // another phone call, or repeating the customer's speech.
    private val audioGate=Any()
    private val recentGooglePcm=ArrayDeque<Pair<ByteArray,Int>>()
    private var recentBytes=0
    private var newTurnPending=false
    private var googleFallback=false
    private val maxBufferedBytes=24_000*2*16

    fun enableGoogleAudioFallback():Boolean = runCatching {
        synchronized(audioGate){
            if(stopped.get())return@synchronized false
            if(googleFallback)return@synchronized true
            googleFallback=true
            for((audio,rate) in recentGooglePcm)runCatching{googleAudio(audio,rate)}
            recentGooglePcm.clear()
            recentBytes=0
            true
        }
    }.getOrDefault(false)
    private fun receiveGoogleAudio(audio:ByteArray,rate:Int){
        if(audio.size<2 || audio.size%2!=0 || audio.size>maxBufferedBytes ||
            rate !in setOf(16000,24000,48000))return
        synchronized(audioGate){
            if(stopped.get())return@synchronized
            if(googleFallback){
                runCatching{googleAudio(audio,rate)}
            }else{
                if(newTurnPending){
                    recentGooglePcm.clear()
                    recentBytes=0
                    newTurnPending=false
                }
                recentGooglePcm.addLast(audio to rate)
                recentBytes+=audio.size
                while(recentBytes>maxBufferedBytes && recentGooglePcm.isNotEmpty()){
                    recentBytes-=recentGooglePcm.removeFirst().first.size
                }
            }
        }
    }
    private fun failure(message:String) {
        lastError=message
        runCatching { if(!ready.isCompleted)ready.completeExceptionally(IllegalStateException(message)) }
        if(!stopped.get())runCatching { disconnected(message) }
    }
    fun start() {
        try {
            require(token.startsWith("auth_tokens/") && token.length>20){"invalid Live ephemeral token"}
            val url=HttpUrl.Builder().scheme("https").host("generativelanguage.googleapis.com")
                .addPathSegments("ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained")
                .addQueryParameter("access_token",token).build().toString().replaceFirst("https:","wss:")
            runCatching { socket?.close(1000,"superseded") }
            socket=client.newWebSocket(Request.Builder().url(url).build(),object:WebSocketListener(){
                override fun onOpen(webSocket:WebSocket,response:Response) {
                    try {
                        if(stopped.get() || webSocket!==socket)return
                        val cleanModel=model.removePrefix("models/")
                        val generationConfig=JSONObject()
                            .put("responseModalities",JSONArray().put("AUDIO"))
                            .put("maxOutputTokens",LiveVadConfig.clampMaxOutputTokens(maxOutputTokens))
                            .put("temperature", 0.9)
                        if(!cleanModel.contains("3.8-live",ignoreCase=true) && cleanModel.contains("2.5",ignoreCase=true))
                            generationConfig.put("thinkingConfig",JSONObject().put("thinkingBudget",0))
                        val setup=JSONObject().put("model","models/"+cleanModel)
                            .put("generationConfig",generationConfig)
                            .put("realtimeInputConfig",JSONObject().put("automaticActivityDetection",JSONObject()
                                .put("disabled",false)
                                .put("startOfSpeechSensitivity","START_SENSITIVITY_HIGH")
                                .put("endOfSpeechSensitivity","END_SENSITIVITY_HIGH")
                                .put("prefixPaddingMs",LiveVadConfig.PREFIX_PADDING_MS)
                                .put("silenceDurationMs",LiveVadConfig.SILENCE_DURATION_MS)))
                            .put("systemInstruction",JSONObject().put("parts",
                                JSONArray().put(JSONObject().put("text",instruction))))
                            .put("inputAudioTranscription",JSONObject())
                            .put("outputAudioTranscription",JSONObject())
                        if(!webSocket.send(JSONObject().put("setup",setup).toString()))
                            failure("premium_live_setup_send_failed")
                    } catch(t:Throwable) {
                        failure("premium_live_open_"+(t.message?:t.javaClass.simpleName).take(60))
                    }
                }
                override fun onMessage(webSocket:WebSocket,text:String) {
                    if(stopped.get() || webSocket!==socket)return
                    try {
                        val message=JSONObject(text)
                        if(message.has("error")){
                            val error=message.optJSONObject("error")
                            failure("premium_live_provider_"+(error?.optInt("code",0)?:0))
                            runCatching { webSocket.close(1000,"provider error") }
                            return
                        }
                        if(message.has("setupComplete") && !ready.isCompleted)ready.complete(Unit)
                        val content=message.optJSONObject("serverContent")?:return
                        if(content.optBoolean("interrupted")){
                            turnGeneration.incrementAndGet()
                            fullOutput="";spokenOffset=0
                            synchronized(audioGate){
                                recentGooglePcm.clear();recentBytes=0;newTurnPending=false
                            }
                            runCatching{interrupted()}
                            return
                        }
                        val gen=turnGeneration.get()
                        content.optJSONObject("inputTranscription")?.optString("text","")
                            ?.takeIf{it.isNotBlank()}?.let{ runCatching{caller(it)} }
                        content.optJSONObject("outputTranscription")?.optString("text","")
                            ?.takeIf{it.isNotBlank()}?.let { outText->
                                if(turnGeneration.get()!=gen)return@let
                                fullOutput=PremiumPhrase.merge(fullOutput,outText)
                                runCatching{assistant(outText)}
                                while(turnGeneration.get()==gen){
                                    val start=spokenOffset.coerceIn(0,fullOutput.length)
                                    val slice=fullOutput.substring(start)
                                    val lead=slice.indexOfFirst{!it.isWhitespace()}
                                    if(lead<0)break
                                    val trimmed=slice.substring(lead)
                                    val prefix=PremiumPhrase.prefix(trimmed)
                                    if(prefix.isEmpty())break
                                    spokenOffset=(start+lead+prefix.length).coerceAtMost(fullOutput.length)
                                    val speak=PremiumPhrase.speakable(prefix)
                                    if(speak.isNotEmpty() && turnGeneration.get()==gen)runCatching{phrase(speak)}
                                }
                            }
                        val parts=content.optJSONObject("modelTurn")?.optJSONArray("parts")
                        if(parts!=null && turnGeneration.get()==gen)for(i in 0 until parts.length()){
                            val inline=parts.optJSONObject(i)?.optJSONObject("inlineData")?:continue
                            val mime=inline.optString("mimeType","")
                            if(!mime.startsWith("audio/pcm"))continue
                            val rate=Regex("rate=(\\d+)").find(mime)?.groupValues
                                ?.getOrNull(1)?.toIntOrNull()?:24000
                            if(turnGeneration.get()!=gen)break
                            receiveGoogleAudio(Base64.decode(inline.getString("data"),Base64.DEFAULT),rate)
                        }
                        if(content.optBoolean("turnComplete") && turnGeneration.get()==gen){
                            val start=spokenOffset.coerceIn(0,fullOutput.length)
                            val remainder=PremiumPhrase.speakable(fullOutput.substring(start))
                            if(remainder.isNotEmpty() && turnGeneration.get()==gen)runCatching{phrase(remainder)}
                            fullOutput="";spokenOffset=0
                            synchronized(audioGate){newTurnPending=true}
                        }
                        // Google audio is normally buffered, never emitted alongside
                        // Fish PCM; only an explicit Fish provider error enables it.
                    }catch(t:Throwable){failure("premium_live_invalid_message_"+t.javaClass.simpleName)}
                }
                override fun onMessage(webSocket:WebSocket,bytes:ByteString){
                    runCatching { onMessage(webSocket,bytes.utf8()) }
                        .onFailure { t-> failure("premium_live_binary_"+t.javaClass.simpleName) }
                }
                override fun onFailure(webSocket:WebSocket,t:Throwable,response:Response?){
                    runCatching {
                        if(!stopped.get())failure("premium_live_network_"+(response?.code?:0)+"_"+t.javaClass.simpleName)
                    }
                }
                override fun onClosed(webSocket:WebSocket,code:Int,reason:String){
                    runCatching {
                        if(!stopped.get())failure("premium_live_closed_"+code)
                    }
                }
            })
        } catch(t:Throwable) {
            failure("premium_live_start_"+(t.message?:t.javaClass.simpleName).take(60))
        }
    }
    // A live 3G/HSPA call may be slower than the initial 25-second LTE path.
    suspend fun awaitConnected(){withTimeout(35000){ready.await()}}
    val queueSize:Long get()=socket?.queueSize()?:0L
    suspend fun pushPcm(bytes:ByteArray, cellular:Boolean=false):Boolean{
        if(stopped.get() || !ready.isCompleted || bytes.isEmpty())return false
        val s=socket?:return false
        if(!LiveAudioQueuePolicy.canSend(cellular, s.queueSize()))return false
        try {
            val audio=JSONObject().put("mimeType","audio/pcm;rate=16000")
                .put("data",Base64.encodeToString(bytes,Base64.NO_WRAP))
            if(s.send(JSONObject().put("realtimeInput",JSONObject().put("audio",audio)).toString())) return true
            failure("premium_live_audio_send_failed")
            return false
        } catch(t:Throwable) {
            failure("premium_live_audio_"+t.javaClass.simpleName)
            return false
        }
    }
    suspend fun prompt(text:String){
        if(stopped.get() || !ready.isCompleted || text.isBlank())return
        try {
            val input=JSONObject().put("text",text)
            if(socket?.send(JSONObject().put("realtimeInput",input).toString())!=true)
                failure("premium_live_text_send_failed")
        } catch(t:Throwable) {
            failure("premium_live_text_"+t.javaClass.simpleName)
        }
    }
    fun interruptLocalTurn(){
        turnGeneration.incrementAndGet()
        fullOutput="";spokenOffset=0
        synchronized(audioGate){
            recentGooglePcm.clear();recentBytes=0;newTurnPending=false
        }
    }
    suspend fun sendClientContent(text:String, turnComplete:Boolean=true){
        if(stopped.get() || !ready.isCompleted || text.isBlank())return
        try {
            val payload=JSONObject().put("clientContent",JSONObject()
                .put("turns",JSONArray().put(JSONObject().put("role","user")
                    .put("parts",JSONArray().put(JSONObject().put("text",text)))))
                .put("turnComplete",turnComplete)).toString()
            if(socket?.send(payload)!=true)
                failure("premium_live_text_send_failed")
        } catch(t:Throwable) {
            failure("premium_live_client_"+t.javaClass.simpleName)
        }
    }
    fun stop(){
        if(stopped.compareAndSet(false,true)){
            runCatching { socket?.close(1000,"call ended") }
            socket=null
            runCatching { synchronized(audioGate){recentGooglePcm.clear();recentBytes=0} }
        }
    }
}
