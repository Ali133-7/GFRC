import type { TemplateElement } from '@/types/template';

interface Props {
  elements: TemplateElement[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onToggleVisibility: (id: string) => void;
  onDelete: (id: string) => void;
  onDuplicate: (el: TemplateElement) => void;
  onReorder: (id: string, direction: 'up' | 'down') => void;
}

const typeLabels: Record<string, string> = {
  field: '📋 حقل',
  text: '📝 نص',
  divider: '➖ فاصل',
  qr: '🔳 QR',
  signature: '✍️ توقيع',
  total: '💰 مجموع',
  image: '🖼️ صورة',
  spacer: '⬜ مسافة',
};

export default function LayersPanel({ elements, selectedId, onSelect, onToggleVisibility, onDelete, onDuplicate, onReorder }: Props) {
  return (
    <div className="h-full flex flex-col bg-white border-l border-gray-200">
      <div className="px-4 py-3 border-b border-gray-100">
        <h3 className="font-bold text-sm text-gray-800">الطبقات</h3>
        <p className="text-xs text-gray-500">{elements.length} عنصر</p>
      </div>
      <div className="flex-1 overflow-y-auto">
        {elements.length === 0 && (
          <div className="p-6 text-center text-gray-400 text-sm">
            <p>لا توجد عناصر</p>
            <p className="text-xs mt-1">اضغط "عنصر جديد" لإضافة عنصر</p>
          </div>
        )}
        {elements.map((el, idx) => {
          const isSelected = selectedId === el.id;
          return (
            <div
              key={el.id}
              onClick={() => onSelect(el.id)}
              className={`flex items-center gap-2 px-3 py-2.5 border-b border-gray-50 cursor-pointer transition-colors group ${
                isSelected ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50'
              }`}
            >
              <span className="text-xs text-gray-400 font-mono w-5 text-center">{idx + 1}</span>
              <span className="text-sm">{typeLabels[el.element_type] || el.element_type}</span>
              <span className="text-xs text-gray-500 truncate flex-1 mr-1">{el.label || ''}</span>

              <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                  onClick={(e) => { e.stopPropagation(); onReorder(el.id, 'up'); }}
                  disabled={idx === 0}
                  className="p-1 text-gray-400 hover:text-blue-600 disabled:opacity-20 rounded"
                  title="للأعلى"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" /></svg>
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); onReorder(el.id, 'down'); }}
                  disabled={idx === elements.length - 1}
                  className="p-1 text-gray-400 hover:text-blue-600 disabled:opacity-20 rounded"
                  title="للأسفل"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); onToggleVisibility(el.id); }}
                  className="p-1 text-gray-400 hover:text-amber-600 rounded"
                  title={el.is_visible !== false ? 'إخفاء' : 'إظهار'}
                >
                  {el.is_visible !== false ? '👁️' : '🚫'}
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); onDuplicate(el); }}
                  className="p-1 text-gray-400 hover:text-blue-600 rounded"
                  title="تكرار"
                >
                  📑
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); onDelete(el.id); }}
                  className="p-1 text-gray-400 hover:text-red-600 rounded"
                  title="حذف"
                >
                  🗑️
                </button>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
