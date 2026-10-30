@extends('layouts.auth')
@section('title','قبول دعوة الفريق — OnTrack AI')
@section('content')
<h1 style="font-size:22px;margin:0 0 8px">قبول دعوة الفريق</h1>
<p class="subtle">أنت على وشك الانضمام إلى مساحة <strong>{{ $invitation->workspace->name }}</strong> بدور <strong>{{ $invitation->role }}</strong>.</p>
<form method="POST" action="{{ route('app.team.accept',['invitation'=>$invitation->id,'token'=>$token]) }}" style="margin-top:20px">@csrf<button class="btn btn-primary" style="width:100%">قبول الدعوة</button></form>
@endsection