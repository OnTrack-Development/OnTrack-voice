<?php
declare(strict_types=1);
require_once __DIR__.'/_auth.php';
gap_map_enter();
gap_map_authorize();
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#090b0e">
<title>OnTrack Voice | Architecture Gap Monitor</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
 <div class="top-main"><div class="brand"><div class="brand-logo">OT</div><div><h1>Architecture Gap Map <small>OnTrack Voice</small></h1><p>خريطة تفاعلية لفجوات البرمجية ونتائج الإصلاح والاختبارات</p></div></div>
 <div class="sync"><span id="health" class="health" aria-hidden="true"></span><span id="sync-label">جارٍ التحميل</span><button class="btn" type="button" id="refresh">تحديث الآن</button></div></div>
 <nav class="filters" id="filters" aria-label="تصفية المكونات"><button class="btn selected" data-area="all" type="button">الكل</button><button class="btn" data-area="saas" type="button">SaaS</button><button class="btn" data-area="shared" type="button">ترابط SaaS + Mobile</button><button class="btn" data-area="mobile" type="button">Mobile — عرض فقط</button><input id="search" type="search" aria-label="ابحث" placeholder="ابحث عن فجوة أو مكوّن أو ملف…"></nav>
</header>
<main>
 <section class="stats" aria-label="حالة الإصلاحات"><div class="stat"><span>بنود SaaS الحالية</span><strong id="stat-all">—</strong><small>المطلوب إغلاقه</small></div><div class="stat"><span>عليها إصلاحات كودية</span><strong id="stat-coded">—</strong><small>ليست معتمدة بالكامل</small></div><div class="stat"><span>تحتاج إغلاقًا أوسع</span><strong id="stat-open">—</strong><small>قائمة المتبقي</small></div><div class="stat"><span>الفجوات المعمارية</span><strong id="stat-gaps">—</strong><small>مربوطة بالأدلة</small></div></section>
 <section class="release-bar"><div><span class="chip warning">Android — متابعة فقط</span><span class="chip">VS/ محذوف</span><span class="chip">Host-Link STOP</span><span class="chip warning">لا تدّعي 100% من CI</span></div><div class="metadata">فرع <code id="branch">—</code> · HEAD <code id="revision">—</code> · آخر فحص <span id="checked-at">—</span></div></section>
 <div class="layout">
  <div class="maincol">
   <section class="surface"><div class="section-heading"><div><h2>الخريطة المعمارية</h2><p>اضغط على أي مكوّن أو فجوة لإظهار مسارات الربط والأخطاء. اللون الأحمر = فجوة مؤثرة، والأزرق = ربط.</p></div></div><div class="architecture" id="architecture"><svg id="connections" aria-hidden="true"></svg><div id="lanes"></div></div><p class="footnote">الارتباط في المصدر ليس إثباتًا أن المكالمة أو خدمة الإنتاج تعمل فعليًا.</p></section>
   <section class="surface"><div class="section-heading"><div><h2>فجوات الكشف الآلي والمراجعة</h2><p>كل فجوة مرتبطة بملفات وشروط؛ تأكيدك يُحفظ ويُطلب تجديده بعد تغيّر الكود المتأثر.</p></div></div><div id="gaps" class="gapgrid"></div></section>
   <section class="surface"><div class="section-heading"><div><h2>سيميوليتر رحلة المستخدم</h2><p>اختبارات معزولة لإنشاء مستخدم وباقات وAgent وFlow وحملة؛ لا اتصال أو دفع حقيقي.</p></div></div><div id="sim-meta" class="info">لم تصل نتائج محاكاة بعد.</div><div id="simulation" class="journey"></div></section>
   <section class="surface"><div class="section-heading"><div><h2>تقدم الإصلاحات (21 بند)</h2><p>حالة كل بند مستقلة عن قبول الهوست والجهاز. الحالة لا تتغير إلا بدليل جديد.</p></div></div><div id="tasks" class="taskgrid"></div></section>
  </div>
  <aside class="surface details" aria-live="polite"><div id="detail"><h2>لوحة التفاصيل</h2><p>اختر فجوة أو مكوّن من الخريطة لعرض الملفات والإصلاحات المقترحة والمسؤول والعلاقات.</p></div><hr><h3>حالة فحص CI</h3><div id="ci" class="ci-list">لا توجد نتيجة مرتبطة بالنسخة الحالية بعد.</div><hr><h3>أوامر عرض</h3><button id="copy-mermaid" type="button" class="btn">نسخ المخطط بصيغة Mermaid</button><p class="footnote">تحدّث الصفحة تلقائيًا كل 20 ثانية بقراءة آخر Snapshot منشور. لا تعني إعادة القراءة إعادة تشغيل الاختبارات.</p></aside>
 </div>
</main><footer>OnTrack Development · Security diagnostic · PIN-restricted · SaaS-first · No native changes</footer>
<script defer src="app.js"></script>
</body>
</html>
