@extends('layouts.app')
@section('title','الفريق — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','الفريق والعضويات')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>الفريق</h1><p>أعضاء الشركة وأدوارهم ودعوات الانضمام.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<section class="panel"><div class="panel-head"><h2>الأعضاء</h2><span class="status gray">{{ $members->count() }} عضو</span></div>
<table class="data-table"><thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>إجراءات</th></tr></thead><tbody>
@foreach($members as $member)
<tr><td>{{ $member->user->name }}</td><td dir="ltr" style="text-align:right">{{ $member->user->email }}</td>
<td><span class="status {{ $member->role === 'owner' ? 'green' : 'gray' }}">{{ $member->role }}</span></td>
<td>
@if($membership->role === 'owner' && $member->id !== $membership->id)
<form method="POST" action="{{ route('app.team.updateRole', ['workspace'=>$workspace->slug,'membership'=>$member->id]) }}" style="display:inline-flex;gap:4px">@csrf<select class="input" name="role" style="width:auto;padding:5px 8px" onchange="this.form.submit()"><option value="owner" @selected($member->role==='owner')>owner</option><option value="admin" @selected($member->role==='admin')>admin</option><option value="member" @selected($member->role==='member')>member</option></select></form>
<form method="POST" action="{{ route('app.team.remove', ['workspace'=>$workspace->slug,'membership'=>$member->id]) }}" style="display:inline" onsubmit="return confirm('إزالة العضو من الشركة؟')">@csrf<button class="btn btn-outline btn-sm">إزالة</button></form>
@else<span class="subtle" style="font-size:11px">—</span>@endif
</td></tr>
@endforeach
</tbody></table></section>

@if($membership->role === 'owner')
<section class="panel"><div class="panel-head"><h2>دعوة عضو</h2></div><form method="POST" action="{{ route('app.team.invite',['workspace'=>$workspace->slug]) }}" class="fields">@csrf<div class="field"><label>البريد الإلكتروني</label><input class="input" type="email" name="email" dir="ltr" required></div><div class="field"><label>الدور</label><select class="input" name="role"><option value="member">عضو</option><option value="admin">مشرف شركة</option><option value="owner">مالك</option></select></div><button class="btn btn-primary">إرسال دعوة</button></form></section>
<section class="panel"><div class="panel-head"><h2>دعوات معلقة</h2></div>@if($invitations->filter(fn($inv) => $inv->isPending())->isEmpty())<div class="empty-state"><p>لا توجد دعوات معلقة.</p></div>@else<table class="data-table"><thead><tr><th>البريد</th><th>الدور</th><th>تنتهي</th></tr></thead><tbody>@foreach($invitations->filter(fn($i)=>$i->isPending()) as $inv)<tr><td dir="ltr" style="text-align:right">{{ $inv->email }}</td><td>{{ $inv->role }}</td><td>{{ $inv->expires_at->format('Y-m-d') }}</td></tr>@endforeach</tbody></table>@endif</section>
@endif
@endsection