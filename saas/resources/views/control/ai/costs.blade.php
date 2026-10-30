@extends('layouts.control')
@section('title','تكاليف AI — إدارة OnTrack')
@section('content')
<div class="app-heading"><div><h1>تكاليف واستهلاك مزودي AI</h1><p>منطقة داخلية فقط؛ لا تعرض هذه الصفحة أي تفاصيل تكلفة للعميل.</p></div></div>
<section class="stats"><div class="stat"><div class="stat-top"><span>الوحدات المسجلة</span></div><strong>{{ $totalUnits }}</strong><small>من ledger داخلي فعلي</small></div></section>
<section class="panel"><div class="panel-head"><h2>آخر السجلات</h2></div>@if($costs->isEmpty())<div class="empty-state"><h3>لا توجد تكاليف مسجلة بعد</h3><p>لن نعرض أرقامًا مختلقة قبل تشغيل مزود وإثبات طلبات فعلية.</p></div>@else<table class="data-table"><thead><tr><th>Model ID</th><th>Tenant internal ID</th><th>Units</th><th>التاريخ</th></tr></thead><tbody>@foreach($costs as $cost)<tr><td dir="ltr">{{ $cost->provider_model_id }}</td><td>{{ $cost->tenant_id ?: '—' }}</td><td>{{ $cost->units }}</td><td>{{ $cost->created_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table>@endif</section>
@endsection