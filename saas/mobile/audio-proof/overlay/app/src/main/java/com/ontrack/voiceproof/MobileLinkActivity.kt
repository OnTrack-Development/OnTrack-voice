package com.ontrack.voiceproof

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.Intent
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.os.Build
import androidx.core.app.NotificationCompat
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
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.barcode.common.Barcode
import java.io.ByteArrayInputStream

/** Only verified local HTML enters this isolated WebView; no remote navigation,
 * subresources, files, iframes or arbitrary native/shell methods are exposed. */
class MobileLinkActivity:ComponentActivity(){
    private lateinit var web:WebView
    private lateinit var ota:MobileOta
    private lateinit var nativeUpdates:NativeUpdates
    private var nativeRelease:NativeUpdates.Release?=null
    private var lastNativeCheckMs=0L
    private var nativeBusy=false
    private var nativeDownloadProgress=-1
    private var uiUpdateStatus=""
    private var deferredUiCheck=false
    private var deferredNativeCheck=false
    private var nativeStatus="جاري فحص تحديث Android"
    private var openUpdatesOnReady=false
    private data class LocalBridgeSnapshot(
        val wifiFreeEnabled:Boolean,
        val wifiFreeReady:Boolean,
        val onWifi:Boolean,
        val internetRoute:String,
        val daemonReady:Boolean,
        val daemonIssue:String
    )
    private var lastNativeInstallResult=""
    @Volatile private var wifiFreeEnabledState=false
    @Volatile private var wifiFreeReadyState=false
    @Volatile private var onWifiState=true
    @Volatile private var internetRouteState=""
    @Volatile private var localDaemonReadyState=false
    @Volatile private var localDaemonIssueState=""
    private lateinit var banner:TextView
    private lateinit var tenantEnrollment:TenantDeviceEnrollment
    private var tenantStatusLines=arrayOf("SIM 1 — جاري التحقق","SIM 2 — جاري التحقق")
    // Separately measured SaaS heartbeat status. Google Live stays disconnected
    // while idle by design, and must never be called a server outage.
    private var tenantServerState="checking"
    private var tenantAgentCards=JSONArray()
    private var tenantStatusJob:Job?=null
    // A freshly paired and server-enabled SIM can start once; a saved local
    // Stop always wins and a failed start won't repeatedly prompt permissions.
    private var firstPairAutoStartAttempted=false
    /** The user's explicit Start command may continue once Phone permission is granted. */
    private var startRequestedAfterPhonePermission=false
    /** One-time code kept in memory only while Android asks for SIM permission. */
    private var pendingTenantPair:Pair<Int,String>?=null
    private var splash:OnTrackLaunchScreen?=null
    private var ready=false
    private var generation=0
    private var screenNonce=""
    @Volatile private var localPage:LocalUiPage?=null
    private var updateBusy=false
    // Keep native motion visible until BOTH the cached screen is ready and
    // the initial signed UI update check has completed or failed.
    private var initialUiUpdateCheckPending=true
    private var pending:MobileOta.Bundle?=null
    private var pendingContacts=JSONArray()
    private var pendingImportUri:Uri?=null
    private var pendingImportName=""
    private val updateNotificationPermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        banner.text=if(granted)"تم تفعيل إشعارات تحديث Android" else "إشعارات Android غير مفعّلة؛ يظهر التنبيه داخل التطبيق"
        banner.visibility=View.VISIBLE
        sendState()
    }
    private val contactsPermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        if(granted)lifecycleScope.launch{loadAllContacts()}
        else {banner.text="لم يتم منح إذن قراءة جهات الاتصال؛ لم تُرفع أي بيانات";banner.visibility=View.VISIBLE}
    }
    private val callNamesPermissions=registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()){grants->
        // The permission launcher only returns the permissions requested *now*.
        // READ_CONTACTS might already have been granted before this call.
        val allowed=ContextCompat.checkSelfPermission(this,Manifest.permission.READ_CALL_LOG)==PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(this,Manifest.permission.READ_CONTACTS)==PackageManager.PERMISSION_GRANTED
        banner.text=if(allowed)"تم تفعيل مطابقة جهات الاتصال للمكالمات الجديدة"
            else "Android لم يمنح كل الصلاحيات؛ ستبقى الأسماء غير المعروفة بدون تخمين"
        banner.visibility=View.VISIBLE
        sendState()
    }
    private val tenantSimPermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        val continueStart=startRequestedAfterPhonePermission
        startRequestedAfterPhonePermission=false
        val pairing=pendingTenantPair
        pendingTenantPair=null
        if(granted && pairing!=null){
            performTenantPair(pairing.first,pairing.second)
            return@registerForActivityResult
        }
        if(granted && continueStart){
            runCatching {
                // Starting an inbound SIM voice assistant needs only
                // READ_PHONE_STATE. Outbound manager callbacks and importing
                // contacts are optional, never asked on startup.
                startTenantMonitor()
            }.onFailure { error ->
                banner.text="تعذر تشغيل مراقب الشريحة: "+(error.message ?: "خطأ غير معروف")
                banner.visibility=View.VISIBLE
                sendState()
            }
        } else {
            banner.text=if(granted) "تم تفعيل إذن حالة الهاتف؛ الشريحة الحالية ما زالت مربوطة بحسابك"
                else "إذن حالة الهاتف غير مفعّل. فعّله من إعدادات التطبيق، ثم اضغط تشغيل الإيجنت"
            banner.visibility=View.VISIBLE
        }
        requestTenantStatusRefresh()
    }
    private val managerCallbackPermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        banner.text=if(granted)"إذن الاتصال مفعّل؛ يمكن إبلاغ المدير بعد انتهاء المكالمة"
            else "إذن الاتصال غير مفعّل؛ المكالمات الواردة تعمل لكن إبلاغ المدير آليًا سيتوقف"
        banner.visibility=View.VISIBLE
        // Do not request call history or contacts as a side-effect of a
        // callback permission result. Voice calls run without either.
        sendState()
    }
    // Campaigns require BOTH CALL_PHONE and READ_PHONE_STATE. The old one-
    // permission callback silently failed on paired Xiaomi/dual-SIM devices.
    // Keep the exact user-requested action: Pull Now must not become Enable.
    private var pendingCampaignAction=MobileLinkService.ACTION_CAMPAIGN_ENABLE
    private val campaignPermissions=registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()){grants->
        val missing=listOf(Manifest.permission.CALL_PHONE,Manifest.permission.READ_PHONE_STATE)
            .filter{ContextCompat.checkSelfPermission(this,it)!=PackageManager.PERMISSION_GRANTED}
        if(missing.isEmpty() && !MobileLinkService.inCall && tenantEnrollment.hasAny()) {
            ContextCompat.startForegroundService(this,
                Intent(this,MobileLinkService::class.java).setAction(pendingCampaignAction))
            banner.text="صلاحيات SIM مكتملة؛ تم إرسال طلب التنفيذ، والاتصال الفعلي قيد التحقق"
        } else {
            banner.text="الحملات متوقفة: "+when {
                Manifest.permission.CALL_PHONE in missing->"اسمح بإذن إجراء المكالمات (CALL_PHONE)"
                Manifest.permission.READ_PHONE_STATE in missing->"اسمح بإذن حالة الهاتف (READ_PHONE_STATE)"
                else->"تأكد من ربط الشريحة وأن الهاتف غير مشغول"
            }+" من صلاحيات التطبيق ثم حاول مجددًا"
        }
        banner.visibility=View.VISIBLE
        sendState()
    }
    private fun requestCampaignAction(action:String) {
        check(!MobileLinkService.inCall){"لا يمكن بدء حملة أثناء مكالمة"}
        check(tenantEnrollment.hasAny()){"اربط شريحة SIM بحساب SaaS أولًا"}
        val missing=listOf(Manifest.permission.CALL_PHONE,Manifest.permission.READ_PHONE_STATE)
            .filter{ContextCompat.checkSelfPermission(this,it)!=PackageManager.PERMISSION_GRANTED}
        pendingCampaignAction=action
        if(missing.isNotEmpty()){
            banner.text="لازم تفعّل الصلاحيات الناقصة لتشغيل المكالمات: "+
                missing.joinToString(" + "){it.substringAfterLast('.')}
            banner.visibility=View.VISIBLE
            campaignPermissions.launch(missing.toTypedArray())
        }else{
            ContextCompat.startForegroundService(this,
                Intent(this,MobileLinkService::class.java).setAction(action))
            banner.text="تم إرسال طلب تنفيذ الحملة لخدمة Android — لم يتم تأكيد الاتصال بعد"
            banner.visibility=View.VISIBLE
            sendState()
        }
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
    private fun selectedTenantSlot():Int {
        val first=tenantEnrollment.paired(0);val second=tenantEnrollment.paired(1)
        check(first||second){"اربط SIM بحساب SaaS من شاشة الأجهزة أولًا"}
        if(first&&!second)return 0
        if(second&&!first)return 1
        val chosen=getSharedPreferences("ontrack_tenant_workspace",MODE_PRIVATE).getInt("sim_slot",-1)
        check(chosen in 0..1 && tenantEnrollment.paired(chosen)){"اختار SIM 1 أو SIM 2 من مساحة العمل"}
        return chosen
    }
    private suspend fun tenantWorkspace(operation:String,body:JSONObject=JSONObject()):JSONObject =
        withContext(Dispatchers.IO) {tenantEnrollment.workspace(selectedTenantSlot(),operation,body)}
    private suspend fun uploadContactsFile() {
        check(!MobileLinkService.inCall){"ارفع الملف بعد انتهاء المكالمة"}
        val uri=pendingImportUri?:error("اختار ملفًا أولًا")
        val name=pendingImportName
        check(Regex("(?i)^.{1,140}\\.(csv|txt|xlsx)$").matches(name)){"نوع الملف غير مسموح"}
        val data=withContext(Dispatchers.IO){
            val stream=contentResolver.openInputStream(uri)?:error("تعذر قراءة الملف")
            val out=ByteArrayOutputStream()
            stream.use{input->
                val chunk=ByteArray(8192)
                while(true){
                    val size=input.read(chunk)
                    if(size<0)break
                    check(out.size()+size<=1048576){"الملف أكبر من 1 ميجابايت"}
                    out.write(chunk,0,size)
                }
            }
            check(out.size()>0){"الملف فارغ"}
            android.util.Base64.encodeToString(out.toByteArray(),android.util.Base64.NO_WRAP)
        }
        val response=tenantWorkspace("workspace_file_import",
            JSONObject().put("confirmed",true).put("name",name).put("data_b64",data))
        pendingImportUri=null;pendingImportName=""
        web.evaluateJavascript("window.onTrackFileImported && window.onTrackFileImported("+response.toString()+")",null)
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
        val result=tenantWorkspace("workspace_calls")
        web.evaluateJavascript("window.onTrackCalls && window.onTrackCalls("+result.toString()+")",null)
    }
    private suspend fun loadCallTranscript(callId:String) {
        require(callId.matches(Regex("[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}"))) {
            "معرّف المكالمة غير صالح"
        }
        val result=tenantWorkspace("workspace_call_transcript",JSONObject().put("call_id",callId))
        web.evaluateJavascript("window.onTrackCallTranscript && window.onTrackCallTranscript("+result.toString()+")",null)
    }
    private suspend fun contactsRequest(payload:JSONObject?) {
        check(!MobileLinkService.inCall){"إدارة الأرقام بعد انتهاء المكالمة"}
        val operation=if(payload==null)"workspace_contacts" else "workspace_contact_dnc"
        val result=tenantWorkspace(operation,payload?:JSONObject())
        if(payload!=null)contactsRequest(null)
        else web.evaluateJavascript("window.onTrackContactsList && window.onTrackContactsList("+result.toString()+")",null)
    }
    private fun darkAlert():AlertDialog.Builder = AlertDialog.Builder(this, android.R.style.Theme_DeviceDefault_Dialog_Alert)

    private suspend fun campaignRequest(payload:JSONObject?) {
        check(!MobileLinkService.inCall){"إدارة الحملات بعد انتهاء المكالمة"}
        val operation=when {
            payload==null->"workspace_campaigns"
            payload.has("action")->"workspace_campaign_action"
            else->"workspace_campaign_create"
        }
        val result=tenantWorkspace(operation,payload?:JSONObject())
        result.optJSONObject("queue")?.let{ MobileLinkService.updateCampaignQueue(it) }
        web.evaluateJavascript("window.onTrackCampaigns && window.onTrackCampaigns("+result.toString()+")",null)
        sendState()
    }
    private suspend fun uploadSelectedContacts() {
        check(!MobileLinkService.inCall){"ارفع الأرقام بعد انتهاء المكالمة"}
        check(pendingContacts.length()>0){"اختر أرقامًا أولًا"}
        val result=tenantWorkspace("workspace_import",
            JSONObject().put("confirmed",true).put("contacts",pendingContacts))
        pendingContacts=JSONArray()
        val counts=result.optJSONObject("counts")?:JSONObject()
        val reply=JSONObject().put("added",counts.optInt("added"))
            .put("duplicates",counts.optInt("duplicates"))
            .put("invalid",counts.optInt("invalid"))
        web.evaluateJavascript("window.onTrackContactsImported && window.onTrackContactsImported("+reply.toString()+")",null)
    }
    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState:Bundle?){
        super.onCreate(savedInstanceState)
        openUpdatesOnReady=intent?.getBooleanExtra("open_updates",false)==true
        getSystemService(android.app.NotificationManager::class.java)
            .createNotificationChannel(android.app.NotificationChannel(
                "ontrack_voice_updates","تحديثات IO Voice",
                android.app.NotificationManager.IMPORTANCE_DEFAULT))
        // Never erase an existing device link during an in-place signed OTA.
        // Legacy Pilot cannot launch in tenant mode, but its credential remains
        // intact for explicit, audited migration/rollback. The encrypted per-SIM
        // SaaS binding, theme, existing OTA and contacts are never touched here.
        // No silent re-pairing and no credential is exposed to the WebView.

        ota=MobileOta(this);nativeUpdates=NativeUpdates(this)
        val linkPrefs=getSharedPreferences("ontrack_link",MODE_PRIVATE)
        if(!linkPrefs.getBoolean("ui_theme_v65_dark_init",false)){
            linkPrefs.edit().putString("ui_theme","dark").putBoolean("ui_theme_v65_dark_init",true).apply()
        }
        window.statusBarColor=Color.rgb(9,9,11);window.navigationBarColor=Color.rgb(9,9,11)
        val root=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setBackgroundColor(Color.rgb(9,9,11))}
        banner=TextView(this).apply{
            setPadding(20,14,20,12);setTextColor(Color.rgb(161,161,170))
            setBackgroundColor(Color.rgb(18,18,22))
            textSize=12f;text="IO Voice • جاري تجهيز الاتصال"
        };root.addView(banner)
        web=WebView(this).apply{setBackgroundColor(Color.rgb(9,9,11))};root.addView(web,LinearLayout.LayoutParams(-1,0,1f))
        val layer=FrameLayout(this)
        layer.addView(root,FrameLayout.LayoutParams(-1,-1))
        tenantEnrollment=TenantDeviceEnrollment(this)
        // Restore a previously enabled SIM monitor after activity/process restart.
        // Never activate a SIM the user explicitly disabled.
        val restore=TenantEngineRestorePolicy.shouldRestore(
            tenantEnrollment.hasAny(),
            getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE).getBoolean("enabled",false),
            MobileLinkService.tenantPairingInProgress)
        if(restore){
            runCatching {
                ContextCompat.startForegroundService(this,
                    Intent(this,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_RESTORE))
            }.onFailure {
                banner.text="تعذر استعادة تشغيل الشرائح: "+(it.message?:"Android منع تشغيل الخدمة")
                banner.visibility=View.VISIBLE
            }
        }
        // All tenant controls now live inside the signed OnTrack WebView shell.
        // No unstyled native Android buttons outside the main vertical scroll.
        setContentView(layer)
        splash=OnTrackLaunchScreen(this,layer)
        web.settings.apply{
            javaScriptEnabled=true;domStorageEnabled=false;allowFileAccess=false;allowContentAccess=false
            blockNetworkLoads=true;cacheMode=WebSettings.LOAD_NO_CACHE;mixedContentMode=WebSettings.MIXED_CONTENT_NEVER_ALLOW
            javaScriptCanOpenWindowsAutomatically=false;setSupportMultipleWindows(false)
        }
        web.webViewClient=object:WebViewClient(){
            override fun shouldOverrideUrlLoading(view:WebView,request:WebResourceRequest):Boolean {
                val url=request.url.toString()
                if(url.startsWith("https://app.ontrackegy.com/") &&
                    (url.contains("/mobile/updates/native/") || url.endsWith(".apk"))){
                    openExternalApkBrowser(url)
                    return true
                }
                return localPage?.allows(url,request.isForMainFrame,request.method)!=true
            }
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
        web.setDownloadListener { url, _, _, _, _ ->
            if(url.startsWith("https://app.ontrackegy.com/")){
                openExternalApkBrowser(url)
            }
        }
        // Explicit Android confirmation for consent, campaign arm and SIM charges.
        // Only the signed local page may display a script-driven dialog.
        web.webChromeClient=object:WebChromeClient(){
            override fun onJsConfirm(view:WebView?,url:String?,message:String?,result:JsResult?):Boolean {
                if(url?.startsWith("https://ontrack-ui.invalid/ui/")!=true){
                    result?.cancel();return true
                }
                darkAlert()
                    .setTitle("تأكيد IO Voice")
                    .setMessage(message.orEmpty().take(300))
                    .setPositiveButton("تأكيد"){_,_->result?.confirm()}
                    .setNegativeButton("إلغاء"){_,_->result?.cancel()}
                    .setOnCancelListener{result?.cancel()}
                    .show()
                return true
            }
            override fun onJsAlert(view:WebView?,url:String?,message:String?,result:JsResult?):Boolean {
                if(url?.startsWith("https://ontrack-ui.invalid/ui/")!=true){
                    result?.cancel();return true
                }
                darkAlert()
                    .setTitle("IO Voice")
                    .setMessage(message.orEmpty().take(300))
                    .setPositiveButton("حسنًا"){_,_->result?.confirm()}
                    .setOnCancelListener{result?.cancel()}
                    .show()
                return true
            }
        }
        web.addJavascriptInterface(Bridge(),"OnTrack")
        lifecycleScope.launch{
            show(withContext(Dispatchers.IO){ota.screen(recover=true)})
            splash?.checking()
            try {
                checkUpdates()
            } finally {
                initialUiUpdateCheckPending=false
                dismissLaunchIfReady()
            }
            checkNativeUpdates()
            var polls=0
            var wasCalling=false
            while(isActive){
                val calling=MobileLinkService.inCall
                if(!calling && deferredUiCheck){deferredUiCheck=false;checkUpdates()}
                if(!calling && deferredNativeCheck){deferredNativeCheck=false;checkNativeUpdates()}
                if(!calling && polls++%5==0){
                    val snap=withContext(Dispatchers.IO){
                        val sw=com.ontrack.voiceproof.capture.StartWithoutWifi
                        val wifi=com.ontrack.voiceproof.capture.NetworkRevive.onWifi(this@MobileLinkActivity)
                        val route=CellularRadioStatus.safeInternetRoute(this@MobileLinkActivity)
                        val wEnabled=sw.enabled(this@MobileLinkActivity)
                        val wReady=runCatching{sw.ready(this@MobileLinkActivity)}.getOrDefault(false)
                        val dReady=if(MobileLinkService.running) MobileLinkService.daemonReady
                            else runCatching{com.ontrack.voiceproof.capture.CaptureDaemon.isRunning(this@MobileLinkActivity)}.getOrDefault(false)
                        val dIssue=if(MobileLinkService.running) MobileLinkService.daemonIssue
                            else if(dReady) "" else {
                                val setupOk=runCatching{com.ontrack.voiceproof.ui.Setup.isComplete(this@MobileLinkActivity)}.getOrDefault(false)
                                CellularDaemonDiagnostics.diagnose(false,setupOk,wifi,wReady,false)
                            }
                        LocalBridgeSnapshot(wEnabled,wReady,wifi,route,dReady,dIssue)
                    }
                    wifiFreeEnabledState=snap.wifiFreeEnabled
                    wifiFreeReadyState=snap.wifiFreeReady
                    onWifiState=snap.onWifi
                    internetRouteState=snap.internetRoute
                    localDaemonReadyState=snap.daemonReady
                    localDaemonIssueState=snap.daemonIssue
                }
                // Keep one transition update; suspend periodic UI work during audio.
                if(!calling || wasCalling!=calling)sendState()
                wasCalling=calling
                if(!calling && polls%5==1)requestTenantStatusRefresh()
                // Foreground-only periodic release check; never downloads without consent.
                if(!calling && polls>0 && polls%900==0 && hasWindowFocus())checkNativeUpdates()
                if(pending!=null && !calling){val next=pending!!;pending=null;activate(next)}
                delay(if(calling)3000L else 1000L)
            }
        }
    }
    override fun onNewIntent(intent:Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        if(intent.getBooleanExtra("open_updates",false)){
            openUpdatesOnReady=true
            if(ready)openUpdatesInWeb()
        }
    }
    /** The homepage has a single clear six-digit pairing action. */
    private fun showQuickTenantPair() {
        if(MobileLinkService.inCall) {
            banner.text="استنى المكالمة تخلص قبل ربط الشريحة"
            banner.visibility=View.VISIBLE
            return
        }
        val slots=(0..1).filter { !tenantEnrollment.paired(it) }
        when(slots.size) {
            0 -> darkAlert().setTitle("الشرائح مربوطة بالفعل")
                .setMessage("تقدر تفحص الربط أو تفصل شريحة من إدارة الشرائح.")
                .setPositiveButton("إدارة الشرائح"){_,_->showTenantMenu()}
                .setNegativeButton("إغلاق",null).show()
            1 -> showTenantPairDialog(slots.first(),"")
            else -> darkAlert().setTitle("اختار الشريحة")
                .setItems(arrayOf("SIM 1","SIM 2")) { _,which ->
                    showTenantPairDialog(which,"")
                }.setNegativeButton("إلغاء",null).show()
        }
    }
    /** Authenticated, scoped SIM heartbeats. No device bearer reaches WebView. */
    private fun requestTenantStatusRefresh() {
        if(tenantStatusJob?.isActive==true)return
        tenantStatusJob=lifecycleScope.launch {
            val report=withContext(Dispatchers.IO) {
                val cards=JSONArray()
                var checked=0
                var ok=0
                var hardwareFailures=0
                var readyCampaignTotal=0
                var firstActiveCampaign=""
                var latestQueue:JSONObject?=null
                var dialRequestedEpoch=0L
                val lines=(0..1).map { slot ->
                    val paired=tenantEnrollment.paired(slot)
                    val card=JSONObject().put("sim_slot",slot).put("paired",paired)
                    val hardwareIssue=if(paired)TenantSimHardware.problem(this@MobileLinkActivity,slot) else null
                    val label=if(!paired){
                        card.put("state","unlinked")
                        TenantPairingContract.statusLine(slot,false)
                    }else if(hardwareIssue!=null){
                        hardwareFailures++
                        card.put("state","hardware").put("error",hardwareIssue.take(120))
                        TenantPairingContract.statusLine(slot,true,error=hardwareIssue)
                    }else try {
                        checked++
                        val profile=tenantEnrollment.heartbeat(slot)
                        ok++
                        // Only published instructions are returned by the SaaS profile;
                        // never leak full flow, device token or private identifiers.
                        val purpose=profile.optString("instruction","")
                            .replace(Regex("\\s+")," ").trim().take(170)
                        card.put("state","online")
                            .put("agent_id",profile.optInt("agent_id",-1))
                            .put("site_id",profile.optInt("site_id",-1))
                            .put("agent_name",profile.optString("agent_name","").take(140))
                            .put("voice_label",when(val vc=profile.optString("voice","")) {
                                "io.premium" -> "IO Premium"
                                else -> profile.optString("voice_label","").trim().ifBlank {
                                    if(vc.startsWith("fish.")) "Fish Audio" else vc.substringAfterLast('.')
                                }
                            })
                            .put("site_name",profile.optString("site_name","").take(140))
                            .put("account_name",profile.optString("account_name","").take(140))
                            .put("purpose",purpose)
                            .put("flow_ready",profile.optBoolean("flow_ready",false))
                            .put("flow_revision",profile.optInt("flow_revision",0))
                            // Surface the actual published auto-answer policy. A
                            // green monitor switch is not an auto-answer grant.
                            .put("auto_answer_enabled",
                                profile.optJSONObject("answer_policy")?.optBoolean("auto_answer",false)?:false)
                            .put("answer_delay_seconds",
                                profile.optJSONObject("answer_policy")?.optInt("answer_delay_seconds",5)?:5)
                            .put("ivr_enabled",
                                profile.optJSONObject("answer_policy")?.optBoolean("ivr_enabled",false)?:false)
                            .put("ivr_routes_count",
                                profile.optJSONObject("answer_policy")?.optJSONArray("ivr_routes")?.length()?:0)
                            .put("manager_callback_enabled",
                                profile.optJSONObject("answer_policy")?.optBoolean("manager_callback_enabled",false)?:false)
                            .put("manager_callback_phone",
                                profile.optJSONObject("answer_policy")?.optString("manager_callback_phone","")?:"")
                        val csum=profile.optJSONObject("campaigns_summary")
                        if(csum!=null){
                            readyCampaignTotal+=csum.optInt("ready_count",0)
                            val cname=csum.optString("active_name","").trim()
                            if(firstActiveCampaign.isBlank() && cname.isNotBlank())firstActiveCampaign=cname
                        }
                        val q=profile.optJSONObject("campaign_queue_status")
                        if(q!=null && (latestQueue==null || q.optJSONObject("next_contact")!=null)){
                            latestQueue=q
                            dialRequestedEpoch=maxOf(dialRequestedEpoch,q.optLong("dial_requested",0L))
                        }
                        TenantPairingContract.statusLine(slot,true,
                            profile.optString("account_name"),profile.optString("site_name"),
                            profile.optString("agent_name")) +
                            if(profile.optBoolean("flow_ready",false))
                                " • Flow v"+profile.optInt("flow_revision") else " • Flow غير منشور"
                    }catch(e:SecurityException){
                        card.put("state","revoked").put("error","اعتماد الشريحة ملغي أو منتهي")
                        TenantPairingContract.statusLine(slot,true,error="تم إلغاء الاعتماد من السيرفر")
                    }catch(e:CancellationException){throw e}
                    catch(e:IllegalStateException){
                        val msg=e.message?.take(120) ?: "تعذر التحقق من الاشتراك"
                        card.put("state","error").put("error",msg)
                        TenantPairingContract.statusLine(slot,true,error=msg)
                    }catch(e:Exception){
                        card.put("state","unavailable").put("error",
                            (e.message?:"تعذر الاتصال").replace(Regex("[\\r\\n\\t]")," ").take(120))
                        TenantPairingContract.statusLine(slot,true,error="تعذر التحقق من السيرفر؛ راجع اتصال الشريحة")
                    }
                    cards.put(card)
                    label
                }
                val health=when{
                    checked==0 && hardwareFailures==0 -> "unlinked"
                    checked==0 -> "device"
                    ok==checked && hardwareFailures==0 -> "online"
                    ok>0 -> "partial"
                    else -> "unreachable"
                }
                if(latestQueue!=null){
                    MobileLinkService.updateCampaignQueue(latestQueue)
                }else{
                    MobileLinkService.updateCampaignSummary(readyCampaignTotal,firstActiveCampaign)
                }
                Triple(Triple(lines,cards,health),dialRequestedEpoch,Unit)
            }
            tenantStatusLines=report.first.first.toTypedArray()
            tenantAgentCards=report.first.second
            tenantServerState=report.first.third
            val dialSignal=report.second
            if(dialSignal>0L && dialSignal>MobileLinkService.lastDialRequestEpoch &&
                !MobileLinkService.inCall &&
                ContextCompat.checkSelfPermission(this@MobileLinkActivity,Manifest.permission.CALL_PHONE)==PackageManager.PERMISSION_GRANTED){
                MobileLinkService.lastDialRequestEpoch=dialSignal
                ContextCompat.startForegroundService(this@MobileLinkActivity,
                    Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                        .setAction(MobileLinkService.ACTION_CAMPAIGN_PULL_NOW))
            }
            // The published SOG policy may have auto_answer=true even when
            // Android's local tenant monitor was never started after pairing.
            // Start once only for a freshly paired SIM with no prior local
            // Start/Stop selection. Never override an explicit user Stop.
            val settings=getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE)
            val publishedAutoReady=(0 until tenantAgentCards.length()).any{index->
                val card=tenantAgentCards.optJSONObject(index)
                card?.optString("state")=="online" &&
                    card.optBoolean("flow_ready",false) &&
                    card.optBoolean("auto_answer_enabled",false)
            }
            val phoneGranted=ContextCompat.checkSelfPermission(
                this@MobileLinkActivity,Manifest.permission.READ_PHONE_STATE
            )==PackageManager.PERMISSION_GRANTED
            if(!firstPairAutoStartAttempted &&
                TenantEngineRestorePolicy.firstPairPublishedAutoStart(
                    tenantEnrollment.hasAny(),settings.contains("enabled"),
                    publishedAutoReady,MobileLinkService.tenantPairingInProgress,
                    phoneGranted,MobileLinkService.inCall)){
                firstPairAutoStartAttempted=true
                runCatching{startTenantMonitor()}.onFailure{problem->
                    banner.text="المساعد منشور للرد الآلي لكن تشغيل الهاتف تعذّر: "+
                        problem.message.orEmpty().take(110)
                    banner.visibility=View.VISIBLE
                }
            }
            sendState()
        }
    }
    private fun openConsole(section:String="",siteId:Int=0,agentId:Int=0){
        val tab=when(section){
            "agents","flows","answering"->"agents"
            "devices"->"devices"
            else->"overview"
        }
        val builder=Uri.parse("https://app.ontrackegy.com/").buildUpon()
            .appendQueryParameter("tab",tab)
        if(tab=="agents" && siteId>0 && agentId>0){
            builder.appendQueryParameter("site_id",siteId.toString())
                .appendQueryParameter("agent_id",agentId.toString())
        }
        runCatching {
            startActivity(Intent(Intent.ACTION_VIEW,builder.build()).apply{
                addCategory(Intent.CATEGORY_BROWSABLE)
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            })
        }.onFailure {
            banner.text="تعذر فتح المتصفح: "+(it.message?:it.javaClass.simpleName)
            banner.visibility=View.VISIBLE
        }
    }
    private fun showScenarioMenu(){
        darkAlert().setTitle("سيناريوهات الإيجنت")
            .setItems(arrayOf("سيناريو SIM 1","سيناريو SIM 2","تعديل السيناريو من الموقع")){_,choice->
                if(choice==2)openConsole("agents") else showTenantPublishedFlow(choice)
            }.setNegativeButton("إغلاق",null).show()
    }
    private fun showTenantMenu() {
        val items=arrayOf(
            "ربط SIM 1"+if(tenantEnrollment.paired(0))" (مربوطة)" else "",
            "ربط SIM 2"+if(tenantEnrollment.paired(1))" (مربوطة)" else "",
            "التحقق من ربط SIM 1","التحقق من ربط SIM 2",
            "فصل SIM 1 من السيرفر","فصل SIM 2 من السيرفر",
            "عرض Flow المنشور لـ SIM 1","عرض Flow المنشور لـ SIM 2")
        darkAlert().setTitle("إدارة شرائح الشركات")
            .setMessage("كل شريحة لها حساب وإيجنت مستقلان. هذا الربط لا يشغّل مكالمات AI تلقائيًا.")
            .setItems(items) { _,which ->
                val slot=which%2
                when(which) {
                    0,1 -> showTenantPairDialog(slot,"")
                    2,3 -> runTenantOperation(slot,false)
                    6,7 -> showTenantPublishedFlow(slot)
                    4,5 -> darkAlert().setTitle("تأكيد فصل SIM ${slot+1}")
                        .setMessage("سيُلغى اعتماد هذه الشريحة على السيرفر، ولن يتأثر حساب الشريحة الأخرى.")
                        .setPositiveButton("فصل") { _,_ -> runTenantOperation(slot,true) }
                        .setNegativeButton("إلغاء",null).show()
                }
            }.setNegativeButton("إغلاق",null).show()
    }

    /** On-device graph preview. No remote browser and no raw JSON/text dump. */
    private fun showTenantPublishedFlow(slot:Int) {
        lifecycleScope.launch {
            try {
                check(!MobileLinkService.inCall) { "افتح المخطط بعد انتهاء المكالمة" }
                val flow=withContext(Dispatchers.IO) { tenantEnrollment.publishedFlow(slot) }
                val graph=flow.getJSONObject("flow")
                val nodes=graph.getJSONArray("nodes")
                val edges=graph.getJSONArray("edges")
                val points=LinkedHashMap<String,Pair<Int,Int>>()
                val labelMap=mapOf("start" to "بداية", "say" to "رسالة", "ask" to "سؤال",
                    "listen" to "استماع", "condition" to "شرط", "instruction" to "تعليمات",
                    "knowledge_lookup" to "بحث في المعرفة", "handoff" to "تحويل لموظف",
                    "schedule" to "موعد", "end" to "نهاية")
                val entries=ArrayList<org.json.JSONObject>()
                for(i in 0 until minOf(nodes.length(),90)){
                    val n=nodes.optJSONObject(i)?:continue
                    val id=n.optString("id").take(80)
                    if(id.isBlank()||points.containsKey(id))continue
                    entries.add(n)
                    points[id]=Pair(n.optInt("x",1000+(i%3)*250),n.optInt("y",100+i*175))
                }
                val minX=points.values.minOfOrNull{it.first}?:0
                val minY=points.values.minOfOrNull{it.second}?:0
                val translated=points.mapValues { (_,v) ->
                    Pair(34+((v.first-minX).coerceIn(0,10000)*0.82).toInt(),
                         35+((v.second-minY).coerceIn(0,10000)*0.82).toInt())
                }
                val canvasWidth=maxOf(390,(translated.values.maxOfOrNull{it.first}?:0)+250)
                val canvasHeight=maxOf(470,(translated.values.maxOfOrNull{it.second}?:0)+175)
                val escape:(String)->String={android.text.TextUtils.htmlEncode(it)}
                val html=StringBuilder()
                html.append("<!doctype html><html lang='ar' dir='rtl'><meta charset='utf-8'>")
                    .append("<meta name='viewport' content='width=device-width,initial-scale=1'>")
                    .append("<style>*{box-sizing:border-box}body{margin:0;background:#101014;color:#fff;font:14px system-ui}")
                    .append("header{padding:17px}h2{margin:0 0 5px;font-size:18px}header small{color:#ababbb}")
                    .append(".scroller{overflow:auto;height:70vh;direction:ltr}.scene{position:relative;background:radial-gradient(circle,#363642 1px,transparent 1px) 0 0/19px 19px}")
                    .append("svg{position:absolute;inset:0;pointer-events:none}svg path.edge{stroke:#ed2140;fill:none;stroke-width:2.7;marker-end:url(#tip)}")
                    .append(".node{position:absolute;direction:rtl;width:205px;min-height:112px;max-height:140px;padding:14px;border-radius:17px;border:1px solid #484853;border-top:3px solid #ef2345;background:#1d1d25;box-shadow:0 9px 25px #0007;overflow:hidden}")
                    .append(".node b{display:block;font-size:13px;margin-bottom:7px}.node span{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;line-height:1.6;color:#d2d2d9;font-size:12px;white-space:pre-wrap}</style>")
                    .append("<header><h2>مخطط المحادثة</h2><small>اسحب المخطط لاستعراض الخطوات والوصلات</small></header>")
                    .append("<div class='scroller'><div class='scene' style='width:").append(canvasWidth)
                    .append("px;height:").append(canvasHeight).append("px'><svg width='").append(canvasWidth)
                    .append("' height='").append(canvasHeight).append("' viewBox='0 0 ")
                    .append(canvasWidth).append(' ').append(canvasHeight)
                    .append("'><defs><marker id='tip' markerWidth='8' markerHeight='8' refX='7' refY='4' orient='auto'><path d='M0 0 L8 4 L0 8 z' fill='#ed2140'/></marker></defs>")
                for(i in 0 until minOf(edges.length(),250)){
                    val e=edges.optJSONObject(i)?:continue
                    val from=translated[e.optString("from")]?:continue
                    val to=translated[e.optString("to")]?:continue
                    val sx=from.first+103;val sy=from.second+113
                    val tx=to.first+103;val ty=to.second
                    val mid=(sy+ty)/2
                    html.append("<path class='edge' d='M ").append(sx).append(' ').append(sy)
                        .append(" C ").append(sx).append(' ').append(mid).append(' ')
                        .append(tx).append(' ').append(mid).append(' ').append(tx)
                        .append(' ').append(ty).append("'/>")
                }
                html.append("</svg>")
                for(n in entries){
                    val xy=translated[n.optString("id")]?:continue
                    val title=labelMap[n.optString("type")]?:"خطوة"
                    html.append("<div class='node' style='left:").append(xy.first)
                        .append("px;top:").append(xy.second).append("px'><b>")
                        .append(escape(title)).append("</b><span>")
                        .append(escape(n.optString("text").take(230).ifBlank{"خطوة في المحادثة"}))
                        .append("</span></div>")
                }
                html.append("</div></div></html>")
                val preview=WebView(this@MobileLinkActivity).apply {
                    settings.javaScriptEnabled=false
                    settings.allowFileAccess=false
                    settings.allowContentAccess=false
                    settings.blockNetworkLoads=true
                    settings.builtInZoomControls=true
                    settings.displayZoomControls=false
                    loadDataWithBaseURL(null,html.toString(),"text/html","UTF-8",null)
                }
                val holder=LinearLayout(this@MobileLinkActivity).apply {
                    orientation=LinearLayout.VERTICAL
                    addView(preview,LinearLayout.LayoutParams(-1,(resources.displayMetrics.heightPixels*0.76).toInt()))
                }
                darkAlert()
                    .setTitle("الشريحة "+(slot+1)+" · المسار المنشور")
                    .setView(holder)
                    .setPositiveButton("تعديل المخطط"){_,_->
                        openConsole("agents",flow.optInt("site_id"),flow.optInt("agent_id"))}
                    .setNegativeButton("إغلاق",null)
                    .setOnDismissListener{preview.destroy()}
                    .show()
            }catch(t:Throwable) {
                if(t is CancellationException)throw t
                banner.text="تعذر عرض مخطط الشريحة "+(slot+1)+": "+(t.message?:"حاول مجددًا")
                banner.visibility=View.VISIBLE
            }
        }
    }
    /** Continue only after a user-initiated Start; never re-enable a stopped agent on its own. */
    private fun startTenantMonitor(){
        check(tenantEnrollment.hasAny()){ "لا توجد شريحة مربوطة بحساب SaaS على هذا الهاتف" }
        check(!MobileLinkService.tenantPairingInProgress){ "اقتران الشريحة قيد التنفيذ؛ أعد المحاولة بعد اكتماله" }
        check(ContextCompat.checkSelfPermission(this,Manifest.permission.READ_PHONE_STATE)
            ==PackageManager.PERMISSION_GRANTED) { "إذن حالة الهاتف غير مفعّل" }
        ContextCompat.startForegroundService(this,
            Intent(this,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_ENABLE))
        banner.text="تم إرسال طلب تشغيل SIM لخدمة الهاتف — جاري تأكيد الحالة"
        banner.visibility=View.VISIBLE
        web.postDelayed({sendState()},700)
    }
    private fun requestCallNamesPermissions() {
        val missing=listOf(Manifest.permission.READ_CALL_LOG,Manifest.permission.READ_CONTACTS)
            .filter{ContextCompat.checkSelfPermission(this,it)!=PackageManager.PERMISSION_GRANTED}
        if(missing.isNotEmpty()){
            banner.text="سماح سجل المكالمات وجهات الاتصال مطلوب لظهور رقم المتصل واسمه؛ من غيره مش هنتخمن بيانات."
            banner.visibility=View.VISIBLE
            callNamesPermissions.launch(missing.toTypedArray())
        }
    }
    private fun requestManagerCallbackPhonePermission(){
        if(ContextCompat.checkSelfPermission(this,Manifest.permission.CALL_PHONE)
            !=PackageManager.PERMISSION_GRANTED)
            managerCallbackPermission.launch(Manifest.permission.CALL_PHONE)
        else requestCallNamesPermissions()
    }
    private fun requestStartTenantMonitor(){
        check(tenantEnrollment.hasAny()){ "لا توجد شريحة مربوطة بحساب SaaS على هذا الهاتف" }
        check(!MobileLinkService.tenantPairingInProgress){ "اقتران الشريحة قيد التنفيذ؛ أعد المحاولة بعد اكتماله" }
        if(ContextCompat.checkSelfPermission(this,Manifest.permission.READ_PHONE_STATE)
            !=PackageManager.PERMISSION_GRANTED) {
            startRequestedAfterPhonePermission=true
            banner.text="يلزم السماح بإذن حالة الهاتف لتشغيل مراقب SIM؛ لن تحتاج مفتاح API"
            banner.visibility=View.VISIBLE
            tenantSimPermission.launch(Manifest.permission.READ_PHONE_STATE)
            return
        }
        startTenantMonitor()
    }
    private fun testTenantReadiness(){
        lifecycleScope.launch {
            banner.text="بنفحص ربط الشرائح والـFlow وجلسة Google Live من غير إجراء مكالمة..."
            banner.visibility=View.VISIBLE
            val results=withContext(Dispatchers.IO){
                (0..1).filter{tenantEnrollment.paired(it)}.map{slot->
                    try{tenantEnrollment.testReadiness(slot)}
                    catch(t:Exception){"SIM ${slot+1}: "+(t.message?:"فشل اختبار الجاهزية")}
                }
            }
            val message=if(results.isEmpty())
                "مفيش SIM مربوطة. افتح حسابك، خد كود 6 أرقام واكتبه هنا."
                else results.joinToString("\n")
            darkAlert()
                .setTitle("اختبار ربط الشريحة")
                .setMessage(message+"\n\nالاختبار يثبت الربط وجاهزية الجلسة فقط؛ مش مكالمة صوتية فعلية.")
                .setPositiveButton("فتح إعدادات الإيجنت"){_,_->openConsole("agents")}
                .setNegativeButton("إغلاق",null).show()
            banner.text=message.take(140)
            sendState()
        }
    }
    private fun showTenantPairDialog(slot:Int,initialCode:String) {
        if(tenantEnrollment.paired(slot)) {
            banner.text="SIM ${slot+1} مربوطة بالفعل. لو هتربط شريحة تانية، اختار رقمها."
            banner.visibility=View.VISIBLE
            return
        }
        val input=EditText(this).apply {
            hint="كود 6 أرقام"
            setText(initialCode)
            setTextColor(Color.rgb(244,244,245))
            setHintTextColor(Color.rgb(161,161,170))
            inputType=android.text.InputType.TYPE_CLASS_NUMBER
            filters=arrayOf(android.text.InputFilter.LengthFilter(6))
        }
        darkAlert().setTitle("ربط SIM ${slot+1} بحسابك")
            .setMessage("اكتب الكود المكوّن من 6 أرقام من حسابك واضغط ربط. مش محتاج API Key.")
            .setView(input)
            .setPositiveButton("تأكيد الربط") { _,_ -> performTenantPair(slot,input.text.toString()) }
            .setNegativeButton("إلغاء",null).show()
    }
    private fun performTenantPair(slot:Int,rawCode:String) {
        lifecycleScope.launch {
            try {
                check(!MobileLinkService.inCall) { "انتظر انتهاء المكالمة أولًا" }
                // Validate first. Permission prompt must not silently eat the
                // one-time code or consume it on the server.
                val code=TenantPairingContract.codeForSlot(rawCode,slot)
                if(ContextCompat.checkSelfPermission(this@MobileLinkActivity,
                        Manifest.permission.READ_PHONE_STATE)!=PackageManager.PERMISSION_GRANTED) {
                    pendingTenantPair=slot to code
                    banner.text="اسمح للتطبيق بقراءة حالة الشريحة لإكمال الربط تلقائيًا"
                    banner.visibility=View.VISIBLE
                    tenantSimPermission.launch(Manifest.permission.READ_PHONE_STATE)
                    return@launch
                }
                check(!MobileLinkService.tenantPairingInProgress) { "الربط جارٍ بالفعل" }
                MobileLinkService.tenantPairingInProgress=true
                try {
                    stopService(Intent(this@MobileLinkActivity,MobileLinkService::class.java))
                    check(getSharedPreferences("ontrack_link",MODE_PRIVATE).edit()
                        .putBoolean("campaign_opt_in",false).commit()) {
                        "تعذر إيقاف حملات هاتف التجربة"
                    }
                    val profile=withContext(Dispatchers.IO) {
                        tenantEnrollment.enroll(code,slot,android.os.Build.MODEL.ifBlank { "Android" })
                    }
                    showTenantProfile(slot,profile)
                    requestTenantStatusRefresh()
                } finally {
                    MobileLinkService.tenantPairingInProgress=false
                }
            } catch(t:Throwable) {
                if(t is CancellationException)throw t
                banner.text="تعذر ربط SIM ${slot+1}: "+(t.message?:"خطأ غير معروف")
                banner.visibility=View.VISIBLE
            }
        }
    }
    private fun showTenantProfile(slot:Int,profile:JSONObject) {
        if(profile.optBoolean("verification_pending")) {
            darkAlert().setTitle("SIM ${slot+1} — تم حفظ الربط")
                .setMessage("السيرفر أصدر اعتماد الشريحة وتم حفظه مشفرًا على الهاتف، لكن التحقق اللاحق لم يكتمل. لا تعيد استخدام الكود؛ اضغط التحقق من ربط الشريحة بعد استعادة الاتصال. المكالمات تظل متوقفة.")
                .setPositiveButton("حسنًا",null).show()
            return
        }
        val account=profile.optString("account_name","غير معروف")
        val site=profile.optString("site_name","غير معروف")
        val agent=profile.optString("agent_name","غير معروف")
        darkAlert().setTitle("SIM ${slot+1} — تم التحقق من الربط")
            .setMessage("الشركة: $account\nالموقع: $site\nالإيجنت: $agent\nتم تأكيد ربط الحساب فقط. تشغيل صوت الشريحة يتطلب اختبار الجهاز.")
            .setPositiveButton("حسنًا",null).show()
    }
    private fun runTenantOperation(slot:Int,disconnect:Boolean) {
        lifecycleScope.launch {
            try {
                check(!MobileLinkService.inCall) { "انتظر انتهاء المكالمة" }
                val profile=withContext(Dispatchers.IO) {
                    if(disconnect) { tenantEnrollment.disconnect(slot);null }
                    else tenantEnrollment.heartbeat(slot)
                }
                if(profile!=null)showTenantProfile(slot,profile)
                else {
                    banner.text="تم فصل SIM ${slot+1} من السيرفر ومسح اعتمادها من الهاتف"
                    banner.visibility=View.VISIBLE
                }
                requestTenantStatusRefresh()
            } catch(t:Throwable) {
                if(t is CancellationException)throw t
                banner.text="SIM ${slot+1}: "+(t.message?:"تعذر الاتصال")
                banner.visibility=View.VISIBLE
            }
        }
    }
    private fun openUpdatesInWeb(){
        if(!ready)return
        openUpdatesOnReady=false
        web.evaluateJavascript("window.onTrackOpenUpdates && window.onTrackOpenUpdates()",null)
    }
    private fun notifyAvailableUpdate(release:NativeUpdates.Release){
        if(Build.VERSION.SDK_INT>=33 &&
            ContextCompat.checkSelfPermission(this,Manifest.permission.POST_NOTIFICATIONS)!=PackageManager.PERMISSION_GRANTED)
            return
        val prefs=getSharedPreferences("ontrack_native_update_alerts",MODE_PRIVATE)
        if(prefs.getLong("last_version",-1L)>=release.versionCode)return
        val open=Intent(this,MobileLinkActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            .putExtra("open_updates",true)
        val pending=android.app.PendingIntent.getActivity(this,1200,open,
            android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_IMMUTABLE)
        val alert=NotificationCompat.Builder(this,"ontrack_voice_updates")
            .setSmallIcon(R.drawable.ic_stat_io_voice)
            .setLargeIcon(android.graphics.BitmapFactory.decodeResource(resources,R.mipmap.ic_launcher))
            .setContentTitle("تحديث IO Voice متاح")
            .setContentText("الإصدار "+release.versionName+" جاهز. اضغط لفتح شاشة التحديث.")
            .setContentIntent(pending).setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT).build()
        getSystemService(android.app.NotificationManager::class.java).notify(1200,alert)
        prefs.edit().putLong("last_version",release.versionCode).apply()
    }
    override fun onResume() {
        super.onResume()
        val result=getSharedPreferences("ontrack_link",MODE_PRIVATE)
            .getString("last_native_install","").orEmpty()
        if(::nativeUpdates.isInitialized && lastNativeCheckMs>0L &&
            android.os.SystemClock.elapsedRealtime()-lastNativeCheckMs>300000L) {
            lifecycleScope.launch { checkNativeUpdates() }
        }
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
    private fun dismissLaunchIfReady() {
        if(!ready || initialUiUpdateCheckPending)return
        web.postDelayed({
            if(ready && !initialUiUpdateCheckPending){
                splash?.hide()
                banner.visibility=View.GONE
            }
        },320)
    }
    private fun show(screen:MobileOta.Screen){
        ready=false;splash?.reveal();splash?.message("جارٍ تجهيز واجهة IO Voice…");val current=++generation
        screenNonce=java.util.UUID.randomUUID().toString()
        require(screen.html.contains("<head>")){"صيغة واجهة التحديث غير صالحة"}
        val content=screen.html.replaceFirst("<head>","<head><script>window.OTA_NONCE="+JSONObject.quote(screenNonce)+";</script>")
        val page=LocalUiPage(screenNonce,content)
        localPage=page
        web.loadUrl(page.url)
        lifecycleScope.launch{delay(10000);if(!ready && current==generation){
            if(screen.revision>1){banner.text="تعذر بدء واجهة IO Voice الجديدة؛ حاول تحديث الصفحة من إعدادات التطبيق. لن نعيد الواجهة القديمة تلقائيًا";splash?.hide()}
            else banner.text="تعذر فتح الواجهة؛ استخدم إعدادات الهاتف من شاشة التشخيص"
            splash?.hide()
        }}
    }
    private suspend fun activate(bundle:MobileOta.Bundle){
        if(MobileLinkService.inCall){pending=bundle;uiUpdateStatus="التحديث جاهز؛ هيتطبق بعد المكالمة";banner.text=uiUpdateStatus;sendState();return}
        uiUpdateStatus="تم تحميل تحديث الواجهة؛ جاري التطبيق…"
        show(withContext(Dispatchers.IO){ota.stage(bundle)});banner.text="تم تحميل تحديث الواجهة؛ جاري التحقق من تشغيله…"
    }
    private fun openExternalApkBrowser(url:String="https://app.ontrackegy.com/mobile/updates/native/latest.apk"){
        val parsed=Uri.parse(url)
        val browsable=Intent(Intent.ACTION_VIEW,parsed)
            .addCategory(Intent.CATEGORY_BROWSABLE)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        runCatching{startActivity(browsable)}.onFailure{
            val plain=Intent(Intent.ACTION_VIEW,parsed).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            runCatching{startActivity(plain)}.onFailure{err->
                runCatching{
                    startActivity(Intent.createChooser(plain,"تحميل IO Voice APK")
                        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                }.onFailure{
                    val msg="تعذر فتح المتصفح للتحميل: "+(err.message?:err.javaClass.simpleName)
                    banner.text=msg
                    banner.visibility=View.VISIBLE
                    nativeStatus=msg
                    sendState()
                }
            }
        }
    }
    private suspend fun checkUpdates(fromUser:Boolean=false){
        if(MobileLinkService.inCall){deferredUiCheck=true;return}
        if(updateBusy)return;updateBusy=true
        uiUpdateStatus="جاري فحص تحديث الواجهة…"
        if(fromUser)sendState()
        try{
            var lastUiPct=-1
            val next=withContext(Dispatchers.IO){
                ota.check {received,total ->
                    // These are real downloaded bytes from a signed OTA manifest.
                    runOnUiThread {
                        if(initialUiUpdateCheckPending)splash?.downloadProgress(received,total)
                        if(total>0L){
                            val pct=((received*100L)/total).toInt().coerceIn(0,100)
                            if(pct!=lastUiPct && (pct-lastUiPct>=5 || pct==100)){
                                lastUiPct=pct
                                uiUpdateStatus="تنزيل تحديث الواجهة: $pct%"
                                if(fromUser)sendState()
                            }
                        }
                    }
                }
            }
            if(next==null){
                uiUpdateStatus="الواجهة محدثة • OTA ${ota.revision}"
                banner.text=uiUpdateStatus
                if(fromUser)banner.visibility=View.VISIBLE
                if(initialUiUpdateCheckPending)splash?.message("الواجهة محدثة، جارٍ فتح IO Voice…")
            } else {
                uiUpdateStatus="تم العثور على واجهة OTA ${next.revision}؛ جاري التشغيل…"
                if(initialUiUpdateCheckPending)splash?.message("تم التحقق من التحديث، جارٍ تشغيله…")
                activate(next)
            }
        }catch(t:Throwable){
            if(t is CancellationException)throw t
            val detail=(t.message?.takeIf{it.isNotBlank()}?:t.javaClass.simpleName).take(110)
            uiUpdateStatus="تعذر فحص تحديث الواجهة: $detail"
            banner.text=uiUpdateStatus
            if(fromUser)banner.visibility=View.VISIBLE
        }
        finally{updateBusy=false;sendState()}
    }
    private suspend fun checkNativeUpdates(fromUser:Boolean=false) {
        if(MobileLinkService.inCall){deferredNativeCheck=true;return}
        if(nativeBusy)return
        nativeBusy=true
        lastNativeCheckMs=android.os.SystemClock.elapsedRealtime()
        nativeStatus="جاري فحص نسخة Android…";sendState()
        try {
            nativeRelease=withContext(Dispatchers.IO){nativeUpdates.check()}
            nativeStatus=if(nativeRelease==null)"تطبيق Android محدّث" else
                "إصدار Android جديد: "+nativeRelease!!.versionName
            nativeRelease?.let { notifyAvailableUpdate(it) }
        }catch(t:Throwable){
            if(t is CancellationException)throw t
            val detail=(t.message?.takeIf{it.isNotBlank()}?:t.javaClass.simpleName).take(110)
            nativeStatus="تعذر فحص تحديث Android: $detail"
            if(fromUser){banner.text=nativeStatus;banner.visibility=View.VISIBLE}
        }finally{nativeBusy=false;sendState()}
    }
    private suspend fun installNativeUpdate() {
        check(!MobileLinkService.inCall){"انتظر نهاية المكالمة قبل تثبيت Android"}
        check(!nativeBusy){"هناك تحديث قيد التنفيذ"}
        if(nativeRelease==null)checkNativeUpdates(true)
        val release=nativeRelease?:run{
            nativeStatus="أحدث إصدار مثبت بالفعل (${ota.nativeVersion})"
            nativeDownloadProgress=-1
            sendState();return
        }
        if(!nativeUpdates.canInstallPackages()){
            nativeStatus="يلزم السماح بالتثبيت من IO Voice مرة واحدة بواسطة Android"
            runCatching{nativeUpdates.openInstallPermission()}.onFailure{
                nativeStatus="تعذر فتح إعدادات التثبيت؛ جاري فتح التحميل المباشر في المتصفح…"
                openExternalApkBrowser(nativeUpdates.latestApkUrl(release))
            }
            sendState();return
        }
        nativeBusy=true
        nativeDownloadProgress=0
        try{
            val already=withContext(Dispatchers.IO){nativeUpdates.cached(release)}
            nativeStatus=if(already)"ملف التحديث محفوظ ومُتحقق منه؛ جارٍ فتح مثبت Android…"
                else "تنزيل تحديث Android: 0%"
            if(already)nativeDownloadProgress=100
            sendState()
            val file=nativeUpdates.download(release){percentage->
                runOnUiThread{
                    nativeDownloadProgress=percentage.coerceIn(0,100)
                    nativeStatus="تحميل التحديث: ${nativeDownloadProgress}%"
                    sendState()
                }
            }
            nativeDownloadProgress=100
            check(!MobileLinkService.inCall){"المكالمة جارية؛ ثبّت بعد انتهائها"}
            val accepted=withContext(Dispatchers.IO){nativeUpdates.requestInstall(file)}
            nativeStatus=if(accepted)
                "التحديث جاهز (100%)؛ بانتظار موافقة Android على التثبيت"
            else "اسمح بالتثبيت من IO Voice؛ ملف التحديث سيظل محفوظًا"
        }catch(t:Throwable){
            if(t is CancellationException)throw t
            nativeDownloadProgress=-1
            val detail=(t.message?.takeIf{it.isNotBlank()}?:t.javaClass.simpleName).take(130)
            nativeStatus="تعذّر التثبيت التلقائي: $detail — جاري التحميل المباشر في المتصفح"
            banner.text=nativeStatus
            banner.visibility=View.VISIBLE
            openExternalApkBrowser(nativeUpdates.latestApkUrl(release))
        }finally{nativeBusy=false;sendState()}
    }
    private suspend fun reviveDaemonNow() {
        check(!MobileLinkService.inCall){"لا يمكن إعادة تنشيط محرك الصوت أثناء المكالمة"}
        banner.text="جاري فحص وتنشيط محرك الصوت المحلي…"
        banner.visibility=View.VISIBLE
        val snap=withContext(Dispatchers.IO){
            val sw=com.ontrack.voiceproof.capture.StartWithoutWifi
            val onWifi=com.ontrack.voiceproof.capture.NetworkRevive.onWifi(this@MobileLinkActivity)
            val wEnabled=sw.enabled(this@MobileLinkActivity)
            val wReady=runCatching{sw.ready(this@MobileLinkActivity)}.getOrDefault(false)
            val ok=runCatching{
                com.ontrack.voiceproof.capture.CaptureDaemon.revive(
                    this@MobileLinkActivity,
                    request=com.ontrack.voiceproof.capture.CaptureDaemon.Request.USER)
            }.getOrDefault(false)
            val setupOk=runCatching{com.ontrack.voiceproof.ui.Setup.isComplete(this@MobileLinkActivity)}.getOrDefault(false)
            val wirelessOn=runCatching{Settings.Global.getInt(contentResolver,"adb_wifi_enabled",0)!=0}.getOrDefault(false)
            val wifiRefused=onWifi && runCatching{com.ontrack.voiceproof.capture.NetworkRevive.stillRefusedHere(this@MobileLinkActivity,wirelessOn)}.getOrDefault(false)
            val issue=if(ok) "" else CellularDaemonDiagnostics.diagnose(false,setupOk,onWifi,wReady,wifiRefused)
            LocalBridgeSnapshot(wEnabled,wReady,onWifi,CellularRadioStatus.safeInternetRoute(this@MobileLinkActivity),ok,issue)
        }
        wifiFreeEnabledState=snap.wifiFreeEnabled
        wifiFreeReadyState=snap.wifiFreeReady
        onWifiState=snap.onWifi
        internetRouteState=snap.internetRoute
        localDaemonReadyState=snap.daemonReady
        localDaemonIssueState=snap.daemonIssue
        if(MobileLinkService.running){
            MobileLinkService.daemonReady=snap.daemonReady
            MobileLinkService.daemonIssue=snap.daemonIssue
            runCatching{
                ContextCompat.startForegroundService(this@MobileLinkActivity,
                    Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                        .setAction(MobileLinkService.ACTION_REVIVE_DAEMON))
            }
        }
        banner.text=if(snap.daemonReady)"محرك الصوت المحلي يعمل وجاهز (${snap.internetRoute})"
            else snap.daemonIssue.ifBlank{"تعذر تنشيط محرك الصوت المحلي"}
        banner.visibility=View.VISIBLE
        sendState()
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
            val readyNow=withContext(Dispatchers.IO){feature.ready(this@MobileLinkActivity)}
            if(!ok || !readyNow){
                feature.setEnabled(this,false)
                wifiFreeEnabledState=false
                wifiFreeReadyState=false
                sendState()
                error("هذا الهاتف لم ينجح في تشغيل ADB المحلي الثابت؛ لم يُفعّل وضع بلا Wi-Fi")
            }
            wifiFreeEnabledState=true
            wifiFreeReadyState=true
            localDaemonReadyState=true
            localDaemonIssueState=""
            if(MobileLinkService.running){
                MobileLinkService.daemonReady=true
                MobileLinkService.daemonIssue=""
                runCatching{
                    ContextCompat.startForegroundService(this@MobileLinkActivity,
                        Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                            .setAction(MobileLinkService.ACTION_REVIVE_DAEMON))
                }
            }
            banner.text="وضع الاتصال بدون Wi-Fi مفعّل بنجاح (منفذ 5555 جاهز لاستقبال المكالمات على الداتا)."
        }else{
            feature.setEnabled(this,false)
            val ok=withContext(Dispatchers.IO){
                com.ontrack.voiceproof.capture.CaptureDaemon.revive(
                    this@MobileLinkActivity,
                    request=com.ontrack.voiceproof.capture.CaptureDaemon.Request.USER)
            }
            val stillReady=withContext(Dispatchers.IO){feature.ready(this@MobileLinkActivity)}
            wifiFreeEnabledState=false
            wifiFreeReadyState=stillReady
            localDaemonReadyState=ok
            banner.text=if(stillReady)
                "المنفذ ما زال مفتوحًا؛ أوقف USB debugging من إعدادات المطوّر لإغلاقه"
            else "تم تعطيل مسار الاتصال بدون Wi-Fi"
        }
        banner.visibility=View.VISIBLE
        sendState()
    }
    private fun sendState(){
        if(!ready)return
        if(banner.text?.toString()?.startsWith("تم إرسال طلب تشغيل SIM")==true &&
            MobileLinkService.running && MobileLinkService.agentEnabled){
            banner.text="مراقبة الشريحة تعمل — بانتظار مكالمة"
        }
        val elapsedSec=if(MobileLinkService.campaignQueueSyncedAtMs>0L)
            maxOf(0L,(System.currentTimeMillis()-MobileLinkService.campaignQueueSyncedAtMs)/1000L) else 0L
        val adjustedSec=if(MobileLinkService.campaignSecondsUntilDial>=0L)
            maxOf(0L,MobileLinkService.campaignSecondsUntilDial-elapsedSec) else -1L
        val nextContactObj=if(MobileLinkService.campaignNextName.isNotBlank()||MobileLinkService.campaignNextPhone.isNotBlank()){
            JSONObject().put("name",MobileLinkService.campaignNextName)
                .put("phone",MobileLinkService.campaignNextPhone)
                .put("scheduled_at_cairo",MobileLinkService.campaignScheduledAtCairo)
                .put("seconds_until_dial",adjustedSec)
        }else JSONObject.NULL
        val queueObj=JSONObject().put("campaign_id",MobileLinkService.campaignId)
            .put("campaign_name",MobileLinkService.campaignActiveName)
            .put("campaign_status",MobileLinkService.campaignStatus)
            .put("total_contacts",MobileLinkService.campaignTotalContacts)
            .put("completed_calls",MobileLinkService.campaignCompletedCalls)
            .put("remaining_contacts",MobileLinkService.campaignRemainingContacts)
            .put("uncertain_pending",MobileLinkService.campaignUncertainPending)
            .put("last_error",MobileLinkService.campaignLastError)
            .put("next_contact",nextContactObj)
        val state=JSONObject().put("status",MobileLinkService.latestStatus).put("running",MobileLinkService.running)
            .put("inCall",MobileLinkService.inCall).put("callAnswered",MobileLinkService.callAnswered).put("humanControl",MobileLinkService.humanControl)
            .put("agentAudioReady",MobileLinkService.audioReady).put("callProblem",MobileLinkService.callProblem)
            .put("audioDiagnostic",MobileLinkService.audioDiagnostic)
            .put("stageCallerPcm",MobileLinkService.stageCallerPcm)
            .put("stageCallerVoice",MobileLinkService.stageCallerVoice)
            .put("stageAiReceived",MobileLinkService.stageAiReceived)
            .put("stageAiPlayed",MobileLinkService.stageAiPlayed)
            .put("audioTelemetryText",MobileLinkService.audioTelemetryText)
            .put("agentEnabled",MobileLinkService.agentEnabled).put("connected",MobileLinkService.connected).put("pendingSync",MobileLinkService.pendingSync).put("serverRevision",MobileLinkService.serverRevision)
            .put("phonePermissionGranted",ContextCompat.checkSelfPermission(this,Manifest.permission.READ_PHONE_STATE)==PackageManager.PERMISSION_GRANTED)
            .put("overlayGranted",Settings.canDrawOverlays(this)).put("revision",ota.revision)
            .put("nativeUpdateAvailable",nativeRelease!=null).put("nativeUpdateBusy",nativeBusy)
            .put("nativeUpdateStatus",nativeStatus)
            .put("nativeUpdateProgress",nativeDownloadProgress)
            .put("uiUpdateStatus",uiUpdateStatus)
            .put("nativeInstallPending",nativeUpdates.installPending())
            .put("nativeInstallPermission",nativeUpdates.canInstallPackages())
            .put("nativeUpdateNotificationsEnabled",Build.VERSION.SDK_INT<33 ||
                ContextCompat.checkSelfPermission(this,Manifest.permission.POST_NOTIFICATIONS)==PackageManager.PERMISSION_GRANTED)
            .put("tenantSim1",tenantStatusLines[0]).put("tenantSim2",tenantStatusLines[1])
            .put("tenantLinked",tenantEnrollment.hasAny())
            .put("tenantServerState",tenantServerState)
            .put("tenantAgents",tenantAgentCards)
            .put("tenantDesiredEnabled",getSharedPreferences("ontrack_tenant_voice",MODE_PRIVATE).getBoolean("enabled",false))
            .put("tenantServiceRunning",MobileLinkService.running)
            .put("tenantPairingInProgress",MobileLinkService.tenantPairingInProgress)
            .put("tenantPaired1",tenantEnrollment.paired(0))
            .put("tenantPaired2",tenantEnrollment.paired(1))
            .put("tenantWorkspaceSlot",getSharedPreferences("ontrack_tenant_workspace",MODE_PRIVATE).getInt("sim_slot",-1))
            .put("wifiFreeEnabled",wifiFreeEnabledState)
            .put("wifiFreeReady",wifiFreeReadyState)
            .put("onWifi",onWifiState)
            .put("internetRoute",internetRouteState)
            .put("daemonReady",if(MobileLinkService.running) MobileLinkService.daemonReady else localDaemonReadyState)
            .put("daemonIssue",if(MobileLinkService.running) MobileLinkService.daemonIssue else localDaemonIssueState)
            .put("campaignAutoEnabled",MobileLinkService.campaignAutoEnabled || getSharedPreferences("ontrack_link",MODE_PRIVATE).getBoolean("campaign_opt_in",false))
            .put("campaignQueueCount",MobileLinkService.campaignReadyCount)
            .put("campaignActiveName",MobileLinkService.campaignActiveName)
            .put("campaignQueue",queueObj)
            .put("managerCallbackActive",MobileLinkService.managerCallbackActive)
            .put("managerCallbackCountdown",MobileLinkService.managerCallbackCountdownSeconds)
            .put("managerCallbackPhone",MobileLinkService.managerCallbackTargetMasked)
            .put("callPhoneGranted",ContextCompat.checkSelfPermission(this,Manifest.permission.CALL_PHONE)==PackageManager.PERMISSION_GRANTED)
            .put("contactsSupported",true)
            .put("callsSupported",true)
            .put("role","user")
            .put("theme",getSharedPreferences("ontrack_link",MODE_PRIVATE).getString("ui_theme","dark"))
            .put("callNamesPermission",
                ContextCompat.checkSelfPermission(this,Manifest.permission.READ_CALL_LOG)==PackageManager.PERMISSION_GRANTED
                &&ContextCompat.checkSelfPermission(this,Manifest.permission.READ_CONTACTS)==PackageManager.PERMISSION_GRANTED)
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
                val command=msg.getString("command")
                // A previously cached signed UI might still send "start".
                // Treat it as the same tenant SIM enable operation, ignoring
                // any old Pilot base/key fields. Never re-enable Pilot transport.
                when(command){
                    "openConsole" -> openConsole()
                    "openAgents" -> openConsole("agents")
                    "openFlowBuilder" -> openConsole("agents")
                    "showTenantFlow" -> {
                        val slot=msg.getInt("sim_slot")
                        check(slot in 0..1 && tenantEnrollment.paired(slot)){"الشريحة غير مربوطة"}
                        showTenantPublishedFlow(slot)
                    }
                    "editTenantAgent" -> {
                        val slot=msg.getInt("sim_slot")
                        check(slot in 0..1 && tenantEnrollment.paired(slot)){"الشريحة غير مربوطة"}
                        val card=tenantAgentCards.optJSONObject(slot) ?: error("حدّث بيانات الإيجنت")
                        check(card.optString("state")=="online"){"تحقق من اتصال الشريحة أولًا"}
                        openConsole("agents",card.optInt("site_id"),card.optInt("agent_id"))
                    }
                    "openScenario" -> showScenarioMenu()
                    "openAnswerSettings" -> openConsole("answering")
                    "tenantMenu" -> showTenantMenu()
                    "tenantQrScan" -> showQuickTenantPair()
                    "tenantQuickPair" -> showQuickTenantPair()
                    "tenantRefresh" -> requestTenantStatusRefresh()
                    "tenantTest" -> testTenantReadiness()
                    "workspaceSlot"->{
                        val slot=msg.getInt("sim_slot")
                        check(slot in 0..1 && tenantEnrollment.paired(slot)){"الشريحة غير مربوطة بحساب SaaS"}
                        check(getSharedPreferences("ontrack_tenant_workspace",MODE_PRIVATE)
                            .edit().putInt("sim_slot",slot).commit()){"تعذر حفظ الشريحة المختارة"}
                        sendState()
                    }
                    "copyKey" -> error("مفتاح تجربة الهاتف القديم غير مستخدم")
                    "ready"->{ready=true;requestTenantStatusRefresh();withContext(Dispatchers.IO){ota.confirm()};banner.text="الواجهة جاهزة • OTA ${ota.revision}";sendState()
                        dismissLaunchIfReady()
                        val initial=JSONObject().put("base","").put("key","")
                        web.evaluateJavascript("window.onTrackInit && window.onTrackInit($initial)",null)
                        if(openUpdatesOnReady)openUpdatesInWeb()
                    }
                    "stop","disable"->{
                        startRequestedAfterPhonePermission=false
                        ContextCompat.startForegroundService(this@MobileLinkActivity,
                            Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                                .setAction(MobileLinkService.ACTION_DISABLE))
                    }
                    "start","enable"-> requestStartTenantMonitor()
                    "takeover"->startService(Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_TAKEOVER))
                    "speaker"->startService(Intent(this@MobileLinkActivity,MobileLinkService::class.java).setAction(MobileLinkService.ACTION_SPEAKER))
                    "overlayPermission"->{
                        if(!Settings.canDrawOverlays(this@MobileLinkActivity)){
                            startActivity(Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,Uri.parse("package:$packageName")))
                        }else banner.text="إذن النافذة العائمة مفعل"
                    }
                    "loadCallHistory"->loadCallHistory()
                    "loadCallTranscript"->loadCallTranscript(msg.optString("call_id"))
                    "enableCallNames"->{
                        // The Voice-only APK no longer declares call-log
                        // scraping. Contact lookup requires explicit choice.
                        banner.text="تم إلغاء صلاحية سجل المكالمات من تطبيق Voice. اختيار جهة اتصال واحدة أو ملف CSV متاح بدون الإذن."
                        banner.visibility=View.VISIBLE
                        sendState()
                    }
                    "setTheme"->{
                        val theme=msg.optString("theme")
                        require(theme=="dark"||theme=="light"){"مظهر غير صالح"}
                        check(getSharedPreferences("ontrack_link",MODE_PRIVATE).edit().putString("ui_theme",theme).commit()){"تعذر حفظ المظهر"}
                        sendState()
                    }
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
                        check(msg.optBoolean("confirmed")){"موافقة صاحب الجهاز وتكاليف المكالمات مطلوبة"}
                        check(!MobileLinkService.inCall){"تشغيل الحملات مسموح والهاتف غير مشغول"}
                        check(tenantEnrollment.hasAny()){"اربط الشريحة بحساب SaaS أولًا"}
                        requestCampaignAction(MobileLinkService.ACTION_CAMPAIGN_ENABLE)
                    }
                    "campaignAutoDisable"->ContextCompat.startForegroundService(this@MobileLinkActivity,
                        Intent(this@MobileLinkActivity,MobileLinkService::class.java)
                            .setAction(MobileLinkService.ACTION_CAMPAIGN_DISABLE))
                    "campaignPullNow"->{
                        check(!MobileLinkService.inCall){"انتظر انتهاء المكالمة الحالية"}
                        check(tenantEnrollment.hasAny()){"اربط الشريحة بحساب SaaS أولًا"}
                        requestCampaignAction(MobileLinkService.ACTION_CAMPAIGN_PULL_NOW)
                    }
                    "listCampaigns"->campaignRequest(null)
                    "campaignAction"->{
                        check(msg.optBoolean("confirmed")){"تأكيد العملية مطلوب"}
                        val action=msg.optString("action")
                        require(action in setOf("preview","pause","resume","cancel","arm","start","dial_next_now")){"إجراء غير مدعوم"}
                        val id=msg.optString("id")
                        require(id.matches(Regex("[a-f0-9]{24}"))){"معرف الحملة غير صحيح"}
                        campaignRequest(JSONObject().put("confirmed",true).put("action",action).put("id",id))
                        if(action=="dial_next_now")
                            requestCampaignAction(MobileLinkService.ACTION_CAMPAIGN_PULL_NOW)
                    }
                    "campaignDialNextNow"->{
                        check(!MobileLinkService.inCall){"انتظر انتهاء المكالمة الحالية"}
                        check(tenantEnrollment.hasAny()){"اربط الشريحة بحساب SaaS أولًا"}
                        requestCampaignAction(MobileLinkService.ACTION_CAMPAIGN_PULL_NOW)
                    }
                    "createCampaign"->{
                        check(msg.optBoolean("confirmed")){"يجب مراجعة مسودة الحملة"}
                        val name=msg.optString("name").trim()
                        val starts=msg.optString("starts").trim()
                        val ends=msg.optString("ends").trim()
                        val scenario=msg.optString("scenario").trim()
                        require(name.isNotEmpty() && name.length<=160 && starts.length==16 && ends.length==16 &&
                            scenario.isNotEmpty() && scenario.length<=8000){"بيانات الحملة غير صالحة"}
                        val ids=msg.optJSONArray("contact_ids")?:JSONArray()
                        require(ids.length()<=60){"الحد الأقصى 60 جهة مختارة لكل طلب"}
                        for(i in 0 until ids.length()){
                            require(ids.getString(i).matches(Regex("[a-f0-9]{24}"))){"معرف جهة اتصال غير صالح"}
                        }
                        campaignRequest(JSONObject().put("confirmed",true).put("name",name).put("starts",starts)
                            .put("ends",ends).put("scenario",scenario).put("contact_ids",ids))
                    }
                    "contactsPick"->{
                        val intent=Intent(Intent.ACTION_PICK).apply{
                            type=ContactsContract.CommonDataKinds.Phone.CONTENT_TYPE
                        }
                        contactPicker.launch(intent)
                    }
                    "contactsAll"->{
                        // No bulk address-book permission in the Voice APK.
                        // Android's one-contact picker provides scoped consent.
                        contactPicker.launch(Intent(Intent.ACTION_PICK).apply{
                            type=ContactsContract.CommonDataKinds.Phone.CONTENT_TYPE
                        })
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
                    "checkUpdates"->checkUpdates(true)
                    "checkNativeUpdates"->checkNativeUpdates(true)
                    "installNativeUpdate"->installNativeUpdate()
                    "openApkDownload"->startActivity(
                        Intent(Intent.ACTION_VIEW,Uri.parse(nativeUpdates.latestApkUrl(nativeRelease)))
                            .addCategory(Intent.CATEGORY_BROWSABLE)
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                    "enableUpdateNotifications"->{
                        if(Build.VERSION.SDK_INT>=33 &&
                            ContextCompat.checkSelfPermission(this@MobileLinkActivity,Manifest.permission.POST_NOTIFICATIONS)!=PackageManager.PERMISSION_GRANTED)
                            updateNotificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
                        else {
                            banner.text="إشعارات تحديث Android مفعّلة"
                            banner.visibility=View.VISIBLE
                        }
                    }
                    "wifiFreeEnable"->setWifiFreeMode(true)
                    "wifiFreeDisable"->setWifiFreeMode(false)
                    "reviveDaemon"->reviveDaemonNow()
                    "rollback"->{check(!MobileLinkService.inCall){"انتظر نهاية المكالمة"};show(withContext(Dispatchers.IO){ota.rollback(false)});banner.text="تم الرجوع للواجهة السابقة"}
                    "diagnostics"->startActivity(Intent(this@MobileLinkActivity,ProofActivity::class.java))
                    "admin"->openConsole()
                    else->error("أمر الواجهة غير مدعوم")
                }
            }.onFailure{err->
                if(err is android.content.ActivityNotFoundException){
                    openExternalApkBrowser(nativeUpdates.latestApkUrl(nativeRelease))
                } else {
                    banner.text=err.message?:"تعذر تنفيذ الطلب"
                    banner.visibility=View.VISIBLE
                    sendState()
                }
            }}}
        }
    }
    override fun onDestroy(){generation++;web.removeJavascriptInterface("OnTrack");web.destroy();super.onDestroy()}
}
