import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateNoQr({ receipt, settings, logo, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-3xl bg-white p-8" dir="rtl">
      {show('header', activeElements) && (
        <div className="flex items-center justify-between mb-6">
          <div className="text-right flex-1">
            {logo && <img src={logo} alt="logo" className="h-24 mb-2 object-contain" />}
            <h1 className="text-2xl font-bold">{settings.company_name}</h1>
            <p className="text-sm text-gray-500">{settings.company_address}</p>
            <p className="text-sm text-gray-500">{settings.company_phone}</p>
          </div>
          {settings.show_stamp && show('stamp', activeElements) && (
            <div className="shrink-0 mr-4 w-28 h-28 border-4 border-red-700 rounded-full flex items-center justify-center bg-red-50">
              <span className="text-red-700 font-bold text-center text-sm">ختم رقمي<br />معتمد<br />GFRC</span>
            </div>
          )}
        </div>
      )}

      {(show('title', activeElements) || show('number', activeElements)) && (
        <div className="text-center mb-6">
          {show('title', activeElements) && <h2 className="text-xl font-bold">{settings.receipt_title}</h2>}
          {show('number', activeElements) && <p className="text-4xl font-bold font-mono mt-2">{receipt.receipt_number}</p>}
        </div>
      )}

      {show('meta', activeElements) && (
        <div className="grid grid-cols-2 gap-4 text-sm mb-6">
          <div><span className="text-gray-500">التاريخ:</span> {formatDateTime(receipt.created_at)}</div>
          <div><span className="text-gray-500">السجل:</span> {receipt.register?.name_ar}</div>
          <div><span className="text-gray-500">أمين الصندوق:</span> {receipt.created_by?.name}</div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse border text-sm mb-6">
          <thead>
            <tr className="bg-gray-100">
              <th className="border px-4 py-2 text-right">#</th>
              <th className="border px-4 py-2 text-right">البيان</th>
              <th className="border px-4 py-2 text-left">المبلغ</th>
            </tr>
          </thead>
          <tbody>
            {(receipt.items || []).map((item, idx) => (
              <tr key={item.id}>
                <td className="border px-4 py-2">{idx + 1}</td>
                <td className="border px-4 py-2">{item.label_ar_snapshot}</td>
                <td className="border px-4 py-2 text-left font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {show('total', activeElements) && (
        <>
          <div className="text-right mb-2">
            <p className="text-xl font-bold">المجموع: {formatCurrency(receipt.total_amount)}</p>
            <p className="text-gray-700">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
          </div>
          {code && <div className="text-right text-xs text-gray-500 mb-6">رمز التحقق: <span className="font-mono">{code}</span></div>}
        </>
      )}

      {show('footer', activeElements) && <div className="text-center text-sm text-gray-500 mb-8">{settings.footer_text}</div>}

      {(show('signature', activeElements) || show('stamp', activeElements)) && (
        <div className="flex justify-between items-end mt-12">
          {settings.show_signature && show('signature', activeElements) && (
            <div className="w-48 text-center">
              <div className="border-t border-gray-400 pt-2">
                <p className="text-sm font-bold">أمين الصندوق</p>
                <p className="text-xs text-gray-500">{receipt.created_by?.name}</p>
              </div>
            </div>
          )}
          {settings.show_stamp && show('stamp', activeElements) && (
            <div className="w-32 h-32 border-2 border-dashed border-gray-400 flex items-center justify-center">
              <span className="text-xs text-gray-400 text-center">ختم النظام<br />GFRC</span>
            </div>
          )}
        </div>
      )}

      {show('footer', activeElements) && <div className="text-center text-sm text-gray-400 mt-6">{settings.thank_you_text}</div>}
    </div>
  );
}
