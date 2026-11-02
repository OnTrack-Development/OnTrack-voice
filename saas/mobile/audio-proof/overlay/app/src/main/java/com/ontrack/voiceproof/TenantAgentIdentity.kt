package com.ontrack.voiceproof

/** A server may change the agent, never the enrolled device/SIM/site identity. */
internal object TenantAgentIdentity {
    fun canRefreshAgent(storedDevice:Int,storedSite:Int,storedSlot:Int,
                        serverDevice:Int,serverSite:Int,serverSlot:Int,serverAgent:Int):Boolean =
        storedDevice>0 && storedSite>0 && storedSlot in 0..1 &&
        serverDevice==storedDevice && serverSite==storedSite &&
        serverSlot==storedSlot && serverAgent>0
}
