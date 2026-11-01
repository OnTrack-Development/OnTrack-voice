package com.ontrack.voiceproof
import android.annotation.SuppressLint
import android.os.Bundle
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
   return u.scheme=="https"&&u.host=="app.ontrackegy.com"&&u.port in listOf(-1,443)&&
      u.userInfo==null&&(p=="/saas/console"||p.startsWith("/saas/console/"))
 }
 @SuppressLint("SetJavaScriptEnabled") override fun onCreate(savedInstanceState:Bundle?){
   super.onCreate(savedInstanceState)
   val layout=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL}
   val top=LinearLayout(this)
   val back=Button(this).apply{text="رجوع";setOnClickListener{if(web.canGoBack())web.goBack() else finish()}}
   val close=Button(this).apply{text="إغلاق";setOnClickListener{finish()}}
   top.addView(back,LinearLayout.LayoutParams(0,-2,1f))
   top.addView(close,LinearLayout.LayoutParams(0,-2,1f))
   layout.addView(top)
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
   }
   onBackPressedDispatcher.addCallback(this,object:OnBackPressedCallback(true){
     override fun handleOnBackPressed(){if(web.canGoBack())web.goBack() else finish()}
   })
   web.loadUrl("https://app.ontrackegy.com/saas/console/"+
      if(intent.getStringExtra("section")=="agents")"?tab=agents" else "")
 }
 override fun onDestroy(){web.stopLoading();web.destroy();super.onDestroy()}
}
