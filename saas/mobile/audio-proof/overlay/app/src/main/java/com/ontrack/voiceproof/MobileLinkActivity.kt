package com.ontrack.voiceproof

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.provider.Settings
import android.provider.ContactsContract
import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.result.contract.ActivityResultContracts
import org.json.JSONArray
import android.graphics.Color
import okhttp3.OkHttpClient
import okhttp3.MultipartBody
import android.provider.OpenableColumns
import java.io.ByteArrayOutputStream
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.MediaType.Companion.toMediaType
import android.os.Bundle
import android.webkit.*
import android.widget.*
import android.view.View
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
    private lateinit var nativeUpdates:NativeUpdates
    private var nativeRelease:NativeUpdates.Release?=null
    private var nativeBusy=false
    private var nativeStatus="جاري فحص تحديث Android"
    private var lastNativeInstallResult=""
    @Volatile private var wifiFreeEnabledState=false
    @Volatile private var wifiFreeReadyState=false
    private lateinit var banner:TextView
    private var splash:OnTrackLaunchScreen?=null
    private var ready=false
    private var generation=0
    private var screenNonce=""
    @Volatile private var localPage:LocalUiPage?=null
    private var updateBusy=false
    private var pending:MobileOta.Bundle?=null
    private var pendingContacts=JSONArray()
    private var pendingImportUri:Uri?=null
    private var pendingImportName=""
    private val contactsPermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        if(granted)lifecycleScope.launch{loadAllContacts()}
        else {banner.text="لم يتم منح إذن قراءة جهات الاتصال؛ لم تُرفع أي بيانات";banner.visibility=View.VISIBLE}
    }
    private val campaignPhonePermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        if(granted)ContextCompat.startForegroundService(this,
            Intent(this,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_CAMPAIGN_ENABLE))
        else {banner.text="إذن الاتصال لم يُمنح؛ تنفيذ الحملات يظل متوقفًا";banner.visibility=View.VISIBLE}
    }
    private val contactPicker=registerForActivityResult(ActivityResultContracts.StartActivityForResult()){result->
        if(result.resultCode==RESULT_OK){
            val uri=result.data?.data
            if(uri!=null)lifecycleScope.launch(Dispatchers.IO){
                val rows=JSONArray()
                runCatching{
                    contentResolver.query(uri,arrayOf(
                        ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME,
                        ContactsContract.CommonDataKinds.Phone.NUMBER),null,null,null)?.use{cursor->
                        val nameIndex=cursor.getColumnIndex(ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME)
                        val numberIndex=cursor.getColumnIndex(ContactsContract.CommonDataKinds.Phone.NUMBER)
                        if(cursor.moveToFirst() && numberIndex>=0)rows.put(JSONObject()
                            .put("name",if(nameIndex>=0)cursor.getString(nameIndex)?:"" else "")
                            .put("phone",cursor.getString(numberIndex)?:""))
                    }
                }
                withContext(Dispatchers.Main){pendingContacts=rows;showContactPreview()}
            }
        }
    }
    private val filePicker=registerForActivityResult(ActivityResultContracts.OpenDocument()){uri->
        if(uri!=null)lifecycleScope.launch{
            val name=withContext(Dispatchers.IO){
                contentResolver.query(uri,arrayOf(OpenableColumns.DISPLAY_NAME),null,null,null)?.use { cursor->
                    if(cursor.moveToFirst())cursor.getString(0) else null
                }?:uri.lastPathSegment.orEmpty()
            }
            if(!Regex("(?i)^.{1,140}\\.(csv|txt|xlsx)$").matches(name)){
                pendingImportUri=null;pendingImportName=""
                banner.text="مسموح CSV أو TXT أو XLSX فقط";banner.visibility=View.VISIBLE
            }else{
                pendingImportUri=uri;pendingImportName=name
                web.evaluateJavascript("window.onTrackFilePreview && window.onTrackFilePreview("+JSONObject().put("name",name).toString()+")",null)
            }
        }
    }
    private suspend fun uploadContactsFile(){
        check(!MobileLinkService.inCall){"ارفع الملف بعد انتهاء المكالمة"}
        val uri=pendingImportUri?:error("اختار ملفًا أولًا")
        val name=pendingImportName
        check(Regex("(?i)^.{1,140}\\.(csv|txt|xlsx)$").matches(name)){"نوع الملف غير مسموح"}
        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","").orEmpty()
        check(key.length>=24){"اربط التطبيق بالسيرفر أولاً"}
        val result=withContext(Dispatchers.IO){
            val stream=contentResolver.openInputStream(uri)?:error("تعذر قراءة الملف")
            val out=ByteArrayOutputStream()
            stream.use { input->
                val bytes=ByteArray(8192)
                while(true){
                    val n=input.read(bytes)
                    if(n<0)break
                    check(out.size()+n<=1048576){"الحد الأقصى للملف 1 ميجابايت"}
                    out.write(bytes,0,n)
                }
            }
            check(out.size()>0){"الملف فارغ"}
            val form=MultipartBody.Builder().setType(MultipartBody.FORM)
                .addFormDataPart("confirmed","1").addFormDataPart("consent","1")
                .addFormDataPart("contacts",name,out.toByteArray().toRequestBody("application/octet-stream".toMediaType()))
                .build()
            OkHttpClient.Builder().followRedirects(false).build().newCall(Request.Builder()
                .url("https://app.ontrackegy.com/saas/mobile/contacts_file_api.php")
                .header("Authorization","Bearer "+key).post(form).build()).execute().use{
                check(it.isSuccessful){"تعذر رفع الملف HTTP "+it.code}
                JSONObject(it.body?.string().orEmpty())
            }
        }
        check(result.optBoolean("ok")){"تم رفض الملف"}
        pendingImportUri=null;pendingImportName=""
        web.evaluateJavascript("window.onTrackFileImported && window.onTrackFileImported("+result.toString()+")",null)
    }
    private suspend fun loadAllContacts()=withContext(Dispatchers.IO){
        val rows=JSONArray()
        runCatching{
            contentResolver.query(ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
                arrayOf(ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME,
                    ContactsContract.CommonDataKinds.Phone.NUMBER),null,null,
                ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME+" ASC")?.use{cursor->
                val nameIndex=cursor.getColumnIndex(ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME)
                val phoneIndex=cursor.getColumnIndex(ContactsContract.CommonDataKinds.Phone.NUMBER)
                while(cursor.moveToNext() && rows.length()<2000){
                    if(phoneIndex>=0)rows.put(JSONObject()
                        .put("name",if(nameIndex>=0)cursor.getString(nameIndex)?:"" else "")
                        .put("phone",cursor.getString(phoneIndex)?:""))
                }
            }
        }
        withContext(Dispatchers.Main){pendingContacts=rows;showContactPreview()}
    }
    private fun showContactPreview(){
        val sample=JSONArray()
        for(i in 0 until minOf(5,pendingContacts.length()))sample.put(pendingContacts.getJSONObject(i))
        val status=JSONObject().put("count",pendingContacts.length()).put("sample",sample)
        web.evaluateJavascript("window.onTrackContactPreview && window.onTrackContactPreview("+status.toString()+")",null)
    }
    private suspend fun loadCallHistory() {
        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","").orEmpty()
        check(key.length>=24){"أدخل مفتاح الربط قبل قراءة سجل المكالمات"}
        val history=withContext(Dispatchers.IO){
            OkHttpClient.Builder().followRedirects(false).build().newCall(Request.Builder()
                .url("https://app.ontrackegy.com/saas/mobile/calls_feed.php")
                .header("Authorization","Bearer "+key).get().build()).execute().use{
                check(it.isSuccessful){"السجل غير متاح HTTP "+it.code}
                val bytes=it.body?.string().orEmpty()
                check(bytes.length<90000){"رد السجل غير صالح"}
                JSONObject(bytes)
            }
        }
        check(history.optBoolean("ok")){"لم يتم تحميل السجل"}
        web.evaluateJavascript("window.onTrackCalls && window.onTrackCalls("+history.toString()+")",null)
    }
    private suspend fun contactsRequest(payload:JSONObject?) {
        check(!MobileLinkService.inCall){"إدارة الأرقام متاحة بعد انتهاء المكالمة"}
        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","").orEmpty()
        check(key.length>=24){"اربط الهاتف بالسيرفر"}
        val result=withContext(Dispatchers.IO){
            val builder=Request.Builder().url("https://app.ontrackegy.com/saas/mobile/contacts_api.php")
                .header("Authorization","Bearer "+key)
            val request=if(payload==null)builder.get().build() else builder
                .post(payload.toString().toRequestBody("application/json".toMediaType())).build()
            OkHttpClient.Builder().followRedirects(false).build().newCall(request).execute().use {
                check(it.isSuccessful){"تعذر إدارة الأرقام HTTP "+it.code}
                val body=it.body?.string().orEmpty()
                check(body.length<=90000){"رد السيرفر كبير"}
                JSONObject(body)
            }
        }
        check(result.optBoolean("ok")){"السيرفر رفض العملية"}
        if(payload!=null)contactsRequest(null)
        else web.evaluateJavascript("window.onTrackContactsList && window.onTrackContactsList("+result.toString()+")",null)
    }
    private suspend fun campaignRequest(payload:JSONObject?) {
        check(!MobileLinkService.inCall){"إدارة الحملات متاحة بعد انتهاء المكالمة"}
        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","").orEmpty()
        check(key.length>=24){"اربط التطبيق بالسيرفر أولاً"}
        val result=withContext(Dispatchers.IO) {
            val builder=Request.Builder().url("https://app.ontrackegy.com/saas/mobile/campaigns_api.php")
                .header("Authorization","Bearer "+key)
            val request=if(payload==null)builder.get().build() else builder
                .post(payload.toString().toRequestBody("application/json".toMediaType())).build()
            OkHttpClient.Builder().followRedirects(false).build().newCall(request).execute().use {
                check(it.isSuccessful){"الحملات غير متاحة HTTP "+it.code}
                val raw=it.body?.string().orEmpty()
                check(raw.length<=90000){"رد الحملات كبير"}
                JSONObject(raw)
            }
        }
        check(result.optBoolean("ok")){"تعذر تحميل الحملات"}
        web.evaluateJavascript("window.onTrackCampaigns && window.onTrackCampaigns("+result.toString()+")",null)
    }
    private suspend fun uploadSelectedContacts() {
        check(!MobileLinkService.inCall){"اترك رفع الأرقام حتى انتهاء المكالمة"}
        check(pendingContacts.length()>0){"اختر أرقامًا قبل الرفع"}
        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","").orEmpty()
        check(key.length>=24){"أدخل مفتاح الربط أولاً"}
        val body=JSONObject().put("confirmed",true).put("contacts",pendingContacts).toString()
        val response=withContext(Dispatchers.IO) {
            OkHttpClient.Builder().followRedirects(false).build().newCall(Request.Builder()
                .url("https://app.ontrackegy.com/saas/mobile/contacts_import.php")
                .header("Authorization","Bearer "+key)
                .post(body.toRequestBody("application/json".toMediaType())).build()).execute().use {
                check(it.isSuccessful){"فشل استيراد الأرقام HTTP "+it.code}
                JSONObject(it.body?.string().orEmpty())
            }
        }
        check(response.optBoolean("ok")){"السيرفر رفض استيراد الأرقام"}
        pendingContacts=JSONArray()
        val counts=response.optJSONObject("counts")?:JSONObject()
        val answer=JSONObject().put("added",counts.optInt("added"))
            .put("duplicates",counts.optInt("duplicates"))
            .put("invalid",counts.optInt("invalid"))
        web.evaluateJavascript("window.onTrackContactsImported && window.onTrackContactsImported("+answer.toString()+")",null)
    }
    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState:Bundle?){
        super.onCreate(savedInstanceState);ota=MobileOta(this);nativeUpdates=NativeUpdates(this)
        window.statusBarColor=Color.rgb(11,16,27);window.navigationBarColor=Color.rgb(11,16,27)
        val root=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setBackgroundColor(Color.rgb(11,16,27))}
        banner=TextView(this).apply{
            setPadding(20,14,20,12);setTextColor(Color.rgb(168,185,208))
            setBackgroundColor(Color.rgb(18,27,42))
            textSize=12f;text="OnTrack Voice • جاري تجهيز الاتصال"
        };root.addView(banner)
        web=WebView(this);root.addView(web,LinearLayout.LayoutParams(-1,0,1f))
        val layer=FrameLayout(this)
        layer.addView(root,FrameLayout.LayoutParams(-1,-1))
        setContentView(layer)
        splash=OnTrackLaunchScreen(this,layer)
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
            checkNativeUpdates()
            var polls=0
            while(isActive){
                if(polls++%5==0){
                    val state=withContext(Dispatchers.IO){
                        val sw=com.ontrack.voiceproof.capture.StartWithoutWifi
                        Pair(sw.enabled(this@MobileLinkActivity),sw.ready(this@MobileLinkActivity))
                    }
                    wifiFreeEnabledState=state.first
                    wifiFreeReadyState=state.second
                }
                sendState()
                if(pending!=null && !MobileLinkService.inCall){val next=pending!!;pending=null;activate(next)}
                delay(1000)
            }
        }
    }
    override fun onResume() {
        super.onResume()
        val result=getSharedPreferences("ontrack_link",MODE_PRIVATE)
            .getString("last_native_install","").orEmpty()
        if(result.isNotBlank() && result!=lastNativeInstallResult) {
            lastNativeInstallResult=result
            nativeStatus=result
            if(::banner.isInitialized) {
                banner.visibility=View.VISIBLE
                banner.text=result
            }
            if(::web.isInitialized)sendState()
        }
    }
    private fun show(screen:MobileOta.Screen){
        ready=false;splash?.reveal();splash?.message("جارٍ تحميل تجربة OnTrack…");val current=++generation
        screenNonce=java.util.UUID.randomUUID().toString()
        require(screen.html.contains("<head>")){"صيغة واجهة التحديث غير صالحة"}
        val content=screen.html.replaceFirst("<head>","<head><script>window.OTA_NONCE="+JSONObject.quote(screenNonce)+";</script>")
        val page=LocalUiPage(screenNonce,content)
        localPage=page
        web.loadUrl(page.url)
        lifecycleScope.launch{delay(10000);if(!ready && current==generation){
            if(screen.revision>1){show(withContext(Dispatchers.IO){ota.rollback(true)});banner.text="تعذر فتح التحديث؛ رجعنا للواجهة السابقة"}
            else banner.text="تعذر فتح الواجهة؛ استخدم إعدادات الهاتف من شاشة التشخيص"
            splash?.hide()
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
    private suspend fun checkNativeUpdates() {
        if(nativeBusy)return
        nativeBusy=true
        nativeStatus="جاري فحص نسخة Android…";sendState()
        try {
            nativeRelease=withContext(Dispatchers.IO){nativeUpdates.check()}
            nativeStatus=if(nativeRelease==null)"تطبيق Android محدّث" else
                "إصدار Android جديد: "+nativeRelease!!.versionName
        }catch(t:Throwable){
            if(t is CancellationException)throw t
            nativeStatus="تعذر فحص تحديث Android؛ جرّب مرة أخرى"
        }finally{nativeBusy=false;sendState()}
    }
    private suspend fun installNativeUpdate() {
        check(!MobileLinkService.inCall){"انتظر نهاية المكالمة قبل تثبيت Android"}
        check(!nativeBusy){"هناك تحديث قيد التنفيذ"}
        if(nativeRelease==null)checkNativeUpdates()
        val release=nativeRelease?:run{nativeStatus="أحدث إصدار مثبت بالفعل";sendState();return}
        if(nativeUpdates.installPending()){
            nativeStatus="تم تنزيل التحديث بالفعل؛ جاري انتظار تأكيد التثبيت من Android"
            sendState();return
        }
        if(!nativeUpdates.canInstallPackages()){
            nativeStatus="يلزم السماح بالتثبيت من OnTrack مرة واحدة بواسطة Android"
            nativeUpdates.openInstallPermission()
            sendState();return
        }
        nativeBusy=true
        try{
            val already=withContext(Dispatchers.IO){nativeUpdates.cached(release)}
            nativeStatus=if(already)"ملف التحديث محفوظ ومُتحقق منه؛ جارٍ فتح مثبت Android…"
                else "تنزيل تحديث Android من OnTrack…"
            sendState()
            val file=nativeUpdates.download(release){percentage->
                runOnUiThread{nativeStatus="تحميل التحديث: $percentage%";sendState()}
            }
            check(!MobileLinkService.inCall){"المكالمة جارية؛ ثبّت بعد انتهائها"}
            val accepted=withContext(Dispatchers.IO){nativeUpdates.requestInstall(file)}
            nativeStatus=if(accepted)
                "التحديث محفوظ؛ بانتظار موافقة Android على التثبيت (لا حاجة لإعادة التحميل)"
            else "اسمح بالتثبيت من OnTrack؛ ملف التحديث سيظل محفوظًا"
        }catch(t:Throwable){
            if(t is CancellationException)throw t
            nativeStatus="تعذّر تحديث Android: "+(t.message?:t.javaClass.simpleName)
        }finally{nativeBusy=false;sendState()}
    }
    private suspend fun setWifiFreeMode(enabled:Boolean) {
        check(!MobileLinkService.inCall){"لا يمكن تغيير مسار ADB أثناء مكالمة"}
        val feature=com.ontrack.voiceproof.capture.StartWithoutWifi
        if(enabled) {
            check(com.ontrack.voiceproof.capture.NetworkRevive.onWifi(this)){
                "فعّل وضع بلا Wi-Fi أول مرة أثناء اتصالك بشبكة Wi-Fi موثوقة"
            }
            feature.setEnabled(this,true)
            val ok=withContext(Dispatchers.IO){
                com.ontrack.voiceproof.capture.CaptureDaemon.revive(
                    this@MobileLinkActivity,
                    request=com.ontrack.voiceproof.capture.CaptureDaemon.Request.USER)
            }
            if(!ok || !withContext(Dispatchers.IO){feature.ready(this@MobileLinkActivity)}){
                feature.setEnabled(this,false)
                error("هذا الهاتف لم ينجح في تشغيل ADB المحلي الثابت؛ لم يُفعّل وضع بلا Wi-Fi")
            }
            banner.text="وضع الاتصال بدون Wi-Fi مفعّل تجريبيًا. ينفتح ADB على 5555 عند تشغيل التصحيح؛ أوقفه إذا لم تحتاجه."
        }else{
            feature.setEnabled(this,false)
            withContext(Dispatchers.IO){
                com.ontrack.voiceproof.capture.CaptureDaemon.revive(
                    this@MobileLinkActivity,
                    request=com.ontrack.voiceproof.capture.CaptureDaemon.Request.USER)
            }
            banner.text=if(withContext(Dispatchers.IO){feature.ready(this@MobileLinkActivity)})
                "المنفذ ما زال مفتوحًا؛ أوقف USB debugging من إعدادات المطوّر لإغلاقه"
            else "تم تعطيل مسار الاتصال بدون Wi-Fi"
        }
        banner.visibility=View.VISIBLE
        sendState()
    }
    private fun sendState(){
        if(!ready)return
        val state=JSONObject().put("status",MobileLinkService.latestStatus).put("running",MobileLinkService.running)
            .put("inCall",MobileLinkService.inCall).put("callAnswered",MobileLinkService.callAnswered).put("humanControl",MobileLinkService.humanControl)
            .put("agentEnabled",MobileLinkService.agentEnabled).put("connected",MobileLinkService.connected).put("pendingSync",MobileLinkService.pendingSync).put("serverRevision",MobileLinkService.serverRevision)
            .put("overlayGranted",Settings.canDrawOverlays(this)).put("revision",ota.revision)
            .put("nativeUpdateAvailable",nativeRelease!=null).put("nativeUpdateBusy",nativeBusy)
            .put("nativeUpdateStatus",nativeStatus)
            .put("nativeInstallPending",nativeUpdates.installPending())
            .put("nativeInstallPermission",nativeUpdates.canInstallPackages())
            .put("wifiFreeEnabled",wifiFreeEnabledState)
            .put("wifiFreeReady",wifiFreeReadyState)
            .put("campaignAutoEnabled",getSharedPreferences("ontrack_link",MODE_PRIVATE).getBoolean("campaign_opt_in",false))
            .put("contactsSupported",true)
            .put("callsSupported",true)
            .put("nativeUpdateVersion",nativeRelease?.versionName?:"").put("native",ota.nativeVersion).put("nativeCode",packageManager.getPackageInfo(packageName,0).longVersionCode).put("updating",updateBusy)
        web.evaluateJavascript("window.onTrackState && window.onTrackState($state)",null)
    }
    inner class Bridge{
        @JavascriptInterface fun postMessage(raw:String){
            if(raw.length>12000)return
            runOnUiThread {lifecycleScope.launch{runCatching{
                val msg=JSONObject(raw)
                if(msg.optString("nonce")!=screenNonce)return@launch
                if(raw.length>2048 && msg.optString("command")!="createCampaign")return@launch
                when(msg.getString("command")){
                    "ready"->{ready=true;withContext(Dispatchers.IO){ota.confirm()};banner.text="الواجهة جاهزة • OTA ${ota.revision}";sendState()
                        web.postDelayed({splash?.hide();banner.visibility=View.GONE},550)
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
                        ContextCompat.startForegroundService(this@MobileLinkActivity,Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_ENABLE))
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
                    "loadCallHistory"->loadCallHistory()
                    "listContacts"->contactsRequest(null)
                    "contactDnc"->{
                        check(msg.optBoolean("confirmed")){"تأكيد مطلوب"}
                        val id=msg.optString("id")
                        require(id.matches(Regex("[a-f0-9]{24}"))){"معرف رقم غير صالح"}
                        require(msg.has("blocked")&&msg.get("blocked") is Boolean){"حالة الرقم غير صالحة"}
                        contactsRequest(JSONObject().put("confirmed",true).put("id",id)
                            .put("blocked",msg.getBoolean("blocked")))
                    }
                    "campaignAutoEnable"->{
                        check(msg.optBoolean("confirmed")){"تأكيد المستخدم مطلوب"}
                        val key=getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("key","").orEmpty()
                        check(key.length>=24){"اربط الهاتف بالسيرفر"}
                        if(ContextCompat.checkSelfPermission(this@MobileLinkActivity,Manifest.permission.CALL_PHONE)
                            ==PackageManager.PERMISSION_GRANTED) {
                            ContextCompat.startForegroundService(this@MobileLinkActivity,
                                Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                                    .setAction(MobileLinkService.ACTION_CAMPAIGN_ENABLE))
                        } else campaignPhonePermission.launch(Manifest.permission.CALL_PHONE)
                    }
                    "campaignAutoDisable"->ContextCompat.startForegroundService(this@MobileLinkActivity,
                        Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                            .setAction(MobileLinkService.ACTION_CAMPAIGN_DISABLE))
                    "listCampaigns"->campaignRequest(null)
                    "campaignAction"->{
                        check(msg.optBoolean("confirmed")){"تأكيد العملية مطلوب"}
                        val action=msg.optString("action")
                        require(action in setOf("preview","pause","resume","cancel","arm")){"إجراء غير مدعوم"}
                        val id=msg.optString("id")
                        require(id.matches(Regex("[a-f0-9]{24}"))){"معرف الحملة غير صحيح"}
                        campaignRequest(JSONObject().put("confirmed",true).put("action",action).put("id",id))
                    }
                    "createCampaign"->{
                        check(msg.optBoolean("confirmed")){"يجب مراجعة مسودة الحملة"}
                        val name=msg.optString("name").trim()
                        val starts=msg.optString("starts").trim()
                        val ends=msg.optString("ends").trim()
                        val scenario=msg.optString("scenario").trim()
                        require(name.isNotEmpty() && name.length<=160 && starts.length==16 && ends.length==16 &&
                            scenario.isNotEmpty() && scenario.length<=8000){"بيانات الحملة غير صالحة"}
                        campaignRequest(JSONObject().put("confirmed",true).put("name",name).put("starts",starts)
                            .put("ends",ends).put("scenario",scenario))
                    }
                    "contactsPick"->{
                        val intent=Intent(Intent.ACTION_PICK).apply{
                            type=ContactsContract.CommonDataKinds.Phone.CONTENT_TYPE
                        }
                        contactPicker.launch(intent)
                    }
                    "contactsAll"->{
                        if(ContextCompat.checkSelfPermission(this@MobileLinkActivity,Manifest.permission.READ_CONTACTS)
                            ==PackageManager.PERMISSION_GRANTED)loadAllContacts()
                        else contactsPermission.launch(Manifest.permission.READ_CONTACTS)
                    }
                    "contactsFilePick"->filePicker.launch(arrayOf(
                        "text/plain","text/csv","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                        "application/octet-stream"))
                    "contactsFileUpload"->{
                        check(msg.optBoolean("confirmed") && msg.optBoolean("consent")){"تأكيد صريح مطلوب"}
                        uploadContactsFile()
                    }
                    "contactsUpload"->{
                        check(msg.optBoolean("confirmed")){"راجع الأرقام وأكّد الرفع أولاً"}
                        uploadSelectedContacts()
                    }
                    "checkUpdates"->checkUpdates()
                    "checkNativeUpdates"->checkNativeUpdates()
                    "installNativeUpdate"->installNativeUpdate()
                    "wifiFreeEnable"->setWifiFreeMode(true)
                    "wifiFreeDisable"->setWifiFreeMode(false)
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
