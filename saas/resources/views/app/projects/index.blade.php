@extends('layouts.app')

@section('title', 'المشروعات — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'المشروعات')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>المشروعات</h1>
            <p>المشروع يجمع وكلاء وقنوات وتكاملات مساحتك.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('app.projects.create', ['workspace' => $workspace->slug]) }}">+ مشروع جديد</a>
    </div>

    @if (session('status'))
        <div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>
    @endif

    <section class="panel">
        <div class="panel-head"><h2>كل المشروعات</h2></div>

        @if ($projects->isEmpty())
            <div class="empty-state">
                <h3>لا توجد مشروعات بعد</h3>
                <p>أنشئ مشروعًا أولًا لتتمكن من إضافة وكلاء إليه.</p>
                <a class="btn btn-primary btn-sm" href="{{ route('app.projects.create', ['workspace' => $workspace->slug]) }}">إنشاء مشروع</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>المشروع</th><th>النوع</th><th>الحالة</th><th>تاريخ الإنشاء</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td>{{ $project->name }}</td>
                                <td>{{ $project->kind }}</td>
                                <td><span class="status green">{{ $project->status === 'active' ? 'نشط' : $project->status }}</span></td>
                                <td>{{ $project->created_at?->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection