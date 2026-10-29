@extends('layouts.app')
@section('title','مهام Voice — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','Voice')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>مهام Voice</h1><p>إدارة القوائم والجدولة والنتائج. النقل الهاتفي الفعلي غير متصل حتى يثبت الجهاز والقناة.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="panel"><div class="panel-head"><h2>المهام</h2><span class="status orange">PENDING_EXTERNAL: telephony</span></div>@if($campaigns->isEmpty())<div class="empty-state"><h3>لا توجد مهام</h3><p>أنشئ مهمة وقائمة أرقام بعد إثبات قناة الاتصال.</p></div>@else<table class="data-table"><thead><tr><th>المهمة</th><th>الحالة</th><th>المنطقة الزمنية</th><th>الأرقام</th></tr></thead><tbody>@foreach($campaigns as $campaign)<tr><td>{{ $campaign->name }}</td><td><span class="status gray">{{ $campaign->status }}</span></td><td>{{ $campaign->timezone }}</td><td>{{ $campaign->entries_count }}</td></tr>@endforeach</tbody></table>@endif</section>
<section class="panel"><div class="panel-head"><h2>مهمة جديدة</h2></div><form method="POST" action="{{ route('app.voice.store',['workspace'=>$workspace->slug]) }}" class="fields">@csrf<div class="field"><label>اسم المهمة</label><input class="input" name="name" required></div><div class="field"><label>المنطقة الزمنية</label><input class="input" name="timezone" value="Africa/Cairo" required></div><div class="field"><label>التأخير بعد المكالمة (ثوانٍ)</label><input class="input" type="number" name="delay_seconds" value="30" min="0" max="86400" required></div><div class="field"><label>أقصى المحاولات</label><input class="input" type="number" name="max_attempts" value="1" min="1" max="10" required></div><button class="btn btn-primary">حفظ كمسودة</button></form></section>
@endsection