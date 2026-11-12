package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class OnTrackSetupChecklistTest {
    private fun facts(
        developer:Boolean?=true,usb:Boolean?=true,wireless:Boolean?=true,
        paired:Boolean=true,connected:Boolean=true,secure:Boolean=true,
        setup:Boolean=true,daemon:Boolean=true,phone:Boolean=true,sim:Boolean=true
    )=VoiceSetupFacts(developer,usb,wireless,paired,connected,secure,setup,daemon,phone,sim)

    private fun step(f:VoiceSetupFacts,id:String)=OnTrackSetupChecklist.evaluate(f)
        .first{it.id==id}

    @Test fun runningPcmDoesNotNeedWirelessUsbOrLiveAdb() {
        val f=facts(developer=false,usb=false,wireless=false,
            connected=false,daemon=true)
        val checklist=OnTrackSetupChecklist.evaluate(f)
        assertEquals(CheckState.READY,step(f,"pcm").state)
        assertEquals(CheckState.OPTIONAL,step(f,"adb").state)
        assertFalse(checklist.any{it.id=="wireless"||it.id=="usb"||it.id=="developer"})
        assertFalse(checklist.any{it.title.contains("تسجيل")||it.title.contains("ميكروفون")})
    }

    @Test fun pairedButOfflineGetsReconnectNotPairCode() {
        val f=facts(wireless=false,connected=false,daemon=false)
        assertEquals(CheckAction.RECONNECT,step(f,"adb").action)
    }

    @Test fun missingFirstPairGetsPairCodeOnlyWhenDaemonDown() {
        val f=facts(paired=false,connected=false,daemon=false,setup=false,secure=false)
        assertEquals(CheckAction.PAIR,step(f,"adb").action)
        assertEquals(CheckAction.PAIR,step(f,"repair").action)
    }

    @Test fun permissionIsOnlyPhoneStateForSimNotMicrophone() {
        val f=facts(phone=false,sim=false)
        assertEquals(CheckAction.PHONE_PERMISSION,step(f,"phone").action)
        assertEquals(CheckAction.OPEN_VOICE,step(f,"sim").action)
    }

    @Test fun unknownOemSettingsAreNotMarkedOff() {
        val f=facts(developer=null,usb=null,wireless=null,daemon=false)
        assertEquals(CheckState.UNKNOWN,step(f,"developer").state)
        assertEquals(CheckState.UNKNOWN,step(f,"usb").state)
        assertEquals(CheckState.UNKNOWN,step(f,"wireless").state)
    }

    @Test fun secureSettingsIsForRebootRecoveryNotCallCapture() {
        val f=facts(secure=false,setup=false,paired=true,daemon=true)
        assertEquals(CheckState.OPTIONAL,step(f,"repair").state)
        assertEquals(CheckAction.RECONNECT,step(f,"repair").action)
    }
}
