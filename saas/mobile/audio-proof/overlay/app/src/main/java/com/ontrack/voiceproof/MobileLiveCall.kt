package com.ontrack.voiceproof

import android.content.Context
import android.util.Base64
import android.os.SystemClock
import com.ontrack.voiceproof.capture.CaptureDaemon
import com.ontrack.voiceproof.capture.AnsweringMachine
import kotlinx.coroutines.*
import kotlinx.coroutines.channels.Channel
import okhttp3.*
import okio.ByteString
import org.json.JSONObject
import java.io.DataInputStream
import java.io.DataOutputStream
import java.net.Socket
import java.util.concurrent.atomic.AtomicBoolean

/** One live Gemini session joined to authenticated shell PCM, no phone mic API. */
internal class MobileLiveCall(
    private val context:Context, private val client:OkHttpClient, private val scope:CoroutineScope,
    private val session:JSONObject, private val settings:JSONObject, private val notice:(String)->Unit,
    private val outgoingCall:Boolean=false,
    private val onFinished:(String)->Unit = {},
) {
    private val stopped=AtomicBoolean(false)
    @Volatile private var remoteSpeechConfirmed=!outgoingCall
    private val ready=CompletableDeferred<Unit>()
    // Cap stale speech to ~600 ms, not several seconds after interruption.
    private val playback=Channel<Pair<Int,ByteArray>>(24)
    @Volatile private var generation=0
    @Volatile private var pipe:Socket?=null
    @Volatile private var output:DataOutputStream?=null
    @Volatile private var ws:WebSocket?=null
    @Volatile private var socketReady=false
    @Volatile private var resumeHandle:String?=null
    @Volatile private var lastWarning=0L
    private lateinit var liveUrl:String
    private var recovery:Job?=null
    private val writeLock=Any()
    private val transcriptLock=Any()
    private val reportTranscript=StringBuilder()
    private fun appendLiveTranscription(label:String,text:String) {
        val clean=text.trim()
        if(clean.isBlank())return
        synchronized(transcriptLock){
            if(reportTranscript.length>=11600)return
            val remaining=11600-reportTranscript.length
            val part=("$label: $clean\n").take(remaining)
            reportTranscript.append(part)
        }
    }
    private var worker:Job?=null
    suspend fun start() {
        val token=session.getString("token")
        require(token.startsWith("auth_tokens/")) { "server did not return an ephemeral token" }
        val url=HttpUrl.Builder().scheme("https").host("generativelanguage.googleapis.com")
            .addPathSegments("ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained")
            .addQueryParameter("access_token",token).build().toString().replaceFirst("https:","wss:")
        liveUrl=url
        openSocket()
        withTimeout(25000){ready.await()}
        check(!stopped.get())
        pipe=CaptureDaemon.livePcm(context,AnsweringMachine.route(context)==AnsweringMachine.ROUTE_SCREENING)
        val input=DataInputStream(pipe!!.getInputStream())
        check(input.readInt()==0x4f545631 && input.readInt()==48000 && input.readInt()==2){"invalid shell PCM format"}
        output=DataOutputStream(pipe!!.getOutputStream())
        val agentName=session.optString("agent_name","مساعد OnTrack").trim().ifBlank{"مساعد OnTrack"}
        if(OutboundFirstTurn.sendInitialGreeting(outgoingCall)) {
            ws?.send(JSONObject().put("clientContent",JSONObject()
                .put("turns",org.json.JSONArray().put(JSONObject().put("role","user")
                    .put("parts",org.json.JSONArray().put(JSONObject().put("text","ابدأ المكالمة بتحية مصرية قصيرة باسم "+agentName+" فقط، ولا تنطق رقم الإيجنت أو نسخة السيناريو. ثم اسمع رد العميل.")))))
                .put("turnComplete",true)).toString())
        }
        val transform=LinkPcm(settings.optString("input_channel","mix"))
        worker=scope.launch {
            try {
                coroutineScope {
                    launch(Dispatchers.IO) {
                        for((g,bytes) in playback) {
                            while(isActive && !stopped.get() && outgoingCall && !remoteSpeechConfirmed) delay(40)
                            if(!stopped.get() && g==generation && (!outgoingCall || remoteSpeechConfirmed))
                                synchronized(writeLock){output?.writeInt(bytes.size);output?.write(bytes);output?.flush()}
                        }
                    }
                    launch(Dispatchers.IO) {
                        while(isActive && !stopped.get()) {delay(5000);synchronized(writeLock){output?.writeInt(0);output?.flush()}}
                    }
                    while(isActive && !stopped.get()) {
                        val size=input.readInt();check(size in 4..4096 && size%4==0){"invalid capture packet"}
                        val bytes=ByteArray(size);input.readFully(bytes)
                        val mono=transform.input(bytes)
                        if(mono.isNotEmpty()) {
                            val socket=ws
                            if(socketReady && socket!=null && socket.queueSize()<192*1024) {
                                if(!socket.send(JSONObject().put("realtimeInput",JSONObject().put("audio",JSONObject()
                                    .put("mimeType","audio/pcm;rate=16000").put("data",Base64.encodeToString(mono,Base64.NO_WRAP)))).toString())){
                                    onTransportLost(socket,"تعذر إرسال الصوت")
                                }
                            }else{
                                val now=SystemClock.elapsedRealtime()
                                if(now-lastWarning>=8000L){lastWarning=now;notice("الشبكة ضعيفة؛ يتم تجاوز الصوت المتأخر")}
                            }
                        }
                    }
                }
            }catch(t:Throwable){if(t !is CancellationException)fail("تعذر تمرير صوت الشريحة: "+t.javaClass.simpleName)}
        }
        notice("المكالمة مرتبطة بالإيجنت — استقبال وإرسال الصوت جاريان")
    }
    private fun openSocket() {
        if(stopped.get())return
        socketReady=false
        val handle=resumeHandle
        ws=client.newWebSocket(Request.Builder().url(liveUrl).build(),object:WebSocketListener(){
            override fun onOpen(webSocket:WebSocket,response:Response) {
                val style=when(settings.optString("response_style","egyptian_short")){
                    "formal_ar"->"تحدث بالعربية الفصحى بأسلوب مهني وواضح."
                    "english"->"Speak in clear and concise English."
                    "bilingual"->"تحدث بالعربية أو الإنجليزية حسب لغة العميل."
                    else->"تحدث باللهجة المصرية بصورة طبيعية ومختصرة."
                }
                val policy=session.getString("system_instruction")+"\\n\\n"+style+
                    " استخدم سؤالًا واحدًا في كل دور ثم استمع للعميل، ولا تكرر التحية أو الأسئلة."+
                    " توقف عند المقاطعة ولا تختلق أي حجز أو دفع أو تكامل لم يتم فعلياً."
                val setup=JSONObject().put("model","models/"+session.getString("model").removePrefix("models/"))
                    .put("generationConfig",JSONObject().put("responseModalities",org.json.JSONArray(listOf("AUDIO")))
                        .put("speechConfig",JSONObject().put("voiceConfig",JSONObject().put("prebuiltVoiceConfig",JSONObject().put("voiceName",settings.optString("voice","Aoede"))))))
                    .put("systemInstruction",JSONObject().put("parts",org.json.JSONArray().put(JSONObject().put("text",policy))))
                    .put("inputAudioTranscription",JSONObject()).put("outputAudioTranscription",JSONObject())
                    .put("sessionResumption",if(handle.isNullOrBlank())JSONObject() else JSONObject().put("handle",handle))
                if(!webSocket.send(JSONObject().put("setup",setup).toString()))onTransportLost(webSocket,"تعذر فتح الصوت")
            }
            override fun onMessage(webSocket:WebSocket,text:String) {
                if(stopped.get() || webSocket!==ws)return
                runCatching {
                    val message=JSONObject(text)
                    val update=message.optJSONObject("sessionResumptionUpdate")
                    if(update!=null && update.optBoolean("resumable")) {
                        val updated=update.optString("newHandle")
                        if(updated.isNotBlank())resumeHandle=updated
                    }
                    if(message.has("setupComplete")) {
                        val wasReady=ready.isCompleted
                        socketReady=true
                        ready.complete(Unit)
                        if(wasReady && handle.isNullOrBlank() && !outgoingCall) {
                            webSocket.send(JSONObject().put("clientContent",JSONObject()
                                .put("turns",org.json.JSONArray().put(JSONObject().put("role","user")
                                    .put("parts",org.json.JSONArray().put(JSONObject().put("text",
                                        "عاد اتصال المكالمة بعد ضعف الشبكة؛ اعتذر باختصار واطلب من العميل إعادة آخر سؤال دون بدء ترحيب جديد.")))))
                                .put("turnComplete",true)).toString())
                        }
                        if(wasReady)notice("عاد صوت الإيجنت بعد انقطاع الشبكة")
                    }
                    if(message.has("error")) error("Gemini error "+message.getJSONObject("error").optInt("code"))
                    val content=message.optJSONObject("serverContent")?:return
                    // Gemini Live emits actual caller and agent speech transcriptions
                    // independently from PCM. Do not attempt speech recognition from
                    // the Android microphone or infer text from audio payload bytes.
                    content.optJSONObject("inputTranscription")
                        ?.optString("text","")?.let { spoken ->
                            appendLiveTranscription("المتصل",spoken)
                            if(outgoingCall && !remoteSpeechConfirmed &&
                               OutboundFirstTurn.remoteSpeechTranscribed(spoken)){
                                remoteSpeechConfirmed=true
                                notice("تم سماع رد الطرف الآخر؛ الإيجنت يبدأ الحوار")
                            }
                        }
                    content.optJSONObject("outputTranscription")
                        ?.optString("text","")?.let{appendLiveTranscription("الإيجنت",it)}
                    if(content.optBoolean("interrupted")) {
                        generation++;while(playback.tryReceive().isSuccess) {}
                        synchronized(writeLock){output?.writeInt(-1);output?.flush()}
                        return // Never enqueue stale audio from the interrupted server turn.
                    }
                    val parts=content.optJSONObject("modelTurn")?.optJSONArray("parts")?:return
                    for(i in 0 until parts.length()) {
                        val inline=parts.getJSONObject(i).optJSONObject("inlineData")?:continue
                        val mime=inline.optString("mimeType")
                        if(!mime.startsWith("audio/pcm")) continue
                        val rate=Regex("rate=(\\d+)").find(mime)?.groupValues?.get(1)?.toInt()?:24000
                        val bytes=LinkPcm.output(Base64.decode(inline.getString("data"),Base64.DEFAULT),rate)
                        for(at in bytes.indices step 2400) {
                            val packet=generation to bytes.copyOfRange(at,minOf(at+2400,bytes.size))
                            if(!playback.trySend(packet).isSuccess){
                                playback.tryReceive()
                                playback.trySend(packet)
                            }
                        }
                    }
                }.onFailure{fail(it.message?:"invalid live response")}
            }
            override fun onMessage(webSocket:WebSocket,bytes:ByteString){onMessage(webSocket,bytes.utf8())}
            override fun onClosing(webSocket:WebSocket,code:Int,reason:String){webSocket.close(code,"");onTransportLost(webSocket,"جلسة الصوت أغلقت: $code")}
            override fun onFailure(webSocket:WebSocket,t:Throwable,response:Response?){onTransportLost(webSocket,"انقطع اتصال خدمة الصوت"+(response?.code?.let{" HTTP $it"}?:""))}
            override fun onClosed(webSocket:WebSocket,code:Int,reason:String){onTransportLost(webSocket,"جلسة الصوت أغلقت: $code")}
        })
    }
    private fun onTransportLost(failed:WebSocket,reason:String) {
        if(stopped.get() || failed!==ws)return
        socketReady=false
        generation++
        while(playback.tryReceive().isSuccess) {}
        synchronized(writeLock){runCatching{output?.writeInt(-1);output?.flush()}}
        if(recovery?.isActive==true)return
        recovery=scope.launch(Dispatchers.IO) {
            notice("$reason — إعادة اتصال صوت الإيجنت")
            var attempt=0
            while(isActive && !stopped.get() && !socketReady && attempt<6) {
                delay((1000L shl attempt).coerceAtMost(8000L))
                if(stopped.get() || socketReady)break
                attempt++
                openSocket()
                delay(4500)
            }
            if(!stopped.get() && !socketReady)fail("تعذر استعادة AI؛ يمكن استلام المكالمة من الهاتف")
        }
    }
    private fun fail(reason:String){if(!stopped.get()){notice(reason);ready.completeExceptionally(IllegalStateException(reason));stop()}}
    fun stop(){
        if(stopped.compareAndSet(false,true)){
            socketReady=false
            val transcription=synchronized(transcriptLock){reportTranscript.toString().trim()}
            // Callback persists the text encrypted on device. No made-up summary
            // if the Live service did not send enough transcription.
            if(transcription.length>=18)runCatching{onFinished(transcription)}
                .onFailure{notice("تعذر حفظ نص المكالمة بأمان؛ التقرير غير متاح مؤقتًا")}
            recovery?.cancel();runCatching{pipe?.close()};playback.close()
            worker?.cancel();ws?.close(1000,"call ended");ws=null;output=null
        }
    }
    val active:Boolean get()=!stopped.get()
}
