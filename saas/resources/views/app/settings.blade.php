@extends('layouts.app')
@section('title','الإعدادات — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','الإعدادات')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>الإعدادات</h1><p>ضبط سلوك التشغيل الآلي على مستوى الشركة.</p></div></div>
@if(session('status'))<div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert" style="margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="panel"><div class="panel-head"><h2>إيقاف رد الذكاء بعد رد الموظف</h2></div><form method="POST" action="{{ route('app.settings.update',['workspace'=>$workspace->slug]) }}" class="fields"><div class="field"><label for="minutes">المدة بالدقائق (1..1440)</label><input id="minutes" class="input" type="number" name="ai_reply_pause_minutes" min="1" max="1440" value="{{ $setting->ai_reply_pause_minutes }}" required></div><button class="btn btn-primary">حفظ</button></form></section>
@endsection