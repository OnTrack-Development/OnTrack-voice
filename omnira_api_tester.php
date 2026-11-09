<?php
/**
 * Omnira API Tester — standalone, read-only browser utility.
 * PHP >=7.4 + cURL. No third-party libraries, no key storage, fixed destination.
 * DELETE THIS FILE from public hosting once finished.
 */
declare(strict_types=1);

require_once __DIR__.'/api/MaintenanceBrowser.php';
$maintenanceCsrf=ontrack_maintenance_browser_require();
const OMNIRA_ORIGIN = 'https://ai.omnira-ai.net';
const RESPONSE_LIMIT = 300000;

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function redacted($value, string $secret) {
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            if (preg_match('/(authorization|x-api-key|api[_-]?key|access[_-]?token|secret)/i', (string)$k)) $value[$k] = '[REDACTED]';
            else $value[$k] = redacted($v, $secret);
        }
        return $value;
    }
    if (is_string($value)) {
        if ($secret !== '') $value = str_replace($secret, '[REDACTED]', $value);
        return preg_replace('/omnira_sk_(?:live|demo)_[A-Za-z0-9._-]+/', '[REDACTED]', $value);
    }
    return $value;
}
function respond(array $result, string $key = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(redacted($result, $key), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function collectText(array $parsed): string {
    $parts = [];
    if (!empty($parsed['content']) && is_array($parsed['content'])) {
        foreach ($parsed['content'] as $part) {
            if (isset($part['text']) && is_string($part['text'])) $parts[] = $part['text'];
            if (($part['type'] ?? '') === 'tool_use') $parts[] = '[TOOL_USE] '.($part['name'] ?? '').': '.json_encode($part['input'] ?? [], JSON_UNESCAPED_UNICODE);
        }
    }
    if (!empty($parsed['choices'][0]['message']['content'])) $parts[] = (string)$parsed['choices'][0]['message']['content'];
    return implode("\n", $parts);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ontrack_maintenance_browser_csrf($_SERVER,$maintenanceCsrf))
        ontrack_maintenance_fail(403,'maintenance_csrf_invalid');
    if (!extension_loaded('curl')) respond(['ok'=>false, 'error'=>'PHP cURL extension is not installed.']);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'OmniraTester') respond(['ok'=>false,'error'=>'Invalid request.']);
    $key = trim((string)($_POST['api_key'] ?? ''));
    $test = (string)($_POST['test'] ?? 'basic');
    $auth = (string)($_POST['auth'] ?? 'bearer');
    $model = trim((string)($_POST['model'] ?? 'claude-sonnet-5'));
    $prompt = trim((string)($_POST['prompt'] ?? 'Reply with exactly: OMNIRA_OK'));
    if (strlen($key) < 12 || strlen($key) > 500) respond(['ok'=>false,'error'=>'Enter a valid API key.']);
    if (!in_array($test, ['basic','stream','tools','models'], true)) respond(['ok'=>false,'error'=>'Invalid test.'], $key);
    if (!in_array($auth, ['bearer','x-api-key'], true)) respond(['ok'=>false,'error'=>'Invalid auth option.'], $key);
    if (!preg_match('/^[a-zA-Z0-9._:\/-]{2,120}$/', $model)) respond(['ok'=>false,'error'=>'Invalid model id.'], $key);
    if (strlen($prompt) > 3000) respond(['ok'=>false,'error'=>'Prompt is too long.'], $key);
    if ($prompt === '') $prompt = 'Reply with exactly: OMNIRA_OK';
    $url = OMNIRA_ORIGIN.($test === 'models' ? '/api/v1/models' : '/api/v1/messages');
    $headers = ['Content-Type: application/json', 'Accept: '.($test==='stream' ? 'text/event-stream' : 'application/json'), 'anthropic-version: 2023-06-01'];
    $headers[] = ($auth === 'bearer' ? 'Authorization: Bearer ' : 'x-api-key: ').$key;
    $body = [ 'model'=>$model, 'max_tokens'=>128, 'messages'=>[['role'=>'user','content'=>$prompt]] ];
    if ($test === 'stream') $body['stream'] = true;
    if ($test === 'tools') {
        $body['messages'] = [['role'=>'user','content'=>'Use the add_numbers tool to calculate 17 + 25. Do not calculate in plain text.']];
        $body['tools'] = [[ 'name'=>'add_numbers', 'description'=>'Add two integer numbers', 'input_schema'=>['type'=>'object','properties'=>['a'=>['type'=>'integer'],'b'=>['type'=>'integer']],'required'=>['a','b']]]];
        $body['tool_choice'] = ['type'=>'tool','name'=>'add_numbers'];
        $body['max_tokens'] = 256;
    }
    $raw = '';
    $responseHeaders = [];
    $firstByteAt = null;
    $firstTextAt = null;
    $start = microtime(true);
    $tooLarge = false;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $test==='models' ? 'GET' : 'POST',
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 65,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADERFUNCTION => function($ch, $line) use (&$responseHeaders) {
            if (strpos($line, ':') !== false) {
                [$name,$v] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                if (in_array($name, ['content-type','server','cf-ray','x-request-id','request-id','anthropic-request-id','retry-after','x-ratelimit-limit-requests','x-ratelimit-remaining-requests','x-ratelimit-reset-requests','x-upstream-provider','x-provider','via'], true)) {
                    $responseHeaders[$name] = trim($v);
                }
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$raw, &$firstByteAt, &$firstTextAt, &$tooLarge, $start, $test) {
            if ($firstByteAt === null) $firstByteAt = microtime(true) - $start;
            $raw .= $chunk;
            if ($test === 'stream' && $firstTextAt === null && (strpos($raw,'"text_delta"') !== false || strpos($raw,'"text":') !== false)) $firstTextAt = microtime(true) - $start;
            if (strlen($raw) > RESPONSE_LIMIT) { $tooLarge = true; return 0; }
            return strlen($chunk);
        },
        CURLOPT_USERAGENT => 'Omnira-API-Tester/1.0',
    ];
    if ($test !== 'models') $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch, $opts);
    $execOk = curl_exec($ch);
    $elapsed = microtime(true) - $start;
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $connectTime = (float)curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $ttfb = (float)curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode($raw, true);
    $parsedSse = null;
    if ($test === 'stream') {
        $text = '';
        $eventCount=0;
        $types=[];
        foreach (preg_split('/\r?\n\r?\n/', $raw) as $event) {
            foreach (preg_split('/\r?\n/', $event) as $line) {
                if (strpos($line, 'data: ') !== 0) continue;
                $jsonData = trim(substr($line, 6));
                if ($jsonData === '[DONE]') continue;
                $evt = json_decode($jsonData, true);
                if (!is_array($evt)) continue;
                $eventCount++;
                $type = $evt['type'] ?? 'unknown';
                $types[$type] = ($types[$type] ?? 0)+1;
                if ($type === 'content_block_delta') $text .= (string)($evt['delta']['text'] ?? '');
                if ($type === 'content_block_start') $text .= (string)($evt['content_block']['text'] ?? '');
            }
        }
        $parsedSse = ['events'=>$eventCount,'event_types'=>$types, 'assembled_text'=>$text];
    }
    $ok = ($status >= 200 && $status < 300 && $execOk !== false && !$tooLarge);
    $shortBody = substr($raw, 0, 25000);
    respond([
        'ok'=>$ok,
        'test'=>$test,
        'endpoint'=>$url,
        'http_status'=>$status,
        'timing'=>['total_ms'=>round($elapsed*1000,1), 'connect_ms'=>round($connectTime*1000,1), 'ttfb_ms'=>round($ttfb*1000,1), 'first_received_ms'=>$firstByteAt===null?null:round($firstByteAt*1000,1), 'first_stream_text_ms'=>$firstTextAt===null?null:round($firstTextAt*1000,1)],
        'response_headers'=>$responseHeaders,
        'model_returned'=>is_array($decoded)?($decoded['model'] ?? null):null,
        'usage'=>is_array($decoded)?($decoded['usage'] ?? null):null,
        'stop_reason'=>is_array($decoded)?($decoded['stop_reason'] ?? null):null,
        'text'=>is_array($decoded)?collectText($decoded):($parsedSse['assembled_text'] ?? ''),
        'stream_info'=>$parsedSse,
        'json'=>is_array($decoded)?$decoded:null,
        'raw_preview'=>$shortBody,
        'curl_error'=>$curlErrNo ? ['code'=>$curlErrNo,'message'=>$curlErr]:null,
        'truncated'=>strlen($raw)>25000,
        'limit_exceeded'=>$tooLarge,
        'note'=> $status === 401 ? 'Authentication failed. Confirm key and header mode.' : ($status === 404 ? 'Endpoint not found: confirm /api/v1/messages and API provider compatibility.' : ($status === 429 ? 'Rate limited or quota exceeded.' : ($status === 402 ? 'Billing or credit required.' : ($status === 403 ? 'Permission or model access denied.' : null))))
    ], $key);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8" />
<meta name="ontrack-maintenance-csrf" content="<?= htmlspecialchars($maintenanceCsrf,ENT_QUOTES,'UTF-8') ?>" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="robots" content="noindex,nofollow" />
<title>Omnira · API Diagnostics</title>
<style>
:root{color-scheme:dark;font-family:system-ui,-apple-system,Segoe UI,Tahoma,sans-serif;--bg:#090e1b;--surface:#111a2d;--border:#29324a;--muted:#a5b4cc;--blue:#78a7ff}*{box-sizing:border-box}body{background:radial-gradient(circle at top left,#162951,#090e1b 50%);color:#edf2ff;margin:0;min-height:100vh}.wrap{max-width:1040px;margin:auto;padding:40px 18px}.brand{display:flex;align-items:center;gap:12px;margin-bottom:10px}.logo{border-radius:13px;background:linear-gradient(145deg,#6884ff,#a87df4);width:44px;height:44px;display:grid;place-items:center;font-size:24px;font-weight:900}.tag{color:#b1bde0;font-size:12px;letter-spacing:2px;text-transform:uppercase}h1{font-size:clamp(24px,4vw,37px);margin:12px 0 7px}.lead{color:var(--muted);line-height:1.8;margin:0 0 24px}.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px;box-shadow:0 15px 45px #0003}.panel h2{font-size:18px;margin:0 0 17px}.field{margin-bottom:15px}label{display:block;font-size:13px;color:#b8c7df;margin-bottom:7px}input,textarea,select{color:#fff;width:100%;background:#0a1326;border:1px solid #33415c;border-radius:11px;padding:12px;font:14px inherit;outline:none;direction:ltr;text-align:left}input:focus,textarea:focus,select:focus{border-color:#8eb4ff}textarea{resize:vertical;min-height:72px;line-height:1.5}.hint{font-size:12px;color:var(--muted);line-height:1.7}.buttons{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:10px}button{border:1px solid #475d82;background:#192949;color:#fff;padding:13px 9px;font-size:13px;border-radius:11px;cursor:pointer;font-weight:650}button.primary{background:#638af2;border-color:#638af2;color:#04112c}button:hover{filter:brightness(1.15)}button:disabled{opacity:.5;cursor:wait}.metricgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.metric{background:#0a1326;border:1px solid #26344d;border-radius:12px;padding:12px}.metric strong{font-size:23px;display:block;margin:7px 0 2px;direction:ltr;text-align:right}.metric span{color:var(--muted);font-size:12px}.status{display:inline-flex;gap:9px;align-items:center;border-radius:999px;background:#202d49;padding:7px 12px;font-size:12px;margin-bottom:15px}.dot{width:8px;height:8px;background:#8798b8;border-radius:50%}.dot.good{background:#32d7a5}.dot.bad{background:#f87171}.response{white-space:pre-wrap;overflow-wrap:anywhere;direction:ltr;text-align:left;background:#080f20;border:1px solid #273551;border-radius:12px;padding:13px;min-height:105px;max-height:380px;overflow:auto;font:12px/1.7 ui-monospace,Consolas,monospace;color:#d1e2fc}details{margin-top:18px}summary{cursor:pointer;color:#b6cafa}.alert{padding:13px;border:1px solid #735624;color:#ffdf9c;background:#36291a;border-radius:12px;font-size:13px;line-height:1.7;margin-bottom:22px}.inline{display:flex;justify-content:space-between;align-items:center;gap:10px}.wide{grid-column:1/-1}.foot{color:var(--muted);font-size:12px;margin-top:18px;line-height:1.7}@media(max-width:750px){.layout{grid-template-columns:1fr}.metricgrid{grid-template-columns:1fr 1fr}.wrap{padding:22px 12px}}
</style>
</head>
<body><main class="wrap">
<div class="brand"><div class="logo">O</div><div><div class="tag">OnTrack Diagnostics</div><div style="font-weight:750">Omnira API Tester</div></div></div>
<h1>فحص Omnira API</h1><p class="lead">اختبار الرد، سرعة أول بايت، الـStreaming، وTool Calling باستخدام نفس مسار Anthropic اللي قدمه المزوّد. بدون Terminal أو مكتبات إضافية.</p>
<div class="alert">🔐 ارفع الملف مؤقتًا على HTTPS محمي بكلمة مرور أو IP Allowlist، ولا تشارك مفتاح API. لا يخزن التطبيق المفتاح في ملف أو Session أو LocalStorage، وكل اختبار يرسل طلبًا فعليًا إلى Omnira وقد يستهلك من حصتك. احذف الملف بعد الانتهاء.</div>
<div class="layout">
<section class="panel"><h2>إعدادات الاتصال</h2>
<div class="field"><label for="apiKey">Omnira API Key</label><input type="password" id="apiKey" placeholder="omnira_sk_..." autocomplete="off" spellcheck="false" /><div class="hint">المفتاح لا يضاف لعنوان الرابط ولا يتم حفظه داخل الكود.</div></div>
<div class="field"><label for="model">Model ID</label><input id="model" list="models" value="claude-sonnet-5" spellcheck="false" /><datalist id="models"><option value="claude-sonnet-5"><option value="claude-opus-5.5"><option value="claude-fable-5.1"><option value="gpt-6-astra"></datalist></div>
<div class="field"><label for="auth">نوع المصادقة</label><select id="auth"><option value="bearer">Authorization: Bearer (الموجود في المثال)</option><option value="x-api-key">x-api-key (Anthropic)</option></select></div>
<div class="field"><label for="prompt">رسالة الاختبار</label><textarea id="prompt">Reply with exactly: OMNIRA_OK</textarea></div>
<div class="buttons"><button type="button" class="primary" data-test="basic">1 · اختبار الرد</button><button type="button" data-test="stream">2 · Streaming</button><button type="button" data-test="tools">3 · Tool Calling</button><button type="button" data-test="models">4 · Models endpoint</button></div>
<p class="foot">طلبات POST إلى <code dir="ltr">/api/v1/messages</code>. اختبار Models يستخدم GET لمسار <code dir="ltr">/api/v1/models</code> وقد لا يدعمه المزوّد. الافتراضي 128 توكن للرد لتقليل استهلاك التجربة.</p></section>
<section class="panel"><div class="inline"><h2>نتيجة الاختبار</h2><button type="button" id="copy" style="padding:8px 12px">نسخ التقرير بعد إخفاء المفتاح</button></div>
<div class="status"><span id="dot" class="dot"></span><span id="status">لم يبدأ الاختبار</span></div>
<div class="metricgrid"><div class="metric"><span>HTTP</span><strong id="http">—</strong></div><div class="metric"><span>مدة الطلب</span><strong id="total">—</strong></div><div class="metric"><span>أول بايت</span><strong id="ttfb">—</strong></div></div>
<p class="hint" id="note">النتائج من استجابة السيرفر الحقيقية، بدون محاكاة.</p>
<h3 style="font-size:15px;margin:18px 0 7px">الرد المستخرج</h3><div class="response" id="text">No result yet.</div>
<details><summary>الـRAW JSON، Headers، وUsage</summary><div class="response" style="margin-top:12px" id="raw">No result yet.</div></details>
</section>
</div><p class="foot">لا توجد عمليات حفظ على الخادم أو إعدادات اتصال قابلة للتغيير. SSL verification مفعّلة، ولا يتم اتباع HTTP Redirect مع ترويسة المفتاح. الاختبار لا ينفذ الأدوات التي يقترحها الموديل.</p>
</main>
<script>
(()=>{
 const $=id=>document.getElementById(id), buttons=[...document.querySelectorAll('button[data-test]')];let last=null;
 const setStatus=(label,good)=>{$('status').textContent=label;$('dot').className='dot'+(good===true?' good':good===false?' bad':'');};
 buttons.forEach(b=>b.addEventListener('click',async()=>{
   if(!$('apiKey').value.trim()){setStatus('أدخل المفتاح الأول',false);$('apiKey').focus();return;}
   buttons.forEach(x=>x.disabled=true);$('text').textContent='جاري الاتصال...';$('raw').textContent='';setStatus('جارٍ الاختبار...',null);
   let data=new FormData();data.set('api_key',$('apiKey').value.trim());data.set('model',$('model').value.trim());data.set('auth',$('auth').value);data.set('prompt',$('prompt').value);data.set('test',b.dataset.test);
   try {
     const r=await fetch(location.href,{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'OmniraTester','Accept':'application/json','X-OnTrack-Maintenance-CSRF':document.querySelector('meta[name="ontrack-maintenance-csrf"]').content},redirect:'error',cache:'no-store'});
     const j=await r.json();last=j;setStatus(j.ok?'تم استلام رد ناجح':'الاختبار فشل أو رُفض',j.ok===true);
     $('http').textContent=j.http_status||'—';$('total').textContent=j.timing?.total_ms!=null?`${j.timing.total_ms} ms`:'—';$('ttfb').textContent=j.timing?.ttfb_ms!=null?`${j.timing.ttfb_ms} ms`:'—';
     $('text').textContent=j.text||j.json?.error?.message||j.error||j.curl_error?.message||j.raw_preview||'No text received';
     $('note').textContent=(j.note||'')+(j.timing?.first_stream_text_ms!=null?` | أول نص Streaming: ${j.timing.first_stream_text_ms}ms`:'')+(j.limit_exceeded?' | Response exceeded size limit.':'');
     $('raw').textContent=JSON.stringify(j,null,2);
   }catch(e){setStatus('مشكلة اتصال مع سيرفر PHP',false);$('text').textContent=String(e);last=null;}
   finally{buttons.forEach(x=>x.disabled=false);}
 }));
 $('copy').addEventListener('click',async()=>{if(!last)return;let text=JSON.stringify(last,null,2);try{await navigator.clipboard.writeText(text);$('copy').textContent='تم النسخ';setTimeout(()=>$('copy').textContent='نسخ التقرير بعد إخفاء المفتاح',1800);}catch(e){window.prompt('انسخ التقرير:',text);}});
})();
</script>
</body></html>
