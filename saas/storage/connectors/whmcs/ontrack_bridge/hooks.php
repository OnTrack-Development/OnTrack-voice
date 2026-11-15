<?php

/**
 * OnTrack Bridge — WHMCS Event Hooks
 * Captures lifecycle events, applies Zero-Knowledge tokenization locally,
 * and asynchronously dispatches signed events to OnTrack SaaS.
 */

require_once __DIR__ . '/LocalPiiTokenizer.php';
require_once __DIR__ . '/AuditLog.php';

function ontrack_bridge_get_config(): array
{
    static $config = null;
    if ($config !== null) return $config;

    $config = [];
    if (class_exists('\WHMCS\Database\Capsule')) {
        try {
            $rows = \WHMCS\Database\Capsule::table('tbladdonmodules')
                ->where('module', 'ontrack_bridge')
                ->get();
            foreach ($rows as $r) {
                $config[$r->setting] = $r->value;
            }
        } catch (\Throwable $e) {}
    }
    return $config;
}

function ontrack_bridge_dispatch_event(string $eventType, array $payload): void
{
    $config = ontrack_bridge_get_config();
    $saasUrl = rtrim((string)($config['saas_url'] ?? 'https://app.ontrackegy.com'), '/');
    $integrationKey = trim((string)($config['integration_key'] ?? ''));
    $secretKey = trim((string)($config['secret_key'] ?? ''));

    if ($integrationKey === '' || $secretKey === '') {
        return;
    }

    // Apply Client-Side Tokenization
    $sessionKey = 'ticket_' . ($payload['ticket_id'] ?? uniqid());
    $tokenizedPayload = LocalPiiTokenizer::tokenize($payload, $sessionKey);
    $tokenizedPayload['event_type'] = $eventType;
    $tokenizedPayload['zero_retention_mode'] = !empty($config['zero_retention_mode']);

    $json = json_encode($tokenizedPayload, JSON_UNESCAPED_UNICODE);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$json}", $secretKey);

    // Fast asynchronous dispatch (timeout 2s) so WHMCS UI never slows down
    $ch = curl_init("{$saasUrl}/api/v1/bridge/events");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "X-Ontrack-Key: {$integrationKey}",
        "X-Ontrack-Timestamp: {$timestamp}",
        "X-Ontrack-Signature: {$signature}",
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    OnTrackBridgeAudit::log($eventType, $tokenizedPayload, json_decode((string)$response, true) ?: [], $httpCode ?: 200);
}

// 1. Hook on Ticket Open
add_hook('TicketOpen', 1, function ($vars) {
    ontrack_bridge_dispatch_event('ticket.opened', [
        'ticket_id' => $vars['ticketid'],
        'client_id' => $vars['userid'] ?? 0,
        'subject' => $vars['subject'] ?? '',
        'message' => $vars['message'] ?? '',
        'priority' => $vars['priority'] ?? 'medium',
        'department' => $vars['deptname'] ?? '',
    ]);
});

// 2. Hook on Ticket User Reply
add_hook('TicketUserReply', 1, function ($vars) {
    ontrack_bridge_dispatch_event('ticket.reply', [
        'ticket_id' => $vars['ticketid'],
        'client_id' => $vars['userid'] ?? 0,
        'message' => $vars['message'] ?? '',
    ]);
});

// 3. Hook on Ticket Admin Reply (triggers Human Handoff / Mute)
add_hook('TicketAdminReply', 1, function ($vars) {
    ontrack_bridge_dispatch_event('ticket.staff_reply', [
        'ticket_id' => $vars['ticketid'],
        'admin_username' => $vars['admin'] ?? 'Staff',
    ]);
});

// 4. Hook on Ticket Close
add_hook('TicketClose', 1, function ($vars) {
    ontrack_bridge_dispatch_event('ticket.closed', [
        'ticket_id' => $vars['ticketid'],
    ]);
});

// 5. Injects White-Label Chat & Voice Widget into Client Area Footer
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $config = ontrack_bridge_get_config();
    if (empty($config['enable_web_widget'])) {
        return '';
    }

    $systemUrl = rtrim($GLOBALS['CONFIG']['SystemURL'] ?? '', '/');
    $proxyUrl = "{$systemUrl}/modules/addons/ontrack_bridge/widget_proxy.php";

    return <<<HTML
<!-- OnTrack White-Label AI Assistant Widget -->
<div id="ontrack-whitelabel-widget" style="position:fixed;bottom:20px;right:20px;z-index:99999;">
    <button id="ontrack-widget-toggle" onclick="toggleOnTrackChat()" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:50px;padding:12px 20px;font-weight:bold;box-shadow:0 10px 25px rgba(0,0,0,0.2);cursor:pointer;display:flex;align-items:center;gap:8px;">
        <span style="font-size:18px;">✨</span>
        <span>المساعد الذكي</span>
    </button>
    <div id="ontrack-chat-frame" style="display:none;position:absolute;bottom:60px;right:0;width:360px;height:520px;background:#FFFFFF;border-radius:16px;box-shadow:0 15px 40px rgba(0,0,0,0.18);border:1px solid #E2E8F0;overflow:hidden;flex-direction:column;">
        <div style="background:#0F172A;color:#FFFFFF;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="display:inline-block;width:8px;height:8px;background:#10B981;border-radius:50%;"></span>
                <strong style="font-size:14px;">خدمة العملاء الذكية</strong>
            </div>
            <span onclick="toggleOnTrackChat()" style="cursor:pointer;font-size:16px;">✕</span>
        </div>
        <div id="ontrack-chat-messages" style="flex:1;padding:14px;overflow-y:auto;font-size:13px;display:flex;flex-direction:column;gap:10px;background:#F8FAFC;">
            <div style="background:#E2E8F0;color:#1E293B;padding:10px 14px;border-radius:12px;max-width:85%;">أهلاً بك! كيف يمكنني مساعدتك في حسابك اليوم؟</div>
        </div>
        <div style="padding:10px 12px;background:#FFFFFF;border-top:1px solid #E2E8F0;display:flex;gap:6px;">
            <input type="text" id="ontrack-chat-input" placeholder="اكتب استفسارك هنا..." onkeydown="if(event.key==='Enter')sendOnTrackMsg()" style="flex:1;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;outline:none;font-size:13px;" />
            <button onclick="sendOnTrackMsg()" style="background:#2563EB;color:#FFF;border:none;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;">إرسال</button>
        </div>
    </div>
</div>
<script>
function toggleOnTrackChat(){
    var f=document.getElementById('ontrack-chat-frame');
    f.style.display=(f.style.display==='none'||f.style.display==='')?'flex':'none';
}
function sendOnTrackMsg(){
    var inp=document.getElementById('ontrack-chat-input');
    var txt=inp.value.trim();
    if(!txt)return;
    var box=document.getElementById('ontrack-chat-messages');
    box.innerHTML+='<div style="align-self:flex-end;background:#2563EB;color:#FFF;padding:10px 14px;border-radius:12px;max-width:85%;">'+txt+'</div>';
    inp.value='';
    box.scrollTop=box.scrollHeight;

    fetch('{$proxyUrl}?action=chat',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:txt})
    }).then(function(r){return r.json();}).then(function(d){
        var reply=d.reply||d.draft_text||'تم استلام رسالتك.';
        box.innerHTML+='<div style="align-self:flex-start;background:#E2E8F0;color:#1E293B;padding:10px 14px;border-radius:12px;max-width:85%;">'+reply+'</div>';
        box.scrollTop=box.scrollHeight;
    }).catch(function(){
        box.innerHTML+='<div style="align-self:flex-start;background:#FEE2E2;color:#991B1B;padding:10px 14px;border-radius:12px;max-width:85%;">تعذر الاتصال بالخادم حالياً.</div>';
        box.scrollTop=box.scrollHeight;
    });
}
</script>
HTML;
});
