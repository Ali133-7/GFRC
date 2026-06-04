import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { usePermissions } from '@/hooks/usePermissions';
import { logError } from '@/utils/errorHandler';
import { ReceiptTemplateRenderer, templateNames, templateColors, defaultTemplate } from './ReceiptTemplateRenderer';
import { ReceiptElementsReorderer } from './ReceiptElementsReorderer';
import { Button } from '@/components/ui/Button';
import { usePrintSettings } from '@/hooks/usePrintSettings';
import { useSystemUploadLogo } from '@/hooks/useSystem';
import { getStoredLogo, storeLogo, removeStoredLogo, fileToBase64 } from '@/utils/localStorageLogo';
import { generateReceiptQr } from '@/utils/generateQr';
import { demoReceipt } from './demoReceipt';
import type { Receipt } from '@/types/receipt';

interface DesignerProps {
  receipt: Receipt;
  allReceipts?: Receipt[];
  onSelectReceipt?: (id: string) => void;
}

interface CustomTemplate {
  id: string;
  name: string;
  order: string[];
  activeElements: Record<string, boolean>;
  createdAt: number;
}

const elementDefs = [
  { id: 'header', label: 'الرأس والشعار', icon: '🏛️' },
  { id: 'title', label: 'عنوان الوصل', icon: '📋' },
  { id: 'number', label: 'رقم الوصل', icon: '🔢' },
  { id: 'meta', label: 'المعلومات', icon: '📄' },
  { id: 'table', label: 'جدول البنود', icon: '📊' },
  { id: 'total', label: 'المجموع', icon: '💰' },
  { id: 'qr', label: 'QR Code', icon: '🔳' },
  { id: 'signature', label: 'التوقيع', icon: '✍️' },
  { id: 'stamp', label: 'الختم', icon: '🔴' },
  { id: 'footer', label: 'التذييل', icon: '📝' },
];

export default function ReceiptDesigner({ receipt, allReceipts, onSelectReceipt }: DesignerProps) {
  const navigate = useNavigate();
  const { can } = usePermissions();
  const { settings, logo: serverLogo } = usePrintSettings();
  const uploadLogoMutation = useSystemUploadLogo();
  const [templateKey, setTemplateKey] = useState(defaultTemplate);
  const [localLogo, setLocalLogo] = useState<string | null>(getStoredLogo());
  const logo = serverLogo || localLogo;
  const [activeElements, setActiveElements] = useState<Record<string, boolean>>(
    () => Object.fromEntries(elementDefs.map((e) => [e.id, true]))
  );
  const [elementOrder, setElementOrder] = useState<string[]>(elementDefs.map((e) => e.id));
  const [showGallery, setShowGallery] = useState(false);
  const [qrDataUrl, setQrDataUrl] = useState('');
  const [customTemplates, setCustomTemplates] = useState<CustomTemplate[]>([]);
  const [showCustomTemplates, setShowCustomTemplates] = useState(false);

  // Load custom templates from localStorage
  useEffect(() => {
    const saved = localStorage.getItem('gfrc-custom-templates');
    if (saved) {
      try {
        setCustomTemplates(JSON.parse(saved));
      } catch (e) {
        logError(e, 'تحميل النماذج المخصصة');
      }
    }
  }, []);

  useEffect(() => {
    const payload = receipt.qr_payload ? JSON.parse(receipt.qr_payload) : {};
    if (payload.hash && receipt.id) {
      generateReceiptQr(receipt.id, payload.hash).then(setQrDataUrl);
    } else {
      setQrDataUrl('');
    }
  }, [receipt]);

  // Update CSS for element ordering
  useEffect(() => {
    const styleId = 'receipt-designer-order';
    let style = document.getElementById(styleId) as HTMLStyleElement | null;
    if (!style) {
      style = document.createElement('style');
      style.id = styleId;
      document.head.appendChild(style);
    }
    const css = elementOrder.map((id, idx) =>
      `[data-element-order="${id}"] { order: ${idx}; }`
    ).join('\n');
    style.textContent = css;
  }, [elementOrder]);

  const handleLogoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      await uploadLogoMutation.mutateAsync(file);
      const base64 = await fileToBase64(file);
      storeLogo(base64);
      setLocalLogo(base64);
    } catch (err) {
      console.error('Logo upload failed:', err);
    }
  };

  const handleRemoveLogo = () => {
    removeStoredLogo();
    setLocalLogo(null);
  };

  const handleSaveAsCustomTemplate = (templateName: string) => {
    const newTemplate: CustomTemplate = {
      id: `custom-${Date.now()}`,
      name: templateName,
      order: elementOrder,
      activeElements: { ...activeElements },
      createdAt: Date.now(),
    };

    const updated = [...customTemplates, newTemplate];
    setCustomTemplates(updated);
    localStorage.setItem('gfrc-custom-templates', JSON.stringify(updated));
    
    // Show success message
    alert(`✅ تم حفظ القالب "${templateName}" بنجاح`);
  };

  const handleLoadCustomTemplate = (template: CustomTemplate) => {
    setElementOrder(template.order);
    setActiveElements({ ...template.activeElements });
    setShowCustomTemplates(false);
  };

  const handleDeleteCustomTemplate = (id: string) => {
    if (confirm('هل أنت متأكد من حذف هذا القالب؟')) {
      const updated = customTemplates.filter((t) => t.id !== id);
      setCustomTemplates(updated);
      localStorage.setItem('gfrc-custom-templates', JSON.stringify(updated));
    }
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="flex flex-col gap-4">
      {/* Top Bar */}
      <div className="bg-white rounded-lg shadow p-4 flex flex-wrap items-center gap-4 no-print">
        {allReceipts && allReceipts.length > 0 && onSelectReceipt && (
          <div>
            <label className="block text-xs text-gray-500 mb-1">اختيار وصل حقيقي</label>
            <select
              className="rounded-md border px-3 py-2 text-sm min-w-[200px]"
              value={receipt.id}
              onChange={(e) => onSelectReceipt(e.target.value)}
            >
              <option value={demoReceipt.id}>🧪 وصل تجريبي</option>
              {allReceipts.map((r) => (
                <option key={r.id} value={r.id}>{r.receipt_number} — {r.register?.name_ar}</option>
              ))}
            </select>
          </div>
        )}

        <div className="flex items-center gap-2">
          {logo ? (
            <>
              <img src={logo} alt="logo" className="h-10 object-contain border rounded p-1" />
              <Button size="sm" variant="ghost" onClick={handleRemoveLogo}>إزالة</Button>
            </>
          ) : (
            <label className="cursor-pointer">
              <span className="text-sm text-blue-600 hover:underline">📎 رفع شعار</span>
              <input type="file" accept="image/*" className="hidden" onChange={handleLogoUpload} />
            </label>
          )}
        </div>

        <div className="flex-1" />

        <Button size="sm" variant="secondary" onClick={() => setShowGallery((s) => !s)}>
          {showGallery ? 'إخفاء' : 'عرض'} القوالب الجاهزة
        </Button>
        {customTemplates.length > 0 && (
          <Button
            size="sm"
            variant="secondary"
            onClick={() => setShowCustomTemplates((s) => !s)}
            className="relative"
          >
            📌 قوالبي ({customTemplates.length})
          </Button>
        )}
        <Button size="sm" onClick={handlePrint}>🖨️ طباعة</Button>
      </div>

      {templateKey === 'designed' && receipt.register_id && (
        <div className="bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 border border-indigo-200 rounded-lg p-5 no-print flex flex-col md:flex-row items-center justify-between gap-4 shadow-md transition-all hover:shadow-lg">
          <div className="flex items-center gap-3 text-right">
            <span className="text-4xl animate-pulse">🎨</span>
            <div>
              <h5 className="font-bold text-indigo-900 text-base">أنت تستخدم قالب التصميم الحر المخصص لهذا السجل!</h5>
              <p className="text-xs text-indigo-700 mt-1 font-medium">يمكنك النقر مباشرة على شعار المؤسسة، النصوص، الحقول لتعديل مواقعها، حجمها، ألوانها، وتخصيص شكل الوصل بشكل كامل ومختلف عن بقية السجلات.</p>
            </div>
          </div>
          {can('manage-settings') && (
            <Button
              onClick={() => navigate(`/registers/${receipt.register_id}/template-designer`)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-lg flex items-center gap-2 shadow-lg hover:shadow-indigo-500/20 transform hover:-translate-y-0.5 transition-all text-sm shrink-0"
            >
              <span>⚙️</span>
              الدخول إلى لوحة السحب والإفلات والتلوين
            </Button>
          )}
        </div>
      )}

      {/* Templates Gallery */}
      {showGallery && (
        <div className="bg-white rounded-lg shadow p-4 no-print">
          <h4 className="font-bold mb-3">اختر تصميم الوصل</h4>
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
            {Object.entries(templateNames).map(([key, name]) => (
              <button
                key={key}
                onClick={() => { setTemplateKey(key); setShowGallery(false); }}
                className={`rounded-lg border-2 p-3 text-center transition-all hover:shadow-md ${
                  templateKey === key ? 'border-blue-600 ring-2 ring-blue-200' : 'border-gray-200'
                } ${templateColors[key] || 'bg-gray-50'}`}
              >
                <div className="text-2xl mb-1">
                  {key === 'designed' && '🎨'}
                  {key === 'classic' && '📄'}
                  {key === 'modern' && '✨'}
                  {key === 'compact' && '🧾'}
                  {key === 'premium' && '💎'}
                  {key === 'wide' && '📃'}
                  {key === 'narrow' && '📜'}
                  {key === 'arabic' && '🇸🇦'}
                  {key === 'bilingual' && '🌐'}
                  {key === 'largeQr' && '🔳'}
                  {key === 'noQr' && '🔒'}
                </div>
                <p className="text-sm font-medium">{name}</p>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Custom Templates */}
      {showCustomTemplates && (
        <div className="bg-white rounded-lg shadow p-4 no-print">
          <h4 className="font-bold mb-3">📌 قوالبي المحفوظة</h4>
          {customTemplates.length === 0 ? (
            <p className="text-sm text-gray-500">لا توجد قوالب مخصصة بعد. احفظ تخطيطك الأول الآن!</p>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {customTemplates.map((template) => (
                <div
                  key={template.id}
                  className="border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow"
                >
                  <div className="flex items-start justify-between mb-2">
                    <div>
                      <h5 className="font-medium text-sm">{template.name}</h5>
                      <p className="text-xs text-gray-500">
                        {new Date(template.createdAt).toLocaleDateString('ar-SA')}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => handleLoadCustomTemplate(template)}
                      className="flex-1"
                    >
                      📂 تحميل
                    </Button>
                    <Button
                      size="sm"
                      variant="danger"
                      onClick={() => handleDeleteCustomTemplate(template.id)}
                      className="flex-1"
                    >
                      🗑️ حذف
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Main Content */}
      <div className="flex flex-col lg:flex-row gap-4">
        {/* Left Sidebar - Elements Reorderer */}
        <div className="w-full lg:w-80 shrink-0 bg-white rounded-lg shadow p-5 no-print overflow-y-auto max-h-[80vh]">
          <ReceiptElementsReorderer
            elements={elementDefs}
            order={elementOrder}
            activeElements={activeElements}
            onOrderChange={setElementOrder}
            onActiveChange={setActiveElements}
            onTemplateSave={handleSaveAsCustomTemplate}
            templateKey={templateKey}
          />
        </div>

        {/* Right Content - Preview */}
        <div className="flex-1 bg-gray-100 rounded-lg p-4 overflow-auto min-h-[600px] max-h-[80vh]">
          <div className="bg-white shadow-lg mx-auto" style={{ width: 'fit-content', minWidth: '300px' }}>
            <ReceiptTemplateRenderer
              templateKey={templateKey}
              receipt={receipt}
              settings={settings}
              logo={logo}
              qrSvg={qrDataUrl}
              activeElements={activeElements}
            />
          </div>
        </div>
      </div>
    </div>
  );
}
