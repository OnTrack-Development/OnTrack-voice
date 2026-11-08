package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class OnTrackPermissionChecklistTest {
    private fun facts(
        phone:Boolean=true,notifications:Boolean?=true,
        outbound:Boolean=false,overlay:Boolean=false,
        install:Boolean=false,recovery:Boolean=false
    )=VoicePermissionFacts(phone,notifications,outbound,overlay,install,recovery)
    private fun check(f:VoicePermissionFacts,id:String)=
        OnTrackPermissionChecklist.evaluate(f).first{it.id==id}

    @Test fun voiceNeedsOnlyPhoneStatePermission() {
        val all=OnTrackPermissionChecklist.evaluate(facts())
        assertEquals(1,all.count{it.state==CheckState.ACTION})
        assertTrue(all.any{it.id=="permission_phone"&&it.state==CheckState.READY})
        assertTrue(all.any{it.id=="permission_calls"&&it.state==CheckState.OPTIONAL})
        assertTrue(all.any{it.id=="permission_overlay"&&it.state==CheckState.OPTIONAL})
        assertFalse(all.any{it.id.contains("record")})
    }

    @Test fun missingPhoneStateShowsDirectAndroidPermissionAction() {
        val c=check(facts(phone=false),"permission_phone")
        assertEquals(CheckState.ACTION,c.state)
        assertEquals(CheckAction.PHONE_PERMISSION,c.action)
        assertEquals("السماح بحالة الهاتف",c.actionLabel)
    }

    @Test fun grantedAndNotGrantedStatusesAreActuallyDistinct() {
        val granted=facts(phone=true,notifications=true,outbound=true,overlay=true,install=true,recovery=true)
        val every=OnTrackPermissionChecklist.evaluate(granted)
        assertTrue(every.filter{it.id!="permission_not_needed"}.all{it.state==CheckState.READY})
        assertEquals(CheckAction.NONE,check(granted,"permission_calls").action)
        val missing=facts()
        assertEquals(CheckAction.OUTBOUND_CALLS,check(missing,"permission_calls").action)
        assertEquals(CheckAction.OVERLAY,check(missing,"permission_overlay").action)
        assertEquals(CheckAction.INSTALL_UPDATES,check(missing,"permission_install").action)
        assertEquals(CheckAction.RECONNECT,check(missing,"permission_background").action)
    }

    @Test fun preAndroid13DoesNotShowNotificationPermissionWarning() {
        assertFalse(OnTrackPermissionChecklist.evaluate(facts(notifications=null))
            .any{it.id=="permission_notifications"})
    }

    @Test fun optionalNotificationsNeverBlockInboundVoice() {
        val c=check(facts(notifications=false),"permission_notifications")
        assertEquals(CheckState.OPTIONAL,c.state)
        assertEquals(CheckAction.NOTIFICATIONS,c.action)
        assertTrue(c.detail.contains("اختياري"))
    }

    @Test fun missingRecordingPermissionsNotSuggested() {
        val all=OnTrackPermissionChecklist.evaluate(facts(phone=false,notifications=false))
        assertTrue(all.first{it.id=="permission_not_needed"}.detail.contains("غير مطلوبة"))
        assertFalse(all.any{it.title.contains("إذن الميكروفون")||it.title.contains("إذن تسجيل")})
    }
}
