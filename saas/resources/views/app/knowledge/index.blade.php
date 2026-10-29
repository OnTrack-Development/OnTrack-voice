@extends('layouts.app')

@section('title', 'قواعد المعرفة — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'قواعد المعرفة')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>قواعد المعرفة</h1>
            <p>أضف نصًا معرفيًا ليستخدمه وكلاؤك لاحقًا — بدون أي ادعاء ببحث متجهي قبل إعداد محرك فعلي.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert" role="alert" style="margin-bottom:16px">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="panel">
        <div class="panel-head"><h2>المصادر</h2></div>

        @if ($sources->isEmpty())
            <div class="empty-state">
                <h3>لا توجد مصادر معرفة بعد</h3>
                <p>أضف أول مصدر نصي من النموذج أدناه.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>المصدر</th><th>النوع</th><th>الحالة</th><th>آخر تحديث</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($sources as $source)
                            <tr>
                                <td>{{ $source->name }}</td>
                                <td>{{ $source->kind }}</td>
                                <td>
                                    <span class="status {{ $source->status === 'indexed' ? 'green' : ($source->status === 'processing' ? 'orange' : 'gray') }}">
                                        {{ $source->status === 'indexed' ? 'مفهرس نصيًا' : ($source->status === 'processing' ? 'قيد المعالجة' : $source->status) }}
                                    </span>
                                </td>
                                <td>{{ $source->updated_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>إضافة مصدر نصي</h2></div>
        <form method="POST" action="{{ route('app.knowledge.store', ['workspace' => $workspace->slug]) }}" class="fields">
            @csrf
            <div class="field">
                <label for="name">اسم المصدر</label>
                <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="content">المحتوى</label>
                <textarea id="content" class="input" name="content" rows="8" required placeholder="اكتب الأسئلة الشائعة أو معلومات الخدمة. اترك سطرًا فارغًا بين كل فقرة ليتم تقسيمها كقطع معرفة.">{{ old('content') }}</textarea>
                <small>يتم حفظ النص كما هو وتقسيمه إلى قطع. الفهرسة الحالية نصية فقط.</small>
            </div>
            <button class="btn btn-primary" type="submit">حفظ وفهرسة</button>
        </form>
    </section>
@endsection