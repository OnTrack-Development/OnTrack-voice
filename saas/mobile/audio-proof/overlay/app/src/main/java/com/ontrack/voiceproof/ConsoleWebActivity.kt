package com.ontrack.voiceproof
import android.annotation.SuppressLint
import android.os.Bundle
import android.app.AlertDialog
import android.webkit.*
import android.widget.*
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import java.io.ByteArrayInputStream

/** Separate authenticated website WebView. No native bridge or SIM credentials are exposed. */
class ConsoleWebActivity:ComponentActivity(){
 private lateinit var web:WebView
 private fun allowed(value:String):Boolean{
   val u=runCatching{android.net.Uri.parse(value)}.getOrNull()?:return false
   val p=u.encodedPath.orEmpty()
   val portal=p=="/"||p in setOf("/login","/logout","/register","/forgot-password","/change-password",
       "/mfa","/workspace","/admin","/reseller","/overview","/agents","/voices","/devices","/calls",
       "/campaigns","/automations","/billing","/invoices","/settings","/whatsapp","/accounts","/plans",
       "/domains","/staff","/integrations","/releases","/brand-logo","/flow-preview.php","/sso","/sso/confirm")
       ||p.startsWith("/console/")||p.startsWith("/device/v1/")
       ||p.startsWith("/assets/")||p.startsWith("/api/")
       ||p=="/saas/console"||p.startsWith("/saas/console/")
   return u.scheme=="https"&&u.host=="app.ontrackegy.com"&&u.port in listOf(-1,443)&&
      u.userInfo==null&&portal
 }
 @SuppressLint("SetJavaScriptEnabled") override fun onCreate(savedInstanceState:Bundle?){
   super.onCreate(savedInstanceState)
   window.statusBarColor=android.graphics.Color.rgb(13,13,17)
   val layout=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setBackgroundColor(android.graphics.Color.rgb(13,13,17))}
   val top=LinearLayout(this).apply{
       setPadding(12,8,12,8);setBackgroundColor(android.graphics.Color.rgb(20,20,24))
   }
   val back=Button(this).apply{
       text="رجوع";setTextColor(android.graphics.Color.WHITE)
       backgroundTintList=android.content.res.ColorStateList.valueOf(android.graphics.Color.rgb(43,43,49))
       setOnClickListener{if(web.canGoBack())web.goBack() else finish()}
   }
   val close=Button(this).apply{
       text="إغلاق";setTextColor(android.graphics.Color.WHITE)
       backgroundTintList=android.content.res.ColorStateList.valueOf(android.graphics.Color.rgb(185,19,45))
       setOnClickListener{finish()}
   }
   top.addView(back,LinearLayout.LayoutParams(0,-2,1f).apply{marginEnd=8})
   top.addView(close,LinearLayout.LayoutParams(0,-2,1f))
   layout.addView(top)
   val error=TextView(this).apply {
       textSize=13f;setTextColor(android.graphics.Color.WHITE)
       setBackgroundColor(android.graphics.Color.rgb(78,26,36))
       setPadding(18,12,18,12);visibility=android.view.View.GONE
   }
   layout.addView(error)
   web=WebView(this);layout.addView(web,LinearLayout.LayoutParams(-1,0,1f));setContentView(layout)
   web.settings.apply{
      javaScriptEnabled=true;domStorageEnabled=true;allowFileAccess=false;allowContentAccess=false
      mixedContentMode=WebSettings.MIXED_CONTENT_NEVER_ALLOW;setSupportMultipleWindows(false)
      javaScriptCanOpenWindowsAutomatically=false;safeBrowsingEnabled=true
   }
   CookieManager.getInstance().setAcceptCookie(true)
   CookieManager.getInstance().setAcceptThirdPartyCookies(web,false)
   web.webViewClient=object:WebViewClient(){
     override fun shouldOverrideUrlLoading(view:WebView,request:WebResourceRequest)=
       !allowed(request.url.toString())||request.method!="GET"
     override fun shouldInterceptRequest(view:WebView,request:WebResourceRequest):WebResourceResponse?{
       if(allowed(request.url.toString()))return null
       return WebResourceResponse("text/plain","UTF-8",ByteArrayInputStream(ByteArray(0)))
     }
     override fun onReceivedError(view:WebView,request:WebResourceRequest,problem:WebResourceError){
       if(request.isForMainFrame){
         error.text="تعذر فتح الاستوديو. تحقق من الإنترنت ثم أعد المحاولة."
         error.visibility=android.view.View.VISIBLE
       }
     }
     override fun onReceivedHttpError(view:WebView,request:WebResourceRequest,response:WebResourceResponse){
       if(request.isForMainFrame&&response.statusCode>=400){
         error.text="تعذر تحميل الصفحة (HTTP "+response.statusCode+"). يمكنك إعادة فتح الاستوديو من صفحة المساعدين."
         error.visibility=android.view.View.VISIBLE
       }
     }
     override fun onPageFinished(view:WebView,url:String){
       if(allowed(url)&&url.contains("tab=agents")){
         val agentId=intent.getIntExtra("agent_id",0)
         if(agentId>0){
           view.evaluateJavascript("(function(){var e=document.getElementById('agent-"+agentId+
             "');if(e){e.open=true;window.ontrackMountFlowEditors?.(e);setTimeout(function(){e.scrollIntoView({block:'start'});window.dispatchEvent(new Event('resize'));},180);}})();",null)
         }
       }
     }
   }
   web.webChromeClient=object:WebChromeClient(){
     override fun onJsConfirm(view:WebView?,url:String?,message:String?,result:JsResult?):Boolean{
       if(!allowed(url.orEmpty())){result?.cancel();return true}
       AlertDialog.Builder(this@ConsoleWebActivity).setTitle("تأكيد إجراء IO Voice")
         .setMessage(message.orEmpty().take(300))
         .setPositiveButton("تأكيد"){_,_->result?.confirm()}
         .setNegativeButton("إلغاء"){_,_->result?.cancel()}
         .setOnCancelListener{result?.cancel()}.show()
       return true
     }
     override fun onJsAlert(view:WebView?,url:String?,message:String?,result:JsResult?):Boolean{
       if(!allowed(url.orEmpty())){result?.cancel();return true}
       AlertDialog.Builder(this@ConsoleWebActivity).setTitle("IO Voice")
         .setMessage(message.orEmpty().take(300))
         .setPositiveButton("حسنًا"){_,_->result?.confirm()}
         .setOnCancelListener{result?.cancel()}.show()
       return true
     }
   }
   onBackPressedDispatcher.addCallback(this,object:OnBackPressedCallback(true){
     override fun handleOnBackPressed(){if(web.canGoBack())web.goBack() else finish()}
   })
   val section=when(intent.getStringExtra("section")){
      "agents","answering","flows"->"?tab=agents"
      "devices"->"?tab=devices"
      else->""
   }
   val site=intent.getIntExtra("site_id",0)
   val agent=intent.getIntExtra("agent_id",0)
   val deepLink=if(section=="?tab=agents" && site in 1..1000000000 && agent in 1..1000000000)
       "&site_id=$site&agent_id=$agent" else ""
   web.loadUrl("https://app.ontrackegy.com/"+section+deepLink)
 }
 override fun onDestroy(){web.stopLoading();web.destroy();super.onDestroy()}
}
