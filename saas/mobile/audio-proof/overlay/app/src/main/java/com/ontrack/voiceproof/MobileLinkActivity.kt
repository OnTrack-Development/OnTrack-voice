package com.ontrack.voiceproof

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
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
    private var updateBusy=false
    private var pending:MobileOta.Bundle?=null
    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState:Bundle?){
        super.onCreate(savedInstanceState);ota=MobileOta(this)
        val root=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setPadding(0,30,0,0)}
        banner=TextView(this).apply{setPadding(20,10,20,10);text="جاري فحص تحديث الواجهة…"};root.addView(banner)
        web=WebView(this);root.addView(web,LinearLayout.LayoutParams(-1,0,1f));setContentView(root)
        web.settings.apply{
            javaScriptEnabled=true;domStorageEnabled=false;allowFileAccess=false;allowContentAccess=false
            blockNetworkLoads=true;cacheMode=WebSettings.LOAD_NO_CACHE;mixedContentMode=WebSettings.MIXED_CONTENT_NEVER_ALLOW
            javaScriptCanOpenWindowsAutomatically=false;setSupportMultipleWindows(false)
        }
        web.webViewClient=object:WebViewClient(){
            override fun shouldOverrideUrlLoading(view:WebView,request:WebResourceRequest)=true
            override fun shouldInterceptRequest(view:WebView,request:WebResourceRequest)=WebResourceResponse("text/plain","UTF-8",403,"Blocked",mapOf(),ByteArrayInputStream(ByteArray(0)))
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
        val policy="<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; connect-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'\">"
        web.loadDataWithBaseURL("https://ontrack-ui.invalid/",screen.html.replaceFirst("<head>","<head>"+policy),"text/html","UTF-8",null)
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
            .put("inCall",MobileLinkService.inCall).put("revision",ota.revision).put("native",BuildConfig.VERSION_NAME)
        web.evaluateJavascript("window.onTrackState && window.onTrackState($state)",null)
    }
    inner class Bridge{
        @JavascriptInterface fun postMessage(raw:String){
            if(raw.length>2048)return
            runOnUiThread {lifecycleScope.launch{runCatching{
                val msg=JSONObject(raw)
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
                    "stop"->stopService(Intent(this@MobileLinkActivity,MobileLinkService::class.java))
                    "checkUpdates"->checkUpdates()
                    "rollback"->{check(!MobileLinkService.inCall){"انتظر نهاية المكالمة"};show(withContext(Dispatchers.IO){ota.rollback(false)});banner.text="تم الرجوع للواجهة السابقة"}
                    "diagnostics"->startActivity(Intent(this@MobileLinkActivity,ProofActivity::class.java))
                    "admin"->startActivity(Intent(Intent.ACTION_VIEW,Uri.parse("https://app.ontrackegy.com/saas/mobile/")))
                    else->error("أمر الواجهة غير مدعوم")
                }
            }.onFailure{banner.text=it.message?:"تعذر تنفيذ الطلب"}}}
        }
    }
    override fun onDestroy(){generation++;web.removeJavascriptInterface("OnTrack");web.destroy();super.onDestroy()}
}
