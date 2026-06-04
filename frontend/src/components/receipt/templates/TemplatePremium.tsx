import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplatePremium({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-3xl bg-white p-10" dir="rtl">
      {show('header', activeElements) && (
        <div className="border-4 border-double border-yellow-600 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="text-right flex-1">
              {logo && <img src={logo} alt="logo" className="h-24 mb-3 object-contain" />}
              <h1 className="text-3xl font-bold text-yellow-800">{settings.company_name}</h1>
              <p className="text-yellow-700">{settings.company_address}</p>
              <p className="text-yellow-700">{settings.company_phone}</p>
            </div>
            {settings.show_qr && show('qr', activeElements) && qrSvg && (
              <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
            )}
          </div>
        </div>
      )}

      {show('title', activeElements) && (
        <div className="text-center mb-6">
          <div className="inline-block border-b-2 border-yellow-600 pb-1">
            <h2 className="text-2xl font-bold text-yellow-800">{settings.receipt_title}</h2>
          </div>
        </div>
      )}

      {show('number', activeElements) && (
        <div className="text-center mb-6">
          <span className="text-4xl font-bold font-mono text-yellow-900">{receipt.receipt_number}</span>
        </div>
      )}

      {show('meta', activeElements) && (
        <div className="border border-yellow-300 rounded-lg p-4 mb-6 bg-yellow-50">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><span className="text-yellow-700">التاريخ:</span> {formatDateTime(receipt.created_at)}</div>
            <div><span className="text-yellow-700">السجل:</span> {receipt.register?.name_ar}</div>
            <div><span className="text-yellow-700">أمين الصندوق:</span> {receipt.created_by?.name}</div>
          </div>
        </div>
      )}

      {show('table', activeElements) && (
        <table className="w-full border-collapse text-sm mb-6">
          <thead>
            <tr className="border-b-2 border-yellow-600">
              <th className="px-4 py-2 text-right">البيان</th>
              <th className="px-4 py-2 text-left">المبلغ</th>
            </tr>
          </thead>
          <tbody>
            {(receipt.items || []).map((item) => (
              <tr key={item.id} className="border-b border-yellow-200">
                <td className="px-4 py-2">{item.label_ar_snapshot}</td>
                <td className="px-4 py-2 text-left font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {show('total', activeElements) && (
        <>
          <div className="text-right mb-2">
            <p className="text-2xl font-bold text-yellow-800">{formatCurrency(receipt.total_amount)}</p>
            <p className="text-yellow-700">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
          </div>
          {code && <div className="text-right text-xs text-yellow-600 mb-6">رمز التحقق: <span className="font-mono">{code}</span></div>}
        </>
      )}

      {show('footer', activeElements) && <div className="text-center text-yellow-700 mb-8">{settings.footer_text}</div>}

      {(show('signature', activeElements) || show('stamp', activeElements)) && (
        <div className="flex justify-between items-end mt-12">
          {settings.show_signature && show('signature', activeElements) && (
            <div className="w-48 text-center">
              <div className="border-t-2 border-yellow-600 pt-2">
                <p className="text-sm font-bold text-yellow-800">أمين الصندوق</p>
                <p className="text-xs text-yellow-600">{receipt.created_by?.name}</p>
              </div>
            </div>
          )}
          {settings.show_stamp && show('stamp', activeElements) && (
            <div className="w-32 h-32 border-4 border-double border-yellow-600 rounded-full flex items-center justify-center">
              <span className="text-xs text-yellow-600 text-center">ختم النظام<br />GFRC</span>
            </div>
          )}
        </div>
      )}

      {show('footer', activeElements) && <div className="text-center text-yellow-700 mt-6">{settings.thank_you_text}</div>}
    </div>
  );
}
