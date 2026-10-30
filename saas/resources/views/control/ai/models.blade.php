@extends('layouts.control')
@section('title','موديلات AI — إدارة OnTrack')
@section('content')
<div class="app-heading"><div><h1>موديلات AI</h1><p>أسماء الموديلات تُدخل حرفيًا بعد اختبار الاتصال، ولا تظهر للعملاء أبدًا.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="panel"><div class="panel-head"><h2>الموديلات حسب المزود</h2></div>
@forelse($providers as $provider)
<h3 style="margin:18px 0 8px">{{ $provider->name }} <span class="status orange">{{ $provider->connection_status }}</span></h3>
@if($provider->models->isEmpty())<div class="empty-state"><p>لا توجد موديلات بعد لهذا المزود.</p></div>@else<table class="data-table"><thead><tr><th>Model ID (حرفي)</th><th>Label</th><th>الحالة</th></tr></thead><tbody>@foreach($provider->models as $model)<tr><td dir="ltr">{{ $model->model_id_exact }}</td><td>{{ $model->label }}</td><td><span class="status gray">{{ $model->test_status }}</span></td></tr>@endforeach</tbody></table>@endif
@empty<div class="empty-state"><h3>لا توجد مزودات</h3><p>أنشئ ملف مزود واختبر الاتصال أولًا.</p></div>@endforelse
</section>
<section class="panel"><div class="panel-head"><h2>إضافة موديل</h2></div><form method="POST" action="{{ route('control.ai.models.store') }}" class="fields">@csrf<div class="field"><label>المزود</label><select class="input" name="provider_profile_id" required>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select></div><div class="field"><label>Model ID بالضبط</label><input class="input" name="model_id_exact" dir="ltr" required placeholder="احفظ النص كما هو حرفيًا"></div><div class="field"><label>تسمية داخلية</label><input class="input" name="label" dir="ltr"></div><button class="btn btn-primary">حفظ الموديل</button></form></section>
@endsection