@extends('layouts.app')
@section('title',$campaign->name.' — Voice')
@section('page-title',$workspace->name)
@section('page-sub','Voice / '.$campaign->name)
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>{{ $campaign->name }}</h1><p>قائمة الأرقام وحالة المعالجة — النقل الهاتفي الحقيقي ما زال PENDING_EXTERNAL.</p></div><span class="status orange">{{ $campaign->status }}</span></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
<section class="stats"><div class="stat"><div class="stat-top"><span>الإجمالي</span></div><strong>{{ $summary['total'] }}</strong></div><div class="stat"><div class="stat-top"><span>في الطابور</span></div><strong>{{ $summary['pending'] }}</strong></div><div class="stat"><div class="stat-top"><span>خاطئ</span></div><strong>{{ $summary['invalid'] }}</strong></div></section>
<section class="panel"><div class="panel-head"><h2>استيراد أرقام</h2><small class="subtle">رقم واحد في السطر أو اسم,رقم</small></div><form method="POST" action="{{ route('app.voice.import',['workspace'=>$workspace->slug,'campaign'=>$campaign->id]) }}" class="fields">@csrf<textarea class="input" name="rows" rows="7" required placeholder="Ahmed,+201001234567&#10;سارة,+201112345678"></textarea><button class="btn btn-primary">استيراد ومعاينة</button></form></section>
<section class="panel"><div class="panel-head"><h2>الأرقام</h2></div>@if($entries->isEmpty())<div class="empty-state"><p>لا توجد أرقام مستوردة.</p></div>@else<table class="data-table"><thead><tr><th>الاسم</th><th>الرقم</th><th>الحالة</th><th>النتيجة الهاتفية</th><th>نتيجة العمل</th></tr></thead><tbody>@foreach($entries as $entry)<tr><td>{{ $entry->display_name ?: '—' }}</td><td dir="ltr">{{ $entry->phone_e164 }}</td><td><span class="status {{ $entry->queue_status==='invalid'?'orange':'gray' }}">{{ $entry->queue_status }}</span></td><td>{{ $entry->telephony_outcome ?: '—' }}</td><td>{{ $entry->business_outcome ?: '—' }}</td></tr>@endforeach</tbody></table>{{ $entries->links() }}@endif</section>
@endsection