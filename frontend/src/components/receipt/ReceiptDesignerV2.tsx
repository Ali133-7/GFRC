import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { HexColorPicker } from 'react-colorful';
import { usePrintSettings } from '@/hooks/usePrintSettings';
import { useSystemUploadLogo } from '@/hooks/useSystem';
import { getStoredLogo, storeLogo, removeStoredLogo, fileToBase64 } from '@/utils/localStorageLogo';
import { generateReceiptQr } from '@/utils/generateQr';
import { demoReceipt } from './demoReceipt';
import { ReceiptTemplateRenderer, templateNames, templateColors } from './ReceiptTemplateRenderer';
import type { Receipt } from '@/types/receipt';
import type { TemplateProps } from './templates/types';

interface DesignerV2Props {
  receipt?: Receipt;
  allReceipts?: Receipt[];
  onSelectReceipt?: (id: string) => void;
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

const fonts = [
  { value: "'Noto Sans Arabic', system-ui, sans-serif", label: 'نوتو سانس عربي' },
  { value: "'Segoe UI', system-ui, sans-serif", label: 'سيغوي' },
  { value: "'Tahoma', sans-serif", label: 'تاهوما' },
  { value: "'Arial', sans-serif", label: 'آريال' },
  { value: "'Courier New', monospace", label: 'كوريير' },
];

const defaultColors = {
  designer_bg: '#ffffff',
  designer_text: '#1f2937',
  designer_border: '#d1d5db',
  designer_header_bg: '#f3f4f6',
  designer_accent: '#2563eb',
};

const STORAGE_KEY = 'gfrc-designer-v2';

export default function ReceiptDesignerV2({ receipt: propReceipt, allReceipts, onSelectReceipt }: DesignerV2Props) {
  const navigate = useNavigate();
  const { settings, logo: serverLogo } = usePrintSettings();
  const uploadLogoMutation = useSystemUploadLogo();
  const [selectedId, setSelectedId] = useState<string>(demoReceipt.id);
  const [localLogo, setLocalLogo] = useState<string | null>(getStoredLogo());
  const logo = serverLogo || localLogo;
  const [qrDataUrl, setQrDataUrl] = useState('');
  const [activeTab, setActiveTab] = useState<'elements' | 'colors' | 'layout'>('elements');
  const [showTemplatePicker, setShowTemplatePicker] = useState(false);
  const [showSaveName, setShowSaveName] = useState(false);
  const [saveName, setSaveName] = useState('');
  const [savedConfigs, setSavedConfigs] = useState<{ id: string; name: string; data: any; createdAt: number }[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Designer state
  const [templateKey, setTemplateKey] = useState('dynamic');
  const [activeElements, setActiveElements] = useState<Record<string, boolean>>(
    () => Object.fromEntries(elementDefs.map((e) => [e.id, true]))
  );
  const [elementOrder, setElementOrder] = useState<string[]>(elementDefs.map((e) => e.id));
  const [designerSettings, setDesignerSettings] = useState<Record<string, string>>(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) return { ...defaultColors, ...JSON.parse(saved) };
    } catch { /* ignore */ }
    return { ...defaultColors };
  });

  // Load saved configs
  useEffect(() => {
    try {
      const saved = localStorage.getItem('gfrc-designer-configs');
      if (saved) setSavedConfigs(JSON.parse(saved));
    } catch { /* ignore */ }
  }, []);

  const receipt = propReceipt || demoReceipt;

  useEffect(() => {
    const payload = receipt.qr_payload ? JSON.parse(receipt.qr_payload) : {};
    if (payload.hash && receipt.id) {
      generateReceiptQr(receipt.id, payload.hash).then(setQrDataUrl);
    } else {
      setQrDataUrl('');
    }
  }, [receipt]);

  // Persist designer settings
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(designerSettings));
  }, [designerSettings]);

  const mergedSettings: TemplateProps['settings'] = {
    ...settings,
    ...designerSettings,
    show_qr: settings?.show_qr ?? true,
    show_stamp: settings?.show_stamp ?? true,
    show_signature: settings?.show_signature ?? true,
  };

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

  const toggleElement = (id: string) => {
    setActiveElements((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const moveElement = (id: string, direction: 'up' | 'down') => {
    setElementOrder((prev) => {
      const idx = prev.indexOf(id);
      if (idx === -1) return prev;
      const newIdx = direction === 'up' ? idx - 1 : idx + 1;
      if (newIdx < 0 || newIdx >= prev.length) return prev;
      const next = [...prev];
      [next[idx], next[newIdx]] = [next[newIdx], next[idx]];
      return next;
    });
  };

  const resetOrder = () => {
    setElementOrder(elementDefs.map((e) => e.id));
    setActiveElements(Object.fromEntries(elementDefs.map((e) => [e.id, true])));
  };

  const updateColor = (key: string, value: string) => {
    setDesignerSettings((prev) => ({ ...prev, [key]: value }));
  };

  const handlePrint = () => window.print();

  const handleSaveConfig = () => {
    if (!saveName.trim()) return;
    const config = {
      id: `cfg-${Date.now()}`,
      name: saveName.trim(),
      data: {
        templateKey,
        activeElements,
        elementOrder,
        designerSettings,
      },
      createdAt: Date.now(),
    };
    const updated = [...savedConfigs, config];
    setSavedConfigs(updated);
    localStorage.setItem('gfrc-designer-configs', JSON.stringify(updated));
    setShowSaveName(false);
    setSaveName('');
  };

  const loadConfig = (config: any) => {
    const data = config.data;
    setTemplateKey(data.templateKey || 'dynamic');
    setActiveElements(data.activeElements || {});
    setElementOrder(data.elementOrder || []);
    setDesignerSettings(data.designerSettings || {});
  };

  const deleteConfig = (id: string) => {
    const updated = savedConfigs.filter((c) => c.id !== id);
    setSavedConfigs(updated);
    localStorage.setItem('gfrc-designer-configs', JSON.stringify(updated));
  };

  const ColorField = ({ label, colorKey }: { label: string; colorKey: string }) => {
    const [open, setOpen] = useState(false);
    const popoverRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
      const handler = (e: MouseEvent) => {
        if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
          setOpen(false);
        }
      };
      if (open) document.addEventListener('mousedown', handler);
      return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    return (
      <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
        <div className="flex items-center gap-2">
          <div ref={popoverRef} className="relative">
            <button
              onClick={() => setOpen(!open)}
              className="w-10 h-10 rounded-lg border-2 border-gray-200 shadow-sm hover:shadow-md transition-shadow"
              style={{ backgroundColor: designerSettings[colorKey] || '#fff' }}
            />
            {open && (
              <div className="absolute z-50 top-full mt-2 right-0 bg-white p-2 rounded-xl shadow-xl border">
                <HexColorPicker
                  color={designerSettings[colorKey] || '#ffffff'}
                  onChange={(v) => updateColor(colorKey, v)}
                />
                <input
                  type="text"
                  value={designerSettings[colorKey] || ''}
                  onChange={(e) => updateColor(colorKey, e.target.value)}
                  className="mt-2 w-full text-xs border rounded px-2 py-1 text-center font-mono"
                />
              </div>
            )}
          </div>
          <input
            type="text"
            value={designerSettings[colorKey] || ''}
            onChange={(e) => updateColor(colorKey, e.target.value)}
            className="flex-1 text-sm border rounded-lg px-3 py-2 font-mono"
          />
        </div>
      </div>
    );
  };

  return (
    <div className="flex flex-col h-[calc(100vh-120px)] gap-4 print:h-auto">
      {/* Top Toolbar */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-3 flex flex-wrap items-center gap-3 no-print shrink-0">
        {/* Receipt selector */}
        {allReceipts && allReceipts.length > 0 && onSelectReceipt && (
          <select
            className="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 min-w-[180px]"
            value={selectedId}
            onChange={(e) => { setSelectedId(e.target.value); onSelectReceipt(e.target.value); }}
          >
            <option value={demoReceipt.id}>🧪 وصل تجريبي</option>
            {allReceipts.map((r) => (
              <option key={r.id} value={r.id}>{r.receipt_number} — {r.register?.name_ar}</option>
            ))}
          </select>
        )}

        <div className="w-px h-6 bg-gray-200" />

        {/* Logo */}
        <div className="flex items-center gap-2">
          {logo ? (
            <div className="flex items-center gap-2">
              <img src={logo} alt="logo" className="h-8 object-contain rounded border" />
              <button onClick={() => { removeStoredLogo(); setLocalLogo(null); }} className="text-xs text-red-500 hover:underline">
                إزالة
              </button>
            </div>
          ) : (
            <button onClick={() => fileInputRef.current?.click()} className="text-sm text-blue-600 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
              📎 رفع شعار
            </button>
          )}
          <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleLogoUpload} />
        </div>

        <div className="w-px h-6 bg-gray-200" />

        {/* Template selector */}
        <div className="relative">
          <button
            onClick={() => setShowTemplatePicker(!showTemplatePicker)}
            className={`text-sm px-3 py-1.5 rounded-lg border transition-colors ${showTemplatePicker ? 'bg-blue-50 border-blue-300 text-blue-700' : 'border-gray-200 hover:bg-gray-50'}`}
          >
            🎨 {templateNames[templateKey] || 'القالب'}
          </button>
          {showTemplatePicker && (
            <div className="absolute top-full mt-2 right-0 z-50 bg-white rounded-xl shadow-xl border border-gray-100 p-3 w-80">
              <p className="text-xs font-bold text-gray-500 mb-2">اختر قالباً</p>
              <div className="grid grid-cols-2 gap-2">
                {Object.entries(templateNames).map(([key, name]) => (
                  <button
                    key={key}
                    onClick={() => { setTemplateKey(key); setShowTemplatePicker(false); }}
                    className={`rounded-lg border-2 p-2.5 text-center text-xs transition-all hover:shadow-md ${templateKey === key ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200'} ${templateColors[key] || ''}`}
                  >
                    {name}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        <div className="flex-1" />

        {/* Saved configs */}
        {savedConfigs.length > 0 && (
          <div className="relative group">
            <button className="text-sm px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
              📌 {savedConfigs.length} قالب محفوظ
            </button>
            <div className="absolute top-full mt-2 left-0 z-50 bg-white rounded-xl shadow-xl border border-gray-100 p-2 w-56 hidden group-hover:block">
              {savedConfigs.map((cfg) => (
                <div key={cfg.id} className="flex items-center gap-1 p-2 hover:bg-gray-50 rounded-lg">
                  <button onClick={() => loadConfig(cfg)} className="flex-1 text-right text-sm truncate">
                    {cfg.name}
                  </button>
                  <button onClick={() => deleteConfig(cfg.id)} className="text-xs text-red-400 hover:text-red-600 px-1">
                    ✕
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        <button
          onClick={() => setShowSaveName(true)}
          className="text-sm px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
        >
          💾 حفظ
        </button>
        <button
          onClick={handlePrint}
          className="text-sm px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors"
        >
          🖨️ طباعة
        </button>
      </div>

      {/* Save name modal */}
      {showSaveName && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 no-print">
          <div className="bg-white rounded-2xl shadow-2xl p-6 w-80">
            <h3 className="font-bold text-lg mb-3">حفظ التصميم</h3>
            <input
              type="text"
              value={saveName}
              onChange={(e) => setSaveName(e.target.value)}
              placeholder="اسم التصميم..."
              className="w-full border rounded-lg px-3 py-2 text-sm mb-4"
              autoFocus
              onKeyDown={(e) => e.key === 'Enter' && handleSaveConfig()}
            />
            <div className="flex gap-2">
              <button onClick={() => setShowSaveName(false)} className="flex-1 text-sm py-2 rounded-lg border hover:bg-gray-50">إلغاء</button>
              <button onClick={handleSaveConfig} disabled={!saveName.trim()} className="flex-1 text-sm py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">حفظ</button>
            </div>
          </div>
        </div>
      )}

      {/* Free Design Banner */}
      {templateKey === 'designed' && propReceipt?.register_id && (
        <div className="bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 border border-indigo-200 rounded-xl p-4 no-print flex flex-col md:flex-row items-center justify-between gap-3 shadow-sm">
          <div className="flex items-center gap-3 text-right">
            <span className="text-3xl">🎨</span>
            <div>
              <h5 className="font-bold text-indigo-900 text-sm">أنت تستخدم قالب التصميم الحر المخصص!</h5>
              <p className="text-xs text-indigo-700 mt-0.5">يمكنك الدخول إلى لوحة السحب والإفلات لتعديل مواقع العناصر، أحجامها، وألوانها بشكل كامل.</p>
            </div>
          </div>
          <button
            onClick={() => navigate(`/registers/${propReceipt.register_id}/template-designer`)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2 rounded-lg flex items-center gap-2 shadow-md text-sm shrink-0 transition-colors"
          >
            <span>⚙️</span>
            الدخول إلى لوحة السحب والإفلات والتلوين
          </button>
        </div>
      )}

      {/* Main workspace */}
      <div className="flex flex-1 gap-4 min-h-0">
        {/* Left sidebar */}
        <div className="w-80 shrink-0 bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col no-print overflow-hidden">
          {/* Tabs */}
          <div className="flex border-b border-gray-100">
            {[
              { key: 'elements', label: 'العناصر', icon: '🧩' },
              { key: 'colors', label: 'الألوان', icon: '🎨' },
              { key: 'layout', label: 'التخطيط', icon: '📐' },
            ].map((tab) => (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key as any)}
                className={`flex-1 text-xs font-medium py-3 flex items-center justify-center gap-1 transition-colors ${
                  activeTab === tab.key
                    ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                }`}
              >
                <span>{tab.icon}</span>
                {tab.label}
              </button>
            ))}
          </div>

          {/* Tab content */}
          <div className="flex-1 overflow-y-auto p-4">
            {/* Elements Tab */}
            {activeTab === 'elements' && (
              <div className="space-y-3">
                <p className="text-xs text-gray-500 mb-2">اضغط ↑ ↓ لإعادة الترتيب، والمربع لإظهار/إخفاء</p>
                {elementOrder.map((id, idx) => {
                  const el = elementDefs.find((e) => e.id === id);
                  if (!el) return null;
                  const isActive = activeElements[id] !== false;
                  return (
                    <div
                      key={id}
                      className={`flex items-center gap-2 p-3 rounded-xl border-2 transition-all ${
                        isActive
                          ? 'bg-blue-50 border-blue-200 shadow-sm'
                          : 'bg-gray-50 border-gray-200 opacity-60'
                      }`}
                    >
                      <div className="flex flex-col gap-0.5">
                        <button
                          onClick={() => moveElement(id, 'up')}
                          disabled={idx === 0}
                          className="text-gray-400 hover:text-blue-600 disabled:opacity-20 disabled:hover:text-gray-400 transition-colors"
                        >
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" /></svg>
                        </button>
                        <button
                          onClick={() => moveElement(id, 'down')}
                          disabled={idx === elementOrder.length - 1}
                          className="text-gray-400 hover:text-blue-600 disabled:opacity-20 disabled:hover:text-gray-400 transition-colors"
                        >
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                        </button>
                      </div>
                      <span className="text-xl">{el.icon}</span>
                      <span className="text-sm font-medium flex-1">{el.label}</span>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={isActive}
                          onChange={() => toggleElement(id)}
                          className="sr-only peer"
                        />
                        <div className="w-9 h-5 bg-gray-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600" />
                      </label>
                    </div>
                  );
                })}
                <button
                  onClick={resetOrder}
                  className="w-full text-sm py-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors text-gray-600"
                >
                  ↺ إعادة التعيين
                </button>
              </div>
            )}

            {/* Colors Tab */}
            {activeTab === 'colors' && (
              <div>
                <ColorField label="خلفية الوصل" colorKey="designer_bg" />
                <ColorField label="لون النصوص" colorKey="designer_text" />
                <ColorField label="لون الحدود" colorKey="designer_border" />
                <ColorField label="خلفية الرأس والبطاقات" colorKey="designer_header_bg" />
                <ColorField label="لون التمييز (Accent)" colorKey="designer_accent" />
              </div>
            )}

            {/* Layout Tab */}
            {activeTab === 'layout' && (
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">نوع الخط</label>
                  <select
                    value={designerSettings['designer_font'] || fonts[0].value}
                    onChange={(e) => updateColor('designer_font', e.target.value)}
                    className="w-full text-sm border rounded-lg px-3 py-2"
                  >
                    {fonts.map((f) => (
                      <option key={f.value} value={f.value}>{f.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">حجم الخط ({designerSettings['designer_font_size'] || '14px'})</label>
                  <input
                    type="range"
                    min={10}
                    max={22}
                    step={1}
                    value={parseInt(designerSettings['designer_font_size'] || '14')}
                    onChange={(e) => updateColor('designer_font_size', `${e.target.value}px`)}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-gray-400 mt-1">
                    <span>10px</span>
                    <span>22px</span>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">الهوامش الداخلية ({designerSettings['designer_padding'] || '32px'})</label>
                  <input
                    type="range"
                    min={8}
                    max={64}
                    step={4}
                    value={parseInt(designerSettings['designer_padding'] || '32')}
                    onChange={(e) => updateColor('designer_padding', `${e.target.value}px`)}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-gray-400 mt-1">
                    <span>8px</span>
                    <span>64px</span>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">تدوير الزوايا ({designerSettings['designer_radius'] || '0px'})</label>
                  <input
                    type="range"
                    min={0}
                    max={24}
                    step={2}
                    value={parseInt(designerSettings['designer_radius'] || '0')}
                    onChange={(e) => updateColor('designer_radius', `${e.target.value}px`)}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-gray-400 mt-1">
                    <span>0px</span>
                    <span>24px</span>
                  </div>
                </div>
                <div className="pt-2 border-t">
                  <label className="flex items-center gap-2 text-sm mb-2">
                    <input
                      type="checkbox"
                      checked={mergedSettings.show_qr}
                      onChange={(e) => updateColor('show_qr', e.target.checked ? 'true' : 'false')}
                      className="rounded"
                    />
                    إظهار QR Code
                  </label>
                  <label className="flex items-center gap-2 text-sm mb-2">
                    <input
                      type="checkbox"
                      checked={mergedSettings.show_stamp}
                      onChange={(e) => updateColor('show_stamp', e.target.checked ? 'true' : 'false')}
                      className="rounded"
                    />
                    إظهار الختم
                  </label>
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={mergedSettings.show_signature}
                      onChange={(e) => updateColor('show_signature', e.target.checked ? 'true' : 'false')}
                      className="rounded"
                    />
                    إظهار التوقيع
                  </label>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Right preview */}
        <div className="flex-1 bg-gray-100 rounded-xl border border-gray-200 overflow-auto p-6 flex items-start justify-center">
          <div className="bg-white shadow-xl rounded-lg overflow-hidden" style={{ width: 'fit-content', minWidth: '320px' }}>
            <ReceiptTemplateRenderer
              templateKey={templateKey}
              receipt={receipt}
              settings={mergedSettings}
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
