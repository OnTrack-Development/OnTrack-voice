package com.ontrack.voiceproof

import android.animation.AnimatorSet
import android.animation.ObjectAnimator
import android.app.Activity
import android.content.res.ColorStateList
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.view.Gravity
import android.view.View
import android.view.animation.AccelerateDecelerateInterpolator
import android.view.animation.DecelerateInterpolator
import android.view.animation.OvershootInterpolator
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView

/**
 * Original OnTrack native motion: visible from the first Activity frame.
 * Never invent download percentages. The progress line becomes determinate
 * only while bytes from a signed UI OTA are actually arriving.
 * Not a substitute for Android's OS-owned APK installer/Play Protect dialogs.
 */
internal class OnTrackLaunchScreen(private val activity:Activity, private val parent:FrameLayout) {
    private val density=activity.resources.displayMetrics.density
    private fun dp(v:Int)=(v*density).toInt()
    private val bg=Color.rgb(8,9,12)
    private val red=Color.rgb(220,17,44)
    private val root=FrameLayout(activity).apply {
        setBackgroundColor(bg)
        isClickable=true
        elevation=dp(26).toFloat()
    }
    private val mark=ImageView(activity).apply {
        setImageResource(R.drawable.ontrack_icon)
        scaleType=ImageView.ScaleType.FIT_CENTER
        contentDescription="OnTrack Voice"
    }
    private val glint=View(activity).apply {
        background=GradientDrawable(GradientDrawable.Orientation.LEFT_RIGHT,
            intArrayOf(Color.TRANSPARENT,red,Color.TRANSPARENT)).apply {
            cornerRadius=dp(4).toFloat()
        }
    }
    private val wordmark=TextView(activity).apply {
        text="OnTrack"
        textSize=30f
        letterSpacing=.02f
        typeface=Typeface.create("sans-serif-medium",Typeface.BOLD)
        gravity=Gravity.CENTER
        setTextColor(Color.WHITE)
    }
    private val caption=TextView(activity).apply {
        text="VOICE INTELLIGENCE"
        textSize=10f
        letterSpacing=.24f
        gravity=Gravity.CENTER
        setTextColor(0xFFE86A7D.toInt())
    }
    private val status=TextView(activity).apply {
        text="جارٍ تجهيز OnTrack Voice…"
        textSize=12f
        setTextColor(0xFFC4C4C8.toInt())
        gravity=Gravity.CENTER
        maxLines=2
    }
    private val progressBar=ProgressBar(activity,null,android.R.attr.progressBarStyleHorizontal).apply {
        isIndeterminate=true
        max=100
        progressTintList=ColorStateList.valueOf(red)
        indeterminateTintList=ColorStateList.valueOf(red)
        progressBackgroundTintList=ColorStateList.valueOf(0xFF303036.toInt())
    }
    private val motion=AnimatorSet()
    private var closing=false

    init {
        val content=LinearLayout(activity).apply {
            orientation=LinearLayout.VERTICAL
            gravity=Gravity.CENTER
            setPadding(dp(24),0,dp(24),0)
        }
        val artwork=FrameLayout(activity)
        artwork.addView(mark,FrameLayout.LayoutParams(dp(108),dp(108),Gravity.CENTER))
        artwork.addView(glint,FrameLayout.LayoutParams(dp(82),dp(3),Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL).apply {
            bottomMargin=dp(4)
        })
        content.addView(artwork,LinearLayout.LayoutParams(-1,dp(150)))
        content.addView(wordmark,LinearLayout.LayoutParams(-1,dp(45)))
        content.addView(caption,LinearLayout.LayoutParams(-1,dp(31)))
        root.addView(content,FrameLayout.LayoutParams(-1,-1,Gravity.CENTER))

        val footer=LinearLayout(activity).apply {
            orientation=LinearLayout.VERTICAL
            gravity=Gravity.CENTER
            setPadding(dp(38),0,dp(38),0)
        }
        footer.addView(status,LinearLayout.LayoutParams(-1,dp(40)))
        footer.addView(progressBar,LinearLayout.LayoutParams(-1,dp(3)))
        root.addView(footer,FrameLayout.LayoutParams(-1,dp(80),Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL).apply {
            bottomMargin=dp(82)
        })
        parent.addView(root,FrameLayout.LayoutParams(-1,-1))
        startMotion()
    }

    private fun startMotion() {
        closing=false
        motion.cancel()
        root.animate().cancel()
        root.alpha=1f
        root.translationX=0f
        mark.alpha=0f
        mark.scaleX=.72f
        mark.scaleY=.72f
        mark.translationY=dp(12).toFloat()
        mark.translationX=dp(92).toFloat()
        mark.rotation=-14f
        wordmark.alpha=0f
        caption.alpha=0f
        glint.alpha=0f
        // Logo sweeps from the right, levels out, then glides into place.
        mark.animate().alpha(1f).scaleX(1f).scaleY(1f)
            .translationY(0f).translationX(0f).rotation(0f)
            .setDuration(850).setInterpolator(OvershootInterpolator(.58f)).start()
        wordmark.animate().alpha(1f).setStartDelay(260).setDuration(420).start()
        caption.animate().alpha(1f).setStartDelay(410).setDuration(400).start()
        glint.animate().alpha(1f).setStartDelay(550).setDuration(350).start()

        val x=ObjectAnimator.ofFloat(mark,"scaleX",1f,1.055f,1f).apply {
            duration=2700
            repeatCount=ObjectAnimator.INFINITE
            interpolator=AccelerateDecelerateInterpolator()
            startDelay=750
        }
        val y=ObjectAnimator.ofFloat(mark,"scaleY",1f,1.055f,1f).apply {
            duration=2700
            repeatCount=ObjectAnimator.INFINITE
            interpolator=AccelerateDecelerateInterpolator()
            startDelay=750
        }
        val shine=ObjectAnimator.ofFloat(glint,"translationX",-dp(24).toFloat(),dp(24).toFloat(),-dp(24).toFloat()).apply {
            duration=3000
            repeatCount=ObjectAnimator.INFINITE
            interpolator=AccelerateDecelerateInterpolator()
            startDelay=800
        }
        // Gentle lateral sweep and tilt are original OnTrack motion, not borrowed Lottie assets.
        val glide=ObjectAnimator.ofFloat(mark,"translationX",0f,-dp(13).toFloat(),0f,dp(7).toFloat(),0f).apply {
            duration=3600;repeatCount=ObjectAnimator.INFINITE
            interpolator=AccelerateDecelerateInterpolator();startDelay=980
        }
        val tilt=ObjectAnimator.ofFloat(mark,"rotation",0f,-6f,2f,0f).apply {
            duration=3600;repeatCount=ObjectAnimator.INFINITE
            interpolator=AccelerateDecelerateInterpolator();startDelay=980
        }
        motion.playTogether(x,y,shine,glide,tilt)
        motion.start()
    }

    fun message(value:String) {
        if (!closing) status.text=value
    }

    /** Called on UI thread with signed-payload byte counts, never with a timer. */
    fun downloadProgress(downloaded:Int,total:Int) {
        if (closing || total<=0) return
        progressBar.isIndeterminate=false
        progressBar.progress=((downloaded.toLong()*100)/total).toInt().coerceIn(0,100)
        status.text="جارٍ تحميل تحديث الواجهة • ${progressBar.progress}%"
    }

    fun checking(value:String="جارٍ فحص تحديث الواجهة…") {
        if (closing) return
        progressBar.isIndeterminate=true
        status.text=value
    }

    fun hide() {
        if (closing || root.parent==null) return
        closing=true
        root.animate().cancel()
        root.animate().alpha(0f).translationX(-dp(48).toFloat()).setDuration(360)
            .setInterpolator(DecelerateInterpolator())
            .withEndAction {
                motion.cancel()
                mark.animate().cancel()
                glint.animate().cancel()
                wordmark.animate().cancel()
                caption.animate().cancel()
                (root.parent as? FrameLayout)?.removeView(root)
            }.start()
    }

    fun reveal() {
        if(root.parent==null) {
            parent.addView(root,FrameLayout.LayoutParams(-1,-1))
        }
        progressBar.isIndeterminate=true
        progressBar.progress=0
        startMotion()
    }
}
