@extends('layouts.app')

@section('title', 'الوكلاء — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'استوديو الوكلاء')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>الوكلاء</h1>
            <p>أنشئ وكيلًا، اكتب تعليماته، ثم انشره.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('app.agents.create', ['workspace' => $workspace->slug]) }}">+ إنشاء وكيل</a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2>كل الوكلاء</h2>
            <a href="{{ route('app.overview', ['workspace' => $workspace->slug]) }}">نظرة عامة</a>
        </div>

        @if ($agents->isEmpty())
            <div class="empty-state">
                <h3>لا توجد وكلاء بعد</h3>
                <p>ابدأ بإنشاء أول وكيل داخل هذه المساحة.</p>
                <a class="btn btn-primary btn-sm" href="{{ route('app.agents.create', ['workspace' => $workspace->slug]) }}">إنشاء وكيل</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>الوكيل</th><th>النوع</th><th>الحالة</th><th>آخر تحديث</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                            <tr>
                                <td>{{ $agent->name }}</td>
                                <td>{{ $agent->kind }}</td>
                                <td>
                                    <span class="status {{ $agent->status === 'published' ? 'green' : ($agent->status === 'paused' ? 'orange' : 'gray') }}">
                                        {{ $agent->statusLabel() }}
                                    </span>
                                </td>
                                <td>{{ $agent->updated_at?->format('Y-m-d H:i') }}</td>
                                <td><a href="{{ route('app.agents.show', ['workspace' => $workspace->slug, 'agent' => $agent->id]) }}">فتح</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection