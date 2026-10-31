package com.ontrack.voiceproof

import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.provider.Settings
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.LinearLayout
import android.widget.TextView
import kotlin.math.abs

/** Brand-aligned, user-consented TYPE_APPLICATION_OVERLAY. No Accessibility. */
internal class CallOverlay(private val context:Context){
    private val window=context.getSystemService(WindowManager::class.java)
    private val density=context.resources.displayMetrics.density
    private val bg=Color.rgb(14,20,33)
    private val muted=Color.rgb(164,179,200)
    private val accent=Color.rgb(227,48,76)
    private val green=Color.rgb(39,205,155)
    private var root:LinearLayout?=null
    private var head:TextView?=null
    private var controls:LinearLayout?=null
    private var takeoverButton:TextView?=null
    private var speakerButton:TextView?=null
    private var powerButton:TextView?=null
    private var expanded=false
    private fun px(value:Int)=(value*density).toInt()
    fun allowed()=Settings.canDrawOverlays(context)
    private fun shape(color:Int,radius:Int=14)=GradientDrawable().apply{
        setColor(color);cornerRadius=px(radius).toFloat()
        setStroke(px(1),Color.rgb(48,62,84))
    }
    private fun action(title:String,background:Int,command:String):TextView{
        return TextView(context).apply{
            text=title;setTextColor(Color.WHITE);textSize=12.5f
            typeface=Typeface.create("sans-serif-medium",Typeface.NORMAL)
            gravity=Gravity.CENTER;setPadding(px(12),px(11),px(12),px(11))
            this.background=shape(background,12)
            setOnClickListener{context.startService(Intent(context,MobileLinkService::class.java).setAction(command))}
        }
    }
    fun show(serviceReady:Boolean,enabled:Boolean,inCall:Boolean,human:Boolean){
        if(!allowed()){remove();return}
        if(root==null){
            val panel=LinearLayout(context).apply{
                orientation=LinearLayout.VERTICAL
                background=shape(bg,18)
                elevation=px(14).toFloat()
                setPadding(px(9),px(8),px(9),px(8))
                layoutDirection=View.LAYOUT_DIRECTION_RTL
            }
            head=TextView(context).apply{
                textSize=13f;setTextColor(Color.WHITE)
                typeface=Typeface.create("sans-serif-medium",Typeface.BOLD)
                setPadding(px(10),px(7),px(10),px(7))
                gravity=Gravity.CENTER_VERTICAL
            }.also{panel.addView(it)}
            controls=LinearLayout(context).apply{
                orientation=LinearLayout.VERTICAL;visibility=View.GONE
            }.also{panel.addView(it)}
            val callRow=LinearLayout(context).apply{orientation=LinearLayout.HORIZONTAL}
            takeoverButton=action("استلام المكالمة",Color.rgb(25,104,89),MobileLinkService.ACTION_TAKEOVER)
            speakerButton=action("السماعة",Color.rgb(42,57,78),MobileLinkService.ACTION_SPEAKER)
            callRow.addView(takeoverButton,LinearLayout.LayoutParams(0,px(46),2f).apply{marginEnd=px(6)})
            callRow.addView(speakerButton,LinearLayout.LayoutParams(0,px(46),1f))
            controls!!.addView(callRow)
            powerButton=action("إيقاف الإيجنت",Color.rgb(111,35,56),MobileLinkService.ACTION_DISABLE)
            controls!!.addView(powerButton,LinearLayout.LayoutParams(-1,px(46)).apply{topMargin=px(7)})
            val params=WindowManager.LayoutParams(-2,-2,WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
                PixelFormat.TRANSLUCENT).apply{
                gravity=Gravity.TOP or Gravity.END;x=px(12);y=px(98)
            }
            var startX=0;var startY=0;var touchX=0f;var touchY=0f
            head!!.setOnTouchListener{_:View,event:MotionEvent->
                when(event.actionMasked){
                    MotionEvent.ACTION_DOWN->{startX=params.x;startY=params.y;touchX=event.rawX;touchY=event.rawY;true}
                    MotionEvent.ACTION_MOVE->{
                        params.x=(startX+touchX-event.rawX).toInt().coerceAtLeast(0)
                        params.y=(startY+event.rawY-touchY).toInt().coerceAtLeast(0)
                        runCatching{window.updateViewLayout(panel,params)};true
                    }
                    MotionEvent.ACTION_UP->{
                        if(abs(event.rawX-touchX)<px(12)&&abs(event.rawY-touchY)<px(12)){
                            expanded=!expanded
                            controls?.visibility=if(expanded)View.VISIBLE else View.GONE
                        };true
                    }
                    else->false
                }
            }
            try{window.addView(panel,params);root=panel}
            catch(_:Exception){root=null;controls=null;head=null}
        }
        head?.text=when{
            inCall&&human->"● أون تراك  |  استلام بشري  ▾"
            inCall->"● أون تراك  |  مكالمة AI  ▾"
            !serviceReady->"● أون تراك  |  إعادة الاتصال  ▾"
            enabled->"● أون تراك  |  الإيجنت نشط  ▾"
            serviceReady->"● أون تراك  |  متوقف  ▾"
            else->"● أون تراك  |  غير متصل  ▾"
        }
        head?.setTextColor(if(enabled||inCall)green else muted)
        controls?.visibility=if(expanded)View.VISIBLE else View.GONE
        takeoverButton?.visibility=if(inCall&&!human)View.VISIBLE else View.GONE
        speakerButton?.visibility=if(inCall)View.VISIBLE else View.GONE
        powerButton?.apply{
            text=if(enabled)"إيقاف الإيجنت" else "تشغيل الإيجنت"
            setOnClickListener{
                context.startService(Intent(context,MobileLinkService::class.java)
                    .setAction(if(enabled)MobileLinkService.ACTION_DISABLE else MobileLinkService.ACTION_ENABLE))
            }
        }
    }
    fun remove(){
        root?.let{runCatching{window.removeView(it)}}
        root=null;head=null;controls=null;takeoverButton=null;speakerButton=null;powerButton=null
        expanded=false
    }
}
