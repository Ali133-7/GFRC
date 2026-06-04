import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateWide({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-4xl bg-white p-10" dir="rtl">
      {show('header', activeElements) && (
        <div className="flex items-center justify-between border-b pb-4 mb-6">
          <div className="flex items-center gap-4">
            {logo && <img src={logo} alt="logo" className="h-24 object-contain" />}
            <div>
              <h1 className="text-2xl font-bold">{settings.company_name}</h1>
              <p className="text-sm text-gray-500">{settings.company_name_en}</p>
              <p className="text-sm text-gray-500">{settings.company_address}</p>
              <p className="text-sm text-gray-500">{settings.company_phone}</p>
            </div>
          </div>
          <div className="text-left">
            {settings.show_qr && show('qr', activeElements) && qrSvg && (
              <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
            )}
          </div>
        </div>
      )}

      {(show('title', activeElements) || show('number', activeElements)) && (
        <div className="flex justify-between items-center mb-6">
          {show('title', activeElements) && <h2 className="text-xl font-bold">{settings.receipt_title}</h2>}
          {show('number', activeElements) && (
            <div className="text-left">
              <p className="text-sm text-gray-500">رقم الوصل</p>
              <p className="text-2xl font-bold font-mono">{receipt.receipt_number}</p>
            </div>
          )}
        </div>
      )}

      {show('meta', activeElements) && (
        <div className="grid grid-cols-4 gap-4 text-sm mb-6 bg-gray-50 p-3 rounded">
          <div><span className="text-gray-500">التاريخ:</span> {formatDateTime(receipt.created_at)}</div>
          <div><span className="text-gray-500">السجل:</span> {receipt.register?.name_ar}</div>
          <div><span className="text-gray-500">أمين الصندوق:</span> {receipt.created_by?.name}</div>
          <div><span className="text-gray-500">الحالة:</span> {receipt.status}</div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse border text-sm mb-6">
          <thead>
            <tr className="bg-gray-100">
              <th className="border px-4 py-2 text-right w-16">#</th>
              <th className="border px-4 py-2 text-right">البيان</th>
              <th className="border px-4 py-2 text-right">الحقل</th>
              <th className="border px-4 py-2 text-left">القيمة</th>
            </tr>
          </thead>
          <tbody>
            {(receipt.items || []).map((item, idx) => (
              <tr key={item.id}>
                <td className="border px-4 py-2">{idx + 1}</td>
                <td className="border px-4 py-2">{item.label_ar_snapshot}</td>
                <td className="border px-4 py-2 text-gray-500">{item.field_name_snapshot}</td>
                <td className="border px-4 py-2 text-left font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {show('total', activeElements) && (
        <div className="flex justify-end mb-6">
          <div className="text-right bg-gray-100 p-4 rounded min-w-[300px]">
            <p className="text-xl font-bold">المجموع: {formatCurrency(receipt.total_amount)}</p>
            <p className="text-gray-700">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
          </div>
        </div>
      )}

      {show('qr', activeElements) && code && <div className="text-right text-xs text-gray-500 mb-6">رمز التحقق: <span className="font-mono">{code}</span></div>}

      {show('footer', activeElements) && (
        <div className="flex justify-between items-end mt-12 pt-6 border-t">
          <div className="text-sm text-gray-500">
            <p>{settings.footer_text}</p>
            <p className="mt-2">{settings.thank_you_text}</p>
          </div>
          <div className="flex gap-8">
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
                <span className="text-xs text-gray-400 text-center">ختم النظام</span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
