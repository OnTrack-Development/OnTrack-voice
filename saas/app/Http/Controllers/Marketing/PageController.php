<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;

class PageController extends Controller
{
    private const PAGES = [
        'platform' => ['title' => 'المنصة', 'h1' => 'منصة واحدة لبناء وتشغيل وكلاء الذكاء', 'sub' => 'شركات ومساحات ومشروعات ووكلاء ومعرفة وأزرار ذكية وقنوات وصندوق وارد ضمن عزل واضح.'],
        'features/agent-builder' => ['title' => 'بناء الوكلاء', 'h1' => 'ابنِ وكيلك خطوة بخطوة', 'sub' => 'اسم ودور وتعليمات ومعرفة وأدوات وقنوات وتجربة ثم نشر. بلا خطوة لاختيار مزود ذكاء أو إدخال مفتاح.'],
        'features/smart-buttons' => ['title' => 'الأزرار الذكية', 'h1' => 'أزرار تتغير حسب الحالة الفعلية', 'sub' => 'قرار مبني على حالة أعمال موثقة وصلاحيات هوية، مع منع التكرار وتدقيق.'],
        'features/knowledge' => ['title' => 'قواعد المعرفة', 'h1' => 'معرفة مرتبطة بمصادر مشروعك', 'sub' => 'نصوص وأسئلة شائعة ومصادر مقسمة إلى قطع، مع فهرسة نصية صريحة لا تدّعي بحثًا متجهيًا.'],
        'features/integrations' => ['title' => 'التكاملات', 'h1' => 'اربط API مشروعك التجاري', 'sub' => 'Laravel وWordPress وWHMCS وأي REST API، بصلاحيات ومفاتيح منفصلة تمامًا عن مزودي الذكاء.'],
        'features/channels' => ['title' => 'القنوات', 'h1' => 'قناة واحدة أو أكثر لكل شركة', 'sub' => 'Widget وقنوات رسائل مستقبلًا؛ كل قناة بملكية وعزل وحالة اتصال صادقة.'],
        'features/telegram' => ['title' => 'Telegram — OnTrack AI', 'h1' => 'Telegram عبر Bot API الرسمي', 'sub' => 'Webhook secret وdedupe وهوية محادثة وقدرات منفصلة؛ لا نعد باتصال حي قبل إضافة Bot Token واختبار الوارد والصادر.'],
        'features/inbox' => ['title' => 'صندوق الوارد', 'h1' => 'محادثات موحدة بعزل واضح', 'sub' => 'رسائل العميل والموظف والذكاء منفصلة، مع قفل بشري وإيقاف مؤقت قابل للضبط.'],
        'features/operations' => ['title' => 'غرفة العمليات', 'h1' => 'رؤية من بياناتك الحقيقية', 'sub' => 'مقاييس وسجلات وأحداث مستمدة من قاعدة بيانات شركتك فقط.'],
        'industries/ecommerce' => ['title' => 'التجارة الإلكترونية', 'h1' => 'وكلاء للتجارة الإلكترونية', 'sub' => 'دعم طلبات، حالة شحن، ومتابعة عملاء عبر قنوات مشروعك.'],
        'industries/real-estate' => ['title' => 'العقارات', 'h1' => 'وكلاء للعقارات', 'sub' => 'متابعة عملاء وحجوزات معاينات وتذكيرات مواعيد.'],
        'industries/hospitality' => ['title' => 'المطاعم والفنادق', 'h1' => 'وكلاء للضيافة', 'sub' => 'حجوزات واستفسارات ومتابعة رضا العملاء.'],
        'industries/education' => ['title' => 'التعليم', 'h1' => 'وكلاء للتعليم', 'sub' => 'إجابات الاستفسارات الشائعة ومتابعة التسجيل.'],
        'industries/healthcare' => ['title' => 'الرعاية الصحية', 'h1' => 'وكلاء للرعاية الصحية', 'sub' => 'مواعيد وتذكيرات ومتابعة، مع احترام خصوصية البيانات.'],
        'industries/logistics' => ['title' => 'الشحن والتوصيل', 'h1' => 'وكلاء للشحن', 'sub' => 'تتبع الشحنات والرد على استفسارات الحالة.'],
        'industries/marketing-agencies' => ['title' => 'وكالات التسويق', 'h1' => 'وكلاء لوكالات التسويق', 'sub' => 'جذب عملاء متوقعين ومتابعة حملات العملاء.'],
        'industries/hosting' => ['title' => 'الاستضافة', 'h1' => 'وكلاء للاستضافة', 'sub' => 'دعم فني أولي وحالة طلبات واستفسارات.'],
        'industries/professional-services' => ['title' => 'الخدمات المهنية', 'h1' => 'وكلاء للخدمات المهنية', 'sub' => 'حجز استشارات ومتابعة عملاء.'],
        'pricing' => ['title' => 'الباقات', 'h1' => 'باقات مرنة لإطلاق عمل', 'sub' => 'خطط واشتراكات قابلة للضبط. الأسعار المعتمدة غير متاحة بعد؛ اطلب عرضًا عبر تواصل معنا.'],
        'security' => ['title' => 'الأمان', 'h1' => 'الأمان أولوية تصميم', 'sub' => 'عزل صارم بين الشركات، تشفير أسرار، توقيع Webhooks، وتدقيق. OnTrack تدير مزودي الذكاء؛ العميل لا يضيف مفاتيح AI.'],
        'enterprise' => ['title' => 'المؤسسات', 'h1' => 'حلول للمؤسسات', 'sub' => 'مساحات متعددة وأدوار وفريق وعزل بيانات.'],
        'developers' => ['title' => 'المطورون', 'h1' => 'وثائق وAPI للفرق', 'sub' => 'تكامل REST وتوثيق متاح خلال مراحل البناء.'],
        'docs' => ['title' => 'التوثيق', 'h1' => 'توثيق المنصة', 'sub' => 'مراجع الإعداد والـAPI تُنشر عند اكتمال كل وحدة.'],
        'contact' => ['title' => 'تواصل معنا', 'h1' => 'تواصل معنا', 'sub' => 'أرسل استفسارك ليصل فريق OnTrack.'],
        'about' => ['title' => 'عن المنصة', 'h1' => 'عن OnTrack AI', 'sub' => 'منصة مستقلة لبناء وتشغيل وكلاء الذكاء لكل أنواع الشركات.'],
    ];

    public function feature(string $feature)
    {
        return $this->render('features/'.$feature);
    }

    public function industry(string $industry)
    {
        return $this->render('industries/'.$industry);
    }

    public function show(string $page)
    {
        return $this->render($page);
    }

    private function render(string $page)
    {
        $key = $this->resolve($page);
        abort_unless($key, 404);
        $data = self::PAGES[$key];
        return view('marketing.page', [
            'pageKey' => $key,
            'title' => $data['title'],
            'h1' => $data['h1'],
            'sub' => $data['sub'],
            'url' => '/'.$key,
        ]);
    }

    public function sitemap()
    {
        $urls = ['/', '/login', '/register'];
        foreach (array_keys(self::PAGES) as $key) {
            $urls[] = '/'.$key;
        }
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= "  <url><loc>https://ontrackegy.com".htmlspecialchars($url, ENT_XML1, 'UTF-8')."</loc></url>\n";
        }
        $xml .= "</urlset>\n";
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }

    private function resolve(string $page): ?string
    {
        $candidates = [$page, trim($page, '/')];
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, self::PAGES)) {
                return $candidate;
            }
        }
        return null;
    }
}