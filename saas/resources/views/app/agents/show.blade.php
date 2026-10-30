@extends('layouts.app')

@section('title', $agent->name . ' — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'استوديو الوكيل')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>{{ $agent->name }}</h1>
            <p>
                <span class="status {{ $agent->status === 'published' ? 'green' : ($agent->status === 'paused' ? 'orange' : 'gray') }}">{{ $agent->statusLabel() }}</span>
                @if ($agent->published_at) · نُشر {{ $agent->published_at->format('Y-m-d H:i') }} @endif
            </p>
        </div>
        <a class="btn btn-outline btn-sm" href="{{ route('app.agents.index', ['workspace' => $workspace->slug]) }}">كل الوكلاء</a>
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
        <div class="panel-head">
            <h2>الإعدادات</h2>
            <form method="POST" action="{{ route('app.agents.publish', ['workspace' => $workspace->slug, 'agent' => $agent->id]) }}" style="margin:0">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit">نشر الوكيل</button>
            </form>
        </div>

        <form method="POST" action="{{ route('app.agents.update', ['workspace' => $workspace->slug, 'agent' => $agent->id]) }}" class="fields">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="name">اسم الوكيل</label>
                <input id="name" class="input" type="text" name="name" value="{{ old('name', $agent->name) }}" required>
            </div>
            <div class="field">
                <label for="kind">النوع</label>
                <select id="kind" class="input" name="kind" required>
                    @foreach (['support' => 'دعم', 'sales' => 'مبيعات', 'booking' => 'حجوزات', 'custom' => 'مخصص'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('kind', $agent->kind) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="project_id">المشروع</label>
                <select id="project_id" class="input" name="project_id" required>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) old('project_id', $agent->project_id) === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="persona">الشخصية</label>
                <input id="persona" class="input" type="text" name="persona" value="{{ old('persona', $agent->persona) }}">
            </div>
            <div class="field">
                <label for="instructions">التعليمات</label>
                <textarea id="instructions" class="input" name="instructions" rows="8">{{ old('instructions', $agent->instructions) }}</textarea>
            </div>
            <button class="btn btn-primary" type="submit">حفظ التعديلات</button>
        </form>
    </section>

<section class="panel">
    <div class="panel-head"><h2>سجل الإصدارات</h2><span class="status gray">{{ $revisions->count() }} إصدار</span></div>
    @if($revisions->isEmpty())<div class="empty-state"><p>لا توجد إصدارات محفوظة بعد.</p></div>@else<table class="data-table"><thead><tr><th>الإصدار</th><th>الملاحظات</th><th>التاريخ</th></tr></thead><tbody>@foreach($revisions as $revision)<tr><td>v{{ $revision->version }}</td><td>{{ $revision->change_notes ?: '—' }}</td><td>{{ $revision->created_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table>@endif
</section>
@endsection