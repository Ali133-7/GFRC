import { useState } from 'react';
import type { TemplateElement } from '@/types/template';

interface Props {
  elements: TemplateElement[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onToggleVisibility: (id: string) => void;
  onDelete: (id: string) => void;
  onDuplicate: (id: string) => void;
  onReorder: (id: string, direction: 'up' | 'down') => void;
  onAdd: (type: TemplateElement['element_type']) => void;
  onClear: () => void;
}

const typeLabels: Record<string, { label: string; icon: string; desc: string }> = {
  field: { label: 'حقل من السجل', icon: '📋', desc: 'يربط بحقول السجل' },
  text: { label: 'نص ثابت', icon: '📝', desc: 'عنوان أو ملاحظة' },
  divider: { label: 'فاصل', icon: '➖', desc: 'خط أفقي' },
  qr: { label: 'رمز QR', icon: '🔳', desc: 'رمز التحقق السريع' },
  signature: { label: 'توقيع', icon: '✍️', desc: 'منطقة التوقيع' },
  total: { label: 'المجموع', icon: '💰', desc: 'إجمالي المبالغ' },
  image: { label: 'صورة / شعار', icon: '🖼️', desc: 'شعار الجهة' },
  spacer: { label: 'مسافة فارغة', icon: '⬜', desc: 'فراغ تنظيمي' },
};

export default function ToolboxPanel({ elements, selectedId, onSelect, onToggleVisibility, onDelete, onDuplicate, onReorder, onAdd, onClear }: Props) {
  const [activeTab, setActiveTab] = useState<'tools' | 'layers'>('tools');

  return (
    <div className="w-64 shrink-0 bg-white border-l border-gray-200 flex flex-col h-full">
      {/* Tabs */}
      <div className="flex border-b border-gray-100">
        <button
          onClick={() => setActiveTab('tools')}
          className={`flex-1 py-2.5 text-xs font-medium transition-colors ${activeTab === 'tools' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'}`}
        >
          🧰 الأدوات
        </button>
        <button
          onClick={() => setActiveTab('layers')}
          className={`flex-1 py-2.5 text-xs font-medium transition-colors ${activeTab === 'layers' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'}`}
        >
          📋 الطبقات ({elements.length})
        </button>
      </div>

      <div className="flex-1 overflow-y-auto">
        {activeTab === 'tools' && (
          <div className="p-3 space-y-2">
            <p className="text-[11px] text-gray-400 mb-2">اضغط لإضافة عنصر جديد</p>
            {Object.entries(typeLabels).map(([type, info]) => (
              <button
                key={type}
                onClick={() => onAdd(type as TemplateElement['element_type'])}
                className="w-full flex items-center gap-3 p-2.5 rounded-lg border border-gray-100 hover:border-blue-300 hover:bg-blue-50/50 transition-all text-right group"
              >
                <span className="text-lg">{info.icon}</span>
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-medium text-gray-700 group-hover:text-blue-700">{info.label}</p>
                  <p className="text-[10px] text-gray-400">{info.desc}</p>
                </div>
                <span className="text-gray-300 group-hover:text-blue-400 text-xs">+</span>
              </button>
            ))}
            <button
              onClick={onClear}
              className="w-full mt-3 p-2 rounded-lg border border-red-100 text-red-600 hover:bg-red-50 text-xs font-medium transition-colors"
            >
              🗑️ مسح جميع العناصر
            </button>
          </div>
        )}

        {activeTab === 'layers' && (
          <div className="py-1">
            {elements.length === 0 && (
              <div className="p-6 text-center text-gray-400 text-xs">
                <p>لا توجد عناصر</p>
                <p className="mt-1">انتقل إلى "الأدوات" لإضافة عنصر</p>
              </div>
            )}
            {elements.map((el, idx) => {
              const isSelected = selectedId === el.id;
              const info = typeLabels[el.element_type];
              return (
                <div
                  key={el.id}
                  onClick={() => onSelect(el.id)}
                  className={`flex items-center gap-2 px-3 py-2 border-b border-gray-50 cursor-pointer transition-colors group ${
                    isSelected ? 'bg-blue-50 border-blue-100' : 'hover:bg-gray-50'
                  }`}
                >
                  <span className="text-xs text-gray-300 font-mono w-4">{idx + 1}</span>
                  <span className="text-sm">{info?.icon || '🔹'}</span>
                  <span className="text-xs font-medium flex-1 truncate">{el.label || info?.label || el.element_type}</span>

                  <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onClick={(e) => { e.stopPropagation(); onReorder(el.id, 'up'); }} disabled={idx === 0} className="p-1 text-gray-400 hover:text-blue-600 disabled:opacity-20">
                      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" /></svg>
                    </button>
                    <button onClick={(e) => { e.stopPropagation(); onReorder(el.id, 'down'); }} disabled={idx === elements.length - 1} className="p-1 text-gray-400 hover:text-blue-600 disabled:opacity-20">
                      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <button onClick={(e) => { e.stopPropagation(); onToggleVisibility(el.id); }} className="p-1 text-gray-400 hover:text-amber-600" title={el.is_visible !== false ? 'إخفاء' : 'إظهار'}>
                      {el.is_visible !== false ? '👁️' : '🚫'}
                    </button>
                    <button onClick={(e) => { e.stopPropagation(); onDuplicate(el.id); }} className="p-1 text-gray-400 hover:text-blue-600" title="تكرار">
                      📑
                    </button>
                    <button onClick={(e) => { e.stopPropagation(); onDelete(el.id); }} className="p-1 text-gray-400 hover:text-red-600" title="حذف">
                      🗑️
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
