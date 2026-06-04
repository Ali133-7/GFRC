import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateNarrow({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-sm bg-white p-6" dir="rtl">
      {show('header', activeElements) && (
        <div className="text-center mb-4">
          {logo && <img src={logo} alt="logo" className="h-24 mx-auto mb-2 object-contain" />}
          <h1 className="text-lg font-bold">{settings.company_name}</h1>
          <div className="h-px bg-gray-300 my-2" />
        </div>
      )}

      {(show('title', activeElements) || show('number', activeElements) || show('meta', activeElements)) && (
        <div className="text-center mb-4">
          {show('title', activeElements) && <p className="font-bold text-sm">{settings.receipt_title}</p>}
          {show('number', activeElements) && <p className="text-2xl font-bold font-mono my-1">{receipt.receipt_number}</p>}
          {show('meta', activeElements) && <p className="text-xs text-gray-500">{formatDateTime(receipt.created_at)}</p>}
        </div>
      )}

      {show('table', activeElements) && (
        <div className="space-y-2 mb-4">
          {(receipt.items || []).map((item) => (
            <div key={item.id} className="flex justify-between text-sm border-b border-gray-100 pb-1">
              <span className="text-gray-600">{item.label_ar_snapshot}</span>
              <span className="font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</span>
            </div>
          ))}
        </div>
      )}

      {show('total', activeElements) && (
        <div className="border-t-2 border-gray-800 pt-2 mb-4">
          <div className="flex justify-between items-center">
            <span className="font-bold">المجموع</span>
            <span className="font-bold font-mono">{formatCurrency(receipt.total_amount)}</span>
          </div>
          <p className="text-xs text-gray-500 mt-1">{amountToArabicWords(parseFloat(receipt.total_amount))}</p>
        </div>
      )}

      {settings.show_qr && show('qr', activeElements) && qrSvg && (
        <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
      )}

      {show('qr', activeElements) && code && <div className="text-center text-xs text-gray-400">رمز: {code}</div>}

      {show('footer', activeElements) && (
        <div className="text-center text-xs text-gray-500 mt-4 pt-3 border-t">
          <p>{settings.footer_text}</p>
          {settings.show_signature && show('signature', activeElements) && <p className="mt-2">أمين الصندوق: {receipt.created_by?.name}</p>}
        </div>
      )}
    </div>
  );
}
