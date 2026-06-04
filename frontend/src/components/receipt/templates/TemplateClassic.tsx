import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateClassic({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-3xl bg-white p-8" dir="rtl">
      {show('header', activeElements) && (
        <div className="flex items-start justify-between border-b-2 border-gray-800 pb-4 mb-6">
          <div className="text-right flex-1">
            {logo && <img src={logo} alt="logo" className="h-24 mb-2 object-contain" style={{ float: 'right' }} />}
            <h1 className="text-2xl font-bold">{settings.company_name}</h1>
            <p className="text-sm text-gray-600">{settings.company_address}</p>
            <p className="text-sm text-gray-600">{settings.company_phone}</p>
          </div>
          {settings.show_qr && show('qr', activeElements) && qrSvg && (
            <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
          )}
        </div>
      )}

      {show('title', activeElements) && (
        <div className="text-center mb-6">
          <h2 className="text-xl font-bold border inline-block px-6 py-1">{settings.receipt_title}</h2>
        </div>
      )}

      {show('number', activeElements) && (
        <div className="text-center mb-6 text-3xl font-bold tracking-widest font-mono">{receipt.receipt_number}</div>
      )}

      {show('meta', activeElements) && (
        <div className="grid grid-cols-2 gap-4 text-sm mb-6">
          <div><span className="text-gray-500">التاريخ:</span> {formatDateTime(receipt.created_at)}</div>
          <div><span className="text-gray-500">السجل:</span> {receipt.register?.name_ar}</div>
          <div><span className="text-gray-500">أمين الصندوق:</span> {receipt.created_by?.name}</div>
          <div><span className="text-gray-500">الحالة:</span> {receipt.status}</div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse border border-gray-800 text-sm mb-6">
          <thead>
            <tr className="bg-gray-100">
              <th className="border border-gray-800 px-4 py-2 text-right">#</th>
              <th className="border border-gray-800 px-4 py-2 text-right">البيان</th>
              <th className="border border-gray-800 px-4 py-2 text-left">القيمة</th>
            </tr>
          </thead>
          <tbody>
            {(receipt.items || []).map((item, idx) => (
              <tr key={item.id}>
                <td className="border border-gray-800 px-4 py-2">{idx + 1}</td>
                <td className="border border-gray-800 px-4 py-2">{item.label_ar_snapshot}</td>
                <td className="border border-gray-800 px-4 py-2 text-left font-mono">
                  {item.amount != null ? formatCurrency(item.amount) : item.text_value}
                </td>
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

      {show('footer', activeElements) && (
        <div className="text-center text-sm text-gray-500 mb-8">{settings.footer_text}</div>
      )}

      {(show('signature', activeElements) || show('stamp', activeElements)) && (
        <div className="flex justify-between items-end mt-12">
          {settings.show_signature && show('signature', activeElements) && (
            <div className="w-48 text-center">
              <div className="border-t border-gray-800 pt-2">
                <p className="text-sm font-bold">أمين الصندوق</p>
                <p className="text-xs text-gray-500">{receipt.created_by?.name}</p>
              </div>
            </div>
          )}
          {settings.show_stamp && show('stamp', activeElements) && (
            <div className="w-32 h-32 border-2 border-dashed border-gray-400 rounded-full flex items-center justify-center">
              <span className="text-xs text-gray-400 text-center">ختم النظام<br />GFRC</span>
            </div>
          )}
        </div>
      )}

      {show('footer', activeElements) && (
        <div className="text-center text-sm text-gray-400 mt-6">{settings.thank_you_text}</div>
      )}
    </div>
  );
}
