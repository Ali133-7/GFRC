import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/Button';

interface StylePanelProps {
  elementId: string;
  templateId: string;
  currentStyle?: any;
  onStyleChange?: (updates: any) => void;
  onStyleSave?: (data: any) => void;
}

interface Styles {
  font_family: string;
  font_size: number;
  font_weight: string;
  font_color: string;
  background_color?: string;
  border_color?: string;
  border_width: number;
  text_align: 'left' | 'center' | 'right';
  padding_top: number;
  padding_right: number;
  padding_bottom: number;
  padding_left: number;
  opacity: number;
  display: 'block' | 'inline' | 'none';
}

const fontFamilies = ['Arial', 'Times New Roman', 'Courier New', 'Georgia', 'Verdana', 'Tahoma', 'Segoe UI', 'Noto Sans Arabic'];
const fontWeights = ['normal', 'bold', '300', '600', '700', '900'];
const presets = ['header', 'title', 'body', 'footer', 'label', 'value', 'currency'];

export default function StylePanel({ elementId, currentStyle, onStyleChange, onStyleSave }: StylePanelProps) {
  const [styles, setStyles] = useState<Partial<Styles>>({
    font_family: 'Arial',
    font_size: 13,
    font_weight: 'normal',
    font_color: '#1e293b',
    background_color: '',
    border_color: '',
    border_width: 1,
    text_align: 'right',
    padding_top: 6,
    padding_right: 10,
    padding_bottom: 6,
    padding_left: 10,
    opacity: 1,
    display: 'block',
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (currentStyle) {
      setStyles({
        font_family: currentStyle.font_family || 'Arial',
        font_size: currentStyle.font_size || 13,
        font_weight: currentStyle.font_weight || 'normal',
        font_color: currentStyle.font_color || '#1e293b',
        background_color: currentStyle.background_color || '',
        border_color: currentStyle.border_color || '',
        border_width: currentStyle.border_width ?? 1,
        text_align: currentStyle.text_align || 'right',
        padding_top: currentStyle.padding?.top ?? currentStyle.padding_top ?? 6,
        padding_right: currentStyle.padding?.right ?? currentStyle.padding_right ?? 10,
        padding_bottom: currentStyle.padding?.bottom ?? currentStyle.padding_bottom ?? 6,
        padding_left: currentStyle.padding?.left ?? currentStyle.padding_left ?? 10,
        opacity: currentStyle.opacity ?? 1,
        display: currentStyle.display || 'block',
      });
    }
  }, [elementId, currentStyle]);

  const handleChange = (key: keyof Styles, value: any) => {
    const next = { ...styles, [key]: value };
    setStyles(next);
    onStyleChange?.(next);
  };

  const handleSave = async () => {
    setSaving(true);
    await onStyleSave?.(styles);
    setSaving(false);
  };

  const handlePreset = async (preset: string) => {
    setSaving(true);
    // Apply preset locally then save
    const presetMap: Record<string, Partial<Styles>> = {
      header: { font_size: 18, font_weight: 'bold', font_color: '#1e293b', text_align: 'center' },
      title: { font_size: 16, font_weight: 'bold', font_color: '#2563eb', text_align: 'right' },
      body: { font_size: 13, font_weight: 'normal', font_color: '#334155', text_align: 'right' },
      footer: { font_size: 11, font_weight: 'normal', font_color: '#64748b', text_align: 'center' },
      label: { font_size: 12, font_weight: '600', font_color: '#475569', text_align: 'right' },
      value: { font_size: 13, font_weight: 'normal', font_color: '#0f172a', text_align: 'left' },
      currency: { font_size: 14, font_weight: 'bold', font_color: '#059669', text_align: 'left' },
    };
    const next = { ...styles, ...presetMap[preset] };
    setStyles(next);
    onStyleChange?.(next);
    await onStyleSave?.({ ...next, preset });
    setSaving(false);
  };

  return (
    <div className="bg-white shadow-sm border-l border-gray-200 p-4 space-y-4">
      <h3 className="font-bold text-sm text-gray-800 border-b pb-2">🎨 تنسيق العنصر</h3>

      {/* Presets */}
      <div>
        <label className="block text-xs font-semibold text-gray-600 mb-1.5">أنماط مسبقة</label>
        <div className="grid grid-cols-3 gap-1.5">
          {presets.map((preset) => (
            <button
              key={preset}
              onClick={() => handlePreset(preset)}
              disabled={saving}
              className="px-2 py-1.5 rounded text-[11px] font-medium border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition disabled:opacity-50"
            >
              {preset}
            </button>
          ))}
        </div>
      </div>

      <hr className="border-gray-100" />

      {/* Font */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">الخط</label>
        <select value={styles.font_family} onChange={(e) => handleChange('font_family', e.target.value)} className="w-full px-2 py-1.5 border rounded text-xs">
          {fontFamilies.map((f) => <option key={f} value={f}>{f}</option>)}
        </select>
      </div>

      {/* Font Size */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">الحجم: {styles.font_size}px</label>
        <input type="range" min={6} max={72} value={styles.font_size || 13} onChange={(e) => handleChange('font_size', parseInt(e.target.value))} className="w-full" />
      </div>

      {/* Font Weight */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">السمك</label>
        <select value={styles.font_weight} onChange={(e) => handleChange('font_weight', e.target.value)} className="w-full px-2 py-1.5 border rounded text-xs">
          {fontWeights.map((w) => <option key={w} value={w}>{w}</option>)}
        </select>
      </div>

      {/* Colors */}
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1">
          <label className="block text-xs font-semibold text-gray-600">لون الخط</label>
          <div className="flex gap-1">
            <input type="color" value={styles.font_color || '#000000'} onChange={(e) => handleChange('font_color', e.target.value)} className="w-8 h-8 border rounded cursor-pointer" />
            <input type="text" value={styles.font_color || ''} onChange={(e) => handleChange('font_color', e.target.value)} className="flex-1 px-2 py-1 border rounded text-xs font-mono" />
          </div>
        </div>
        <div className="space-y-1">
          <label className="block text-xs font-semibold text-gray-600">خلفية</label>
          <div className="flex gap-1">
            <input type="color" value={styles.background_color || '#FFFFFF'} onChange={(e) => handleChange('background_color', e.target.value)} className="w-8 h-8 border rounded cursor-pointer" />
            <input type="text" value={styles.background_color || ''} onChange={(e) => handleChange('background_color', e.target.value)} placeholder="شفاف" className="flex-1 px-2 py-1 border rounded text-xs font-mono" />
          </div>
        </div>
      </div>

      {/* Text Align */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">المحاذاة</label>
        <div className="flex gap-1">
          {['right', 'center', 'left'].map((a) => (
            <button key={a} onClick={() => handleChange('text_align', a)} className={`flex-1 px-2 py-1.5 rounded text-xs transition ${styles.text_align === a ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200'}`}>
              {a === 'right' ? 'يمين' : a === 'center' ? 'وسط' : 'يسار'}
            </button>
          ))}
        </div>
      </div>

      {/* Border */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">الحد: {styles.border_width}px</label>
        <input type="range" min={0} max={10} value={styles.border_width ?? 0} onChange={(e) => handleChange('border_width', parseInt(e.target.value))} className="w-full" />
        {(styles.border_width || 0) > 0 && (
          <div className="flex gap-1">
            <input type="color" value={styles.border_color || '#000000'} onChange={(e) => handleChange('border_color', e.target.value)} className="w-8 h-8 border rounded cursor-pointer" />
            <input type="text" value={styles.border_color || ''} onChange={(e) => handleChange('border_color', e.target.value)} className="flex-1 px-2 py-1 border rounded text-xs font-mono" />
          </div>
        )}
      </div>

      {/* Opacity */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">الشفافية: {Math.round((styles.opacity || 1) * 100)}%</label>
        <input type="range" min={0} max={100} value={Math.round((styles.opacity || 1) * 100)} onChange={(e) => handleChange('opacity', parseInt(e.target.value) / 100)} className="w-full" />
      </div>

      {/* Padding */}
      <div className="space-y-1.5">
        <label className="block text-xs font-semibold text-gray-600">المسافات الداخلية (px)</label>
        <div className="grid grid-cols-2 gap-2">
          {[
            { key: 'padding_top', label: 'أعلى' },
            { key: 'padding_right', label: 'يمين' },
            { key: 'padding_bottom', label: 'أسفل' },
            { key: 'padding_left', label: 'يسار' },
          ].map((p) => (
            <div key={p.key}>
              <label className="text-[10px] text-gray-500">{p.label}</label>
              <input
                type="number"
                value={(styles as any)[p.key] || 0}
                onChange={(e) => handleChange(p.key as keyof Styles, parseInt(e.target.value) || 0)}
                className="w-full px-2 py-1 border rounded text-xs"
              />
            </div>
          ))}
        </div>
      </div>

      {/* Save */}
      <Button onClick={handleSave} disabled={saving} className="w-full" variant="primary">
        {saving ? '💾 جاري...' : '💾 حفظ التنسيق'}
      </Button>
    </div>
  );
}
