import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateArabic({ receipt, settings, logo, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-3xl bg-white p-8 font-arabic" dir="rtl">
      {show('header', activeElements) && (
        <div className="text-center border-b-2 border-green-700 pb-4 mb-6">
          {logo && <img src={logo} alt="الشعار" className="h-24 mx-auto mb-2 object-contain" />}
          <h1 className="text-2xl font-bold text-green-800">{settings.company_name}</h1>
          <p className="text-green-700">{settings.company_address}</p>
          <p className="text-green-700">{settings.company_phone}</p>
        </div>
      )}

      {show('title', activeElements) && (
        <div className="text-center mb-6">
          <h2 className="text-xl font-bold text-green-900">{settings.receipt_title}</h2>
        </div>
      )}

      {show('number', activeElements) && (
        <div className="text-center mb-6">
          <p className="text-sm text-green-600">رقم الوصل</p>
          <p className="text-3xl font-bold font-mono text-green-900">{receipt.receipt_number}</p>
        </div>
      )}

      {show('meta', activeElements) && (
        <div className="grid grid-cols-2 gap-4 text-sm mb-6 bg-green-50 p-4 rounded">
          <div><span className="text-green-700">التاريخ:</span> {formatDateTime(receipt.created_at)}</div>
          <div><span className="text-green-700">السجل:</span> {receipt.register?.name_ar}</div>
          <div><span className="text-green-700">أمين الصندوق:</span> {receipt.created_by?.name}</div>
          <div><span className="text-green-700">الحالة:</span> {receipt.status}</div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse border border-green-200 text-sm mb-6">
          <thead>
            <tr className="bg-green-100">
              <th className="border border-green-200 px-4 py-2 text-right">م</th>
              <th className="border border-green-200 px-4 py-2 text-right">البيان</th>
              <th className="border border-green-200 px-4 py-2 text-left">المبلغ</th>
            </tr>
          </thead>
          <tbody>
            {(receipt.items || []).map((item, idx) => (
              <tr key={item.id}>
                <td className="border border-green-200 px-4 py-2">{idx + 1}</td>
                <td className="border border-green-200 px-4 py-2">{item.label_ar_snapshot}</td>
                <td className="border border-green-200 px-4 py-2 text-left font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {show('total', activeElements) && (
        <>
          <div className="text-right mb-2">
            <p className="text-xl font-bold text-green-800">الإجمالي: {formatCurrency(receipt.total_amount)}</p>
            <p className="text-green-700">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
          </div>
          {code && <div className="text-right text-xs text-green-600 mb-6">رمز التحقق: <span className="font-mono">{code}</span></div>}
        </>
      )}

      {show('footer', activeElements) && <div className="text-center text-green-700 mb-8">{settings.footer_text}</div>}

      {(show('signature', activeElements) || show('stamp', activeElements)) && (
        <div className="flex justify-between items-end mt-12">
          {settings.show_signature && show('signature', activeElements) && (
            <div className="w-48 text-center">
              <div className="border-t border-green-700 pt-2">
                <p className="text-sm font-bold text-green-800">التوقيع</p>
                <p className="text-xs text-green-600">{receipt.created_by?.name}</p>
              </div>
            </div>
          )}
          {settings.show_stamp && show('stamp', activeElements) && (
            <div className="w-32 h-32 border-2 border-dashed border-green-600 rounded-full flex items-center justify-center">
              <span className="text-xs text-green-600 text-center">الختم الرسمي</span>
            </div>
          )}
        </div>
      )}

      {show('footer', activeElements) && <div className="text-center text-green-700 mt-6">{settings.thank_you_text}</div>}
    </div>
  );
}
