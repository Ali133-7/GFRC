import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { useTemplateActions } from '@/hooks/useTemplateDesigner';

interface DesignerToolbarProps {
  templateId: string;
  onAddField: () => void;
  onSave?: (templateData: any) => void;
  onClear?: () => void;
  snapToGrid: boolean;
  onToggleSnapToGrid: () => void;
}

export default function DesignerToolbar({
  templateId,
  onAddField,
  onSave,
  onClear,
  snapToGrid,
  onToggleSnapToGrid,
}: DesignerToolbarProps) {
  const { saveTemplate, undoAction, redoAction, canUndo, canRedo, isLoading } =
    useTemplateActions(templateId);
  const [showMore, setShowMore] = useState(false);

  const handleSave = async () => {
    const result = await saveTemplate();
    if (result && onSave) {
      onSave(result);
    }
  };

  return (
    <div className="bg-white rounded-lg shadow border border-gray-200 p-3">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="primary"
            onClick={handleSave}
            disabled={isLoading}
            className="flex items-center gap-1"
          >
            <span>💾</span>
            {isLoading ? 'جاري الحفظ...' : 'حفظ'}
          </Button>

          <Button
            size="sm"
            variant="secondary"
            onClick={onAddField}
            className="flex items-center gap-1"
          >
            <span>➕</span>
            عنصر جديد
          </Button>

          <Button
            size="sm"
            variant={snapToGrid ? 'primary' : 'secondary'}
            onClick={onToggleSnapToGrid}
            className={`flex items-center gap-1 transition-all ${
              snapToGrid 
                ? 'bg-blue-600 hover:bg-blue-700 text-white border-blue-600 shadow-sm' 
                : 'text-gray-600 hover:bg-gray-100'
            }`}
            title="تقييد محاذاة العناصر تلقائياً مع الشبكة (10 بكسل)"
          >
            <span className={snapToGrid ? 'animate-pulse' : ''}>🧲</span>
            {snapToGrid ? 'المحاذاة مفعلة' : 'محاذاة الشبكة'}
          </Button>

          <div className="flex gap-1 border-l pl-2 ml-2">
            <Button
              size="sm"
              variant="secondary"
              onClick={undoAction}
              disabled={!canUndo}
              title="تراجع (Ctrl+Z)"
            >
              ↶
            </Button>
            <Button
              size="sm"
              variant="secondary"
              onClick={redoAction}
              disabled={!canRedo}
              title="إعادة (Ctrl+Y)"
            >
              ↷
            </Button>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="secondary"
            onClick={() => setShowMore(!showMore)}
            className="flex items-center gap-1"
          >
            <span>⋮</span>
            المزيد
          </Button>

          <select
            defaultValue="100"
            onChange={(e) => {
              const scale = parseInt(e.target.value) / 100;
              const canvas = document.querySelector('[style*="width"]');
              if (canvas) {
                (canvas as HTMLElement).style.transform = `scale(${scale})`;
              }
            }}
            className="px-2 py-1 border border-gray-300 rounded text-sm"
          >
            <option value="50">50%</option>
            <option value="75">75%</option>
            <option value="100">100%</option>
            <option value="125">125%</option>
            <option value="150">150%</option>
          </select>
        </div>
      </div>

      {showMore && (
        <div className="mt-3 pt-3 border-t border-gray-200 grid grid-cols-2 gap-2 md:grid-cols-4">
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="استيراد قالب"
          >
            📥 استيراد
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="تصدير قالب"
          >
            📤 تصدير
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="معاينة"
          >
            👁️ معاينة
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="مساعدة"
          >
            ❓ مساعدة
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs font-bold text-red-600 hover:text-red-700"
            title="إعادة تعيين القالب والبدء من الصفر"
            onClick={onClear}
          >
            🔄 تصميم من الصفر
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="خيارات الصفحة"
          >
            📄 خيارات الصفحة
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="الخيارات المتقدمة"
          >
            ⚙️ خيارات متقدمة
          </Button>
          <Button
            size="sm"
            variant="secondary"
            className="text-xs"
            title="حول المصمم"
          >
            ℹ️ حول
          </Button>
        </div>
      )}
    </div>
  );
}
