@extends('layouts.app')
@section('title','الباقة والرصيد — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','الباقة والرصيد')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>الباقة والرصيد</h1><p>محددات استهلاك تقدمها OnTrack؛ لا يعرض تكلفة أو هوامش داخلية لأي مزود AI.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if(!$subscription)<section class="panel"><div class="empty-state"><h3>لا توجد باقة نشطة</h3><p>لم تُرصد اشتراكات بعد على هذه المساحة.</p></div></section>
@else
<section class="panel"><div class="panel-head"><h2>{{ $subscription->plan->name }}</h2><span class="status green">{{ $subscription->status }}</span></div><div class="stats" style="grid-template-columns:repeat(3,1fr)"><div class="stat"><div class="stat-top"><span>حد الشهر</span></div><strong>{{ $subscription->plan->monthly_quota_credits }}</strong><small>رصيد اعتمادي</small></div><div class="stat"><div class="stat-top"><span>المستخدم هذا الشهر</span></div><strong>{{ $ledger->sum('units') }}</strong><small>من سجل الاستهلاك الحقيقي</small></div><div class="stat"><div class="stat-top"><span>بدأت</span></div><strong style="font-size:16px">{{ $subscription->started_at->format('Y-m-d') }}</strong><small>تجدد {{ $subscription->renews_at?->format('Y-m-d') ?? 'حسب السياسة' }}</small></div></div></section>
<section class="panel"><div class="panel-head"><h2>سجل الاستهلاك (آخر 50)</h2></div>@if($ledger->isEmpty())<div class="empty-state"><p>لا يوجد استهلاك مسجل حتى الآن.</p></div>@else<table class="data-table"><thead><tr><th>النوع</th><th>الوحدات</th><th>مفتاح الإثبات</th><th>التاريخ</th></tr></thead><tbody>@foreach($ledger as $row)<tr><td>{{ $row->kind }}</td><td>{{ $row->units }}</td><td dir="ltr">{{ $row->idempotency_key }}</td><td>{{ $row->occurred_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table>@endif</section>
@endif
<section class="panel" style="margin-top:18px"><div class="panel-head"><h2>الباقات المتاحة</h2><span class="status gray">Kashier Checkout</span></div>
<p>الباقة لا تُفعَّل إلا بعد وصول تأكيد دفع صحيح إلى خادم OnTrack. الأسعار التي لم تعتمدها الإدارة لا يُسمح بشرائها.</p>
<div class="stats" style="grid-template-columns:repeat(auto-fit,minmax(225px,1fr));gap:16px">
@foreach($availablePlans as $plan)
<div class="stat" style="min-height:190px">
<div class="stat-top"><span>{{ $plan->name }}</span></div>
<strong>{{ $plan->price_minor === null ? 'سعر غير محدد' : number_format($plan->price_minor/100,2).' EGP' }}</strong>
<small>شهريًا · {{ $plan->monthly_quota_credits }} رصيد</small>
<div style="margin:12px 0;font-size:13px;line-height:1.8">{{ $plan->entitlements->where('quota','>',0)->pluck('feature')->implode(' · ') ?: 'بدون مزايا إضافية' }}</div>
@if($plan->price_minor)
<form method="POST" action="{{ route('app.plan.checkout',['workspace'=>$workspace->slug,'plan'=>$plan->id]) }}">@csrf
<label style="display:block;margin-bottom:9px"><input type="checkbox" name="agree_terms" value="1" required> أوافق على شروط الباقة وتجديدها يدويًا بعد انتهاء المدة</label>
<button class="btn btn-primary" type="submit">الدفع الآمن عبر Kashier</button></form>
@else <small>بانتظار التسعير من الإدارة</small> @endif
</div>
@endforeach
</div></section>
<section class="panel" style="margin-top:18px"><div class="panel-head"><h2>الأصوات الخاصة Premium</h2><span class="status orange">تتطلب محرك صوت متوافق</span></div>
<div class="stats" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
@foreach($premiumVoices as $id=>$voice)
<div class="stat"><span>{{ $voice['label'] }}</span><small>{{ $id }}</small><div style="margin-top:8px">🔒 {{ $voice['ready'] ? 'متاح حسب الباقة' : 'قيد الدمج — غير متاح للمكالمات الآن' }}</div></div>
@endforeach
</div></section>
@endsection