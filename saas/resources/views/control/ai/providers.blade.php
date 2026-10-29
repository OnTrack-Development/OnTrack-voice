@extends('layouts.control')

@section('title', 'مزودو AI — إدارة OnTrack')

@section('content')
    <div class="app-heading">
        <div>
            <h1>مزودو الذكاء الاصطناعي</h1>
            <p>منطقة OnTrack فقط. هذه الإعدادات لا تظهر ولا تتوفر لأي عميل أو مساحة.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head"><h2>ملفات المزودين</h2></div>

        @if ($providers->isEmpty())
            <div class="empty-state">
                <h3>لا توجد ملفات مزودين بعد</h3>
                <p>أنشئ ملفًا أولًا، ثم اختبر الاتصال فعليًا قبل إدخال الموديلات.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>الاسم</th><th>النوع</th><th>الخدمة</th><th>الحالة</th><th>الموديلات</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($providers as $provider)
                            <tr>
                                <td>{{ $provider->name }}</td>
                                <td>{{ $provider->type }}</td>
                                <td dir="ltr" style="text-align:right">{{ $provider->base_url ?: '—' }}</td>
                                <td><span class="status {{ $provider->connection_status === 'connected' ? 'green' : 'orange' }}">{{ $provider->connection_status }}</span></td>
                                <td>{{ $provider->models_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>إضافة ملف مزود</h2></div>
        <form method="POST" action="{{ route('control.ai.providers.store') }}" class="fields">
            @csrf
            <div class="field">
                <label for="type">النوع</label>
                <select id="type" class="input" name="type" required>
                    <option value="openai_compatible">OpenAI-compatible</option>
                    <option value="google">Google</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="field">
                <label for="name">اسم المزود</label>
                <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="base_url">عنوان الخدمة (Base URL)</label>
                <input id="base_url" class="input" type="url" name="base_url" value="{{ old('base_url') }}" dir="ltr" style="text-align:right">
                <small>HTTPS فقط، تُفصح القيم في واجهة OnTrack وتُخزن مشفرة.</small>
            </div>
            <div class="field">
                <label for="api_key">مفتاح API</label>
                <input id="api_key" class="input" type="password" name="api_key" dir="ltr" autocomplete="off">
                <small>يُخزن مشفرًا ولا يُعرض بعد الحفظ.</small>
            </div>
            <div class="field">
                <label for="extra">بيانات إضافية (اختياري)</label>
                <textarea id="extra" class="input" name="extra" rows="3" dir="ltr">{{ old('extra') }}</textarea>
            </div>
            <button class="btn btn-primary" type="submit">حفظ كمسودة</button>
        </form>
    </section>
@endsection