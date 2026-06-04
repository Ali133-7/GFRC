import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateLargeQr({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-3xl bg-white p-8" dir="rtl">
      {show('header', activeElements) && (
        <div className="text-center mb-4">
          {logo && <img src={logo} alt="logo" className="h-24 mx-auto mb-2 object-contain" />}
          <h1 className="text-xl font-bold">{settings.company_name}</h1>
          <p className="text-sm text-gray-500">{settings.company_address}</p>
        </div>
      )}

      {show('qr', activeElements) && (
        <div className="flex justify-center my-6">
          {qrSvg ? (
            <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
          ) : (
            <div className="w-40 h-40 bg-gray-100 flex items-center justify-center text-gray-400">QR</div>
          )}
        </div>
      )}

      {show('qr', activeElements) && code && <div className="text-center text-sm font-mono text-gray-600 mb-4">رمز التحقق: {code}</div>}

      {(show('title', activeElements) || show('number', activeElements)) && (
        <div className="text-center mb-4">
          {show('title', activeElements) && <p className="text-sm text-gray-500">{settings.receipt_title}</p>}
          {show('number', activeElements) && <p className="text-2xl font-bold font-mono">{receipt.receipt_number}</p>}
        </div>
      )}

      {show('meta', activeElements) && (
        <div className="grid grid-cols-2 gap-4 text-sm mb-4 text-center">
          <div>{formatDateTime(receipt.created_at)}</div>
          <div>{receipt.register?.name_ar}</div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse border text-sm mb-4">
          <thead>
            <tr className="bg-gray-100">
              <th className="border px-4 py-2 text-right">البيان</th>
              <th className="border px-4 py-2 text-left">المبلغ</th>
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
        <div className="text-center mb-6">
          <p className="text-xl font-bold">{formatCurrency(receipt.total_amount)}</p>
          <p className="text-gray-700">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
        </div>
      )}

      {show('footer', activeElements) && (
        <>
          <div className="text-center text-sm text-gray-500">{settings.footer_text}</div>
          <div className="text-center text-xs text-gray-400 mt-2">{settings.thank_you_text}</div>
        </>
      )}
    </div>
  );
}
