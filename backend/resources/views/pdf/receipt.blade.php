<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; direction: rtl; font-size: 14px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .dept-name { font-size: 18px; font-weight: bold; }
        .receipt-number { font-size: 28px; font-weight: bold; text-align: center; margin: 20px 0; }
        .qr-code { position: absolute; top: 20mm; left: 20mm; width: 3cm; height: 3cm; }
        .info-row { display: flex; justify-content: space-between; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: right; }
        th { background: #f0f0f0; }
        .total-row { font-size: 20px; font-weight: bold; text-align: left; margin-top: 15px; }
        .words { text-align: center; font-size: 16px; margin-top: 5px; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature { border-top: 1px solid #000; width: 150px; text-align: center; padding-top: 5px; }
        .stamp-box { border: 1px dashed #000; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666; }
        .verification { text-align: center; font-size: 12px; color: #555; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="qr-code">
        {!! \App\Services\ReceiptService::generateQrSvg(json_decode($receipt->qr_payload ?? '{}', true) ? json_encode(json_decode($receipt->qr_payload ?? '{}', true)) : $receipt->receipt_number) !!}
    </div>
    <div class="header">
        <div class="dept-name">{{ $deptName }}</div>
        <div>نظام الإيصالات المالية</div>
    </div>

    <div class="receipt-number">{{ $receipt->receipt_number }}</div>

    <div class="info-row">
        <span>التاريخ: {{ $receipt->created_at->format('d/m/Y H:i') }}</span>
        <span>السجل: {{ $receipt->register->name_ar }}</span>
    </div>
    <div class="info-row">
        <span>أمين الصندوق: {{ $receipt->creator->name }}</span>
        <span>الحالة: {{ $receipt->status }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>البيان</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->items as $item)
            <tr>
                <td>{{ $item->label_ar_snapshot }}</td>
                <td>{{ $item->amount !== null ? number_format((float)$item->amount, 3) : $item->text_value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-row">المجموع: {{ number_format((float)$receipt->total_amount, 3) }} د.ع</div>
    <div class="words">{{ \App\Services\ReceiptService::amountToWords((float)$receipt->total_amount) }}</div>

    <div class="verification">كود التحقق: {{ substr(hash('sha256', $receipt->receipt_number . number_format((float)$receipt->total_amount, 3, '.', '') . $receipt->created_at->toDateString() . env('APP_KEY')), 0, 8) }}</div>

    <div class="footer">
        <div class="signature">أمين الصندوق: ___________</div>
        <div class="stamp-box">ختم النظام</div>
    </div>
</body>
</html>
