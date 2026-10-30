@extends('layouts.app')
@section('title','بحث المعرفة — OnTrack AI')
@section('page-title',$workspace->name)
@section('page-sub','بحث في المعرفة المفهرسة')
@section('workspace-name',$workspace->name)
@section('workspace-sub',$workspace->tenant->name)
@section('content')
<div class="app-heading"><div><h1>بحث المعرفة</h1><p>بحث نصي فعلي في القطع المفهرسة — لا يُدّعى بحث متجهي.</p></div></div>
<form method="GET" action="{{ route('app.knowledge.search',['workspace'=>$workspace->slug]) }}" class="fields" style="margin-bottom:18px"><div class="field"><input class="input" name="q" value="{{ $q }}" placeholder="اكتب كلمة بحث..." required></div><button class="btn btn-primary">بحث</button></form>
<section class="panel"><div class="panel-head"><h2>النتائج ({{ $chunks->count() }})</h2></div>
@if($chunks->isEmpty())<div class="empty-state"><p>لا توجد قطع مطابقة في معرفة هذه الشركة.</p></div>@else
@foreach($chunks as $chunk)<div class="small-tile" style="display:block;margin-bottom:10px"><strong>{{ $chunk->version?->source?->name ?: 'مصدر' }}</strong><div>{{ $chunk->content }}</div></div>@endforeach
@endif</section>
@endsection