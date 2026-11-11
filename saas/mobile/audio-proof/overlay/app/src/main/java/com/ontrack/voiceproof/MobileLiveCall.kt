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
    private val onTranscript:(String,String)->Unit,
) {
    private val stopped=AtomicBoolean(false)
    private val ready=CompletableDeferred<Unit>()
    private val playback=Channel<Pair<Int,ByteArray>>(72)
    // Socket callbacks enqueue reports without writing SharedPreferences or waiting on disk.
    private val pendingTranscripts=Channel<Pair<String,String>>(Channel.UNLIMITED)
    private val transcriptWorker=CoroutineScope(SupervisorJob()+Dispatchers.IO).launch {
        for((role,text) in pendingTranscripts) {
            runCatching { onTranscript(role,text) }
                .onFailure { notice("تعذر حفظ جزء من تفريغ المكالمة") }
        }
    }
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
    private var worker:Job?=null
    suspend fun start() {
        val token=session.getString("token")
        require(token.startsWith("auth_tokens/")) { "server did not return an ephemeral token" }
        val url=HttpUrl.Builder().scheme("https").host("generativelanguage.googleapis.com")
            .addPathSegments("ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained")
            .addQueryParameter("access_token",token).build().toString().replaceFirst("https:","wss:")
        liveUrl=url
        openSocket()
        withTimeout(25000){ready.await()}
        check(!stopped.get())
        pipe=CaptureDaemon.livePcm(context,AnsweringMachine.route(context)==AnsweringMachine.ROUTE_SCREENING)
        val input=DataInputStream(pipe!!.getInputStream())
        check(input.readInt()==0x4f545631 && input.readInt()==48000 && input.readInt()==2){"invalid shell PCM format"}
        output=DataOutputStream(pipe!!.getOutputStream())
        ws?.send(JSONObject().put("clientContent",JSONObject()
            .put("turns",org.json.JSONArray().put(JSONObject().put("role","user")
                .put("parts",org.json.JSONArray().put(JSONObject().put("text","ابدأ المكالمة بتحية مصرية قصيرة باسم الإيجنت المخصص، ثم اسمع رد العميل.")))))
            .put("turnComplete",true)).toString())
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
                val setup=JSONObject().put("model","models/"+session.getString("model").removePrefix("models/"))
                    .put("generationConfig",JSONObject().put("responseModalities",org.json.JSONArray(listOf("AUDIO")))
                        .put("speechConfig",JSONObject().put("voiceConfig",JSONObject().put("prebuiltVoiceConfig",JSONObject().put("voiceName",settings.optString("voice","Aoede"))))))
                    .put("systemInstruction",JSONObject().put("parts",org.json.JSONArray().put(JSONObject().put("text",(session.getString("system_instruction") + "\\n\\n" +
                        "قواعد إلزامية للمكالمة الحية: تحدث بالعربية المصرية فقط، ولا تحول إلى أي لغة أخرى مهما كان نص العميل. " +
                        "التزم بجملة أو جملتين قصيرتين في كل رد، بحد أقصى 20 كلمة تقريبًا، ثم اسكت لتسمع العميل. " +
                        "اعرض معلومة عقارية واحدة مفيدة أو اسأل سؤالًا واحدًا في كل دور، ولا تقرأ السكريبت دفعة واحدة. " +
                        "لا تكرر الترحيب أو الأسعار أو وصف العقار أو ما سبق قوله إلا إذا طلب العميل ذلك. " +
                        "عند مقاطعة العميل توقف فورًا، واستمع إليه حتى ينتهي؛ إذا كان سؤاله مرتبطًا بالموضوع أجب عنه باختصار، " +
                        "ثم أكمل من النقطة التي كنت وصلت لها لا من أول السيناريو. إذا كان السؤال خارج الموضوع أجب بمهنية " +
                        "دون اختراع معلومات، ثم اعرض العودة للموضوع مرة واحدة فقط. " +
                        "تذكر ما عرفته عن الميزانية والاحتياجات والاعتراضات أثناء الجلسة؛ لا تعاود الأسئلة التي أجاب عنها. " +
                        "ابدأ ردك بالمعلومة المطلوبة مباشرة، وقلل الحشو وانتظر المتصل.")))))
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
                        if(wasReady && handle.isNullOrBlank()) {
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
                    if(content.optBoolean("interrupted")) {
                        generation++;while(playback.tryReceive().isSuccess) {}
                        synchronized(writeLock){output?.writeInt(-1);output?.flush()}
                        return // Never enqueue stale audio from the interrupted server turn.
                    }
                    // These are Gemini Live's actual text transcripts, not invented call notes.
                    for((field,role) in listOf("inputTranscription" to "caller","outputTranscription" to "agent")) {
                        val messageText=content.optJSONObject(field)?.optString("text")?.trim().orEmpty()
                        if(messageText.isNotBlank() && !pendingTranscripts.trySend(role to messageText).isSuccess)
                            notice("طابور تفريغ المكالمة ممتلئ")
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
    fun stop(){if(stopped.compareAndSet(false,true)){socketReady=false;recovery?.cancel();runCatching{pipe?.close()};playback.close();pendingTranscripts.close();worker?.cancel();ws?.close(1000,"call ended");ws=null;output=null}}
    suspend fun awaitTranscripts() { withTimeoutOrNull(1200L) { transcriptWorker.join() } }
    val active:Boolean get()=!stopped.get()
}
