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
@endsection