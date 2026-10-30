@extends('layouts.control')
@section('title','سجل التدقيق — إدارة OnTrack')
@section('content')
<div class="app-heading"><div><h1>سجل تدقيق المنصة</h1><p>أحداث platform-only دون أسرار.</p></div></div>
<section class="panel"><div class="panel-head"><h2>آخر 100 حدث</h2></div>
@if($events->isEmpty())<div class="empty-state"><p>لا توجد أحداث بعد.</p></div>@else<table class="data-table"><thead><tr><th>الإجراء</th><th>المنفذ</th><th>الملخص المنقح</th><th>التاريخ</th></tr></thead><tbody>@foreach($events as $event)<tr><td>{{ $event->action }}</td><td>{{ $event->actor?->name ?: 'النظام' }}</td><td>{{ $event->redacted_summary ?: '—' }}</td><td>{{ $event->created_at?->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table>@endif</section>
@endsection