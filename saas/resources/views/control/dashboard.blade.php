@extends('layouts.control')
@section('title','لوحة إدارة المنصة — OnTrack')
@section('content')
<div class="app-heading"><div><h1>لوحة إدارة المنصة</h1><p>منطقة OnTrack فقط. لا يصلها أي عميل أو عضو في شركة.</p></div></div>
<section class="stats">
<div class="stat"><div class="stat-top"><span>الشركات</span></div><strong>{{ $counts['tenants'] }}</strong><small>tenants مسجلة</small></div>
<div class="stat"><div class="stat-top"><span>المستخدمون</span></div><strong>{{ $counts['users'] }}</strong><small>حسابات منصة</small></div>
<div class="stat"><div class="stat-top"><span>الوكلاء</span></div><strong>{{ $counts['agents'] }}</strong><small>عبر كل الشركات</small></div>
<div class="stat"><div class="stat-top"><span>المحادثات</span></div><strong>{{ $counts['conversations'] }}</strong><small>مخزنة</small></div>
<div class="stat"><div class="stat-top"><span>مزودو AI</span></div><strong>{{ $counts['providers'] }}</strong><small>ملفات منصة</small></div>
<div class="stat"><div class="stat-top"><span>الباقات</span></div><strong>{{ $counts['plans'] }}</strong><small>خطط قابلة للضبط</small></div>
</section>
<section class="panel"><div class="panel-head"><h2>مشرفو المنصة الحاليون</h2><a href="{{ route('control.ai.audit.index') }}">سجل التدقيق</a></div>
@if($adminRoles->isEmpty())<div class="empty-state"><p>لا يوجد مشرفو منصة بعد. أنشئ حساب مشرف عبر السيدر.</p></div>@else<table class="data-table"><thead><tr><th>الاسم</th><th>الدور</th></tr></thead><tbody>@foreach($adminRoles as $grant)<tr><td>{{ $grant->user->name }}</td><td>{{ $grant->role }}</td></tr>@endforeach</tbody></table>@endif
<div style="margin-top:16px" class="stack-pills"><span><a href="{{ route('control.ai.providers.index') }}">مزوّدو AI</a></span><span><a href="{{ route('control.ai.models.index') }}">الموديلات</a></span><span><a href="{{ route('control.plans.index') }}">الباقات</a></span></div>
</section>
@endsection