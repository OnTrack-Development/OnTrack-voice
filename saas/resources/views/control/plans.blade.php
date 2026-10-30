@extends('layouts.control')
@section('title','الباقات — إدارة OnTrack')
@section('content')
<div class="app-heading"><div><h1>الباقات والحصص</h1><p>خطط قابلة للضبط؛ الأسعار تنتظر قرار المالك.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="panel"><div class="panel-head"><h2>الباقات الحالية</h2></div>
@if($plans->isEmpty())<div class="empty-state"><p>لا توجد باقات بعد.</p></div>@else<table class="data-table"><thead><tr><th>الباقة</th><th>رصيد شهري</th><th>الحصص</th><th>الحالة</th></tr></thead><tbody>@foreach($plans as $plan)<tr><td>{{ $plan->name }}</td><td>{{ $plan->monthly_quota_credits }}</td><td>{{ $plan->entitlements->pluck('feature')->implode('، ') ?: '—' }}</td><td><span class="status green">{{ $plan->status }}</span></td></tr>@endforeach</tbody></table>@endif</section>
<section class="panel"><div class="panel-head"><h2>إضافة باقة</h2></div><form method="POST" action="{{ route('control.plans.store') }}" class="fields">@csrf<div class="field"><label>اسم الباقة</label><input class="input" name="name" required></div><div class="field"><label>رصيد شهري (credits)</label><input class="input" type="number" name="monthly_quota_credits" min="0" required></div><div class="field"><label>الحصص (مفصولة بفاصلة)</label><input class="input" name="features" placeholder="messages,smart_actions,conversations"></div><button class="btn btn-primary">إنشاء الباقة</button></form></section>
@endsection