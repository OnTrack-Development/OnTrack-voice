package com.ontrack.voiceproof.capture

import android.content.Context

/** Notification access is absent from OnTrack. Compatibility for upstream UI.
 * No listener, notification reading, banner hiding, or notification grant API.
 */
@Suppress("UNUSED_PARAMETER")
object DebugNotificationCleaner {
    fun asked(context: Context): Boolean = true
    fun hiding(context: Context): Boolean = false
    fun accessGranted(context: Context): Boolean = false
    fun choose(context: Context, hide: Boolean) = apply(context)
    fun forget(context: Context) = apply(context)
    fun apply(context: Context) { VoipCallNotes.setEnabled(context, false) }
}
