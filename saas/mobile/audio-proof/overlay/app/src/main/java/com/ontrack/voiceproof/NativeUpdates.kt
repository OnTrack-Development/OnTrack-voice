package com.ontrack.voiceproof

import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.provider.Settings
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.io.File
import java.io.FileOutputStream
import java.security.MessageDigest
import java.security.cert.CertificateFactory
import java.util.concurrent.TimeUnit

/**
 * Pure helper for resumable HTTP Range native APK download decisions.
 */
internal object NativeDownloadResumePolicy {
    fun metaToken(sha256: String, size: Long): String = "${sha256.trim().lowercase()}:$size"

    fun isPartialMetaValid(metaText: String?, sha256: String, size: Long): Boolean =
        metaText?.trim() == metaToken(sha256, size)

    fun rangeHeaderFor(existingBytes: Long, expectedSize: Long): String? =
        if (existingBytes in 1L until expectedSize) "bytes=$existingBytes-" else null

    fun shouldAppend206(httpCode: Int, contentRange: String?, expectedStart: Long): Boolean {
        if (httpCode != 206 || expectedStart <= 0L) return false
        val range = contentRange?.trim() ?: return false
        return range.startsWith("bytes $expectedStart-")
    }
}

/**
 * Native updates are distinct from signed UI OTA. Android itself confirms APK
 * installation. No silent install, Accessibility or elevated privileges.
 */
internal class NativeUpdates(private val context: Context) {
    data class Release(val versionCode: Long, val versionName: String, val size: Long, val sha256: String, val file: String)
    private val root = "https://app.ontrackegy.com/mobile/updates/native/"
    private val client = OkHttpClient.Builder()
        .followRedirects(false).followSslRedirects(false)
        .connectTimeout(15, TimeUnit.SECONDS).readTimeout(25, TimeUnit.SECONDS)
        .callTimeout(30, TimeUnit.SECONDS).build()
    private val downloadClient = OkHttpClient.Builder()
        .followRedirects(false).followSslRedirects(false)
        .connectTimeout(30_000, TimeUnit.MILLISECONDS)
        .readTimeout(180_000, TimeUnit.MILLISECONDS)
        .writeTimeout(60_000, TimeUnit.MILLISECONDS)
        .callTimeout(0, TimeUnit.MILLISECONDS)
        .build()
    private val packageInfo = context.packageManager.getPackageInfo(context.packageName, PackageManager.GET_SIGNING_CERTIFICATES)
    private val localVersion = packageInfo.longVersionCode
    private val signer = packageInfo.signingInfo!!.apkContentsSigners.single().toByteArray()
    private val publicKey = CertificateFactory.getInstance("X.509")
        .generateCertificate(signer.inputStream()).publicKey
    private val destination=File(context.filesDir, "native-updates").apply { mkdirs() }
    val installedVersion:Long get()=localVersion
    private val installPrefs=context.getSharedPreferences("ontrack_native_installer",Context.MODE_PRIVATE)
    fun canInstallPackages():Boolean=Build.VERSION.SDK_INT<26 ||
        context.packageManager.canRequestPackageInstalls()
    fun openInstallPermission(){
        if(Build.VERSION.SDK_INT>=26)context.startActivity(
            Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                Uri.parse("package:${context.packageName}"))
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    }
    /** Prevent duplicate APK downloads and sessions while Android requests approval. */
    fun installPending():Boolean {
        val sessionId=installPrefs.getInt("session_id",-1)
        if(sessionId<=0)return false
        val current=runCatching{
            context.packageManager.packageInstaller.getSessionInfo(sessionId)
        }.getOrNull()
        if(current==null){
            installPrefs.edit().remove("session_id").remove("created_at").apply()
            return false
        }
        return true
    }
    fun cached(release:Release):Boolean=verified(apk(release),release)

    private fun fetchManifest():ByteArray {
        val request=Request.Builder().url(root+"latest.json").header("Cache-Control","no-cache").build()
        client.newCall(request).execute().use { reply ->
            require(reply.code==200) { "مفيش تحديث Android متاح دلوقتي (HTTP ${reply.code})" }
            val bytes=reply.body?.byteStream()?.use { input ->
                val out=java.io.ByteArrayOutputStream()
                val buffer=ByteArray(4096)
                while(out.size()<16385) {
                    val n=input.read(buffer,0,minOf(buffer.size,16385-out.size()))
                    if(n<0)break
                    out.write(buffer,0,n)
                }
                out.toByteArray()
            }?:error("رد التحديث فارغ")
            require(bytes.size<=16384) { "ملف التحديث أكبر من المسموح" }
            return bytes
        }
    }

    suspend fun check():Release?=withContext(Dispatchers.IO){
        val envelope=JSONObject(fetchManifest().toString(Charsets.UTF_8))
        val payload=envelope.getString("payload")
        require(SignedOta.verify(payload,envelope.getString("signature"),publicKey)){
            "توقيع تحديث Android غير صحيح"
        }
        val meta=JSONObject(payload)
        require(meta.getInt("schema")==1 && meta.getString("channel")=="stable" &&
            meta.getString("package") == context.packageName){ "التحديث ليس لهذا التطبيق" }
        val version=meta.getLong("version_code")
        val hash=meta.getString("sha256")
        val file=meta.getString("file")
        val size=meta.getLong("size")
        require(NativeReleasePolicy.valid(version,localVersion,size,hash,file) &&
            localVersion>=meta.optLong("min_installed_version",1)){
            "إصدار تحديث Android أو بياناته غير متوافقة"
        }
        if(version<=localVersion) null else Release(version,meta.getString("version_name"),size,hash,file)
    }

    private fun apk(release:Release)=File(destination,release.file)
    private fun digest(file:File):String{
        val md=MessageDigest.getInstance("SHA-256")
        file.inputStream().buffered().use { stream ->
            val buffer=ByteArray(65536)
            while(true){val n=stream.read(buffer);if(n<0)break;md.update(buffer,0,n)}
        }
        return md.digest().joinToString(""){"%02x".format(it)}
    }
    private fun verified(file:File,release:Release):Boolean {
        if(!file.isFile || file.length()!=release.size || digest(file)!=release.sha256)return false
        val parsed=context.packageManager.getPackageArchiveInfo(file.absolutePath,PackageManager.GET_SIGNING_CERTIFICATES)?:return false
        if(parsed.packageName!=context.packageName || parsed.longVersionCode!=release.versionCode)return false
        val remote=parsed.signingInfo?.apkContentsSigners?.singleOrNull()?.toByteArray()?:return false
        return MessageDigest.isEqual(remote,signer)
    }
    suspend fun download(release:Release,progress:(Int)->Unit):File=withContext(Dispatchers.IO){
        val target=apk(release)
        if(verified(target,release))return@withContext target
        val temp=File(destination,".native-download.apk")
        val metaFile=File(destination,".native-download.meta")
        val metaToken=NativeDownloadResumePolicy.metaToken(release.sha256,release.size)
        val existingMeta=runCatching{metaFile.takeIf{it.isFile}?.readText()}.getOrNull()
        if(!NativeDownloadResumePolicy.isPartialMetaValid(existingMeta,release.sha256,release.size) ||
            temp.length()>release.size){
            runCatching{temp.delete()}
        }
        runCatching{metaFile.writeText(metaToken)}

        val maxAttempts=6
        var attempt=0
        var lastError:Throwable?=null
        while(attempt<maxAttempts && (!temp.isFile || temp.length()<release.size)){
            attempt++
            var existing=if(temp.isFile) temp.length().coerceAtMost(release.size) else 0L
            if(existing==release.size)break
            if(existing>0L)progress(((existing*100)/release.size).toInt().coerceIn(0,99))
            val reqBuilder=Request.Builder()
                .url(root+release.file)
                .header("Accept-Encoding","identity")
            NativeDownloadResumePolicy.rangeHeaderFor(existing,release.size)?.let{rangeVal->
                reqBuilder.header("Range",rangeVal)
            }
            try{
                downloadClient.newCall(reqBuilder.build()).execute().use{response->
                    if(response.code==416){
                        runCatching{temp.delete()}
                        existing=0L
                        return@use
                    }
                    require(response.code==200 || response.code==206){
                        "تعذر تحميل APK من IO Voice: HTTP ${response.code}"
                    }
                    val append=NativeDownloadResumePolicy.shouldAppend206(
                        response.code,response.header("Content-Range"),existing)
                    var count=if(append) existing else 0L
                    val body=response.body?:error("ملف التحديث فارغ")
                    FileOutputStream(temp,append).use{out->
                        body.byteStream().use{input->
                            val buffer=ByteArray(65536)
                            while(true){
                                val n=input.read(buffer);if(n<0)break
                                count+=n
                                require(count<=release.size){"حجم ملف APK تجاوز الحجم الموقّع"}
                                out.write(buffer,0,n)
                                if(count%262144L<n || count==release.size){
                                    progress(((count*100)/release.size).toInt().coerceIn(0,99))
                                }
                            }
                        }
                        out.fd.sync()
                    }
                }
                lastError=null
            }catch(err:java.io.IOException){
                lastError=err
                if(attempt<maxAttempts)runCatching{Thread.sleep(1500L*attempt)}
            }
        }
        if(!temp.isFile || temp.length()!=release.size){
            val detail=lastError?.message?.takeIf{it.isNotBlank()}?.let{" ($it)"}?:""
            error("تنزيل APK غير مكتمل بعد عدة محاولات$detail")
        }
        if(!verified(temp,release)){
            runCatching{temp.delete()};runCatching{metaFile.delete()}
            error("توقيع APK أو بصمته لا تطابق إصدار IO Voice")
        }
        if(target.exists() && !target.delete())error("تعذر استبدال ملف التحديث القديم")
        require(temp.renameTo(target)){"تعذر تخزين التحديث"}
        runCatching{metaFile.delete()}
        destination.listFiles()?.filter{it.name.endsWith(".apk") && it!=target}?.forEach{it.delete()}
        progress(100)
        target
    }

    /**
     * Stream a fully verified APK to Android's PackageInstaller session, rather
     * than relying on OEM file/URI parsing through ACTION_VIEW.
     * The OS still controls install authorization and user confirmation.
     */
    fun requestInstall(file:File):Boolean {
        require(file.isFile && file.parentFile?.canonicalPath==destination.canonicalPath){"ملف تحديث غير موثوق"}
        require(file.name.endsWith(".apk")){"امتداد التحديث غير صالح"}
        if(!canInstallPackages()){openInstallPermission();return false}
        if(installPending())return true
        val installer=context.packageManager.packageInstaller
        val params=android.content.pm.PackageInstaller.SessionParams(
            android.content.pm.PackageInstaller.SessionParams.MODE_FULL_INSTALL)
        params.setAppPackageName(context.packageName)
        params.setSize(file.length())
        if(Build.VERSION.SDK_INT>=31) {
            params.setRequireUserAction(
                android.content.pm.PackageInstaller.SessionParams.USER_ACTION_REQUIRED)
        }
        val sessionId=installer.createSession(params)
        try {
            installer.openSession(sessionId).use { session ->
                session.openWrite("base.apk",0L,file.length()).use { out ->
                    file.inputStream().buffered().use { input -> input.copyTo(out,65536) }
                    session.fsync(out)
                }
                // The receiver is explicit. PendingIntent must be mutable so
                // PackageInstaller can attach STATUS and its confirmation intent.
                val result=Intent(context,NativeInstallResultReceiver::class.java)
                    .setAction(NativeInstallResultReceiver.ACTION_RESULT)
                    .setPackage(context.packageName)
                val sender=android.app.PendingIntent.getBroadcast(
                    context,sessionId,result,
                    android.app.PendingIntent.FLAG_UPDATE_CURRENT or
                        android.app.PendingIntent.FLAG_MUTABLE).intentSender
                installPrefs.edit().putInt("session_id",sessionId)
                    .putLong("created_at",System.currentTimeMillis()).apply()
                session.commit(sender)
            }
            return true
        }catch(t:Throwable) {
            runCatching{installer.abandonSession(sessionId)}
            installPrefs.edit().remove("session_id").remove("created_at").apply()
            throw t
        }
    }
}
