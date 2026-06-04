import { useState, useEffect } from 'react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
  DragStartEvent,
  DragOverlay,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

interface ElementDef {
  id: string;
  label: string;
  icon: string;
}

interface SortableElementItemProps {
  element: ElementDef;
  isActive: boolean;
  index: number;
  onToggle: (id: string) => void;
}

function SortableElementItem({
  element,
  isActive,
  onToggle,
}: SortableElementItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: element.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`
        flex items-center gap-3 p-3 rounded-lg border-2 transition-all
        cursor-grab active:cursor-grabbing group
        ${
          isActive
            ? 'bg-blue-50 border-blue-300 shadow-sm'
            : 'bg-gray-50 border-gray-200 opacity-60'
        }
        ${isDragging ? 'shadow-lg border-blue-500 scale-105' : 'hover:shadow-md'}
      `}
      {...attributes}
      {...listeners}
    >
      {/* Drag Handle */}
      <div
        className={`
          flex-shrink-0 flex items-center justify-center w-6 h-6 rounded
          transition-colors
          ${isActive ? 'text-blue-600 bg-blue-100' : 'text-gray-400 bg-gray-100'}
        `}
      >
        <svg
          className="w-4 h-4"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path d="M10 3a2 2 0 110 4 2 2 0 010-4zM10 9a2 2 0 110 4 2 2 0 010-4zM10 15a2 2 0 110 4 2 2 0 010-4z" />
        </svg>
      </div>

      {/* Icon */}
      <span className="text-2xl flex-shrink-0">{element.icon}</span>

      {/* Label */}
      <span className="text-sm font-medium flex-1">{element.label}</span>

      {/* Checkbox */}
      <input
        type="checkbox"
        checked={isActive}
        onChange={() => onToggle(element.id)}
        className="w-5 h-5 cursor-pointer rounded border-gray-300"
      />
    </div>
  );
}

interface ReceiptElementsReordererProps {
  elements: ElementDef[];
  order: string[];
  activeElements: Record<string, boolean>;
  onOrderChange: (newOrder: string[]) => void;
  onActiveChange: (newActive: Record<string, boolean>) => void;
  onTemplateSave?: (templateName: string) => void;
  templateKey?: string;
}

export function ReceiptElementsReorderer({
  elements,
  order,
  activeElements,
  onOrderChange,
  onActiveChange,
  onTemplateSave,
  templateKey,
}: ReceiptElementsReordererProps) {
  const [localOrder, setLocalOrder] = useState(order);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [customTemplateName, setCustomTemplateName] = useState('');
  const [showSaveModal, setShowSaveModal] = useState(false);

  useEffect(() => {
    setLocalOrder(order);
  }, [order]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragStart = (event: DragStartEvent) => {
    setActiveId(event.active.id as string);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    setActiveId(null);

    if (over && active.id !== over.id) {
      const oldIndex = localOrder.indexOf(active.id as string);
      const newIndex = localOrder.indexOf(over.id as string);

      if (oldIndex !== -1 && newIndex !== -1) {
        const newOrder = arrayMove(localOrder, oldIndex, newIndex);
        setLocalOrder(newOrder);
        onOrderChange(newOrder);
      }
    }
  };

  const toggleElement = (id: string) => {
    const newActive = {
      ...activeElements,
      [id]: !activeElements[id],
    };
    onActiveChange(newActive);
  };

  const resetOrder = () => {
    const defaultOrder = elements.map((e) => e.id);
    setLocalOrder(defaultOrder);
    onOrderChange(defaultOrder);
  };

  const handleSaveTemplate = () => {
    if (customTemplateName.trim()) {
      onTemplateSave?.(customTemplateName);
      setCustomTemplateName('');
      setShowSaveModal(false);
    }
  };

  const activeElement = elements.find((e) => e.id === activeId);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="border-b pb-4">
        <h3 className="text-lg font-bold mb-2">🔄 ترتيب عناصر الوصل</h3>
        <p className="text-sm text-gray-500">
          اسحب العناصر لإعادة ترتيبها، واضغط على الاختيار لإظهار/إخفاء
        </p>
      </div>

      {/* Drag and Drop Container */}
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        <div className="space-y-2">
          <SortableContext
            items={localOrder}
            strategy={verticalListSortingStrategy}
          >
            {localOrder.map((elementId, index) => {
              const element = elements.find((e) => e.id === elementId);
              if (!element) return null;

              return (
                <SortableElementItem
                  key={element.id}
                  element={element}
                  isActive={activeElements[element.id] ?? true}
                  index={index}
                  onToggle={toggleElement}
                />
              );
            })}
          </SortableContext>
        </div>

        {/* Drag Overlay */}
        <DragOverlay>
          {activeElement ? (
            <div className="flex items-center gap-3 p-3 rounded-lg bg-white shadow-lg border-2 border-blue-500 scale-105">
              <div className="flex items-center justify-center w-6 h-6 rounded bg-blue-100">
                <svg className="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 3a2 2 0 110 4 2 2 0 010-4zM10 9a2 2 0 110 4 2 2 0 010-4zM10 15a2 2 0 110 4 2 2 0 010-4z" />
                </svg>
              </div>
              <span className="text-2xl">{activeElement.icon}</span>
              <span className="text-sm font-medium">{activeElement.label}</span>
            </div>
          ) : null}
        </DragOverlay>
      </DndContext>

      {/* Action Buttons */}
      <div className="flex gap-2 pt-4 border-t">
        <Button
          size="sm"
          variant="secondary"
          onClick={resetOrder}
          className="flex-1"
        >
          ↺ إعادة تعيين
        </Button>
        <Button
          size="sm"
          onClick={() => setShowSaveModal(true)}
          className="flex-1"
        >
          💾 حفظ كقالب
        </Button>
      </div>

      {/* Save Template Modal */}
      {showSaveModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full">
            <h4 className="font-bold text-lg mb-4">💾 حفظ كقالب مخصص</h4>
            <p className="text-sm text-gray-600 mb-4">
              أدخل اسماً لهذا القالب ليسهل التعرف عليه لاحقاً
            </p>

            <Input
              label="اسم القالب"
              placeholder="مثال: قالبي المخصص"
              value={customTemplateName}
              onChange={(e) => setCustomTemplateName(e.target.value)}
              autoFocus
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleSaveTemplate();
              }}
            />

            {templateKey && (
              <p className="text-xs text-gray-500 mt-3">
                📌 القالب الحالي: <strong>{templateKey}</strong>
              </p>
            )}

            <div className="flex gap-2 mt-6">
              <Button
                variant="ghost"
                onClick={() => {
                  setShowSaveModal(false);
                  setCustomTemplateName('');
                }}
                className="flex-1"
              >
                إلغاء
              </Button>
              <Button
                onClick={handleSaveTemplate}
                disabled={!customTemplateName.trim()}
                className="flex-1"
              >
                حفظ
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Help Section */}
      <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <h4 className="font-medium text-blue-900 mb-2">💡 نصائح للاستخدام:</h4>
        <ul className="text-xs text-blue-800 space-y-1">
          <li>✓ اسحب العنصر من أيقونة النقاط (:::) لإعادة ترتيبه</li>
          <li>✓ اختر/أخفِ العناصر باستخدام الاختيار على اليمين</li>
          <li>✓ استخدم "إعادة تعيين" للعودة للترتيب الافتراضي</li>
          <li>✓ احفظ الترتيب المخصص كقالب جديد</li>
          <li>✓ المعاينة تتحدث مباشرة أثناء التعديل</li>
        </ul>
      </div>
    </div>
  );
}
