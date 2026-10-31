package com.ontrack.voiceproof

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.provider.Settings
import android.graphics.Color
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.MediaType.Companion.toMediaType
import android.os.Bundle
import android.webkit.*
import android.widget.*
import androidx.activity.ComponentActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.*
import org.json.JSONObject
import java.io.ByteArrayInputStream

/** Only verified local HTML enters this isolated WebView; no remote navigation,
 * subresources, files, iframes or arbitrary native/shell methods are exposed. */
class MobileLinkActivity:ComponentActivity(){
    private lateinit var web:WebView
    private lateinit var ota:MobileOta
    private lateinit var banner:TextView
    private var ready=false
    private var generation=0
    private var screenNonce=""
    @Volatile private var localPage:LocalUiPage?=null
    private var updateBusy=false
    private var pending:MobileOta.Bundle?=null
    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState:Bundle?){
        super.onCreate(savedInstanceState);ota=MobileOta(this)
        window.statusBarColor=Color.rgb(11,16,27);window.navigationBarColor=Color.rgb(11,16,27)
        val root=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setBackgroundColor(Color.rgb(11,16,27))}
        banner=TextView(this).apply{
            setPadding(20,14,20,12);setTextColor(Color.rgb(168,185,208))
            setBackgroundColor(Color.rgb(18,27,42))
            textSize=12f;text="OnTrack Voice • جاري تجهيز الاتصال"
        };root.addView(banner)
        web=WebView(this);root.addView(web,LinearLayout.LayoutParams(-1,0,1f));setContentView(root)
        web.settings.apply{
            javaScriptEnabled=true;domStorageEnabled=false;allowFileAccess=false;allowContentAccess=false
            blockNetworkLoads=true;cacheMode=WebSettings.LOAD_NO_CACHE;mixedContentMode=WebSettings.MIXED_CONTENT_NEVER_ALLOW
            javaScriptCanOpenWindowsAutomatically=false;setSupportMultipleWindows(false)
        }
        web.webViewClient=object:WebViewClient(){
            override fun shouldOverrideUrlLoading(view:WebView,request:WebResourceRequest)=
                localPage?.allows(request.url.toString(),request.isForMainFrame,request.method)!=true
            override fun shouldInterceptRequest(view:WebView,request:WebResourceRequest):WebResourceResponse {
                val page=localPage
                if(page!=null && page.allows(request.url.toString(),request.isForMainFrame,request.method)) {
                    return WebResourceResponse("text/html","UTF-8",200,"OK",
                        mapOf("Content-Security-Policy" to LocalUiPage.CSP,"Cache-Control" to "no-store"),
                        ByteArrayInputStream(page.html.toByteArray(Charsets.UTF_8)))
                }
                return WebResourceResponse("text/plain","UTF-8",403,"Blocked",mapOf(),ByteArrayInputStream(ByteArray(0)))
            }
        }
        web.addJavascriptInterface(Bridge(),"OnTrack")
        lifecycleScope.launch{
            show(withContext(Dispatchers.IO){ota.screen(recover=true)})
            checkUpdates()
            while(isActive){
                sendState()
                if(pending!=null && !MobileLinkService.inCall){val next=pending!!;pending=null;activate(next)}
                delay(1000)
            }
        }
    }
    private fun show(screen:MobileOta.Screen){
        ready=false;val current=++generation
        screenNonce=java.util.UUID.randomUUID().toString()
        require(screen.html.contains("<head>")){"صيغة واجهة التحديث غير صالحة"}
        val content=screen.html.replaceFirst("<head>","<head><script>window.OTA_NONCE="+JSONObject.quote(screenNonce)+";</script>")
        val page=LocalUiPage(screenNonce,content)
        localPage=page
        web.loadUrl(page.url)
        lifecycleScope.launch{delay(10000);if(!ready && current==generation){
            if(screen.revision>1){show(withContext(Dispatchers.IO){ota.rollback(true)});banner.text="تعذر فتح التحديث؛ رجعنا للواجهة السابقة"}
            else banner.text="تعذر فتح الواجهة؛ استخدم إعدادات الهاتف من شاشة التشخيص"
        }}
    }
    private suspend fun activate(bundle:MobileOta.Bundle){
        if(MobileLinkService.inCall){pending=bundle;banner.text="التحديث جاهز؛ هيتطبق بعد المكالمة";return}
        show(withContext(Dispatchers.IO){ota.stage(bundle)});banner.text="تم تحميل تحديث الواجهة؛ جاري التحقق من تشغيله…"
    }
    private suspend fun checkUpdates(){
        if(updateBusy)return;updateBusy=true
        try{
            val next=withContext(Dispatchers.IO){ota.check()}
            if(next==null)banner.text="الواجهة محدثة • OTA ${ota.revision}" else activate(next)
        }catch(t:Throwable){if(t is CancellationException)throw t;banner.text="الواجهة الحالية تعمل؛ تعذر جلب التحديث الآن"}
        finally{updateBusy=false}
    }
    private fun sendState(){
        if(!ready)return
        val state=JSONObject().put("status",MobileLinkService.latestStatus).put("running",MobileLinkService.running)
            .put("inCall",MobileLinkService.inCall).put("humanControl",MobileLinkService.humanControl)
            .put("agentEnabled",MobileLinkService.agentEnabled).put("serverRevision",MobileLinkService.serverRevision)
            .put("overlayGranted",Settings.canDrawOverlays(this)).put("revision",ota.revision).put("native",ota.nativeVersion).put("updating",updateBusy)
        web.evaluateJavascript("window.onTrackState && window.onTrackState($state)",null)
    }
    inner class Bridge{
        @JavascriptInterface fun postMessage(raw:String){
            if(raw.length>2048)return
            runOnUiThread {lifecycleScope.launch{runCatching{
                val msg=JSONObject(raw)
                if(msg.optString("nonce")!=screenNonce)return@launch
                when(msg.getString("command")){
                    "ready"->{ready=true;withContext(Dispatchers.IO){ota.confirm()};banner.text="الواجهة جاهزة • OTA ${ota.revision}";sendState()
                        val prefs=getSharedPreferences("ontrack_link",MODE_PRIVATE)
                        val initial=JSONObject().put("base",prefs.getString("base","https://app.ontrackegy.com/"))
                            .put("key",prefs.getString("key",""))
                        web.evaluateJavascript("window.onTrackInit && window.onTrackInit($initial)",null)
                    }
                    "start"->{
                        check(!MobileLinkService.running){"أوقف الربط الحالي قبل تغيير بياناته"}
                        val base=MobileLinkService.base(msg.getString("base"));val key=msg.getString("key").trim()
                        require(key.matches(Regex("[A-Za-z0-9_-]{24,128}"))){"انسخ المفتاح فقط بدون مسافات أو علامات إضافية"}
                        getSharedPreferences("ontrack_link",MODE_PRIVATE).edit().putString("base",base).putString("key",key).commit()
                        ContextCompat.startForegroundService(this@MobileLinkActivity,Intent(this@MobileLinkActivity,MobileLinkService::class.java))
                    }
                    "stop","disable"->ContextCompat.startForegroundService(this@MobileLinkActivity,Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_DISABLE))
                    "enable"->ContextCompat.startForegroundService(this@MobileLinkActivity,Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_ENABLE))
                    "takeover"->startService(Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_TAKEOVER))
                    "speaker"->startService(Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_SPEAKER))
                    "overlayPermission"->{
                        if(!Settings.canDrawOverlays(this@MobileLinkActivity)){
                            startActivity(Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,Uri.parse("package:$packageName")))
                        }else banner.text="إذن النافذة العائمة مفعل"
                    }
                    "checkUpdates"->checkUpdates()
                    "rollback"->{check(!MobileLinkService.inCall){"انتظر نهاية المكالمة"};show(withContext(Dispatchers.IO){ota.rollback(false)});banner.text="تم الرجوع للواجهة السابقة"}
                    "diagnostics"->startActivity(Intent(this@MobileLinkActivity,ProofActivity::class.java))
                    "admin"->{
                        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","")?:""
                        require(key.length>=24){"أدخل مفتاح الربط أولاً"}
                        val ticketUrl=withContext(Dispatchers.IO){
                            val client=OkHttpClient.Builder().followRedirects(false).build()
                            val req=Request.Builder().url("https://app.ontrackegy.com/saas/mobile/admin_ticket.php")
                                .header("Authorization","Bearer "+key)
                                .post("{}".toRequestBody("application/json".toMediaType())).build()
                            client.newCall(req).execute().use{
                                check(it.isSuccessful){"تعذر الدخول إلى اللوحة: HTTP "+it.code}
                                JSONObject(it.body?.string()?:"").getString("url")
                            }
                        }
                        val uri=Uri.parse(ticketUrl)
                        check(uri.scheme=="https" && uri.host=="app.ontrackegy.com" &&
                            uri.path=="/saas/mobile/admin_enter.php"){"رابط الدخول غير آمن"}
                        startActivity(Intent(Intent.ACTION_VIEW,uri))
                    }
                    else->error("أمر الواجهة غير مدعوم")
                }
            }.onFailure{banner.text=it.message?:"تعذر تنفيذ الطلب"}}}
        }
    }
    override fun onDestroy(){generation++;web.removeJavascriptInterface("OnTrack");web.destroy();super.onDestroy()}
}
