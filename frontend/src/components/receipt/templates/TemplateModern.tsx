import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateModern({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-2xl bg-white p-10" dir="rtl">
      {show('header', activeElements) && <div className="h-2 bg-blue-700 rounded-full mb-8" />}

      {show('header', activeElements) && (
        <div className="flex items-center justify-between mb-8">
          <div className="text-right">
            {logo && <img src={logo} alt="logo" className="h-24 mb-3 object-contain" />}
            <h1 className="text-xl font-bold text-blue-900">{settings.company_name}</h1>
            <p className="text-xs text-gray-500">{settings.company_address}</p>
          </div>
          <div className="text-left">
            {settings.show_qr && show('qr', activeElements) && qrSvg && (
              <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
            )}
          </div>
        </div>
      )}

      {(show('title', activeElements) || show('number', activeElements)) && (
        <div className="bg-blue-50 rounded-lg p-4 mb-6 flex justify-between items-center">
          <div>
            {show('title', activeElements) && <p className="text-xs text-blue-600 uppercase tracking-wider">{settings.receipt_title}</p>}
            {show('number', activeElements) && <p className="text-2xl font-bold text-blue-900 font-mono">{receipt.receipt_number}</p>}
          </div>
          {show('meta', activeElements) && (
            <div className="text-left text-sm text-gray-600">
              <p>{formatDateTime(receipt.created_at)}</p>
              <p>{receipt.register?.name_ar}</p>
            </div>
          )}
        </div>
      )}

      {show('table', activeElements) && (
        <div className="space-y-3 mb-6">
          {(receipt.items || []).map((item) => (
            <div key={item.id} className="flex justify-between items-center border-b border-gray-100 pb-2">
              <span className="text-gray-700">{item.label_ar_snapshot}</span>
              <span className="font-mono font-medium">
                {item.amount != null ? formatCurrency(item.amount) : item.text_value}
              </span>
            </div>
          ))}
        </div>
      )}

      {show('total', activeElements) && (
        <div className="bg-gray-900 text-white rounded-lg p-4 mb-6">
          <div className="flex justify-between items-center">
            <span className="text-gray-300">المجموع الكلي</span>
            <span className="text-2xl font-bold font-mono">{formatCurrency(receipt.total_amount)}</span>
          </div>
          <p className="text-sm text-gray-400 mt-1">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
        </div>
      )}

      {show('qr', activeElements) && code && <div className="text-right text-xs text-gray-400 mb-4">رمز التحقق: <span className="font-mono">{code}</span></div>}

      {(show('footer', activeElements) || show('signature', activeElements)) && (
        <div className="flex justify-between items-end mt-8 pt-6 border-t">
          {show('footer', activeElements) && (
            <div className="text-sm text-gray-500">
              <p>{settings.footer_text}</p>
              <p className="mt-1 text-xs">{settings.thank_you_text}</p>
            </div>
          )}
          {settings.show_signature && show('signature', activeElements) && (
            <div className="text-center">
              <p className="text-sm font-medium">{receipt.created_by?.name}</p>
              <p className="text-xs text-gray-400">أمين الصندوق</p>
            </div>
          )}
        </div>
      )}

      {settings.show_stamp && show('stamp', activeElements) && (
        <div className="mt-4 flex justify-center">
          <div className="w-24 h-24 border-2 border-dashed border-gray-300 rounded-full flex items-center justify-center">
            <span className="text-xs text-gray-300">GFRC</span>
          </div>
        </div>
      )}
    </div>
  );
}
