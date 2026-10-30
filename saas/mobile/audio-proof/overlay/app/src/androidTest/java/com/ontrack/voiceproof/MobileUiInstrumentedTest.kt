package com.ontrack.voiceproof

import android.view.View
import android.view.ViewGroup
import android.webkit.WebView
import androidx.test.core.app.ActivityScenario
import androidx.test.platform.app.InstrumentationRegistry
import androidx.test.ext.junit.runners.AndroidJUnit4
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import java.io.File
import org.json.JSONObject
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicReference

@RunWith(AndroidJUnit4::class)
class MobileUiInstrumentedTest {
    private fun find(view:View):WebView? {
        if(view is WebView)return view
        if(view is ViewGroup)for(i in 0 until view.childCount){val child=find(view.getChildAt(i));if(child!=null)return child}
        return null
    }
    private fun js(scenario:ActivityScenario<MobileLinkActivity>,script:String):String {
        val latch=CountDownLatch(1);val result=AtomicReference("")
        scenario.onActivity{activity->find(activity.findViewById(android.R.id.content))!!.evaluateJavascript(script){result.set(it);latch.countDown()}}
        assertTrue("WebView JS callback",latch.await(5,TimeUnit.SECONDS));return result.get()
    }
    private fun await(scenario:ActivityScenario<MobileLinkActivity>,script:String){
        val until=System.currentTimeMillis()+25000
        while(System.currentTimeMillis()<until){if(js(scenario,script)=="true")return;Thread.sleep(250)}
        fail("Local signed UI failed to render or native ready/init bridge failed")
    }
    @Test fun localMainDocumentRendersAndNativeBridgeInitializes(){
        val context=InstrumentationRegistry.getInstrumentation().targetContext
        val fake="instrumentation_only_key_12345678"
        context.getSharedPreferences("ontrack_link",0).edit().putString("base","https://app.ontrackegy.com/").putString("key",fake).commit()
        // Model the failed0.1.6 OTA remembered on the owner phone.
        val cache=File(context.filesDir,"mobile-ota").apply{mkdirs()}
        File(cache,"state.json").writeText("{\"highest\":3,\"rejected\":3,\"pending\":false}")
        ActivityScenario.launch(MobileLinkActivity::class.java).use{scenario->
            await(scenario,"document.title==='OnTrack' && document.getElementById('key')?.value==='instrumentation_only_key_12345678'")
            scenario.onActivity{activity->assertTrue(find(activity.findViewById(android.R.id.content))!!.url!!.startsWith("https://ontrack-ui.invalid/ui/"))}
            // No actual call or real connection key: test only visible local UI.
            js(scenario,"document.getElementById('key').value='bad';document.getElementById('start').disabled=false;document.getElementById('start').click();true")
            await(scenario,"document.getElementById('error').textContent.length>0")
            assertFalse(MobileLinkService.running)
            val state=JSONObject(File(cache,"state.json").readText())
            assertEquals(context.packageManager.getPackageInfo(context.packageName,0).longVersionCode,state.getLong("native_version"))
            assertFalse(state.has("rejected"))
        }
    }
}
