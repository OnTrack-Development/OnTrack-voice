package com.ontrack.voiceproof

import android.app.Activity
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.view.Gravity
import android.view.View
import android.view.animation.*
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView

/**
 * An original OnTrack-branded motion screen while the local signed UI loads.
 * It never blocks the connection worker or interrupts a running phone call.
 */
internal class OnTrackLaunchScreen(private val activity:Activity, private val parent:FrameLayout) {
    private val px=activity.resources.displayMetrics.density
    private fun dp(v:Int)=(v*px).toInt()
    private val dark=Color.rgb(10,15,26)
    private val crimson=Color.rgb(227,48,76)
    private val root=FrameLayout(activity).apply{
        setBackgroundColor(dark)
        isClickable=true
        elevation=dp(26).toFloat()
    }
    private val mark=ImageView(activity).apply {
        setImageResource(R.drawable.ontrack_icon)
        scaleType=ImageView.ScaleType.FIT_CENTER
    }
    private val ring=View(activity).apply{
        background=GradientDrawable().apply{
            shape=GradientDrawable.OVAL
            setColor(Color.TRANSPARENT)
            setStroke(dp(2),0x60E3304C)
        }
    }
    private val status=TextView(activity).apply{
        text="جارٍ تجهيز مركز الاتصال…"
        textSize=13f
        setTextColor(0xFFABB9CF.toInt())
        gravity=Gravity.CENTER
    }
    private val spin=RotateAnimation(0f,360f,Animation.RELATIVE_TO_SELF,.5f,Animation.RELATIVE_TO_SELF,.5f).apply{
        duration=1900;repeatCount=Animation.INFINITE;interpolator=LinearInterpolator()
    }
    init {
        val content=LinearLayout(activity).apply{orientation=LinearLayout.VERTICAL;gravity=Gravity.CENTER}
        val symbol=FrameLayout(activity)
        symbol.addView(ring,FrameLayout.LayoutParams(dp(126),dp(126),Gravity.CENTER))
        symbol.addView(mark,FrameLayout.LayoutParams(dp(86),dp(86),Gravity.CENTER))
        content.addView(symbol,LinearLayout.LayoutParams(dp(152),dp(154)))
        content.addView(TextView(activity).apply{
            text="ONTRACK"
            textSize=29f;letterSpacing=.16f
            setTextColor(Color.WHITE)
            gravity=Gravity.CENTER
            typeface=Typeface.create("sans-serif-medium",Typeface.BOLD)
        },LinearLayout.LayoutParams(-1,dp(47)))
        content.addView(TextView(activity).apply{
            text="V O I C E  ·  I N T E L L I G E N C E"
            textSize=9.5f;letterSpacing=.06f
            setTextColor(crimson);gravity=Gravity.CENTER
        },LinearLayout.LayoutParams(-1,dp(37)))
        content.addView(status,LinearLayout.LayoutParams(-1,dp(90)))
        root.addView(content,FrameLayout.LayoutParams(-1,-1,Gravity.CENTER))
        parent.addView(root,FrameLayout.LayoutParams(-1,-1))
        ring.startAnimation(spin)
        root.alpha=1f
    }
    fun message(value:String){status.text=value}
    fun hide() {
        if(root.parent==null)return
        root.animate().alpha(0f).setDuration(420).setInterpolator(DecelerateInterpolator()).withEndAction {
            ring.clearAnimation()
            (root.parent as? FrameLayout)?.removeView(root)
        }.start()
    }
    fun reveal() {
        if(root.parent==null) {
            parent.addView(root,FrameLayout.LayoutParams(-1,-1))
        }
        root.animate().cancel();root.alpha=1f
        ring.startAnimation(spin)
    }
}
