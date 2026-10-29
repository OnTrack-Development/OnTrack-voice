@extends('layouts.app')
@section('title','إدارة العملاء — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','إدارة العملاء')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>إدارة العملاء</h1><p>جهات اتصال داخل شركتك مع حالة lead وهوية منفصلة عن بيانات القنوات الخام.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="panel"><div class="panel-head"><h2>جهات الاتصال</h2><span class="status gray">{{ $contacts->total() }} إجمالي</span></div>@if($contacts->isEmpty())<div class="empty-state"><h3>لا توجد جهات اتصال</h3><p>أضف جهة اتصال أو دع قناة موثقة تنشئها بعد استقبال حدث صالح.</p></div>@else<table class="data-table"><thead><tr><th>الاسم</th><th>البريد</th><th>الهاتف</th><th>الحالة</th></tr></thead><tbody>@foreach($contacts as $contact)<tr><td>{{ $contact->display_name }}</td><td dir="ltr">{{ $contact->email ?: '—' }}</td><td dir="ltr">{{ $contact->phone_e164 ?: '—' }}</td><td><span class="status gray">{{ $contact->lead_status }}</span></td></tr>@endforeach</tbody></table>{{ $contacts->links() }}@endif</section>
<section class="panel"><div class="panel-head"><h2>إضافة جهة اتصال</h2></div><form method="POST" action="{{ route('app.crm.store',['workspace'=>$workspace->slug]) }}" class="fields">@csrf<div class="field"><label>الاسم</label><input class="input" name="display_name" required></div><div class="field"><label>البريد</label><input class="input" type="email" name="email" dir="ltr"></div><div class="field"><label>الهاتف E.164</label><input class="input" name="phone_e164" dir="ltr"></div><div class="field"><label>الحالة</label><select class="input" name="lead_status"><option value="new">جديد</option><option value="qualified">مؤهل</option><option value="customer">عميل</option><option value="unqualified">غير مؤهل</option></select></div><div class="field"><label>ملاحظة</label><textarea class="input" name="notes" rows="3"></textarea></div><button class="btn btn-primary">حفظ</button></form></section>
@endsection