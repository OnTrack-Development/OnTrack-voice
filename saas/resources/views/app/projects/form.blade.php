@extends('layouts.app')

@section('title', 'مشروع جديد — OnTrack AI')
@section('page-title', $workspace->name)
@section('page-sub', 'مشروع جديد')
@section('workspace-name', $workspace->name)
@section('workspace-sub', $workspace->tenant->name)

@section('content')
    <div class="app-heading">
        <div>
            <h1>مشروع جديد</h1>
            <p>سمِّ المشروع وحدد نوعه.</p>
        </div>
        <a class="btn btn-outline btn-sm" href="{{ route('app.projects.index', ['workspace' => $workspace->slug]) }}">رجوع</a>
    </div>

    @if ($errors->any())
        <div class="alert" role="alert" style="margin-bottom:16px">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="panel">
        <form method="POST" action="{{ route('app.projects.store', ['workspace' => $workspace->slug]) }}" class="fields">
            @csrf
            <div class="field">
                <label for="name">اسم المشروع</label>
                <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="kind">النوع</label>
                <select id="kind" class="input" name="kind" required>
                    @foreach (['service' => 'خدمة', 'store' => 'متجر', 'leads' => 'عملاء متوقعون', 'operations' => 'عمليات', 'custom' => 'مخصص'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary" type="submit">إنشاء المشروع</button>
        </form>
    </section>
@endsection