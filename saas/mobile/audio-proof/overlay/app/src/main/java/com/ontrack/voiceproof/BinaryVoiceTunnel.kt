package com.ontrack.voiceproof

import android.content.Context
import android.os.SystemClock
import android.util.Log
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import okio.ByteString
import okio.ByteString.Companion.toByteString
import org.json.JSONObject
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicLong

/**
 * High-performance Single Binary WSS Tunnel Client (v80 / 0.3.57).
 * Binds directly to active cellular network, streams raw binary PCM (no JSON/Base64),
 * and implements 8kHz/16kHz low-bitrate adaptive mode with 32KiB backpressure.
 */
internal class BinaryVoiceTunnel(
    private val context: Context,
    private val baseClient: OkHttpClient,
    private val token: String,
    private val isCellular: Boolean,
    private val onAudioChunkReceived: (ByteArray) -> Unit,
    private val onReady: (JSONObject) -> Unit = {},
    private val onTurnComplete: () -> Unit = {},
    private val onFallbackRequested: (String) -> Unit = {},
    private val onMetricsUpdated: (tunnelMs: Double, bytesSent: Long, bytesRecv: Long) -> Unit = { _, _, _ -> }
) {
    companion object {
        private const val TAG = "BinaryVoiceTunnel"
        const val WSS_URL = "wss://app.ontrackegy.com/ws-voice"
        const val MAX_BACKPRESSURE_BYTES = 32 * 1024L // 32 KiB cap
    }

    private var webSocket: WebSocket? = null
    private val isConnected = AtomicBoolean(false)
    private val isClosed = AtomicBoolean(false)
    private val queuedBytes = AtomicLong(0L)
    private val totalBytesSent = AtomicLong(0L)
    private val totalBytesRecv = AtomicLong(0L)
    private var connectStartTimeNanos = 0L

    val connected: Boolean get() = isConnected.get()
    fun isConnected(): Boolean = isConnected.get()
    fun stop() = close()

    fun start() {
        if (isClosed.get()) return
        connectStartTimeNanos = SystemClock.elapsedRealtimeNanos()

        // Bind OkHttpClient to active Cellular network if requested
        val clientBuilder = baseClient.newBuilder()
            .dns(ResilientDns)
            .connectTimeout(5, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)

        if (isCellular) {
            val cellNet = CellularRadioStatus.getActiveCellularNetwork(context)
            if (cellNet != null) {
                clientBuilder.socketFactory(cellNet.socketFactory)
                Log.i(TAG, "Bound BinaryVoiceTunnel OkHttpClient to active cellular network")
            }
        }

        val client = clientBuilder.build()
        val request = Request.Builder()
            .url(WSS_URL)
            .header("User-Agent", "OnTrack-BinaryTunnel/0.3.57")
            .build()

        webSocket = client.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(ws: WebSocket, response: Response) {
                val connMs = (SystemClock.elapsedRealtimeNanos() - connectStartTimeNanos) / 1e6
                Log.i(TAG, "BinaryVoiceTunnel connected in ${connMs}ms")
                isConnected.set(true)

                // Send initial JSON handshake frame with token & bitrate settings
                val init = JSONObject()
                    .put("type", "init")
                    .put("token", token)
                    .put("low_bitrate", isCellular)
                ws.send(init.toString())
            }

            override fun onMessage(ws: WebSocket, bytes: ByteString) {
                val arr = bytes.toByteArray()
                totalBytesRecv.addAndGet(arr.size.toLong())
                onMetricsUpdated(0.0, totalBytesSent.get(), totalBytesRecv.get())
                onAudioChunkReceived(arr)
            }

            override fun onMessage(ws: WebSocket, text: String) {
                try {
                    val json = JSONObject(text)
                    when (json.optString("type")) {
                        "ready" -> {
                            val connMs = (SystemClock.elapsedRealtimeNanos() - connectStartTimeNanos) / 1e6
                            onMetricsUpdated(connMs, totalBytesSent.get(), totalBytesRecv.get())
                            onReady(json)
                        }
                        "turn_complete" -> onTurnComplete()
                        "tts_fallback" -> onFallbackRequested(json.optString("reason", "server_fallback"))
                    }
                } catch (e: Exception) {
                    Log.w(TAG, "Error parsing server control message: $e")
                }
            }

            override fun onFailure(ws: WebSocket, t: Throwable, response: Response?) {
                Log.w(TAG, "BinaryVoiceTunnel failure: ${t.message}")
                isConnected.set(false)
                if (!isClosed.get()) {
                    onFallbackRequested("tunnel_failure: ${t.message}")
                }
            }

            override fun onClosed(ws: WebSocket, code: Int, reason: String) {
                Log.i(TAG, "BinaryVoiceTunnel closed: $code / $reason")
                isConnected.set(false)
            }
        })
    }

    /**
     * Send raw binary PCM audio frame to relay.
     * In cellular mode (8kHz), downsamples 16kHz -> 8kHz to cut uplink bandwidth to 128 kbps.
     * Enforces 32 KiB backpressure limit.
     */
    fun sendAudioPcm(pcm16k: ByteArray): Boolean {
        val ws = webSocket ?: return false
        if (!isConnected.get() || isClosed.get()) return false

        // Check backpressure cap
        if (queuedBytes.get() > MAX_BACKPRESSURE_BYTES) {
            // Drop buffer to protect real-time latency on congested 3G/4G
            Log.w(TAG, "Dropping PCM frame due to 32KiB backpressure (${queuedBytes.get()} bytes)")
            return false
        }

        val outBytes = if (isCellular) downsample16kTo8k(pcm16k) else pcm16k
        queuedBytes.addAndGet(outBytes.size.toLong())
        totalBytesSent.addAndGet(outBytes.size.toLong())

        val success = ws.send(outBytes.toByteString())
        queuedBytes.addAndGet(-outBytes.size.toLong())
        return success
    }

    fun sendBargeIn() {
        val ws = webSocket ?: return
        if (isConnected.get() && !isClosed.get()) {
            ws.send(JSONObject().put("type", "barge_in").toString())
        }
    }

    fun close() {
        if (isClosed.compareAndSet(false, true)) {
            isConnected.set(false)
            try {
                webSocket?.close(1000, "call_ended")
            } catch (_: Exception) {}
            webSocket = null
        }
    }

    private fun downsample16kTo8k(pcm16k: ByteArray): ByteArray {
        val out = ByteArray(pcm16k.size / 2)
        var j = 0
        for (i in 0 until pcm16k.size - 3 step 4) {
            out[j] = pcm16k[i]
            out[j + 1] = pcm16k[i + 1]
            j += 2
        }
        return out
    }
}
