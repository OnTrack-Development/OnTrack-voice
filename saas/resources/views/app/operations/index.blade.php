@extends('layouts.app')
@section('title','غرفة العمليات — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','غرفة العمليات')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>غرفة العمليات</h1><p>أرقام حقيقية من قاعدة بيانات شركتك فقط — لا توجد أرقام مختلقة.</p></div></div>
<section class="stats">
<div class="stat"><div class="stat-top"><span>الوكلاء</span></div><strong>{{ $counts['agents'] }}</strong><small>منهم {{ $counts['published_agents'] }} منشور</small></div>
<div class="stat"><div class="stat-top"><span>المحادثات</span></div><strong>{{ $counts['conversations'] }}</strong><small>{{ $counts['open_conversations'] }} مفتوحة</small></div>
<div class="stat"><div class="stat-top"><span>قفل بشري دائم</span></div><strong>{{ $counts['human_locked'] }}</strong><small>يتطلب فكًا يدويًا</small></div>
<div class="stat"><div class="stat-top"><span>استهلاك الشهر</span></div><strong>{{ $counts['usage_units_month'] }}</strong><small>وحدة من ledger فعلي</small></div>
<div class="stat"><div class="stat-top"><span>مصادر المعرفة</span></div><strong>{{ $counts['knowledge_sources'] }}</strong><small>قيد الفهرسة أو مفهرسة</small></div>
<div class="stat"><div class="stat-top"><span>استدعاءات الأزرار</span></div><strong>{{ $counts['button_invocations'] }}</strong><small>سجل idempotent</small></div>
</section>
<section class="panel"><div class="panel-head"><h2>أحداث التدقيق الأخيرة</h2></div>@if($recentAudit->isEmpty())<div class="empty-state"><p>لا توجد أحداث تدقيق بعد.</p></div>@else<table class="data-table"><thead><tr><th>الإجراء</th><th>النوع</th><th>التاريخ</th></tr></thead><tbody>@foreach($recentAudit as $event)<tr><td>{{ $event->action }}</td><td>{{ $event->resource_type ?: '—' }}</td><td>{{ $event->occurred_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table>@endif</section>
@endsection