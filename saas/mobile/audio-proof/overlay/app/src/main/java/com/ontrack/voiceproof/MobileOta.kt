package com.ontrack.voiceproof

import android.content.Context
import android.content.pm.PackageManager
import android.util.AtomicFile
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.io.File
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.security.cert.CertificateFactory
import java.util.concurrent.TimeUnit

class MobileOta(private val context: Context) {
    data class Bundle(val envelope:String,val revision:Long,val file:String,val hash:String,val bytes:ByteArray)
    data class Screen(val html:String,val revision:Long)
    private val folder=File(context.filesDir,"mobile-ota").apply{mkdirs()}
    private val stateFile=AtomicFile(File(folder,"state.json"))
    private val client=OkHttpClient.Builder().dns(ResilientDns).followRedirects(false).followSslRedirects(false)
        .connectTimeout(5,TimeUnit.SECONDS).readTimeout(8,TimeUnit.SECONDS).callTimeout(15,TimeUnit.SECONDS).build()
    private val info=context.packageManager.getPackageInfo(context.packageName,PackageManager.GET_SIGNING_CERTIFICATES)
    val nativeVersion:String get()=info.versionName.orEmpty()
    private val publicKey=CertificateFactory.getInstance("X.509").generateCertificate(
        ByteArrayInputStream(info.signingInfo!!.apkContentsSigners.single().toByteArray())).publicKey
    private var state=runCatching{JSONObject(stateFile.openRead().use{it.readBytes().toString(Charsets.UTF_8)})}.getOrElse{JSONObject()}
    @Volatile var revision=1L; private set
    private fun write(file:AtomicFile,bytes:ByteArray){val out=file.startWrite();try{out.write(bytes);file.finishWrite(out)}catch(t:Throwable){file.failWrite(out);throw t}}
    private fun save(){write(stateFile,state.toString().toByteArray(Charsets.UTF_8))}
    private fun manifest(envelope:String):Bundle {
        val outer=JSONObject(envelope);val raw=outer.getString("payload")
        require(SignedOta.verify(raw,outer.getString("signature"),publicKey)){"توقيع تحديث الواجهة غير صالح"}
        val p=JSONObject(raw);val hash=p.getString("sha256");val file=p.getString("file");val rev=p.getLong("revision")
        require(p.getInt("schema")==1 && p.getString("channel")=="stable" && SignedOta.compatible(p.getInt("runtime"),p.getLong("min_native"),p.getLong("max_native"),info.longVersionCode,rev,p.getInt("size"),hash,file)){"تحديث الواجهة لا يناسب إصدار التطبيق"}
        return Bundle(envelope,rev,file,hash,ByteArray(0))
    }
    @Synchronized fun screen(recover:Boolean=false):Screen {
        // A UI rejected by an older native renderer must be retryable after
        // the renderer is repaired by an APK update. Keep only a compatible,
        // confirmed revision as the floor; preserve pairing/key preferences.
        if(recover && state.optLong("native_version",-1)!=info.longVersionCode){
            val confirmed=state.optString(if(state.optBoolean("pending")) "previous" else "active")
            val floor=runCatching{manifest(confirmed).revision}.getOrDefault(1L)
            state.put("highest",floor);state.remove("rejected")
            state.put("native_version",info.longVersionCode);save()
        }
        if(recover && state.optBoolean("pending")) rollback(true)
        val active=state.optString("active")
        if(active.isNotEmpty()) {
            val candidate=runCatching{manifest(active)}.getOrNull()
            if(candidate!=null){
                val bytes=runCatching{File(folder,candidate.file).readBytes()}.getOrNull()
                if(bytes!=null && bytes.size<=SignedOta.MAX_HTML && SignedOta.hash(bytes)==candidate.hash){revision=candidate.revision;return Screen(bytes.toString(Charsets.UTF_8),revision)}
            }
            state.remove("active");state.put("pending",false);save()
        }
        revision=1
        return Screen(context.assets.open("ontrack-ui.html").bufferedReader().use{it.readText()},1)
    }
    private fun fetch(path:String,limit:Int,progress:((Int)->Unit)?=null):ByteArray {
        client.newCall(Request.Builder().url("https://app.ontrackegy.com/mobile/updates/$path").build()).execute().use { r ->
            check(r.isSuccessful){"تحديثات الموقع HTTP ${r.code}"}
            val bytes=r.body?.byteStream()?.use{input->
                val out=ByteArrayOutputStream();val buffer=ByteArray(8192)
                while(out.size()<=limit){
                    val count=input.read(buffer,0,minOf(buffer.size,limit+1-out.size()))
                    if(count<0)break
                    out.write(buffer,0,count)
                    progress?.invoke(out.size())
                }
                out.toByteArray()
            }?:error("ملف التحديث فارغ")
            require(bytes.size<=limit){"ملف التحديث أكبر من الحد"};return bytes
        }
    }
    fun check(progress:((Int,Int)->Unit)?=null):Bundle? {
        val envelope=fetch("latest.json",16384).toString(Charsets.UTF_8)
        val meta=manifest(envelope)
        synchronized(this){if(meta.revision<=state.optLong("highest",1))return null}
        // Only a cryptographically authenticated manifest determines the expected
        // byte count. No pretend timer-based percentages or untrusted HTTP lengths.
        val p=JSONObject(JSONObject(envelope).getString("payload"))
        val expected=p.getInt("size")
        progress?.invoke(0,expected)
        val bytes=fetch(meta.file,SignedOta.MAX_HTML){received->progress?.invoke(received,expected)}
        require(bytes.size==expected && SignedOta.hash(bytes)==meta.hash){"بصمة تحديث الواجهة غير مطابقة"}
        return meta.copy(bytes=bytes)
    }
    @Synchronized fun stage(bundle:Bundle):Screen {
        require(bundle.revision>state.optLong("highest",1))
        require(SignedOta.hash(bundle.bytes)==bundle.hash)
        write(AtomicFile(File(folder,bundle.file)),bundle.bytes)
        state.put("previous",state.optString("active"));state.put("active",bundle.envelope)
        state.put("pending",true);state.put("highest",bundle.revision);save()
        return screen()
    }
    @Synchronized fun confirm(){if(state.optBoolean("pending")){state.put("pending",false);save();clean()}}
    @Synchronized fun rollback(reject:Boolean):Screen {
        if(reject)state.put("rejected",state.optLong("highest",1))
        state.put("active",state.optString("previous"));state.remove("previous");state.put("pending",false);save()
        return screen()
    }
    private fun clean(){
        val keep=listOf("active","previous").mapNotNull{k->runCatching{manifest(state.optString(k)).file}.getOrNull()}.toSet()
        folder.listFiles()?.filter{it.name.matches(Regex("ui-[a-f0-9]{64}\\.html")) && it.name !in keep}?.forEach{it.delete()}
    }
}
