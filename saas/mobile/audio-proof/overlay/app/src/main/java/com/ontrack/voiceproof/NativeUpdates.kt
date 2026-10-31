package com.ontrack.voiceproof

import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.content.FileProvider
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
 * Native updates are distinct from signed UI OTA. Android itself confirms APK
 * installation. No silent install, Accessibility or elevated privileges.
 */
internal class NativeUpdates(private val context: Context) {
    data class Release(val versionCode: Long, val versionName: String, val size: Long, val sha256: String, val file: String)
    private val root = "https://app.ontrackegy.com/saas/mobile/updates/native/"
    private val client = OkHttpClient.Builder()
        .followRedirects(false).followSslRedirects(false)
        .connectTimeout(8, TimeUnit.SECONDS).readTimeout(20, TimeUnit.SECONDS)
        .callTimeout(90, TimeUnit.SECONDS).build()
    private val packageInfo = context.packageManager.getPackageInfo(context.packageName, PackageManager.GET_SIGNING_CERTIFICATES)
    private val localVersion = packageInfo.longVersionCode
    private val signer = packageInfo.signingInfo!!.apkContentsSigners.single().toByteArray()
    private val publicKey = CertificateFactory.getInstance("X.509")
        .generateCertificate(signer.inputStream()).publicKey
    private val destination=File(context.filesDir, "native-updates").apply { mkdirs() }
    val installedVersion:Long get()=localVersion

    private fun fetchManifest():ByteArray {
        val request=Request.Builder().url(root+"latest.json").header("Cache-Control","no-cache").build()
        client.newCall(request).execute().use { reply ->
            require(reply.code==200) { "مفيش تحديث Android متاح دلوقتي (HTTP ${reply.code})" }
            val bytes=reply.body?.byteStream()?.use { it.readNBytes(16385) }?:error("رد التحديث فارغ")
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
        val temp=File(destination,".native-download.tmp")
        try {
            val request=Request.Builder().url(root+release.file).build()
            client.newCall(request).execute().use {response ->
                require(response.code==200){"تعذر تحميل APK من OnTrack: HTTP ${response.code}"}
                val body=response.body?:error("ملف التحديث فارغ")
                var count=0L
                FileOutputStream(temp).use{out->
                    body.byteStream().use{input->
                        val buffer=ByteArray(65536)
                        while(true) {
                            val n=input.read(buffer);if(n<0)break
                            count+=n
                            require(count<=release.size){"حجم ملف APK تجاوز الحجم الموقّع"}
                            out.write(buffer,0,n)
                            if(count%1048576<n)progress(((count*100)/release.size).toInt().coerceIn(0,100))
                        }
                    }
                    out.fd.sync()
                }
                require(count==release.size){"تنزيل APK غير مكتمل"}
            }
            require(verified(temp,release)){"توقيع APK أو بصمته لا تطابق إصدار OnTrack"}
            if(target.exists() && !target.delete())error("تعذر استبدال ملف التحديث القديم")
            require(temp.renameTo(target)){"تعذر تخزين التحديث"}
            destination.listFiles()?.filter{it.name.endsWith(".apk") && it!=target}?.forEach{it.delete()}
            progress(100)
            target
        }finally{if(temp.exists())temp.delete()}
    }

    fun requestInstall(file:File):Boolean {
        require(file.isFile && file.parentFile?.canonicalPath==destination.canonicalPath){"ملف غير موثوق"}
        if(Build.VERSION.SDK_INT>=26 && !context.packageManager.canRequestPackageInstalls()) {
            context.startActivity(Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                Uri.parse("package:${context.packageName}")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
            return false
        }
        val content=FileProvider.getUriForFile(context,"${context.packageName}.updates",file)
        context.startActivity(Intent(Intent.ACTION_VIEW).apply{
            setDataAndType(content,"application/vnd.android.package-archive")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
        })
        return true
    }
}
