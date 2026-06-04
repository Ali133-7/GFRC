import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateBilingual({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-4xl bg-white p-8" dir="rtl">
      {show('header', activeElements) && (
        <div className="flex items-center justify-between border-b-2 pb-4 mb-6">
          <div className="text-right flex-1">
            {logo && <img src={logo} alt="logo" className="h-24 mb-2 object-contain" />}
            <h1 className="text-xl font-bold">{settings.company_name}</h1>
            <p className="text-sm text-gray-500">{settings.company_name_en}</p>
          </div>
          <div className="text-left">
            {settings.show_qr && show('qr', activeElements) && qrSvg && (
              <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
            )}
          </div>
        </div>
      )}

      {show('title', activeElements) && (
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-lg font-bold">{settings.receipt_title}</h2>
          <h2 className="text-lg font-bold text-gray-500">RECEIPT</h2>
        </div>
      )}

      {show('number', activeElements) && (
        <div className="text-center mb-6">
          <p className="text-3xl font-bold font-mono">{receipt.receipt_number}</p>
        </div>
      )}

      {show('meta', activeElements) && (
        <div className="grid grid-cols-2 gap-6 text-sm mb-6">
          <div className="text-right">
            <p><span className="text-gray-500">التاريخ / Date:</span> {formatDateTime(receipt.created_at)}</p>
            <p><span className="text-gray-500">السجل / Register:</span> {receipt.register?.name_ar}</p>
          </div>
          <div className="text-right">
            <p><span className="text-gray-500">أمين الصندوق / Cashier:</span> {receipt.created_by?.name}</p>
            <p><span className="text-gray-500">الحالة / Status:</span> {receipt.status}</p>
          </div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse border text-sm mb-6">
          <thead>
            <tr className="bg-gray-100">
              <th className="border px-4 py-2 text-right">البيان / Description</th>
              <th className="border px-4 py-2 text-left">المبلغ / Amount</th>
            </tr>
          </thead>
          <tbody>
            {(receipt.items || []).map((item) => (
              <tr key={item.id}>
                <td className="border px-4 py-2">{item.label_ar_snapshot}</td>
                <td className="border px-4 py-2 text-left font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {show('total', activeElements) && (
        <div className="flex justify-between items-center bg-gray-100 p-4 rounded mb-6">
          <div>
            <p className="font-bold">المجموع / Total</p>
            <p className="text-sm text-gray-600">{amountToArabicWords(parseFloat(receipt.total_amount))} IQD</p>
          </div>
          <p className="text-2xl font-bold font-mono">{formatCurrency(receipt.total_amount)}</p>
        </div>
      )}

      {show('qr', activeElements) && code && <div className="text-right text-xs text-gray-500 mb-6">Verification Code / رمز التحقق: <span className="font-mono">{code}</span></div>}

      {show('footer', activeElements) && <div className="text-center text-sm text-gray-500 mb-6">{settings.footer_text}</div>}

      {(show('signature', activeElements) || show('stamp', activeElements)) && (
        <div className="flex justify-between items-end mt-8">
          {settings.show_signature && show('signature', activeElements) && (
            <div className="w-48 text-center">
              <div className="border-t border-gray-400 pt-2">
                <p className="text-sm font-bold">التوقيع / Signature</p>
                <p className="text-xs text-gray-500">{receipt.created_by?.name}</p>
              </div>
            </div>
          )}
          {settings.show_stamp && show('stamp', activeElements) && (
            <div className="w-32 h-32 border-2 border-dashed border-gray-400 flex items-center justify-center">
              <span className="text-xs text-gray-400 text-center">Official Stamp<br />الختم الرسمي</span>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
