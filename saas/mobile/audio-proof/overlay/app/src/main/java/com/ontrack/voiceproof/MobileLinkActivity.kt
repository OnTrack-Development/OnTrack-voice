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
    private var nativeStatus="جاري فحص تحديث Android"
    private var openUpdatesOnReady=false
    private var lastNativeInstallResult=""
    @Volatile private var wifiFreeEnabledState=false
    @Volatile private var wifiFreeReadyState=false
    private lateinit var banner:TextView
    private lateinit var tenantEnrollment:TenantDeviceEnrollment
    private var tenantStatusLines=arrayOf("SIM 1 — جاري التحقق","SIM 2 — جاري التحقق")
    // Separately measured SaaS heartbeat status. Google Live stays disconnected
    // while idle by design, and must never be called a server outage.
    private var tenantServerState="checking"
    private var tenantAgentCards=JSONArray()
    private var tenantStatusJob:Job?=null
    /** The user's explicit Start command may continue once Phone permission is granted. */
    private var startRequestedAfterPhonePermission=false
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
        val allowed=grants[Manifest.permission.READ_CALL_LOG]==true&&grants[Manifest.permission.READ_CONTACTS]==true
        banner.text=if(allowed)"تم تفعيل مطابقة جهات الاتصال للمكالمات الجديدة"
            else "Android لم يمنح كل الصلاحيات؛ ستبقى الأسماء غير المعروفة بدون تخمين"
        banner.visibility=View.VISIBLE
        sendState()
    }
    private val tenantSimPermission=registerForActivityResult(ActivityResultContracts.RequestPermission()){granted->
        val continueStart=startRequestedAfterPhonePermission
        startRequestedAfterPhonePermission=false
        if(granted && continueStart){
            runCatching { startTenantMonitor() }.onFailure { error ->
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
    private suspend fun campaignRequest(payload:JSONObject?) {
        check(!MobileLinkService.inCall){"إدارة الحملات بعد انتهاء المكالمة"}
        val operation=when {
            payload==null->"workspace_campaigns"
            payload.has("action")->"workspace_campaign_action"
            else->"workspace_campaign_create"
        }
        val result=tenantWorkspace(operation,payload?:JSONObject())
        web.evaluateJavascript("window.onTrackCampaigns && window.onTrackCampaigns("+result.toString()+")",null)
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
                "ontrack_voice_updates","تحديثات OnTrack Voice",
                android.app.NotificationManager.IMPORTANCE_DEFAULT))
        // Never erase an existing device link during an in-place signed OTA.
        // Legacy Pilot cannot launch in tenant mode, but its credential remains
        // intact for explicit, audited migration/rollback. The encrypted per-SIM
        // SaaS binding, theme, existing OTA and contacts are never touched here.
        // No silent re-pairing and no credential is exposed to the WebView.

        ota=MobileOta(this);nativeUpdates=NativeUpdates(this)
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
        handleTenantLink(intent)
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
        // Explicit Android confirmation for consent, campaign arm and SIM charges.
        // Only the signed local page may display a script-driven dialog.
        web.webChromeClient=object:WebChromeClient(){
            override fun onJsConfirm(view:WebView?,url:String?,message:String?,result:JsResult?):Boolean {
                if(url?.startsWith("https://ontrack-ui.invalid/ui/")!=true){
                    result?.cancel();return true
                }
                AlertDialog.Builder(this@MobileLinkActivity)
                    .setTitle("تأكيد OnTrack")
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
                AlertDialog.Builder(this@MobileLinkActivity)
                    .setTitle("OnTrack Voice")
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
                if(polls%10==1)requestTenantStatusRefresh()
                // Foreground-only periodic release check; never downloads without consent.
                if(polls>0 && polls%900==0 && hasWindowFocus())checkNativeUpdates()
                if(pending!=null && !MobileLinkService.inCall){val next=pending!!;pending=null;activate(next)}
                delay(1000)
            }
        }
    }
    override fun onNewIntent(intent:Intent) {
        super.onNewIntent(intent)
        handleTenantLink(intent)
    }
    private fun handleTenantLink(intent:Intent?) {
        val parsed=TenantPairingContract.qr(intent?.dataString.orEmpty()) ?: return
        // Scanning a QR only fills the native form. It never redeems automatically.
        showTenantPairDialog(parsed.first,parsed.second)
    }
    private fun scanTenantQr() {
        if(MobileLinkService.inCall) {
            banner.text="انتظر انتهاء المكالمة قبل ربط الشريحة"
            banner.visibility=View.VISIBLE
            return
        }
        // Optional Play services scanner; code stays on-device until confirmed.
        // Manual code entry works even when Google Play services is unavailable.
        val options=GmsBarcodeScannerOptions.Builder()
            .setBarcodeFormats(Barcode.FORMAT_QR_CODE).build()
        GmsBarcodeScanning.getClient(this,options).startScan()
            .addOnSuccessListener { barcode ->
                val parsed=TenantPairingContract.qr(barcode.rawValue.orEmpty())
                if(parsed==null) {
                    banner.text="QR غير صالح؛ استخدم رابط OnTrack أو أدخل الرمز يدويًا"
                    banner.visibility=View.VISIBLE
                } else showTenantPairDialog(parsed.first,parsed.second)
            }
            .addOnFailureListener {
                banner.text="ماسح QR غير متاح على الجهاز؛ أدخل رمز الاقتران يدويًا"
                banner.visibility=View.VISIBLE
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
                            .put("site_name",profile.optString("site_name","").take(140))
                            .put("account_name",profile.optString("account_name","").take(140))
                            .put("purpose",purpose)
                            .put("flow_ready",profile.optBoolean("flow_ready",false))
                            .put("flow_revision",profile.optInt("flow_revision",0))
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
                Triple(lines,cards,health)
            }
            tenantStatusLines=report.first.toTypedArray()
            tenantAgentCards=report.second
            tenantServerState=report.third
            sendState()
        }
    }
    private fun openConsole(section:String="",siteId:Int=0,agentId:Int=0){
        startActivity(Intent(this,ConsoleWebActivity::class.java).apply{
            if(section in setOf("agents","flows","answering","devices"))putExtra("section",section)
            if(section=="agents" && siteId>0 && agentId>0){
                putExtra("site_id",siteId)
                putExtra("agent_id",agentId)
            }
        })
    }
    private fun showScenarioMenu(){
        AlertDialog.Builder(this).setTitle("سيناريوهات الإيجنت")
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
        AlertDialog.Builder(this).setTitle("إدارة شرائح الشركات")
            .setMessage("كل شريحة لها حساب وإيجنت مستقلان. هذا الربط لا يشغّل مكالمات AI تلقائيًا.")
            .setItems(items) { _,which ->
                val slot=which%2
                when(which) {
                    0,1 -> showTenantPairDialog(slot,"")
                    2,3 -> runTenantOperation(slot,false)
                    6,7 -> showTenantPublishedFlow(slot)
                    4,5 -> AlertDialog.Builder(this).setTitle("تأكيد فصل SIM ${slot+1}")
                        .setMessage("سيُلغى اعتماد هذه الشريحة على السيرفر، ولن يتأثر حساب الشريحة الأخرى.")
                        .setPositiveButton("فصل") { _,_ -> runTenantOperation(slot,true) }
                        .setNegativeButton("إلغاء",null).show()
                }
            }.setNegativeButton("إغلاق",null).show()
    }

    private fun showTenantPublishedFlow(slot:Int) {
        lifecycleScope.launch {
            try {
                check(!MobileLinkService.inCall) { "اعرض Flow بعد انتهاء المكالمة" }
                val flow=withContext(Dispatchers.IO) { tenantEnrollment.publishedFlow(slot) }
                val flowData=flow.getJSONObject("flow")
                val nodes=flowData.getJSONArray("nodes");val edges=flowData.getJSONArray("edges")
                val text=StringBuilder("الصوت: ").append(flow.optString("voice"))
                    .append("\nالتعليمات: ").append(flow.optString("instructions").take(3000))
                    .append("\n\nخطوات السيناريو:\n")
                for(i in 0 until nodes.length()){
                    val n=nodes.getJSONObject(i)
                    text.append("\n").append(i+1).append(" · ").append(n.optString("type"))
                        .append(" [").append(n.optString("id")).append("]\n")
                        .append(n.optString("text").take(1000)).append("\n")
                }
                text.append("\nالروابط والشروط:\n")
                for(i in 0 until edges.length()){
                    val e=edges.getJSONObject(i)
                    text.append("• ").append(e.optString("from")).append(" → ")
                        .append(e.optString("to"))
                    if(e.optString("label").isNotBlank())
                        text.append(" (").append(e.optString("label").take(160)).append(")")
                    text.append("\n")
                }
                val scroll=ScrollView(this@MobileLinkActivity)
                scroll.addView(TextView(this@MobileLinkActivity).apply{
                    this.text=text.toString();textSize=15f;setTextIsSelectable(true)
                    setPadding(25,20,25,20)
                })
                AlertDialog.Builder(this@MobileLinkActivity)
                    .setTitle("SIM ${slot+1} · سيناريو v${flow.getInt("revision")}")
                    .setView(scroll)
                    .setPositiveButton("فتح Flow Builder"){_,_->
                         openConsole("agents",flow.optInt("site_id"),flow.optInt("agent_id"))}
                    .setNegativeButton("إغلاق",null).show()
            }catch(t:Throwable) {
                if(t is CancellationException)throw t
                banner.text="Flow SIM ${slot+1}: "+(t.message?:"تعذر جلب النسخة المنشورة")
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
                "مفيش أي SIM مربوطة. افتح الأجهزة في حسابك وأنشئ QR واربط الشريحة الأول."
                else results.joinToString("\n")
            AlertDialog.Builder(this@MobileLinkActivity)
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
            banner.text="SIM ${slot+1} مربوطة بالفعل. افصلها أولًا قبل ربط حساب آخر."
            banner.visibility=View.VISIBLE
            return
        }
        val input=EditText(this).apply {
            hint="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
            setText(initialCode)
            inputType=android.text.InputType.TYPE_CLASS_TEXT or
                android.text.InputType.TYPE_TEXT_VARIATION_VISIBLE_PASSWORD
        }
        AlertDialog.Builder(this).setTitle("ربط SIM ${slot+1} بحساب OnTrack")
            .setMessage("أدخل الرمز المؤقت من لوحة الحساب. لا تستخدم مفتاح هاتف التجربة.")
            .setView(input)
            .setPositiveButton("ربط") { _,_ ->
                val code=input.text.toString()
                lifecycleScope.launch {
                    try {
                        check(!MobileLinkService.inCall) { "انتظر انتهاء المكالمة أولًا" }
                        TenantPairingContract.codeForSlot(code,slot)
                        if(ContextCompat.checkSelfPermission(this@MobileLinkActivity,
                                Manifest.permission.READ_PHONE_STATE)!=PackageManager.PERMISSION_GRANTED) {
                            tenantSimPermission.launch(Manifest.permission.READ_PHONE_STATE)
                            return@launch
                        }
                        // Block pilot dial immediately, including the in-flight lease-to-dial window.
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
            }.setNegativeButton("إلغاء",null).show()
    }
    private fun showTenantProfile(slot:Int,profile:JSONObject) {
        if(profile.optBoolean("verification_pending")) {
            AlertDialog.Builder(this).setTitle("SIM ${slot+1} — تم حفظ الربط")
                .setMessage("السيرفر أصدر اعتماد الشريحة وتم حفظه مشفرًا على الهاتف، لكن التحقق اللاحق لم يكتمل. لا تعيد استخدام رمز QR؛ اضغط التحقق من ربط الشريحة بعد استعادة الاتصال. المكالمات تظل متوقفة.")
                .setPositiveButton("حسنًا",null).show()
            return
        }
        val account=profile.optString("account_name","غير معروف")
        val site=profile.optString("site_name","غير معروف")
        val agent=profile.optString("agent_name","غير معروف")
        AlertDialog.Builder(this).setTitle("SIM ${slot+1} — تم التحقق من الربط")
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
    override fun onNewIntent(intent:Intent){
        super.onNewIntent(intent)
        setIntent(intent)
        if(intent.getBooleanExtra("open_updates",false)){
            openUpdatesOnReady=true
            if(ready)openUpdatesInWeb()
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
            .setSmallIcon(android.R.drawable.stat_sys_download_done)
            .setContentTitle("تحديث OnTrack Voice متاح")
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
    private fun show(screen:MobileOta.Screen){
        ready=false;splash?.reveal();splash?.message("جارٍ تحميل تجربة OnTrack…");val current=++generation
        screenNonce=java.util.UUID.randomUUID().toString()
        require(screen.html.contains("<head>")){"صيغة واجهة التحديث غير صالحة"}
        val content=screen.html.replaceFirst("<head>","<head><script>window.OTA_NONCE="+JSONObject.quote(screenNonce)+";</script>")
        val page=LocalUiPage(screenNonce,content)
        localPage=page
        web.loadUrl(page.url)
        lifecycleScope.launch{delay(10000);if(!ready && current==generation){
            if(screen.revision>1){banner.text="تعذر بدء واجهة OnTrack الجديدة؛ حاول تحديث الصفحة من إعدادات التطبيق. لن نعيد الواجهة القديمة تلقائيًا";splash?.hide()}
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
        lastNativeCheckMs=android.os.SystemClock.elapsedRealtime()
        nativeStatus="جاري فحص نسخة Android…";sendState()
        try {
            nativeRelease=withContext(Dispatchers.IO){nativeUpdates.check()}
            nativeStatus=if(nativeRelease==null)"تطبيق Android محدّث" else
                "إصدار Android جديد: "+nativeRelease!!.versionName
            nativeRelease?.let { notifyAvailableUpdate(it) }
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
            .put("campaignAutoEnabled",getSharedPreferences("ontrack_link",MODE_PRIVATE).getBoolean("campaign_opt_in",false))
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
                    "openFlowBuilder" -> openConsole("flows")
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
                    "tenantQrScan" -> scanTenantQr()
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
                        web.postDelayed({splash?.hide();banner.visibility=View.GONE},550)
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
                        val missing=listOf(Manifest.permission.READ_CALL_LOG,Manifest.permission.READ_CONTACTS)
                            .filter{ContextCompat.checkSelfPermission(this@MobileLinkActivity,it)!=PackageManager.PERMISSION_GRANTED}
                        if(missing.isNotEmpty())callNamesPermissions.launch(missing.toTypedArray())
                        else {banner.text="الصلاحيات مفعّلة؛ سيتم مطابقة المكالمات الجديدة";banner.visibility=View.VISIBLE;sendState()}
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
                    "campaignAutoEnable"->error("الاتصال الجماعي معطل حتى تكتمل اختبارات سياسات الموافقة وDNC على SaaS؛ لن يستخدم مفتاح Pilot")
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
                    "rollback"->{check(!MobileLinkService.inCall){"انتظر نهاية المكالمة"};show(withContext(Dispatchers.IO){ota.rollback(false)});banner.text="تم الرجوع للواجهة السابقة"}
                    "diagnostics"->startActivity(Intent(this@MobileLinkActivity,ProofActivity::class.java))
                    "admin"->openConsole()
                    else->error("أمر الواجهة غير مدعوم")
                }
            }.onFailure{
                banner.text=it.message?:"تعذر تنفيذ الطلب"
                banner.visibility=View.VISIBLE
                sendState()
            }}}
        }
    }
    override fun onDestroy(){generation++;web.removeJavascriptInterface("OnTrack");web.destroy();super.onDestroy()}
}
