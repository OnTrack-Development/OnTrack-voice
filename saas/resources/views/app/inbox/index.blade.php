@extends('layouts.app')
@section('title','صندوق الوارد — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','صندوق الوارد')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>صندوق الوارد</h1><p>محادثات محفوظة مع فصل واضح بين العميل والموظف والذكاء.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
<section class="panel"><div class="panel-head"><h2>المحادثات</h2><div style="display:flex;gap:8px"><a class="btn btn-outline btn-sm" href="{{ route('app.inbox.index',['workspace'=>$workspace->slug]) }}">الكل</a><a class="btn btn-outline btn-sm" href="{{ route('app.inbox.index',['workspace'=>$workspace->slug,'channel'=>'telegram']) }}">Telegram</a><a class="btn btn-outline btn-sm" href="{{ route('app.inbox.index',['workspace'=>$workspace->slug,'channel'=>'whatsapp']) }}">WhatsApp</a><a class="btn btn-outline btn-sm" href="{{ route('app.inbox.index',['workspace'=>$workspace->slug,'channel'=>'web']) }}">Web</a></div></div>
@if($conversations->isEmpty())<div class="empty-state"><h3>لا توجد محادثات بعد</h3><p>تظهر هنا الرسائل الواردة بعد تفعيل قناة رسمية والتحقق من webhook.</p></div>@else<table class="data-table"><thead><tr><th>المعرف</th><th>القناة</th><th>الحالة</th><th>التحكم البشري</th><th></th></tr></thead><tbody>@foreach($conversations as $conversation)<tr><td>{{ $conversation->contact_key }}</td><td>{{ $conversation->channel }}</td><td>{{ $conversation->status }}</td><td>@if($conversation->isHumanLocked())<span class="status orange">قفل دائم</span>@elseif($conversation->isAiPaused())<span class="status orange">إيقاف مؤقت حتى {{ $conversation->ai_pause_until_utc->format('H:i') }}</span>@else<span class="status green">AI متاح حسب القناة</span>@endif</td><td><a href="{{ route('app.inbox.show',['workspace'=>$workspace->slug,'conversation'=>$conversation->id]) }}">فتح</a></td></tr>@endforeach</tbody></table>@endif</section>
@endsection