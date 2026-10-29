@extends('layouts.marketing')
@section('title', $title.' — OnTrack AI')
@section('description', $sub)
@section('content')
<section class="section section-white" style="padding-top:110px">
    <div class="container">
        <div class="section-heading">
            <div class="eyebrow">{{ $pageKey }}</div>
            <h1>{{ $h1 }}</h1>
            <p>{{ $sub }}</p>
        </div>
        <div class="split">
            <div>
                <p class="subtle" style="line-height:2">هذه الصفحة جزء من موقع OnTrack AI. الوحدات تُبنى وتُختبر خطوة بخطوة، والحالة التفصيلية موثقة في ريبو المشروع دون أرقام ادعائية.</p>
                <div class="stack-pills" style="margin-top:20px">
                    <span>بناء الوكلاء</span><span>الأزرار الذكية</span><span>قواعد المعرفة</span><span>قنوات آمنة</span>
                </div>
            </div>
            <div class="product-visual">
                <div style="font-size:11px;color:#c7cad0;margin-bottom:15px">OnTrack AI — نظرة عامة</div>
                <div class="visual-card">
                    <h3>تشغيل واضح</h3>
                    <div class="visual-row">مساحات ومشروعات <em>معزولة</em></div>
                    <div class="visual-row">وكلاء بتعليمات <em>موثقة</em></div>
                    <div class="visual-row">أزرار حسب الحالة <em>من Backend</em></div>
                    <div class="visual-row">قنوات بحالة صادقة <em>لا green كاذب</em></div>
                </div>
            </div>
        </div>
        <div style="text-align:center;margin-top:44px">
            <a class="btn btn-primary" href="{{ route('register') }}">أنشئ حسابك ↖</a>
        </div>
    </div>
</section>
@endsection