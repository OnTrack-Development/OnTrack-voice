package com.ontrack.agentphone;

import android.net.Uri;
import android.telecom.Call;
import android.telecom.InCallService;
import android.util.Log;

import org.json.JSONObject;

import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

public class OnTrackInCallService extends InCallService {
    private final Map<Call, Integer> inboundIds = new ConcurrentHashMap<>();
    private final Map<Call, Integer> outboundIds = new ConcurrentHashMap<>();

    @Override public void onCallAdded(Call call) {
        super.onCallAdded(call);

        Call.Details details = call.getDetails();
        String number = number(details == null ? null : details.getHandle());
        int direction = android.os.Build.VERSION.SDK_INT >= 29 && details != null
                ? details.getCallDirection()
                : Call.Details.DIRECTION_UNKNOWN;

        if (direction == Call.Details.DIRECTION_INCOMING || call.getState() == Call.STATE_RINGING) {
            registerInbound(call, number);
        } else {
            int id = pendingOutbound(number);
            if (id > 0) {
                outboundIds.put(call, id);
                BridgeService.updateCallAsync(this, id, stateToApi(call.getState()), null);
            }
        }

        call.registerCallback(new Call.Callback() {
            @Override public void onStateChanged(Call c, int state) {
                Integer out = outboundIds.get(c);
                if (out != null) {
                    BridgeService.updateCallAsync(
                            OnTrackInCallService.this,
                            out,
                            stateToApi(state),
                            null);
                }

                Integer in = inboundIds.get(c);
                if (in != null) updateInbound(in, state);
            }
        });
    }

    @Override public void onCallRemoved(Call call) {
        Integer out = outboundIds.remove(call);
        if (out != null) {
            BridgeService.updateCallAsync(this, out, "completed", null);
            getSharedPreferences("ontrack_agent_phone", MODE_PRIVATE)
                    .edit()
                    .remove("pending_call_id")
                    .remove("pending_call_phone")
                    .apply();
        }

        Integer in = inboundIds.remove(call);
        if (in != null) sendInboundState(in, "ended");

        super.onCallRemoved(call);
    }

    private void registerInbound(Call call, String phone) {
        if (!AppState.paired(this)) return;

        new Thread(() -> {
            try {
                JSONObject b = new JSONObject();
                b.put("state", "ringing");
                b.put("phone_number", phone.isEmpty() ? "00000000" : phone);

                JSONObject out = ApiClient.post(
                        AppState.server(this),
                        "/api/device/incoming-event.php",
                        b,
                        AppState.token(this));

                int id = out.getInt("call_id");
                inboundIds.put(call, id);

                JSONObject instruction = out.optJSONObject("instruction");
                if (instruction != null) {
                    Log.i("OnTrackBridge", "Inbound instruction: " + instruction.optString("action"));
                }

                // v0.1 intentionally does NOT auto-answer. The first field test proves
                // call detection and server linkage before enabling the AI conference leg.

            } catch (Exception e) {
                Log.w("OnTrackBridge", "incoming registration", e);
            }
        }, "OnTrackIncomingRegister").start();
    }

    private void updateInbound(int id, int state) {
        if (state == Call.STATE_ACTIVE) sendInboundState(id, "answered");
        else if (state == Call.STATE_DISCONNECTED) sendInboundState(id, "ended");
    }

    private void sendInboundState(int id, String state) {
        new Thread(() -> {
            try {
                JSONObject b = new JSONObject();
                b.put("state", state);
                b.put("call_id", id);

                ApiClient.post(
                        AppState.server(this),
                        "/api/device/incoming-event.php",
                        b,
                        AppState.token(this));

            } catch (Exception e) {
                Log.w("OnTrackBridge", "incoming state", e);
            }
        }, "OnTrackIncomingState").start();
    }

    private int pendingOutbound(String number) {
        android.content.SharedPreferences p =
                getSharedPreferences("ontrack_agent_phone", MODE_PRIVATE);

        int id = p.getInt("pending_call_id", 0);
        String expected = p.getString("pending_call_phone", "");

        if (id <= 0) return 0;
        if (expected.isEmpty()) return id;

        String actualTail = trimCountry(number);
        String expectedTail = trimCountry(expected);

        return !actualTail.isEmpty() &&
                (actualTail.endsWith(expectedTail) || expectedTail.endsWith(actualTail))
                ? id : 0;
    }

    private static String stateToApi(int state) {
        if (state == Call.STATE_DIALING || state == Call.STATE_CONNECTING) return "dialing";
        if (state == Call.STATE_RINGING) return "ringing";
        if (state == Call.STATE_ACTIVE) return "answered";
        if (state == Call.STATE_DISCONNECTED) return "completed";
        return "dialing";
    }

    private static String number(Uri uri) {
        return uri == null ? "" : normalize(uri.getSchemeSpecificPart());
    }

    private static String normalize(String s) {
        return s == null ? "" : s.replaceAll("[^0-9+]", "");
    }

    private static String trimCountry(String s) {
        String n = normalize(s).replace("+", "");
        return n.length() > 10 ? n.substring(n.length() - 10) : n;
    }
}
