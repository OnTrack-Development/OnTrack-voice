package com.ontrack.voiceproof

import android.content.Context
import android.util.Base64
import com.ontrack.voiceproof.capture.CaptureDaemon
import com.ontrack.voiceproof.capture.AnsweringMachine
import kotlinx.coroutines.*
import kotlinx.coroutines.channels.Channel
import okhttp3.*
import org.json.JSONObject
import java.io.DataInputStream
import java.io.DataOutputStream
import java.net.Socket
import java.util.concurrent.atomic.AtomicBoolean

/** One live Gemini session joined to authenticated shell PCM, no phone mic API. */
internal class MobileLiveCall(
    private val context:Context, private val client:OkHttpClient, private val scope:CoroutineScope,
    private val session:JSONObject, private val settings:JSONObject, private val notice:(String)->Unit,
) {
    private val stopped=AtomicBoolean(false)
    private val ready=CompletableDeferred<Unit>()
    private val playback=Channel<Pair<Int,ByteArray>>(256)
    @Volatile private var generation=0
    @Volatile private var pipe:Socket?=null
    @Volatile private var output:DataOutputStream?=null
    private var ws:WebSocket?=null
    private val writeLock=Any()
    private var worker:Job?=null
    suspend fun start() {
        val token=session.getString("token")
        require(token.startsWith("auth_tokens/")) { "server did not return an ephemeral token" }
        val url=HttpUrl.Builder().scheme("https").host("generativelanguage.googleapis.com")
            .addPathSegments("ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained")
            .addQueryParameter("access_token",token).build().toString().replaceFirst("https:","wss:")
        ws=client.newWebSocket(Request.Builder().url(url).build(),object:WebSocketListener(){
            override fun onOpen(webSocket:WebSocket,response:Response) {
                val setup=JSONObject().put("model","models/"+session.getString("model").removePrefix("models/"))
                    .put("generationConfig",JSONObject().put("responseModalities",org.json.JSONArray(listOf("AUDIO")))
                        .put("speechConfig",JSONObject().put("voiceConfig",JSONObject().put("prebuiltVoiceConfig",JSONObject().put("voiceName",settings.optString("voice","Aoede"))))))
                    .put("systemInstruction",JSONObject().put("parts",org.json.JSONArray().put(JSONObject().put("text",session.getString("system_instruction")))))
                    .put("inputAudioTranscription",JSONObject()).put("outputAudioTranscription",JSONObject())
                webSocket.send(JSONObject().put("setup",setup).toString())
            }
            override fun onMessage(webSocket:WebSocket,text:String) {
                runCatching {
                    val message=JSONObject(text)
                    if(message.has("setupComplete")) ready.complete(Unit)
                    if(message.has("error")) error("Gemini error "+message.getJSONObject("error").optInt("code"))
                    val content=message.optJSONObject("serverContent")?:return
                    if(content.optBoolean("interrupted")) {
                        generation++;while(playback.tryReceive().isSuccess) {}
                        synchronized(writeLock){output?.writeInt(-1);output?.flush()}
                    }
                    val parts=content.optJSONObject("modelTurn")?.optJSONArray("parts")?:return
                    for(i in 0 until parts.length()) {
                        val inline=parts.getJSONObject(i).optJSONObject("inlineData")?:continue
                        val mime=inline.optString("mimeType")
                        if(!mime.startsWith("audio/pcm")) continue
                        val rate=Regex("rate=(\\d+)").find(mime)?.groupValues?.get(1)?.toInt()?:24000
                        val bytes=LinkPcm.output(Base64.decode(inline.getString("data"),Base64.DEFAULT),rate)
                        for(at in bytes.indices step 4800) check(playback.trySend(generation to bytes.copyOfRange(at,minOf(at+4800,bytes.size))).isSuccess){"playback queue full"}
                    }
                }.onFailure{fail(it.message?:"invalid live response")}
            }
            override fun onFailure(webSocket:WebSocket,t:Throwable,response:Response?){fail("انقطع اتصال خدمة الصوت"+(response?.code?.let{" HTTP $it"}?:""))}
            override fun onClosed(webSocket:WebSocket,code:Int,reason:String){if(!stopped.get())fail("جلسة الصوت أغلقت: $code")}
        })
        withTimeout(25000){ready.await()}
        check(!stopped.get())
        pipe=CaptureDaemon.livePcm(context,AnsweringMachine.route(context)==AnsweringMachine.ROUTE_SCREENING)
        val input=DataInputStream(pipe!!.getInputStream())
        check(input.readInt()==0x4f545631 && input.readInt()==48000 && input.readInt()==2){"invalid shell PCM format"}
        output=DataOutputStream(pipe!!.getOutputStream())
        val transform=LinkPcm(settings.optString("input_channel","right"))
        worker=scope.launch {
            try {
                coroutineScope {
                    launch(Dispatchers.IO) {
                        for((g,bytes) in playback) if(g==generation) synchronized(writeLock){output?.writeInt(bytes.size);output?.write(bytes);output?.flush()}
                    }
                    launch(Dispatchers.IO) {
                        while(isActive && !stopped.get()) {delay(5000);synchronized(writeLock){output?.writeInt(0);output?.flush()}}
                    }
                    while(isActive && !stopped.get()) {
                        val size=input.readInt();check(size in 4..4096 && size%4==0){"invalid capture packet"}
                        val bytes=ByteArray(size);input.readFully(bytes)
                        val mono=transform.input(bytes)
                        check((ws?.queueSize()?:0)<1024*1024){"audio uplink stalled"}
                        if(mono.isNotEmpty()) check(ws?.send(JSONObject().put("realtimeInput",JSONObject().put("audio",JSONObject()
                            .put("mimeType","audio/pcm;rate=16000").put("data",Base64.encodeToString(mono,Base64.NO_WRAP)))).toString())==true){"audio connection closed"}
                    }
                }
            }catch(t:Throwable){if(t !is CancellationException)fail("تعذر تمرير صوت الشريحة: "+t.javaClass.simpleName)}
        }
        notice("المكالمة مرتبطة بالإيجنت — استقبال وإرسال الصوت جاريان")
    }
    private fun fail(reason:String){if(!stopped.get()){notice(reason);ready.completeExceptionally(IllegalStateException(reason));stop()}}
    fun stop(){if(stopped.compareAndSet(false,true)){runCatching{pipe?.close()};playback.close();worker?.cancel();ws?.close(1000,"call ended");ws=null;output=null}}
    val active:Boolean get()=!stopped.get()
}
