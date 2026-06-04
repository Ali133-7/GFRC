import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateCompact({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  return (
    <div className="mx-auto max-w-xs bg-white p-3 text-xs" dir="rtl" style={{ width: '80mm' }}>
      {show('header', activeElements) && (
        <div className="text-center border-b border-dashed border-gray-400 pb-2 mb-2">
          {logo && <img src={logo} alt="logo" className="h-24 mx-auto mb-1 object-contain" />}
          <h1 className="font-bold text-sm">{settings.company_name}</h1>
          <p className="text-gray-500 scale-90 origin-center">{settings.company_phone}</p>
        </div>
      )}

      {(show('title', activeElements) || show('number', activeElements) || show('meta', activeElements)) && (
        <div className="text-center mb-2">
          {show('title', activeElements) && <p className="font-bold">{settings.receipt_title}</p>}
          {show('number', activeElements) && <p className="font-mono text-sm font-bold">{receipt.receipt_number}</p>}
          {show('meta', activeElements) && <p className="text-gray-500">{formatDateTime(receipt.created_at)}</p>}
        </div>
      )}

      {show('table', activeElements) && (
        <div className="border-t border-b border-dashed border-gray-400 py-2 mb-2">
          {(receipt.items || []).map((item) => (
            <div key={item.id} className="flex justify-between py-0.5">
              <span>{item.label_ar_snapshot}</span>
              <span className="font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</span>
            </div>
          ))}
        </div>
      )}

      {show('total', activeElements) && (
        <div className="text-center mb-2">
          <p className="font-bold text-sm">{formatCurrency(receipt.total_amount)}</p>
          <p className="text-gray-600 scale-90">{amountToArabicWords(parseFloat(receipt.total_amount))}</p>
        </div>
      )}

      {settings.show_qr && show('qr', activeElements) && qrSvg && (
        <img src={qrSvg} alt="QR" className="w-32 h-32 object-contain" />
      )}

      {show('qr', activeElements) && code && <div className="text-center text-gray-400 mb-1">رمز: {code}</div>}

      {show('footer', activeElements) && (
        <div className="text-center text-gray-500 border-t border-dashed border-gray-400 pt-2">
          <p>{settings.footer_text}</p>
          <p>{settings.thank_you_text}</p>
        </div>
      )}
    </div>
  );
}
