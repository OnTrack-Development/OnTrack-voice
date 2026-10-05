<?php
$config = require __DIR__ . '/api/demo_config.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#08090c">
  <meta name="description" content="OnTrack Live — مكالمة صوتية ذكية مباشرة">
  <title>OnTrack Live</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="assets/app.css?v=060">
</head>
<body>
<div class="ambient ambient-a"></div>
<div class="ambient ambient-b"></div>

<div class="call-app">
  <main class="call-screen">
    <section class="call-panel">
      <div class="call-kicker">ONTRACK LIVE · CALL DESK</div>

      <div class="call-title">
        <h1 id="callTitle">جهّز مكالمة العميل.</h1>
        <p id="callDescription">حدد هوية الإيجنت والعرض وحدود التفاوض، ثم اتصل بالعميل من Phone Link.</p>
      </div>

      <div class="mission-controls">
        <label for="callMode" class="sr-only">نوع المكالمة</label>
        <select id="callMode">
          <option value="outbound" selected>مكالمة عميل من التليفون</option>
          <option value="demo">تجربة خدمات أون تراك</option>
        </select>
        <button type="button" id="editMissionBtn" class="mission-edit">إعداد المهمة</button>
      </div>
      <div id="missionSummary" class="mission-summary" aria-live="polite">ابدأ بإعداد بيانات العميل والعرض.</div>

      <div class="voice-core-wrap">
        <div class="voice-core" id="orb">
          <div class="core-glow"></div>
          <div class="wave wave-1"></div>
          <div class="wave wave-2"></div>
          <div class="wave wave-3"></div>

          <div class="core-center">
            <div class="core-icon core-logo">
              <img src="https://ontrackegy.com/wp-content/uploads/2026/05/image.svg" alt="OnTrack">
            </div>
            <strong id="orbText">جاهز</strong>
            <small id="callTimer">00:00</small>
          </div>
        </div>
      </div>

      <div class="call-info-line">
        <span class="call-info-item">
          <small>الحالة</small>
          <strong id="liveState">جاهز</strong>
        </span>
        <span class="call-info-separator"></span>
        <span class="call-info-item">
          <small>الميكروفون</small>
          <strong id="micState">متوقف</strong>
        </span>
        <span class="call-info-separator"></span>
        <span class="call-info-item">
          <small>الصوت</small>
          <strong id="voiceState">Charon</strong>
        </span>
      </div>

      <div class="voice-select-row">
        <label for="voice">شخصية الصوت</label>
        <div class="select-wrap">
          <select id="voice" aria-label="اختيار صوت Gemini">
            <optgroup label="مقترحة">
              <option value="Charon" selected>Charon — واضح ومعلوماتي</option>
              <option value="Achird">Achird — ودود</option>
              <option value="Algieba">Algieba — ناعم</option>
              <option value="Gacrux">Gacrux — ناضج</option>
              <option value="Sulafat">Sulafat — دافئ</option>
              <option value="Schedar">Schedar — متزن</option>
              <option value="Kore">Kore — حازم</option>
              <option value="Puck">Puck — حيوي</option>
            </optgroup>
            <optgroup label="أصوات إضافية">
              <option value="Zephyr">Zephyr — مشرق</option>
              <option value="Fenrir">Fenrir — متحمس</option>
              <option value="Leda">Leda — شبابي</option>
              <option value="Orus">Orus — حازم</option>
              <option value="Aoede">Aoede — خفيف</option>
              <option value="Callirrhoe">Callirrhoe — هادي</option>
              <option value="Autonoe">Autonoe — مشرق</option>
              <option value="Enceladus">Enceladus — هوائي</option>
              <option value="Iapetus">Iapetus — واضح</option>
              <option value="Umbriel">Umbriel — مريح</option>
              <option value="Despina">Despina — ناعم</option>
              <option value="Erinome">Erinome — واضح</option>
              <option value="Algenib">Algenib — خشن</option>
              <option value="Rasalgethi">Rasalgethi — معلوماتي</option>
              <option value="Laomedeia">Laomedeia — حيوي</option>
              <option value="Achernar">Achernar — ناعم</option>
              <option value="Alnilam">Alnilam — حازم</option>
              <option value="Pulcherrima">Pulcherrima — مباشر</option>
              <option value="Zubenelgenubi">Zubenelgenubi — كاجوال</option>
              <option value="Vindemiatrix">Vindemiatrix — لطيف</option>
              <option value="Sadachbia">Sadachbia — نشيط</option>
              <option value="Sadaltager">Sadaltager — خبير</option>
            </optgroup>
          </select>
          <span class="select-arrow">⌄</span>
        </div>
      </div>

      <div class="call-actions">
        <button id="callBtn" class="call-btn call-btn-start">
          <span class="call-btn-icon">●</span>
          <span id="callBtnText">جهّز الإيجنت</span>
        </button>

        <button id="muteBtn" class="call-btn call-btn-mute" disabled>
          <span id="muteIcon">◉</span>
          <span id="muteText">كتم</span>
        </button>

        <button id="stopBtn" class="call-btn call-btn-end" disabled>
          <span>■</span>
          <span>إيقاف الإيجنت</span>
        </button>
      </div>

      <button type="button" id="armCallBtn" class="arm-call" hidden disabled>العميل رد — فعّل انتظار صوته</button>
      <p id="callError" class="call-error" role="alert" hidden></p>

      <div class="call-note" id="callNote">
        جهّز الإيجنت، رن من Phone Link، وبعد الرد فعّل السماع. إنهاء الإيجنت لا يقفل مكالمة الهاتف.
      </div>

      <div class="sr-only" id="modelState">gemini-3.8-live</div>
      <div class="sr-only" id="sessionBadge">READY</div>
    </section>
  </main>
</div>

<section class="chat-popup" id="chatPopup" aria-hidden="true">
  <div class="chat-popup-card">
    <div class="chat-popup-head">
      <div class="chat-person">
        <span class="chat-avatar chat-avatar-logo">
          <img src="https://ontrackegy.com/wp-content/uploads/2026/05/image.svg" alt="OnTrack">
        </span>
        <div>
          <strong id="chatAgentName">الإيجنت</strong>
          <small><span class="chat-live-dot"></span><span id="chatCallState">المكالمة جارية</span></small>
        </div>
      </div>

      <div class="chat-head-actions">
        <button id="clearTranscript" class="chat-clear" type="button" aria-label="مسح المحادثة">مسح</button>
        <button id="chatCloseBtn" class="chat-close" type="button" aria-label="إغلاق نافذة المحادثة">✕</button>
      </div>
    </div>

    <div class="conversation" id="transcript">
      <div class="empty-state" id="emptyTranscript">
        <div class="typing-bars"><i></i><i></i><i></i></div>
        <strong>ابدأ تتكلم</strong>
        <span>المحادثة هتظهر هنا أثناء المكالمة.</span>
      </div>
    </div>

    <div class="chat-call-controls">
      <button id="popupMuteBtn" class="popup-call-btn popup-mute" type="button" disabled>
        <span id="popupMuteIcon">◉</span>
        <span id="popupMuteText">كتم</span>
      </button>

      <button id="popupStopBtn" class="popup-call-btn popup-end" type="button" disabled>
        <span>■</span>
        <span>إيقاف الإيجنت</span>
      </button>
    </div>
  </div>
</section>

<button id="chatReopenBtn" class="chat-reopen" type="button" hidden>
  <span class="chat-reopen-dot"></span>
  <span>فتح المحادثة</span>
</button>

<dialog id="missionDialog" class="mission-dialog" aria-labelledby="missionHeading">
  <form id="missionForm">
    <div class="mission-dialog-head">
      <div><h2 id="missionHeading">مهمة المكالمة</h2><p>البيانات دي هي مرجع الإيجنت الوحيد في مكالمة العميل.</p></div>
      <button type="button" id="closeMissionBtn" class="chat-close" aria-label="إغلاق إعداد المهمة">✕</button>
    </div>
    <div class="mission-fields">
      <fieldset><legend>الإيجنت والعميل</legend>
        <div class="mission-grid">
          <label>اسم الإيجنت<input name="agent_name" required maxlength="80" placeholder="مثلاً: عمر"></label>
          <label>اسم الشركة<input name="company_name" required maxlength="120" placeholder="اسم شركتك"></label>
          <label>دوره<input name="agent_role" required maxlength="120" placeholder="مثلاً: مسؤول مبيعات عقارات"></label>
          <label>اسم العميل<input name="customer_name" required maxlength="120" autocomplete="off"></label>
          <label class="wide">رقم العميل — للاتصال اليدوي فقط<input name="customer_phone" type="tel" maxlength="40" autocomplete="off" dir="ltr"></label>
          <label class="wide">ما تعرفه عن العميل<textarea name="customer_context" maxlength="1500" rows="2" placeholder="احتياجه، ميزانيته، آخر تواصل، مصدر الاهتمام..."></textarea></label>
        </div>
      </fieldset>
      <fieldset><legend>الشقة أو المنتج المعروض</legend>
        <div class="mission-grid">
          <label class="wide">اسم العرض<input name="offer_name" required maxlength="200" placeholder="مثلاً: شقة 150 متر في التجمع"></label>
          <label class="wide">كل التفاصيل المؤكدة<textarea name="offer_details" required maxlength="8000" rows="5" placeholder="العنوان، المساحة، عدد الغرف، الدور، التشطيب، الخدمات، حالة الملكية، التسليم، المعاينة... اكتب المعلومات المؤكدة فقط."></textarea></label>
          <label>السعر المطلوب<input name="asking_price" type="number" min="0.01" max="999999999999.99" step="0.01" placeholder="بدون فواصل"></label>
          <label>العملة<input name="currency" maxlength="40" value="جنيه مصري"></label>
          <label class="wide">شروط الدفع المعتمدة<textarea name="payment_terms" maxlength="1500" rows="2" placeholder="نقدي أو تقسيط، المقدم، المدة، الرسوم..."></textarea></label>
        </div>
      </fieldset>
      <fieldset><legend>التفاوض والهدف</legend>
        <div class="mission-grid">
          <label class="wide">أقل سعر مسموح — سري<input name="minimum_price" type="number" min="0.01" max="999999999999.99" step="0.01" placeholder="سيبه فاضي لو الخصم محتاج موافقتك"><small>الإيجنت لا يكشف الحد للعميل. أي خصم يحتاج تحديد سعر أدنى.</small></label>
          <label class="wide">التسهيلات المسموحة<textarea name="allowed_concessions" maxlength="1500" rows="2" placeholder="اكتب المسموح فقط؛ الفاضي يعني لا توجد تسهيلات إضافية."></textarea></label>
          <label class="wide">طريقة التفاوض<textarea name="negotiation_style" maxlength="2500" rows="3" placeholder="استكشف الاحتياج، أبرز القيمة، لا تعرض خصماً قبل اعتراض السعر، خطوات التنازل..."></textarea></label>
          <label class="wide">اعتراضات متوقعة وردودها<textarea name="objection_responses" maxlength="3500" rows="3" placeholder="السعر غالي → ... / الموقع بعيد → ..."></textarea></label>
          <label class="wide">هدف المكالمة<textarea name="goal" required maxlength="1000" rows="2" placeholder="مثلاً: الاتفاق على موعد معاينة مقترح وتأكيد اهتمام العميل."></textarea></label>
          <label class="wide">الافتتاحية — اختيارية<textarea name="opening" maxlength="1000" rows="2" placeholder="ألو، أستاذ ...؟ أنا ... مساعد مبيعات شركة ...، الوقت مناسب نتكلم دقيقة؟"></textarea></label>
          <label class="wide">إمتى يرجع لك؟<textarea name="handoff_rules" maxlength="1500" rows="2" placeholder="طلبات خارج السعر أو الصلاحيات، معلومات قانونية ناقصة، موافقة نهائية..."></textarea></label>
        </div>
      </fieldset>
      <p class="mission-help">المهمة تفضل في الصفحة الحالية فقط؛ إعادة تحميل الصفحة تمسحها. تجهيز الجلسة يرسلها لخدمة الصوت. الحجز أو إرسال الرسائل يحتاج تنفيذك.</p>
      <p id="missionError" class="call-error" role="alert" hidden></p>
    </div>
    <div class="mission-dialog-actions">
      <button type="button" id="exampleMissionBtn" class="mission-edit">تحميل مثال تجريبي</button>
      <button type="submit" class="call-btn call-btn-start">اعتمد المهمة</button>
    </div>
  </form>
</dialog>

<span id="statusDot" class="sr-only"></span>
<span id="engineBadge" class="sr-only">جاهز للمكالمة</span>

<script type="module" src="assets/app.js?v=060"></script>
</body>
</html>
