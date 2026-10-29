export const REQUIRED_FIELDS = ['agent_name', 'company_name', 'agent_role', 'customer_name', 'offer_name', 'offer_details', 'goal'];

export function validateMission(mission) {
  if (!mission || typeof mission !== 'object' || Array.isArray(mission)) throw new Error('اكتب تفاصيل المهمة.');
  if (Object.hasOwn(mission, 'brief')) {
    const brief = typeof mission.brief === 'string' ? mission.brief.trim() : '';
    if (!brief || [...brief].length > 8000 || /[\x00-\x08\x0B\x0C\x0E-\x1F]/.test(brief)) {
      throw new Error('اكتب تفاصيل المهمة، بحد أقصى 8000 حرف.');
    }
    return {brief};
  }
  if (REQUIRED_FIELDS.some(key => typeof mission[key] !== 'string' || !mission[key].trim())) {
    throw new Error('اكتب اسم الإيجنت والشركة ودوره والعميل والعرض وتفاصيله وهدف المكالمة.');
  }
  for (const key of ['asking_price', 'minimum_price']) {
    const value = mission[key] || '';
    if (value && (!/^\d{1,12}(?:\.\d{1,2})?$/.test(value) || Number(value) <= 0)) {
      throw new Error('اكتب الأسعار كمبالغ موجبة بدون فواصل، بحد أقصى منزلتين عشريتين.');
    }
  }
  if (mission.asking_price && !mission.currency?.trim()) throw new Error('حدد العملة مع السعر.');
  if (mission.minimum_price && (!mission.asking_price || Number(mission.minimum_price) > Number(mission.asking_price))) {
    throw new Error('أقل سعر مسموح لازم يكون أقل من أو يساوي السعر المطلوب.');
  }
  return {...mission};
}

// Live transcription can arrive AFTER model audio; hold the whole first response
// until a recognized input transcript arrives, instead of clipping its beginning.
export class CustomerSpeechGate {
  constructor(outbound) {
    this.outbound = outbound;
    this.armed = !outbound;
    this.heardCustomer = !outbound;
    this.pending = [];
    this.pendingBytes = 0;
  }
  arm() { this.armed = true; }
  accept(content) {
    if (!this.outbound) return [content];
    if (!this.armed) return [];
    if (content.interrupted) {
      this.pending = [];
      this.pendingBytes = 0;
    }
    const text = content.inputTranscription?.text;
    if (typeof text === 'string' && /[\p{L}\p{N}]/u.test(text)) {
      this.heardCustomer = true;
      const queued = this.pending;
      this.pending = [];
      this.pendingBytes = 0;
      if (queued.length) {
        const {inputTranscription, ...output} = content;
        return [{inputTranscription}, ...queued, output];
      }
    }
    if (this.heardCustomer) return [content];
    if (content.modelTurn || content.outputTranscription || content.turnComplete) {
      const bytes = JSON.stringify(content).length;
      if (this.pending.length >= 128 || this.pendingBytes + bytes > 2 * 1024 * 1024) {
        throw new Error('لم يتم التعرف على كلام العميل. أوقف الجلسة وراجع مدخل الصوت.');
      }
      this.pending.push(content);
      this.pendingBytes += bytes;
    }
    return [];
  }
}

export const LEGACY_EXAMPLE_MISSION = {
  agent_name: 'عمر', company_name: 'شركة المثال العقارية — بيانات تجريبية',
  agent_role: 'مساعد مبيعات عقارات', customer_name: 'أحمد — عميل تجريبي', customer_phone: '',
  customer_context: 'مثال فقط: العميل مهتم بسكن عائلي من 3 غرف، وسأل عن موعد معاينة. لا توجد بيانات عميل حقيقي.',
  offer_name: 'شقة تجريبية 150 متر',
  offer_details: 'كل البيانات التالية افتراضية للاختبار فقط:\nشقة 150 متر في مشروع تجريبي بالقاهرة الجديدة، 3 غرف، 2 حمام، الدور الثاني، أسانسير، تشطيب كامل.\nحالة التوافر والملكية والأوراق وموعد التسليم تحتاج تأكيد المسؤول. لا توجد مواعيد معاينة محجوزة بالفعل.',
  asking_price: '4500000', minimum_price: '4300000', currency: 'جنيه مصري',
  payment_terms: 'مثال تجريبي: الدفع نقدي فقط. التقسيط غير معتمد.',
  allowed_concessions: 'التفاوض على السعر داخل الحد المعتمد فقط؛ أي تسهيل إضافي يرجع للمسؤول.',
  negotiation_style: 'اسأل عن المساحة والميزانية المناسبة، اشرح القيمة، ولا تعرض خصماً من البداية. عند اعتراض السعر استكشف السبب، وقلل تدريجياً بمقدار 50000 جنيه فقط عند وجود اهتمام جاد، داخل الحد المعتمد.',
  objection_responses: 'السعر غالي: اسأل عن الميزانية ووضح المواصفات المعروفة ثم تفاوض داخل الحدود.\nعايز تقسيط: وضح أن المعتمد نقدي، ويمكن طلب مراجعة المسؤول بدون وعد بالموافقة.\nهل الأوراق سليمة؟: يحتاج تأكيد المسؤول؛ لا تقدم ضماناً قانونياً.',
  goal: 'تأكيد الاهتمام والاتفاق على موعد معاينة مقترح، ثم تلخيصه للمشغّل ليؤكده. لا تدّعي أن المعاينة حُجزت.',
  opening: 'ألو، أستاذ أحمد؟ أنا عمر مساعد مبيعات شركة المثال العقارية. الوقت مناسب نتكلم دقيقة عن الشقة اللي كنت مهتم بيها؟',
  handoff_rules: 'عرض أقل من الحد المعتمد، تقسيط، ضمانات قانونية أو أي مواصفة غير مذكورة: يرجع للمسؤول بدون وعد.',
};

export const EXAMPLE_MISSION = {
  brief: `اسمك عمر، مساعد مبيعات في شركة المثال العقارية. بتكلم أحمد اللي مهتم بسكن عائلي من 3 غرف.

بتعرض شقة 150 متر في مشروع تجريبي بالقاهرة الجديدة: 3 غرف، 2 حمام، الدور الثاني، أسانسير وتشطيب كامل. كل البيانات افتراضية للاختبار؛ التوافر والملكية والأوراق والتسليم يحتاجوا تأكيد المسؤول.

السعر المطلوب 4500000 جنيه مصري. أقل سعر مسموح 4300000 جنيه، وده سري ما تقولوش للعميل. الدفع نقدي فقط، والتقسيط غير معتمد. ما تعرضش خصم من البداية؛ لو فيه اهتمام جاد تفاوض تدريجياً بمقدار 50000 جنيه داخل الحد المسموح. أي ضمان قانوني أو تسهيل إضافي يرجع للمسؤول من غير وعد.

اتكلم بالمصري بحماس، اسأل عن احتياجه واربط المواصفات بيه. لو قال السعر غالي اسأله عن الميزانية ووضح القيمة. هدفك تأكيد الاهتمام والاتفاق على موعد معاينة مقترح، من غير ما تدعي إنه اتحجز.

ابدأ بعد ما تسمع العميل: ألو، أستاذ أحمد؟ أنا عمر مساعد مبيعات شركة المثال العقارية، بكلمك بخصوص الشقة اللي كنت مهتم بيها.`,
};
