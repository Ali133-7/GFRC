import { useEffect, useState } from 'react';
import { useReceipts } from '@/hooks/useReceipts';
import { ReceiptTemplateRenderer } from '@/components/receipt/ReceiptTemplateRenderer';
import { generateReceiptQr } from '@/utils/generateQr';
import type { ReceiptTemplate } from '@/types/template';

interface Props {
  template: ReceiptTemplate | null;
  open: boolean;
  onClose: () => void;
  onConfirmSave: () => void;
}

export default function PreviewBeforeSaveModal({ template, open, onClose, onConfirmSave }: Props) {
  const { data: receiptsList } = useReceipts({ per_page: 10 });
  const [selectedReceiptId, setSelectedReceiptId] = useState('');
  const [qrSvg, setQrSvg] = useState('');
  const [activeTab, setActiveTab] = useState<'receipt' | 'empty'>('receipt');

  const receipt = receiptsList?.find((r: any) => r.id === selectedReceiptId) || receiptsList?.[0];

  useEffect(() => {
    if (receipt?.qr_payload) {
      try {
        const payload = JSON.parse(receipt.qr_payload);
        if (payload.hash && receipt.id) {
          generateReceiptQr(receipt.id, payload.hash).then(setQrSvg);
        }
      } catch { /* ignore */ }
    }
  }, [receipt]);

  if (!open || !template) return null;

  const demoSettings = {
    company_name: 'جهة تجريبية',
    company_name_en: 'Demo Entity',
    company_address: 'بغداد - العراق',
    company_phone: '0770 123 4567',
    footer_text: 'هذا وصل رسمي صادر عن النظام',
    thank_you_text: 'شكراً لثقتكم بنا',
    receipt_title: 'وصل قبض',
    show_qr: true,
    show_stamp: true,
    show_signature: true,
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b">
          <div>
            <h2 className="text-lg font-bold text-gray-800">معاينة التصميم قبل الحفظ</h2>
            <p className="text-sm text-gray-500">تأكد من مظهر الوصل قبل تثبيت التصميم</p>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>

        {/* Controls */}
        <div className="px-6 py-3 border-b bg-gray-50 flex items-center gap-4 flex-wrap">
          <div className="flex gap-1 bg-white rounded-lg border p-1">
            <button
              onClick={() => setActiveTab('receipt')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${activeTab === 'receipt' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}
            >
              📋 مع وصل حقيقي
            </button>
            <button
              onClick={() => setActiveTab('empty')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${activeTab === 'empty' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}
            >
              📄 إطار فارغ
            </button>
          </div>

          {activeTab === 'receipt' && (
            <select
              value={selectedReceiptId}
              onChange={(e) => setSelectedReceiptId(e.target.value)}
              className="text-sm border rounded-lg px-3 py-1.5 bg-white"
            >
              <option value="">{receipt ? `${receipt.receipt_number} — ${receipt.register?.name_ar}` : 'اختر وصلاً...'}</option>
              {(receiptsList || []).map((r: any) => (
                <option key={r.id} value={r.id}>{r.receipt_number} — {r.register?.name_ar}</option>
              ))}
            </select>
          )}

          <div className="text-xs text-gray-500 mr-auto">
            {template.elements.length} عنصر | {template.page_width}×{template.page_height}mm
          </div>
        </div>

        {/* Preview */}
        <div className="flex-1 overflow-auto p-6 bg-gray-100 flex justify-center">
          <div
            className="relative bg-white shadow-lg border border-gray-300 rounded"
            style={{
              width: `${template.page_width}mm`,
              height: `${template.page_height}mm`,
              backgroundColor: template.background_color || '#ffffff',
            }}
          >
            {activeTab === 'empty' ? (
              <div className="absolute inset-0 flex items-center justify-center text-gray-300 text-6xl select-none pointer-events-none">
                🖼️
              </div>
            ) : receipt ? (
              <ReceiptTemplateRenderer
                templateKey="designed"
                receipt={receipt}
                settings={demoSettings}
                logo={null}
                qrSvg={qrSvg}
              />
            ) : (
              <div className="absolute inset-0 flex items-center justify-center text-gray-400">
                <p>لا توجد وصولات لعرض المعاينة</p>
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t flex items-center justify-between bg-white">
          <button onClick={onClose} className="px-4 py-2 rounded-lg border hover:bg-gray-50 text-sm">إلغاء</button>
          <button
            onClick={onConfirmSave}
            className="px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium transition-colors"
          >
            ✅ تأكيد وحفظ التصميم
          </button>
        </div>
      </div>
    </div>
  );
}
