@extends('layouts.app')
@section('title', 'الأزرار الذكية — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'الأزرار الذكية')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>الأزرار الذكية</h1><p>قواعد مقيدة بالحالة والصلاحية؛ لا تتغير لأن النموذج ادعى أن الحالة تغيرت.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
<section class="panel"><div class="panel-head"><h2>القواعد</h2><span class="status orange">مسودات حتى مراجعة Backend</span></div>
@if($rules->isEmpty())<div class="empty-state"><h3>لا توجد قواعد بعد</h3><p>أضف زرًا مربوطًا بمشروع وحالة أعمال معروفة.</p></div>@else<table class="data-table"><thead><tr><th>الزر</th><th>Action key</th><th>الهوية</th><th>الحالة</th></tr></thead><tbody>@foreach($rules as $rule)<tr><td>{{ $rule->label_ar }}</td><td dir="ltr">{{ $rule->action_key }}</td><td>{{ $rule->min_identity_level }}</td><td><span class="status gray">{{ $rule->status }}</span></td></tr>@endforeach</tbody></table>@endif</section>
<section class="panel"><div class="panel-head"><h2>قاعدة جديدة</h2></div><form method="POST" action="{{ route('app.smart-actions.store', ['workspace'=>$workspace->slug]) }}" class="fields">@csrf<div class="field"><label>المشروع</label><select class="input" name="project_id" required>@foreach($workspace->projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></div><div class="field"><label>اسم الزر</label><input class="input" name="label_ar" required></div><div class="field"><label>Action key</label><input class="input" name="action_key" dir="ltr" placeholder="booking.details" required></div><div class="field"><label>مستوى الهوية الأدنى</label><select class="input" name="min_identity_level"><option value="guest">ضيف</option><option value="verified">متحقق</option><option value="staff">موظف</option></select></div><div class="field"><label>حالات الأعمال (مفصولة بفاصلة)</label><input class="input" name="business_state_in" placeholder="booking.pending,booking.confirmed"></div><button class="btn btn-primary">حفظ كمسودة</button></form></section>
@endsection