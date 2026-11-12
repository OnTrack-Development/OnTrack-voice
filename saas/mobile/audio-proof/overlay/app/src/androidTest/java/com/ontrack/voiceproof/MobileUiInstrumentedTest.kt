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
    // Cold-boot Android emulators may stall the UI thread for >5s while initializing WebView.
    // Still fail unless the actual signed page and JS bridge initialize within the deadline, including a slow API-34 cold boot.
    private fun js(scenario:ActivityScenario<MobileLinkActivity>,script:String):String {
        val latch=CountDownLatch(1);val result=AtomicReference("")
        scenario.onActivity{activity->find(activity.findViewById(android.R.id.content))!!.evaluateJavascript(script){result.set(it);latch.countDown()}}
        assertTrue("WebView JS callback",latch.await(40,TimeUnit.SECONDS));return result.get()
    }
    private fun await(scenario:ActivityScenario<MobileLinkActivity>,script:String){
        val until=System.currentTimeMillis()+90000
        while(System.currentTimeMillis()<until){if(js(scenario,script)=="true")return;Thread.sleep(250)}
        fail("Local signed UI failed to render or native ready/init bridge failed")
    }
    @Test fun localMainDocumentRendersAndNativeBridgeInitializes(){
        val context=InstrumentationRegistry.getInstrumentation().targetContext
        // Seed a historical link solely to verify that an in-place update
        // preserves private app data. The old bearer is not used by tenant voice.
        // Never force re-enrollment merely because an APK update was installed.
        context.getSharedPreferences("ontrack_link",0).edit()
            .putString("key","instrumentation_only_key_12345678")
            .putString("base","https://app.ontrackegy.com/").commit()
        // Model the failed 0.1.6 OTA remembered on the owner phone.
        val cache=File(context.filesDir,"mobile-ota").apply{mkdirs()}
        File(cache,"state.json").writeText("{\"highest\":3,\"rejected\":3,\"pending\":false}")
        ActivityScenario.launch(MobileLinkActivity::class.java).use{scenario->
            await(scenario,"document.title==='IO Voice' && document.getElementById('tenantWorkspaceSlot')!==null && document.getElementById('key')===null && typeof window.onTrackState==='function'")
            scenario.onActivity{activity->assertTrue(find(activity.findViewById(android.R.id.content))!!.url!!.startsWith("https://ontrack-ui.invalid/ui/"))}
            // No SIM enrollment in this emulator: the UI must block enable
            // without ever asking for a Pilot key or attempting a real call.
            js(scenario,"document.getElementById('enable').disabled=false;document.getElementById('enable').click();true")
            await(scenario,"document.getElementById('message').textContent.includes('SIM')")
            assertEquals("true",js(scenario,"!document.getElementById('key') && !document.getElementById('copyKey') && !document.getElementById('pair')"))
            assertEquals("Update must not erase the saved device data",
                "instrumentation_only_key_12345678",
                context.getSharedPreferences("ontrack_link",0).getString("key",null))
            assertEquals("Update must not erase saved endpoint data",
                "https://app.ontrackegy.com/",
                context.getSharedPreferences("ontrack_link",0).getString("base",null))
            // The old link remains dormant: no Pilot fields or buttons exist
            // and an unpaired SIM cannot run the Android AI service.
            assertFalse(MobileLinkService.running)
            val state=JSONObject(File(cache,"state.json").readText())
            assertEquals(context.packageManager.getPackageInfo(context.packageName,0).longVersionCode,state.getLong("native_version"))
            assertFalse(state.has("rejected"))
        }
    }
}
