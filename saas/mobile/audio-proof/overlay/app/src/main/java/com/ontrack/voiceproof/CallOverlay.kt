package com.ontrack.voiceproof

import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.provider.Settings
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView
import kotlin.math.abs

/**
 * User-opt-in, small movable status overlay. Only TYPE_APPLICATION_OVERLAY:
 * never AccessibilityService, never captures input outside its own buttons.
 * Safe fallback is the persistent foreground notification when not granted.
 */
internal class CallOverlay(private val context: Context) {
    private val manager=context.getSystemService(WindowManager::class.java)
    private var root: LinearLayout?=null
    private var params: WindowManager.LayoutParams?=null
    private var active=false
    private var human=false
    private var expanded=false
    private var label:TextView?=null
    private var actions:LinearLayout?=null
    private var speaker:Button?=null
    private val d=context.resources.displayMetrics.density
    fun allowed():Boolean=Settings.canDrawOverlays(context)
    private fun send(action:String){
        context.startService(Intent(context,MobileLinkService::class.java).setAction(action))
    }
    private fun button(title:String,action:String):Button=Button(context).apply {
        text=title;isAllCaps=false;textSize=12f;setTextColor(Color.WHITE)
        setBackgroundColor(Color.rgb(46,58,83))
        setPadding((7*d).toInt(),0,(7*d).toInt(),0)
        setOnClickListener{send(action)}
    }
    fun show(inCall:Boolean,handedOff:Boolean){
        active=inCall;human=handedOff
        if(!allowed()){remove();return}
        if(root==null){
            val container=LinearLayout(context).apply {
                orientation=LinearLayout.VERTICAL
                setPadding((8*d).toInt(),(6*d).toInt(),(8*d).toInt(),(6*d).toInt())
                setBackgroundColor(Color.rgb(17,25,42))
                elevation=10*d
            }
            val headline=TextView(context).apply{
                setTextColor(Color.WHITE);textSize=13f;setTypeface(null,Typeface.BOLD)
                setPadding((5*d).toInt(),(3*d).toInt(),(5*d).toInt(),(3*d).toInt())
            }
            label=headline
            container.addView(headline)
            val controls=LinearLayout(context).apply{orientation=LinearLayout.VERTICAL;visibility=View.GONE}
            controls.addView(button("استلام المكالمة",MobileLinkService.ACTION_TAKEOVER))
            controls.addView(button("السماعة الخارجية (تجريبي)",MobileLinkService.ACTION_SPEAKER))
            controls.addView(button("إيقاف الإيجنت",MobileLinkService.ACTION_PAUSE))
            actions=controls
            container.addView(controls)
            val lp=WindowManager.LayoutParams(
                WindowManager.LayoutParams.WRAP_CONTENT,WindowManager.LayoutParams.WRAP_CONTENT,
                WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
                PixelFormat.TRANSLUCENT
            ).apply {gravity=Gravity.TOP or Gravity.END;x=(12*d).toInt();y=(100*d).toInt()}
            var initialX=0;var initialY=0;var touchX=0f;var touchY=0f
            headline.setOnTouchListener { _:View,event:MotionEvent ->
                when(event.actionMasked){
                    MotionEvent.ACTION_DOWN->{initialX=lp.x;initialY=lp.y;touchX=event.rawX;touchY=event.rawY;true}
                    MotionEvent.ACTION_MOVE->{
                        lp.x=(initialX+touchX-event.rawX).toInt().coerceAtLeast(0)
                        lp.y=(initialY+event.rawY-touchY).toInt().coerceAtLeast(0)
                        runCatching{manager.updateViewLayout(container,lp)};true
                    }
                    MotionEvent.ACTION_UP->{
                        if(abs(event.rawX-touchX)<12*d && abs(event.rawY-touchY)<12*d){
                            expanded=!expanded
                            controls.visibility=if(expanded)View.VISIBLE else View.GONE
                        }
                        true
                    }
                    else->false
                }
            }
            try{manager.addView(container,lp);root=container;params=lp}
            catch(_:Exception){root=null;params=null}
        }
        label?.text=when{
            human->"● OnTrack • التحكم البشري  ▾"
            active->"● OnTrack • AI يتحدث  ▾"
            else->"● OnTrack • جاهز  ▾"
        }
        actions?.visibility=if(expanded && active) View.VISIBLE else View.GONE
    }
    fun remove(){
        root?.let{runCatching{manager.removeView(it)}}
        root=null;params=null;label=null;actions=null;speaker=null
    }
}
