@extends('layouts.app')

@section('title', 'إنشاء وكيل — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'وكيل جديد')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>إنشاء وكيل</h1>
            <p>اسم واضح، نطاق عمل، وتعليمات تشرح ما يجب فعله وما يجب تجنبه.</p>
        </div>
        <a class="btn btn-outline btn-sm" href="{{ route('app.agents.index', ['workspace' => $workspace->slug]) }}">رجوع</a>
    </div>

    @if ($errors->any())
        <div class="alert" role="alert" style="margin-bottom:16px">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="panel">
        <form method="POST" action="{{ route('app.agents.store', ['workspace' => $workspace->slug]) }}" class="fields">
            @csrf
            <div class="field">
                <label for="name">اسم الوكيل</label>
                <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="kind">النوع</label>
                <select id="kind" class="input" name="kind" required>
                    <option value="support" @selected(old('kind') === 'support')>دعم</option>
                    <option value="sales" @selected(old('kind') === 'sales')>مبيعات</option>
                    <option value="booking" @selected(old('kind') === 'booking')>حجوزات</option>
                    <option value="custom" @selected(old('kind') === 'custom')>مخصص</option>
                </select>
            </div>
            <div class="field">
                <label for="project_id">المشروع</label>
                <select id="project_id" class="input" name="project_id" required>
                    @forelse ($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) old('project_id') === $project->id)>{{ $project->name }}</option>
                    @empty
                        <option value="">لا توجد مشروعات بعد</option>
                    @endforelse
                </select>
                <small>لازم ينشأ المشروع أولًا داخل المساحة.</small>
            </div>
            <div class="field">
                <label for="persona">الشخصية (اختياري)</label>
                <input id="persona" class="input" type="text" name="persona" value="{{ old('persona') }}" placeholder="مثال: مساعد ودود ومختصر">
            </div>
            <div class="field">
                <label for="instructions">التعليمات</label>
                <textarea id="instructions" class="input" name="instructions" rows="7" placeholder="اشرح دور الوكيل، ما يعرفه، وما لا يجب أن يفعله.">{{ old('instructions') }}</textarea>
            </div>
            <button class="btn btn-primary" type="submit">حفظ كمسودة</button>
        </form>
    </section>
@endsection