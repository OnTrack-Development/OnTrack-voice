package com.ontrack.voiceproof

import android.content.Context
import android.content.Intent
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Path
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.provider.Settings
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.view.accessibility.AccessibilityNodeInfo
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.TextView
import kotlin.math.abs

/**
 * Compact native control over the ongoing SIM call.
 * No Accessibility, fake mute controls, injected HTML or second call service.
 * All buttons use existing verified service actions and update on actual state.
 */
internal class CallOverlay(private val context: Context) {
    private val window=context.getSystemService(WindowManager::class.java)
    private val dp=context.resources.displayMetrics.density
    private val red=Color.rgb(225,29,72)
    private val green=Color.rgb(16,185,129)
    private val black=Color.rgb(9,9,11)
    private val cardSurface=Color.rgb(18,18,22)
    private val white=Color.rgb(244,244,245)
    private val gray=Color.rgb(161,161,170)
    private var root:LinearLayout?=null
    private var orb:FrameLayout?=null
    private var orbGlyph:Glyph?=null
    private var menu:LinearLayout?=null
    private var status:TextView?=null
    private var title:TextView?=null
    private var toggle:LinearLayout?=null
    private var takeover:LinearLayout?=null
    private var speaker:LinearLayout?=null
    private var expand=false
    private var lightTheme=false
    private var currentColor=red
    private var params:WindowManager.LayoutParams?=null

    private fun px(value:Int)=(value*dp+0.5f).toInt()
    private fun themeLight()=context.getSharedPreferences("ontrack_link",Context.MODE_PRIVATE)
        .getString("ui_theme","dark")=="light"
    fun allowed()=Settings.canDrawOverlays(context)
    private fun shape(color:Int,radius:Int,stroke:Int?=null,strokeWidth:Int=1)=
        GradientDrawable().apply{
            setColor(color);cornerRadius=px(radius).toFloat()
            if(stroke!=null)setStroke(px(strokeWidth),stroke)
        }
    private fun text(value:String,size:Float=13f,bold:Boolean=false,color:Int=white)=
        TextView(context).apply{
            text=value;textSize=size;setTextColor(color)
            gravity=Gravity.CENTER_VERTICAL
            typeface=Typeface.create("sans-serif",if(bold)Typeface.BOLD else Typeface.NORMAL)
        }

    /** Draw simple consistent line icons rather than font emoji or OEM assets. */
    private inner class Glyph(private var symbol:String, tint:Int):View(context) {
        private val p=Paint(Paint.ANTI_ALIAS_FLAG).apply{
            color=tint;strokeWidth=2.1f;strokeCap=Paint.Cap.ROUND
            strokeJoin=Paint.Join.ROUND;style=Paint.Style.STROKE
        }
        fun configure(newSymbol:String,newTint:Int){symbol=newSymbol;p.color=newTint;invalidate()}
        override fun onDraw(canvas:Canvas){
            super.onDraw(canvas)
            val save=canvas.save()
            val side=minOf(width,height).toFloat()
            canvas.translate((width-side)/2f,(height-side)/2f)
            canvas.scale(side/24f,side/24f)
            when(symbol){
                "power"->{canvas.drawArc(5f,5f,19f,19f,-54f,288f,false,p);canvas.drawLine(12f,3f,12f,12f,p)}
                "person"->{
                    canvas.drawCircle(12f,7f,3.1f,p)
                    canvas.drawArc(5f,11f,19f,23f,187f,166f,false,p)
                }
                "speaker"->{
                    val path=Path().apply{
                        moveTo(3f,9f);lineTo(7.4f,9f);lineTo(12f,5f)
                        lineTo(12f,19f);lineTo(7.4f,15f);lineTo(3f,15f);close()
                    }
                    canvas.drawPath(path,p)
                    canvas.drawArc(10f,6f,20f,18f,-66f,132f,false,p)
                    canvas.drawArc(7.5f,2.5f,23f,21.5f,-62f,124f,false,p)
                }
                "home"->{
                    val path=Path().apply{
                        moveTo(3f,11f);lineTo(12f,4f);lineTo(21f,11f)
                        moveTo(6f,10f);lineTo(6f,20f);lineTo(18f,20f);lineTo(18f,10f)
                        moveTo(10f,20f);lineTo(10f,14f);lineTo(14f,14f);lineTo(14f,20f)
                    };canvas.drawPath(path,p)
                }
                "close"->{canvas.drawLine(6f,6f,18f,18f,p);canvas.drawLine(18f,6f,6f,18f,p)}
                "phone"->{
                    val path=Path().apply{
                        moveTo(5f,4f);lineTo(8f,3f);lineTo(11f,8f);lineTo(9f,10f)
                        cubicTo(10f,13f,12f,15f,15f,16f)
                        lineTo(17f,14f);lineTo(21f,17f);lineTo(20f,20f)
                        cubicTo(17f,23f,10f,19f,6f,14f)
                        cubicTo(3f,10f,2f,6f,5f,4f)
                    };canvas.drawPath(path,p)
                }
            }
            canvas.restoreToCount(save)
        }
    }
    private fun serviceAction(command:String){
        context.startService(Intent(context,MobileLinkService::class.java).setAction(command))
        collapse()
    }
    private fun collapse(){
        expand=false
        orbGlyph?.configure("power",white)
        menu?.visibility=View.GONE
        root?.requestLayout()
        params?.let{p->root?.let{v->runCatching{window.updateViewLayout(v,p)}}}
    }
    private fun iconTile(symbol:String,label:String,description:String,color:Int,enabled:Boolean,onClick:()->Unit):LinearLayout{
        val foreground=if(lightTheme)Color.rgb(9,9,11) else white
        val surface=if(lightTheme)Color.rgb(250,250,250) else cardSurface
        val border=if(lightTheme)Color.rgb(228,228,231) else Color.rgb(39,39,42)
        return LinearLayout(context).apply{
            orientation=LinearLayout.VERTICAL
            gravity=Gravity.CENTER
            setPadding(px(6),px(12),px(6),px(10))
            background=shape(surface,14,border)
            alpha=if(enabled)1f else .44f
            isEnabled=enabled;isClickable=enabled;isFocusable=enabled
            contentDescription="$label، $description"
            accessibilityDelegate=object:View.AccessibilityDelegate(){
                override fun onInitializeAccessibilityNodeInfo(host:View,info:AccessibilityNodeInfo){
                    super.onInitializeAccessibilityNodeInfo(host,info)
                    info.className="android.widget.Button"
                }
            }
            val backgroundColor=if(lightTheme)Color.rgb(255,235,239) else Color.rgb(63,25,34)
            val icon=FrameLayout(context).apply{background=shape(backgroundColor,12)}
            icon.addView(Glyph(symbol,color),FrameLayout.LayoutParams(px(24),px(24),Gravity.CENTER))
            addView(icon,LinearLayout.LayoutParams(px(44),px(44)))
            addView(text(label,12f,true,foreground).apply{gravity=Gravity.CENTER},
                LinearLayout.LayoutParams(-1,px(27)))
            addView(text(description,10f,false,if(lightTheme)Color.rgb(93,93,101) else Color.rgb(183,183,190))
                .apply{gravity=Gravity.CENTER;textAlignment=View.TEXT_ALIGNMENT_CENTER;maxLines=2},
                LinearLayout.LayoutParams(-1,px(34)))
            if(enabled)setOnClickListener{onClick()}
        }
    }
    private fun attach(){
        lightTheme=themeLight()
        val panelSurface=if(lightTheme)Color.WHITE else cardSurface
        val border=if(lightTheme)Color.rgb(228,228,231) else Color.rgb(39,39,42)
        val container=LinearLayout(context).apply{
            orientation=LinearLayout.VERTICAL;gravity=Gravity.END
            layoutDirection=View.LAYOUT_DIRECTION_RTL
        }
        val circle=FrameLayout(context).apply{
            background=shape(black,60,red,2)
            elevation=px(7).toFloat()
            isClickable=true;isFocusable=true
            contentDescription="فتح عناصر تحكم IO Voice"
            accessibilityDelegate=object:View.AccessibilityDelegate(){
                override fun onInitializeAccessibilityNodeInfo(host:View,info:AccessibilityNodeInfo){
                    super.onInitializeAccessibilityNodeInfo(host,info);info.className="android.widget.Button"
                }
            }
        }
        orbGlyph=Glyph("power",white).also{circle.addView(it,
            FrameLayout.LayoutParams(px(26),px(26),Gravity.CENTER))}
        container.addView(circle,LinearLayout.LayoutParams(px(58),px(58)).apply{gravity=Gravity.END})
        val sheet=LinearLayout(context).apply{
            orientation=LinearLayout.VERTICAL
            layoutDirection=View.LAYOUT_DIRECTION_RTL
            setPadding(px(13),px(13),px(13),px(13))
            background=shape(panelSurface,20,border)
            elevation=px(10).toFloat()
            visibility=View.GONE
        }
        title=text("IO Voice",16f,true,if(lightTheme)black else white)
        status=text("الحالة",12f,false,gray)
        val header=LinearLayout(context).apply{
            orientation=LinearLayout.VERTICAL
            addView(title,LinearLayout.LayoutParams(-1,px(28)))
            addView(status,LinearLayout.LayoutParams(-1,px(22)))
        }
        sheet.addView(header,LinearLayout.LayoutParams(-1,px(58)))
        // Two tidy rows, two square touch targets each. No mock/inert controls.
        val first=LinearLayout(context).apply{orientation=LinearLayout.HORIZONTAL}
        val second=LinearLayout(context).apply{orientation=LinearLayout.HORIZONTAL}
        sheet.addView(first)
        sheet.addView(second,LinearLayout.LayoutParams(-1,-2).apply{topMargin=px(8)})
        val note=text("الاستلام منك للإيجنت يخص المكالمة الحالية فقط",10f,false,gray).apply{gravity=Gravity.CENTER}
        sheet.addView(note,LinearLayout.LayoutParams(-1,px(30)).apply{topMargin=px(6)})
        container.addView(sheet,LinearLayout.LayoutParams(px(284),-2).apply{
            topMargin=px(8);gravity=Gravity.END
        })
        val lp=WindowManager.LayoutParams(-2,-2,WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
            PixelFormat.TRANSLUCENT).apply{
            gravity=Gravity.TOP or Gravity.END;x=px(14)
            // Default near bottom, not over the header/hero. Still freely draggable.
            y=(context.resources.displayMetrics.heightPixels-px(240)).coerceAtLeast(px(170))
        }
        var startX=0;var startY=0;var touchX=0f;var touchY=0f
        circle.setOnTouchListener {_,event->
            when(event.actionMasked){
                MotionEvent.ACTION_DOWN->{
                    startX=lp.x;startY=lp.y;touchX=event.rawX;touchY=event.rawY;true
                }
                MotionEvent.ACTION_MOVE->{
                    lp.x=(startX+touchX-event.rawX).toInt().coerceIn(0,px(240))
                    lp.y=(startY+event.rawY-touchY).toInt()
                        .coerceIn(px(28),context.resources.displayMetrics.heightPixels-px(170))
                    runCatching{window.updateViewLayout(container,lp)}
                    true
                }
                MotionEvent.ACTION_UP->{
                    if(abs(event.rawX-touchX)<px(12)&&abs(event.rawY-touchY)<px(12)){
                        expand=!expand
                        orbGlyph?.configure(if(expand)"close" else "power",white)
                        sheet.visibility=if(expand)View.VISIBLE else View.GONE
                        if(expand)renderActions()
                        container.requestLayout()
                        runCatching{window.updateViewLayout(container,lp)}
                    };true
                }
                MotionEvent.ACTION_CANCEL->true
                else->false
            }
        }
        try{
            window.addView(container,lp)
            root=container;orb=circle;menu=sheet;params=lp
        }catch(_:Exception){
            root=null;orb=null;menu=null;orbGlyph=null;params=null
        }
    }
    private var lastReady=false
    private var lastEnabled=false
    private var lastCall=false
    private var lastAnswered=false
    private var lastHuman=false
    private var lastAudio=false
    private var lastCallProblem=""
    private var lastAudioDiagnostic=""
    private fun renderActions(){
        val sheet=menu?:return
        if(!expand)return
        val first=sheet.getChildAt(1) as LinearLayout
        val second=sheet.getChildAt(2) as LinearLayout
        first.removeAllViews();second.removeAllViews()
        val powerOn=lastEnabled
        val power=iconTile("power",
            if(powerOn)"إيقاف الإيجنت" else "تشغيل الإيجنت",
            if(powerOn)"تعطيل الرد الآلي" else "تفعيل الرد الآلي",
            if(powerOn)red else green,true){
            serviceAction(if(powerOn)MobileLinkService.ACTION_DISABLE else MobileLinkService.ACTION_ENABLE)
        }
        val model=overlayUiModel(lastReady,lastEnabled,lastCall,lastAnswered,lastHuman,lastAudio,lastCallProblem,lastAudioDiagnostic)
        val canTake=model.canTakeover
        val handoff=iconTile("person",
            model.takeoverTitle,model.takeoverHint,if(canTake)green else gray,canTake){
            serviceAction(MobileLinkService.ACTION_TAKEOVER)
        }
        val audio=iconTile("speaker","مخرج الصوت",
            if(model.canSwitchSpeaker)"تبديل السماعة" else "بعد الرد فقط",
            red,model.canSwitchSpeaker){serviceAction(MobileLinkService.ACTION_SPEAKER)}
        val home=iconTile("home","فتح التطبيق","التحكم الكامل",red,true){
            collapse()
            context.startActivity(Intent(context,MobileLinkActivity::class.java)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP))
        }
        fun add(row:LinearLayout,tile:LinearLayout,margin:Boolean){
            row.addView(tile,LinearLayout.LayoutParams(0,px(119),1f).apply{
                if(margin)marginStart=px(8)
            })
        }
        add(first,power,false);add(first,handoff,true)
        add(second,audio,false);add(second,home,true)
    }
    fun show(serviceReady:Boolean,enabled:Boolean,inCall:Boolean,answered:Boolean,human:Boolean,
             audioReady:Boolean,callProblem:String,audioDiagnostic:String){
        if(!allowed()){remove();return}
        if(root!=null&&themeLight()!=lightTheme)remove()
        if(root==null)attach()
        val ready=serviceReady
        lastReady=ready;lastEnabled=enabled;lastCall=inCall
        lastAnswered=answered;lastHuman=human
        lastAudio=audioReady;lastCallProblem=callProblem;lastAudioDiagnostic=audioDiagnostic
        val model=overlayUiModel(ready,enabled,inCall,answered,human,audioReady,callProblem,audioDiagnostic)
        val color=if(model.running && callProblem.isBlank())green else red
        val changed=color!=currentColor
        currentColor=color
        orb?.background=shape(color,60,if(lightTheme)white else black,2)
        orbGlyph?.invalidate()
        orb?.contentDescription=model.announcement
        status?.apply{
            text="● "+model.status
            setTextColor(color)
        }
        if(expand){
            // Only rebuild controls on real state changes; preserve focus and avoid UI flicker.
            val token="$ready:$enabled:$inCall:$answered:$human:$audioReady:$callProblem:$audioDiagnostic:$lightTheme"
            if(token!=renderedToken||changed){renderedToken=token;renderActions()}
        }
    }
    private var renderedToken=""
    fun remove(){
        root?.let{runCatching{window.removeView(it)}}
        root=null;orb=null;orbGlyph=null;menu=null;title=null;status=null;params=null
        toggle=null;takeover=null;speaker=null;expand=false;renderedToken=""
    }
}
