@extends('layouts.app')
@section('title','القنوات الاجتماعية — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','القنوات الاجتماعية وTelegram')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>القنوات الاجتماعية</h1><p>ربط رسمي فقط، وقدرات صادقة لكل قناة، وبدون scraping أو حسابات شخصية.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<section class="panel"><div class="panel-head"><h2>القدرات الرسمية لكل قناة</h2></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>القناة</th><th>الوضع</th><th>استقبال/إرسال DM</th><th>تعليقات/ردود</th><th>Mentions</th><th>وسائط</th><th>أزرار</th><th>حالة التسليم</th></tr></thead><tbody>
@foreach($channels as $key => $channel)
<tr><td>{{ $channel['label'] }}</td><td><span class="status {{ $channel['enabled'] ? 'green' : 'gray' }}">{{ $channel['enabled'] ? 'فعالة' : 'غير مفعّلة' }}</span></td>
<td>{{ $channel['capabilities']['receive_private_dm'] && $channel['capabilities']['send_private_dm'] ? 'نعم' : 'لا' }}</td>
<td>{{ $channel['capabilities']['receive_comments'] && $channel['capabilities']['send_comments'] ? 'نعم' : 'لا' }}</td>
<td>{{ $channel['capabilities']['receive_mentions'] ? 'نعم' : 'لا' }}</td>
<td>{{ $channel['capabilities']['attachments'] ? 'نعم' : 'لا' }}</td>
<td>{{ $channel['capabilities']['buttons'] ? 'نعم' : 'لا' }}</td>
<td>{{ $channel['capabilities']['delivery_status'] ? 'نعم' : 'لا' }}</td></tr>
<tr><td colspan="8" class="subtle" style="font-size:11px">{{ $channel['mode'] }}</td></tr>
@endforeach
</tbody></table></div></section>

<section class="panel"><div class="panel-head"><h2>الاتصالات المحفوظة</h2><span class="status orange">كلها Draft حتى دليل حي</span></div>
@if($connections->isEmpty())<div class="empty-state"><p>لا توجد اتصالات اجتماعية محفوظة بعد.</p></div>@else<table class="data-table"><thead><tr><th>الحساب</th><th>القناة</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>@foreach($connections as $connection)<tr><td>{{ $connection->account_label }}</td><td>{{ $connection->channel }}</td><td><span class="status orange">{{ $connection->state }}</span></td><td><form method="POST" action="{{ route('app.social.revoke',['workspace'=>$workspace->slug,'connection'=>$connection->id]) }}" onsubmit="return confirm('فصل القناة؟')">@csrf<button class="btn btn-outline btn-sm">فصل</button></form></td></tr>@endforeach</tbody></table>@endif</section>

<section class="panel"><div class="panel-head"><h2>ربط قناة Telegram</h2></div><form method="POST" action="{{ route('app.social.store',['workspace'=>$workspace->slug]) }}" class="fields">@csrf<input type="hidden" name="channel" value="telegram"><div class="field"><label>تسمية الحساب/البوت</label><input class="input" name="account_label" required></div><div class="field"><label>Bot Token (من @BotFather)</label><input class="input" type="password" name="bot_token" dir="ltr" autocomplete="off" required></div><button class="btn btn-primary">حفظ كمسودة</button></form></section>
@endsection