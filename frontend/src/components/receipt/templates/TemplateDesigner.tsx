import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

function show(id: string, active?: Record<string, boolean>) {
  return active?.[id] !== false;
}

export default function TemplateDesigner({ receipt, settings, logo, qrSvg, activeElements }: TemplateProps) {
  const hash = receipt.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const code = hash ? hash.substring(0, 8) : '';

  // Read CSS variables from parent container
  const containerStyle: React.CSSProperties = {
    '--rd-bg': settings['designer_bg'] || '#ffffff',
    '--rd-text': settings['designer_text'] || '#1f2937',
    '--rd-border': settings['designer_border'] || '#374151',
    '--rd-header-bg': settings['designer_header_bg'] || '#f9fafb',
    '--rd-accent': settings['designer_accent'] || '#2563eb',
    '--rd-font': settings['designer_font'] || 'system-ui, -apple-system, sans-serif',
    '--rd-font-size': settings['designer_font_size'] || '14px',
    '--rd-padding': settings['designer_padding'] || '32px',
    '--rd-radius': settings['designer_radius'] || '0px',
  } as React.CSSProperties;

  return (
    <div
      className="mx-auto max-w-3xl p-[var(--rd-padding)] transition-all duration-200"
      dir="rtl"
      style={{
        ...containerStyle,
        backgroundColor: 'var(--rd-bg)',
        color: 'var(--rd-text)',
        fontFamily: 'var(--rd-font)',
        fontSize: 'var(--rd-font-size)',
        borderRadius: 'var(--rd-radius)',
        minHeight: '400px',
      }}
    >
      {/* Header */}
      {show('header', activeElements) && (
        <div
          className="flex items-start justify-between pb-4 mb-6"
          style={{
            borderBottom: '2px solid var(--rd-border)',
            backgroundColor: 'var(--rd-header-bg)',
            borderRadius: 'var(--rd-radius)',
            padding: '16px',
          }}
        >
          <div className="text-right flex-1">
            {logo && (
              <img
                src={logo}
                alt="logo"
                className="h-20 mb-2 object-contain"
                style={{ float: 'right', marginLeft: '12px' }}
              />
            )}
            <h1 className="text-2xl font-bold" style={{ color: 'var(--rd-accent)' }}>
              {settings.company_name}
            </h1>
            <p className="text-sm opacity-70">{settings.company_address}</p>
            <p className="text-sm opacity-70">{settings.company_phone}</p>
          </div>
          {settings.show_qr && show('qr', activeElements) && qrSvg && (
            <img src={qrSvg} alt="QR" className="w-28 h-28 object-contain" />
          )}
        </div>
      )}

      {/* Title */}
      {show('title', activeElements) && (
        <div className="text-center mb-6">
          <h2
            className="text-xl font-bold inline-block px-6 py-1"
            style={{
              border: '2px solid var(--rd-accent)',
              color: 'var(--rd-accent)',
              borderRadius: 'var(--rd-radius)',
            }}
          >
            {settings.receipt_title}
          </h2>
        </div>
      )}

      {/* Receipt Number */}
      {show('number', activeElements) && (
        <div
          className="text-center mb-6 text-3xl font-bold tracking-widest font-mono"
          style={{ color: 'var(--rd-accent)' }}
        >
          {receipt.receipt_number}
        </div>
      )}

      {/* Meta Info */}
      {show('meta', activeElements) && (
        <div className="grid grid-cols-2 gap-4 text-sm mb-6 p-4" style={{ backgroundColor: 'var(--rd-header-bg)', borderRadius: 'var(--rd-radius)' }}>
          <div>
            <span className="opacity-60">التاريخ:</span> {formatDateTime(receipt.created_at)}
          </div>
          <div>
            <span className="opacity-60">السجل:</span> {receipt.register?.name_ar}
          </div>
          <div>
            <span className="opacity-60">أمين الصندوق:</span> {receipt.created_by?.name}
          </div>
          <div>
            <span className="opacity-60">الحالة:</span> {receipt.status}
          </div>
        </div>
      )}

      {/* Items Table */}
      {show('table', activeElements) && (
        <div className="mb-6 overflow-hidden" style={{ border: '1px solid var(--rd-border)', borderRadius: 'var(--rd-radius)' }}>
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr style={{ backgroundColor: 'var(--rd-header-bg)', borderBottom: '2px solid var(--rd-border)' }}>
                <th className="px-4 py-3 text-right font-bold">#</th>
                <th className="px-4 py-3 text-right font-bold">البيان</th>
                <th className="px-4 py-3 text-left font-bold">القيمة</th>
              </tr>
            </thead>
            <tbody>
              {(receipt.items || []).map((item, idx) => (
                <tr key={item.id} style={{ borderBottom: '1px solid var(--rd-border)' }}>
                  <td className="px-4 py-3">{idx + 1}</td>
                  <td className="px-4 py-3">{item.label_ar_snapshot}</td>
                  <td className="px-4 py-3 text-left font-mono">
                    {item.amount != null ? formatCurrency(item.amount) : item.text_value}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Total */}
      {show('total', activeElements) && (
        <div className="text-right mb-6 p-4" style={{ backgroundColor: 'var(--rd-header-bg)', borderRadius: 'var(--rd-radius)' }}>
          <p className="text-xl font-bold" style={{ color: 'var(--rd-accent)' }}>
            المجموع: {formatCurrency(receipt.total_amount)}
          </p>
          <p className="opacity-80 mt-1">
            {amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي
          </p>
          {code && (
            <div className="text-right text-xs opacity-60 mt-2">
              رمز التحقق: <span className="font-mono">{code}</span>
            </div>
          )}
        </div>
      )}

      {/* Footer */}
      {show('footer', activeElements) && (
        <div className="text-center text-sm opacity-70 mb-4">{settings.footer_text}</div>
      )}

      {/* Signature & Stamp */}
      {(show('signature', activeElements) || show('stamp', activeElements)) && (
        <div className="flex justify-between items-end mt-8 pt-6" style={{ borderTop: '1px solid var(--rd-border)' }}>
          {settings.show_signature && show('signature', activeElements) && (
            <div className="w-48 text-center">
              <div className="border-t-2 pt-2" style={{ borderColor: 'var(--rd-border)' }}>
                <p className="text-sm font-bold">أمين الصندوق</p>
                <p className="text-xs opacity-60">{receipt.created_by?.name}</p>
              </div>
            </div>
          )}
          {settings.show_stamp && show('stamp', activeElements) && (
            <div
              className="w-28 h-28 border-2 border-dashed rounded-full flex items-center justify-center"
              style={{ borderColor: 'var(--rd-border)', opacity: 0.5 }}
            >
              <span className="text-xs text-center">
                ختم النظام
                <br />
                GFRC
              </span>
            </div>
          )}
        </div>
      )}

      {/* Thank You */}
      {show('footer', activeElements) && (
        <div className="text-center text-xs opacity-50 mt-6">{settings.thank_you_text}</div>
      )}
    </div>
  );
}
