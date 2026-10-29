@extends('layouts.app')

@section('title', 'نظرة عامة — OnTrack AI')
@section('page-title', 'مساحة {{ $tenant->name }}')
@section('page-sub', 'نظرة عامة على مشروعك ووكلائك')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>نظرة عامة على مساحتك</h1>
            <p>ملخص حالة مشروعك ووكلائك — بيانات فعلية من قاعدة بيانات مشروعك.</p>
        </div>
        <a class="btn btn-primary" href="#">+ إنشاء وكيل جديد</a>
    </div>

    <div class="stats" aria-label="إحصائيات">
        <div class="stat">
            <div class="stat-top"><span>المشروعات</span><span class="mini-icon" aria-hidden="true">▦</span></div>
            <strong>{{ $stats['projects'] }}</strong>
            <small>داخل هذه المساحة</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>الوكلاء</span><span class="mini-icon" aria-hidden="true">◇</span></div>
            <strong>{{ $stats['agents'] }}</strong>
            <small>في كل مشروعات الشركة</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>الوكلاء المنشورون</span><span class="mini-icon" aria-hidden="true">✓</span></div>
            <strong>{{ $stats['published'] }}</strong>
            <small>يعملون فعليًا</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>التكاملات المتصلة</span><span class="mini-icon" aria-hidden="true">⌁</span></div>
            <strong>0</strong>
            <small>لا يوجد تكامل موثق بعد</small>
        </div>
    </div>

    <div class="app-grid">
        <div>
            <section class="panel" aria-labelledby="agents-title">
                <div class="panel-head">
                    <h2 id="agents-title">الوكلاء</h2>
                    <a href="#">فتح الاستوديو</a>
                </div>
                @if ($agents->isEmpty())
                    <div class="empty-state">
                        <h3>لا توجد وكلاء بعد</h3>
                        <p>ابدأ بإنشاء وكيلك الأول وربطه بمشروع داخل مساحتك.</p>
                        <button class="btn btn-primary btn-sm" type="button">إنشاء وكيل</button>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>الوكيل</th><th>النوع</th><th>الحالة</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($agents as $agent)
                                    <tr>
                                        <td>{{ $agent->name }}</td>
                                        <td>{{ $agent->kind }}</td>
                                        <td><span class="status {{ $agent->status === 'published' ? 'green' : 'gray' }}">{{ $agent->statusLabel() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div>
            <section class="panel" aria-labelledby="start-title">
                <div class="panel-head">
                    <h2 id="start-title">ابدأ في أربع خطوات</h2>
                    <span class="status orange">بدء التشغيل</span>
                </div>
                <div class="workspace-cards">
                    <div class="small-tile">1. أنشئ مشروعًا داخل مساحتك <span>→</span></div>
                    <div class="small-tile">2. أضف وكيلًا وتعليماته <span>→</span></div>
                    <div class="small-tile">3. جرّب الوكيل بقدرات باقتك <span>→</span></div>
                    <div class="small-tile">4. انشر عبر القنوات المتاحة <span>→</span></div>
                </div>
                <div class="alert" style="margin-top:18px">وحدات الاستوديو والمعرفة والقنوات قيد البناء في هذه المرحلة.</div>
            </section>
        </div>
    </div>
@endsection