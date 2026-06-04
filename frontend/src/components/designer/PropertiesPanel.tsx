import { useState, useEffect } from 'react';
import type { TemplateElement, TemplateStyle, ReceiptTemplate } from '@/types/template';

interface Props {
  element: TemplateElement | null;
  template: ReceiptTemplate | null;
  onUpdateElement: (id: string, updates: Partial<TemplateElement>) => void;
  onUpdateStyle: (id: string, style: Partial<TemplateStyle>) => void;
  onUpdateMeta: (meta: Partial<ReceiptTemplate>) => void;
  onSyncStyle: (elementId: string, data: any) => void;
}

const fonts = [
  { value: "'Noto Sans Arabic', system-ui, sans-serif", label: 'نوتو سانس عربي' },
  { value: "'Segoe UI', system-ui, sans-serif", label: 'سيغوي' },
  { value: "'Tahoma', sans-serif", label: 'تاهوما' },
  { value: "'Arial', sans-serif", label: 'آريال' },
  { value: "'Courier New', monospace", label: 'كوريير' },
];

const presets = [
  { key: 'header', label: 'رأس', style: { font_size: 18, font_weight: 'bold', font_color: '#1e293b', text_align: 'center' } },
  { key: 'title', label: 'عنوان', style: { font_size: 16, font_weight: 'bold', font_color: '#2563eb', text_align: 'right' } },
  { key: 'body', label: 'نص', style: { font_size: 13, font_weight: 'normal', font_color: '#334155', text_align: 'right' } },
  { key: 'footer', label: 'تذييل', style: { font_size: 11, font_weight: 'normal', font_color: '#64748b', text_align: 'center' } },
  { key: 'label', label: 'تسمية', style: { font_size: 12, font_weight: '600', font_color: '#475569', text_align: 'right' } },
  { key: 'value', label: 'قيمة', style: { font_size: 13, font_weight: 'normal', font_color: '#0f172a', text_align: 'left' } },
  { key: 'currency', label: 'عملة', style: { font_size: 14, font_weight: 'bold', font_color: '#059669', text_align: 'left' } },
];

export default function PropertiesPanel({ element, template, onUpdateElement, onUpdateStyle, onUpdateMeta, onSyncStyle }: Props) {
  const [tab, setTab] = useState<'element' | 'style' | 'page'>('element');
  const [saving, setSaving] = useState(false);

  // Reset tab when element changes
  useEffect(() => { if (element) setTab('element'); }, [element?.id]);

  if (!element || !template) {
    return (
      <div className="w-72 shrink-0 bg-gray-50 border-r border-gray-200 h-full flex flex-col items-center justify-center text-gray-400 p-6 text-center">
        <span className="text-4xl mb-3">🎨</span>
        <p className="text-sm">اختر عنصراً من القائمة أو من ساحة العمل</p>
        <p className="text-xs mt-1 text-gray-300">ستظهر خصائصه هنا للتعديل</p>
      </div>
    );
  }

  const style = element.style || {
    font_family: "'Noto Sans Arabic', Arial, sans-serif",
    font_size: 13,
    font_weight: 'normal',
    font_color: '#1f293b',
    background_color: '',
    border_color: '#e2e8f0',
    border_width: 0,
    text_align: 'right',
    padding: { top: 4, right: 8, bottom: 4, left: 8 },
    opacity: 1,
    display: 'block',
    line_height: 1.4,
  };

  const applyPreset = (preset: any) => {
    onUpdateStyle(element.id, preset);
    onSyncStyle(element.id, preset);
  };

  const handleStyleChange = (key: keyof TemplateStyle, value: any) => {
    onUpdateStyle(element.id, { [key]: value });
  };

  const handleSaveStyle = async () => {
    setSaving(true);
    await onSyncStyle(element.id, style);
    setSaving(false);
  };

  return (
    <div className="w-72 shrink-0 bg-white border-r border-gray-200 h-full flex flex-col">
      {/* Tabs */}
      <div className="flex border-b border-gray-100">
        {[
          { key: 'element', label: 'العنصر' },
          { key: 'style', label: 'التنسيق' },
          { key: 'page', label: 'الصفحة' },
        ].map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key as any)}
            className={`flex-1 py-2.5 text-xs font-medium transition-colors ${
              tab === t.key ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {tab === 'element' && (
          <>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">العنوان</label>
              <input
                type="text"
                value={element.label || ''}
                onChange={(e) => onUpdateElement(element.id, { label: e.target.value })}
                className="w-full text-sm border rounded-lg px-3 py-2"
              />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">X (px)</label>
                <input type="number" value={element.x} onChange={(e) => onUpdateElement(element.id, { x: parseInt(e.target.value) || 0 })} className="w-full text-sm border rounded-lg px-2 py-1.5" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Y (px)</label>
                <input type="number" value={element.y} onChange={(e) => onUpdateElement(element.id, { y: parseInt(e.target.value) || 0 })} className="w-full text-sm border rounded-lg px-2 py-1.5" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">العرض</label>
                <input type="number" value={element.width} onChange={(e) => onUpdateElement(element.id, { width: parseInt(e.target.value) || 10 })} className="w-full text-sm border rounded-lg px-2 py-1.5" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">الارتفاع</label>
                <input type="number" value={element.height} onChange={(e) => onUpdateElement(element.id, { height: parseInt(e.target.value) || 10 })} className="w-full text-sm border rounded-lg px-2 py-1.5" />
              </div>
            </div>
            <label className="flex items-center gap-2 text-xs">
              <input type="checkbox" checked={element.is_visible !== false} onChange={(e) => onUpdateElement(element.id, { is_visible: e.target.checked })} className="rounded" />
              إظهار العنصر
            </label>
          </>
        )}

        {tab === 'style' && (
          <>
            {/* Presets */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1.5">أنماط مسبقة</label>
              <div className="grid grid-cols-3 gap-1.5">
                {presets.map((p) => (
                  <button key={p.key} onClick={() => applyPreset(p.style)} className="px-1 py-1.5 rounded text-[10px] font-medium border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition">
                    {p.label}
                  </button>
                ))}
              </div>
            </div>

            <hr className="border-gray-100" />

            {/* Font */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">الخط</label>
              <select value={style.font_family} onChange={(e) => handleStyleChange('font_family', e.target.value)} className="w-full text-sm border rounded-lg px-2 py-1.5">
                {fonts.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">الحجم: {style.font_size}px</label>
              <input type="range" min={8} max={48} value={style.font_size || 13} onChange={(e) => handleStyleChange('font_size', parseInt(e.target.value))} className="w-full" />
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">السمك</label>
              <select value={style.font_weight} onChange={(e) => handleStyleChange('font_weight', e.target.value)} className="w-full text-sm border rounded-lg px-2 py-1.5">
                {['normal', 'bold', '300', '500', '600', '700', '900'].map((w) => <option key={w} value={w}>{w}</option>)}
              </select>
            </div>

            {/* Colors */}
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">لون الخط</label>
                <div className="flex gap-1">
                  <input type="color" value={style.font_color || '#000'} onChange={(e) => handleStyleChange('font_color', e.target.value)} className="w-8 h-8 border rounded cursor-pointer" />
                  <input type="text" value={style.font_color || ''} onChange={(e) => handleStyleChange('font_color', e.target.value)} className="flex-1 text-xs border rounded px-2 font-mono" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">الخلفية</label>
                <div className="flex gap-1">
                  <input type="color" value={style.background_color || '#ffffff'} onChange={(e) => handleStyleChange('background_color', e.target.value)} className="w-8 h-8 border rounded cursor-pointer" />
                  <input type="text" value={style.background_color || ''} onChange={(e) => handleStyleChange('background_color', e.target.value)} placeholder="شفاف" className="flex-1 text-xs border rounded px-2 font-mono" />
                </div>
              </div>
            </div>

            {/* Align */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">المحاذاة</label>
              <div className="flex gap-1">
                {['right', 'center', 'left'].map((a) => (
                  <button key={a} onClick={() => handleStyleChange('text_align', a as any)} className={`flex-1 py-1.5 rounded text-xs transition ${style.text_align === a ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200'}`}>
                    {a === 'right' ? 'يمين' : a === 'center' ? 'وسط' : 'يسار'}
                  </button>
                ))}
              </div>
            </div>

            {/* Border */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">الحد: {style.border_width || 0}px</label>
              <input type="range" min={0} max={8} value={style.border_width || 0} onChange={(e) => handleStyleChange('border_width', parseInt(e.target.value))} className="w-full" />
              {(style.border_width || 0) > 0 && (
                <div className="flex gap-1 mt-1">
                  <input type="color" value={style.border_color || '#000'} onChange={(e) => handleStyleChange('border_color', e.target.value)} className="w-8 h-8 border rounded cursor-pointer" />
                  <input type="text" value={style.border_color || ''} onChange={(e) => handleStyleChange('border_color', e.target.value)} className="flex-1 text-xs border rounded px-2 font-mono" />
                </div>
              )}
            </div>

            {/* Opacity */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">الشفافية: {Math.round((style.opacity || 1) * 100)}%</label>
              <input type="range" min={0} max={100} value={Math.round((style.opacity || 1) * 100)} onChange={(e) => handleStyleChange('opacity', parseInt(e.target.value) / 100)} className="w-full" />
            </div>

            {/* Padding */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">المسافات الداخلية (px)</label>
              <div className="grid grid-cols-2 gap-2">
                {[
                  { key: 'padding_top', label: 'أعلى' },
                  { key: 'padding_right', label: 'يمين' },
                  { key: 'padding_bottom', label: 'أسفل' },
                  { key: 'padding_left', label: 'يسار' },
                ].map((p) => (
                  <div key={p.key}>
                    <label className="text-[10px] text-gray-400">{p.label}</label>
                    <input type="number" value={(style.padding as any)?.[p.key.replace('padding_', '')] || 0} onChange={(e) => {
                      const padKey = p.key.replace('padding_', '') as 'top' | 'right' | 'bottom' | 'left';
                      handleStyleChange('padding', { ...(style.padding || {}), [padKey]: parseInt(e.target.value) || 0 });
                    }} className="w-full text-xs border rounded px-2 py-1" />
                  </div>
                ))}
              </div>
            </div>

            <button onClick={handleSaveStyle} disabled={saving} className="w-full py-2 rounded-lg bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors">
              {saving ? 'جاري الحفظ...' : '💾 حفظ التنسيق'}
            </button>
          </>
        )}

        {tab === 'page' && (
          <>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">اسم القالب</label>
              <input type="text" value={template.name} onChange={(e) => onUpdateMeta({ name: e.target.value })} className="w-full text-sm border rounded-lg px-3 py-2" />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">العرض (mm)</label>
                <input type="number" value={template.page_width} onChange={(e) => onUpdateMeta({ page_width: parseInt(e.target.value) || 210 })} className="w-full text-sm border rounded-lg px-2 py-1.5" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">الارتفاع (mm)</label>
                <input type="number" value={template.page_height} onChange={(e) => onUpdateMeta({ page_height: parseInt(e.target.value) || 297 })} className="w-full text-sm border rounded-lg px-2 py-1.5" />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">خلفية الصفحة</label>
              <div className="flex gap-2">
                <input type="color" value={template.background_color || '#ffffff'} onChange={(e) => onUpdateMeta({ background_color: e.target.value })} className="w-10 h-10 border rounded cursor-pointer" />
                <input type="text" value={template.background_color || ''} onChange={(e) => onUpdateMeta({ background_color: e.target.value })} className="flex-1 text-sm border rounded-lg px-3 py-2 font-mono" />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">الاتجاه</label>
              <select value={template.layout_type} onChange={(e) => onUpdateMeta({ layout_type: e.target.value as any })} className="w-full text-sm border rounded-lg px-2 py-1.5">
                <option value="portrait">عمودي</option>
                <option value="landscape">أفقي</option>
              </select>
            </div>
            <div className="text-xs text-gray-400 pt-2">
              {template.elements.length} عنصر | آخر تحديث: {new Date(template.updated_at).toLocaleDateString('ar-IQ')}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
