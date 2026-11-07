package com.ontrack.voiceproof

import com.google.genai.kotlin.Client
import com.google.genai.kotlin.types.AudioTranscriptionConfig
import com.google.genai.kotlin.types.Blob
import com.google.genai.kotlin.types.Content
import com.google.genai.kotlin.types.HttpOptions
import com.google.genai.kotlin.types.LiveConnectConfig
import com.google.genai.kotlin.types.Modality
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.collect
import java.util.concurrent.atomic.AtomicBoolean

/**
 * Official Google Gen AI Kotlin Live client used ONLY when the published
 * agent selects io.premium. The tenant's ephemeral token is obtained by
 * the existing bound SIM SaaS API. Never embed a permanent provider API key.
 * Google AUDIO is discarded: audible PCM comes exclusively from Fish.
 */
internal class PremiumOfficialLive(
    private val scope:CoroutineScope,
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
    @Volatile private var send:(suspend(ByteArray)->Unit)?=null
    @Volatile private var sendText:(suspend(String)->Unit)?=null
    private var job:Job?=null
    private var fullOutput=""
    private var spoken=""
    fun start() {
        check(job==null)
        job=scope.launch(Dispatchers.IO) {
            try {
                // Official SDK handles WebSocket handshake, framing, and the
                // Live API protocol; unlike the previous handcrafted socket.
                Client(
                    apiKey=token,
                    httpOptions=HttpOptions(apiVersion="v1alpha")
                ).use { client ->
                    val config=LiveConnectConfig(
                        responseModalities=listOf(Modality.AUDIO),
                        systemInstruction=Content.fromText(instruction),
                        inputAudioTranscription=AudioTranscriptionConfig(),
                        outputAudioTranscription=AudioTranscriptionConfig()
                    )
                    client.live.connect(model.removePrefix("models/"),config).use { session ->
                        send={pcm->session.sendRealtimeInput(
                            audio=Blob(data=pcm,mimeType="audio/pcm;rate=16000")
                        )}
                        sendText={text->session.sendRealtimeInput(text=text)}
                        ready.complete(Unit)
                        session.receive().collect{server->
                            if(stopped.get())return@collect
                            val content=server.serverContent?:return@collect
                            content.inputTranscription?.text?.takeIf{it.isNotBlank()}?.let(caller)
                            content.outputTranscription?.text?.takeIf{it.isNotBlank()}?.let { text->
                                fullOutput=PremiumPhrase.merge(fullOutput,text)
                                assistant(text)
                                if(spoken.isEmpty()){
                                    val prefix=PremiumPhrase.prefix(fullOutput)
                                    if(prefix.isNotEmpty()){
                                        spoken=prefix
                                        phrase(prefix)
                                    }
                                }
                            }
                            if(content.interrupted==true) {
                                fullOutput="";spoken=""
                                interrupted()
                            }else if(content.turnComplete==true) {
                                val remainder=if(spoken.isNotEmpty()&&fullOutput.startsWith(spoken))
                                    fullOutput.substring(spoken.length).trim() else fullOutput.trim()
                                if(remainder.isNotEmpty())phrase(remainder)
                                fullOutput="";spoken=""
                            }
                            // server.data and modelTurn.inlineData contain
                            // Google voice audio, intentionally NOT played.
                        }
                    }
                }
                if(!stopped.get())disconnected("IO Premium: انتهت جلسة الاتصال")
            }catch(t:Throwable){
                if(!ready.isCompleted)ready.completeExceptionally(t)
                if(!stopped.get() && t !is CancellationException)
                    disconnected("IO Premium Live: "+(t.message?:"تعذر الاتصال").take(160))
            }finally{
                send=null;sendText=null
            }
        }
    }
    suspend fun awaitConnected(){withTimeout(25000){ready.await()}}
    suspend fun pushPcm(bytes:ByteArray){
        if(stopped.get())return
        send?.invoke(bytes)
    }
    suspend fun prompt(text:String){if(!stopped.get())sendText?.invoke(text)}
    fun stop(){
        if(stopped.compareAndSet(false,true)){
            // Cancelling the coroutine closes the SDK session via 'use'.
            job?.cancel()
            send=null;sendText=null
        }
    }
}
