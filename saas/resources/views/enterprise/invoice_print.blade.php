<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فاتورة {{ $invoice['invoice_no'] ?? '' }}</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 36px; color: #111; max-width: 760px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #E11D48; padding-bottom: 16px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: right; }
        th { background: #f8f8f8; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print" style="margin-bottom:18px">
    <button onclick="window.print()" style="padding:8px 16px;background:#E11D48;color:#fff;border:none;border-radius:6px;cursor:pointer">طباعة / حفظ PDF</button>
    <a href="/?tab=billing" style="margin-right:12px">عودة للفوترة</a>
</div>
<div class="head">
    <div>
        <h2 style="margin:0">{{ $invoice['issuer_name'] ?? 'IO Voice' }}</h2>
        <div>فاتورة رقم: <strong dir="ltr">{{ $invoice['invoice_no'] }}</strong></div>
    </div>
    <div style="text-align:left">
        <div>الحالة: <strong>{{ strtoupper((string) ($invoice['status'] ?? '')) }}</strong></div>
        <div>تاريخ الإصدار: {{ $invoice['issued_at'] }}</div>
    </div>
</div>
<div><strong>العميل:</strong> {{ $invoice['bill_to_name'] }} ({{ $invoice['bill_to_email'] }})</div>
<table>
    <thead><tr><th>الباقة / البند</th><th>المبلغ</th><th>مرجع السداد</th></tr></thead>
    <tbody>
        <tr>
            <td>{{ $invoice['plan_name'] }}</td>
            <td dir="ltr">{{ number_format(((int) ($invoice['amount_minor'] ?? 0)) / 100, 2) }} {{ $invoice['currency'] ?? 'EGP' }}</td>
            <td dir="ltr">{{ $invoice['payment_reference'] ?? '—' }}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
