@extends('layouts.marketing')

@section('title', 'OnTrack AI — اصنع موظفك الذكي')

@section('content')
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="ui-badge"><span class="dot"></span>منصة لبناء وتشغيل الوكلاء الذكيين</span>
                <h1>حوّل كل محادثة<br>إلى <span class="stroke">تجربة أذكى.</span></h1>
                <p>أنشئ وكلاء ذكاء اصطناعي يفهموا شغلك، يربطوا بيانات مشروعاتك، وينفذوا الإجراءات المسموح بها — من مكان واحد، ولأي نشاط تجاري.</p>
                <div class="hero-buttons">
                    <a class="btn btn-primary" href="{{ route('register') }}">أنشئ وكيلك <span aria-hidden="true">↖</span></a>
                    <a class="btn btn-outline" href="#demo">شاهد التجربة <span aria-hidden="true">◁</span></a>
                </div>
                <div class="trust-row">
                    <span>مخصص لأي شركة</span>
                    <span>تكامل API</span>
                    <span>أزرار تتغير حسب الحالة</span>
                </div>
            </div>

            <div class="demo-card" id="demo" aria-label="عرض توضيحي تفاعلي لمحرك الأزرار">
                <div class="demo-header">
                    <div class="agent-name">
                        <div class="agent-icon">AI</div>
                        <div><b>مساعد خدمة العملاء</b><small>مثال توضيحي لمحرك المحادثة</small></div>
                    </div>
                    <span class="demo-chip">تجربة توضيحية</span>
                </div>
                <div class="demo-body">
                    <div class="bubble customer">أهلاً، ممكن أعرف حالة حجزي؟<div class="message-ts">العميل · الآن</div></div>
                    <div class="bubble bot" id="heroReply">أهلاً بيك! نقدر نراجع حجزك بعد التحقق من الحساب. اختار الخطوة المناسبة:</div>
                    <div class="demo-tools" id="heroActions">
                        <button type="button" class="pill-action" data-state="verified">✓ التحقق من الحساب</button>
                        <button type="button" class="pill-action" data-state="human">◉ التحدث مع موظف</button>
                    </div>
                    <div class="demo-trace" id="heroTrace">● حالة توضيحية: الحساب غير متحقق — الإجراءات الحساسة مقفولة</div>
                </div>
                <div class="demo-footer">
                    <small>الأزرار تتغير حسب حالة النظام الحقيقية — هذه معاينة توضيحية فقط</small>
                    <button type="button" class="btn btn-outline btn-sm" id="resetDemo">إعادة التجربة ↻</button>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-white" id="features">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">المزايا الرئيسية</div>
                <h2>نظام واحد. كل المحادثات والأدوات.</h2>
                <p>من أول سؤال بسيط لحد تنفيذ إجراء داخل مشروعك، أنت متحكم في الطريقة والصلاحيات والنتيجة.</p>
            </div>
            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">✧</div>
                    <h3>وكلاء على مقاس نشاطك</h3>
                    <p>دعم، مبيعات، حجوزات، عمليات أو أي مهمة مخصصة. لكل وكيل شخصية وتعليمات واضحة.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">◇</div>
                    <h3>معرفة مرتبطة بمصادرك</h3>
                    <p>ملفات وأسئلة شائعة وبيانات مشروع، مع فصل المعرفة العامة عن بيانات العملاء الحية.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">↗</div>
                    <h3>أفعال وأزرار ذكية</h3>
                    <p>قرارات حسب الحالة الفعلية للعميل، مع تأكيد وموافقة قبل أي إجراء حساس.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">⌁</div>
                    <h3>تشغيل ومراقبة واضحة</h3>
                    <p>محادثات وسجلات وتنبيهات، ومعرفة هل الرد اتبعت أو الإجراء اتنفذ فعلاً.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="workflow">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">طريقة العمل</div>
                <h2>من فكرة إلى وكيل جاهز للتجربة.</h2>
                <p>خطوات واضحة: اختار باقتك وابنِ الوكيل. OnTrack تدير الذكاء بالكامل، وإنت تركز على عملك.</p>
            </div>
            <div class="steps">
                <article class="step"><span>01</span><h3>أنشئ مساحتك</h3><p>أضف شركتك ومشروعك والموظفين حسب أدوارهم.</p></article>
                <article class="step"><span>02</span><h3>صمّم وكيلك</h3><p>اختار الهدف والتعليمات وحدود الردود والإجراءات.</p></article>
                <article class="step"><span>03</span><h3>اربط المعرفة والأدوات</h3><p>ملفات وقواعد معرفة، وربط API مشروعك التجاري.</p></article>
                <article class="step"><span>04</span><h3>اختبر وانشر</h3><p>جرّب الشات والأزرار والـWidget قبل تفعيلها.</p></article>
            </div>
        </div>
    </section>

    <section class="section section-white" id="buttons">
        <div class="container split">
            <div>
                <div class="eyebrow">Dynamic Smart Actions</div>
                <h2>مش مجرد رد.<br>الخطوة المناسبة لكل حالة.</h2>
                <p class="subtle">أزرار المحادثة بتتغيّر حسب بيانات النظام الحقيقي، مش لأن الوكيل تخيّل حالة جديدة. كل خطوة بتراجع الهوية والصلاحيات وبترجع تأكيد التنفيذ من التطبيق المصدر.</p>
                <div class="stack-pills">
                    <span>حالة العميل</span><span>ملكية البيانات</span><span>صلاحيات الأفعال</span><span>تأكيد التنفيذ</span>
                </div>
            </div>
            <div class="product-visual">
                <div style="font-size:11px;color:#c7cad0;margin-bottom:15px">منطق الأزرار — معاينة للتصميم</div>
                <div class="visual-card">
                    <h3>تدفق طلب العميل</h3>
                    <div class="visual-row">◉ التحقق من الحساب <em>مطلوب</em></div>
                    <div class="visual-row">◆ استدعاء بيانات الحجز <em>عند التحقق</em></div>
                    <div class="visual-row">↗ عرض الخيارات المناسبة <em>حسب الحالة</em></div>
                    <div class="visual-row">✓ تأكيد وإعادة التحقق <em>قبل التنفيذ</em></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="integrations">
        <div class="container split">
            <div>
                <div class="eyebrow">Integration Hub</div>
                <h2>أي مشروع برمجي.<br>نفس محرك الذكاء.</h2>
                <p class="subtle">اربط Laravel أو WordPress أو WHMCS أو أي نظام تاني بالـREST API والـWebhooks. كل تكامل له صلاحيات وبيانات اعتماد منفصلة عن غيره.</p>
                <a class="btn btn-outline" href="{{ route('register') }}">ابدأ ربط مشروعك ↖</a>
            </div>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:23px;padding:26px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:13px">
                    <div class="visual-row">Laravel <em>REST/SDK</em></div>
                    <div class="visual-row">WordPress <em>REST API</em></div>
                    <div class="visual-row">WHMCS <em>API</em></div>
                    <div class="visual-row">REST API <em>قابل للتوصيل</em></div>
                </div>
                <div style="border-top:1px dashed #dedfe4;margin:24px 0"></div>
                <div class="visual-row" style="background:#fcfcfd">⊕ OnTrack Integration Gateway <em>صلاحيات + مراجعة</em></div>
            </div>
        </div>
    </section>

    <section class="section section-white" id="industries">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">كل أنواع الشركات</div>
                <h2>منصة واحدة، لكل قطاع.</h2>
                <p>اختر قالب يناسبك أو ابدأ من الصفر، من غير قصر الخدمة على صناعة بعينها.</p>
            </div>
            <div class="industry-grid">
                <div>التجارة والمتاجر الإلكترونية</div>
                <div>العقارات وإدارة المشروعات</div>
                <div>الفنادق والمطاعم</div>
                <div>التعليم ومراكز التدريب</div>
                <div>الخدمات الصحية</div>
                <div>شركات البرمجة والاستضافة</div>
                <div>وكالات التسويق</div>
                <div>الشحن والتوصيل</div>
                <div>وأي قطاع آخر ←</div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">أسئلة مهمة</div>
                <h2>قبل ما تبدأ.</h2>
            </div>
            <div class="faq-list">
                <details>
                    <summary>هل لازم أكون مبرمج عشان أنشئ وكيل؟</summary>
                    <p>تقدر تبدأ من واجهة البناء. التكاملات المعقدة مع أنظمتك تحتاج إعداد API آمن من مطوّر عند الحاجة.</p>
                </details>
                <details>
                    <summary>هل محتاج أشتري API للذكاء الاصطناعي؟</summary>
                    <p>لأ. OnTrack هي اللي بتوفر وتشغّل الذكاء الاصطناعي ضمن باقتك. تقدر تربط API مشروعك التجاري عشان الوكيل ينفذ المهام المصرح بها.</p>
                </details>
                <details>
                    <summary>هل الأزرار بتتغير حسب حالة الطلب؟</summary>
                    <p>المحرك مصمم يستند للحالة المؤكدة والصلاحيات. في معاينة الصفحة الحالية الحالة توضيحية وليست خدمة مباشرة.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="footer-cta">
                <h2>موظف ذكي، على مقاس مشروعك.</h2>
                <p>ابدأ بتجربة الوكيل، وربطه بمشروعك خطوة بخطوة.</p>
                <a class="btn btn-primary" href="{{ route('register') }}">افتح حسابك ↖</a>
            </div>
        </div>
    </section>

    <script>
        (function () {
            var reply = document.getElementById('heroReply');
            var trace = document.getElementById('heroTrace');
            var actions = document.getElementById('heroActions');
            var reset = document.getElementById('resetDemo');
            var original = { reply: reply.textContent, trace: trace.textContent };
            var states = {
                verified: {
                    reply: 'تم التحقق من الحساب. تقدر الآن تشوف تفاصيل الحجز: الموعد، الحالة، وخيارات التعديل المتاحة حسب حالتك.',
                    trace: '● حالة توضيحية: الحساب متحقق — أصبحت إجراءات الحجز متاحة'
                },
                human: {
                    reply: 'تمام، هحوّلك لموظف بشري. سيتم إبلاغ فريق الدعم مع ملخص المحادثة.',
                    trace: '● حالة توضيحية: طلب تحويل إلى موظف — يتطلب ربط قناة فعلية لإرساله'
                }
            };
            actions.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-state]');
                if (!btn) return;
                var s = states[btn.getAttribute('data-state')];
                if (!s) return;
                reply.textContent = s.reply;
                trace.textContent = s.trace;
            });
            reset.addEventListener('click', function () {
                reply.textContent = original.reply;
                trace.textContent = original.trace;
            });
        })();
    </script>
@endsection