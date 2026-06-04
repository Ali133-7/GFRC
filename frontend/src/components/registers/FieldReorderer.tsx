import { useState } from 'react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { RegisterField } from '@/types/register';
import { registersApi } from '@/api/registers';

interface SortableFieldItemProps {
  field: RegisterField;
}

function SortableFieldItem({ field }: SortableFieldItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging: isSortableDragging,
  } = useSortable({ id: field.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isSortableDragging ? 0.5 : 1,
  };

  const fieldTypeLabels: Record<string, string> = {
    text: 'نص',
    number: 'رقم',
    decimal: 'عشري',
    date: 'تاريخ',
    select: 'قائمة',
    textarea: 'نص طويل',
    hidden: 'مخفي',
    calculated: 'محسوب',
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`
        flex items-center gap-3 p-4 bg-white border rounded-lg
        transition-all duration-200 cursor-grab active:cursor-grabbing
        ${isSortableDragging ? 'shadow-lg bg-blue-50 border-blue-300' : 'border-gray-200 hover:border-gray-300 hover:shadow-md'}
      `}
      {...attributes}
      {...listeners}
    >
      {/* Drag Handle Icon */}
      <div className="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600">
        <svg
          className="w-5 h-5"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path d="M10 3a2 2 0 110 4 2 2 0 010-4zM10 9a2 2 0 110 4 2 2 0 010-4zM10 15a2 2 0 110 4 2 2 0 010-4z" />
        </svg>
      </div>

      {/* Field Info */}
      <div className="flex-1">
        <div className="flex items-center gap-2">
          <h4 className="font-medium text-gray-900">{field.label_ar || field.name}</h4>
          {!field.is_visible && (
            <span className="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded">
              مخفي
            </span>
          )}
          {field.is_required && (
            <span className="px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded">
              مطلوب
            </span>
          )}
          {field.is_financial && (
            <span className="px-2 py-1 text-xs font-medium text-green-600 bg-green-50 rounded">
              مالي
            </span>
          )}
        </div>
        <p className="text-sm text-gray-500 mt-1">
          {field.name} • {fieldTypeLabels[field.field_type] || field.field_type}
        </p>
      </div>

      {/* Sort Order Badge */}
      <div className="flex items-center justify-center w-8 h-8 font-bold text-white bg-blue-500 rounded-full">
        {field.sort_order}
      </div>
    </div>
  );
}

interface FieldReordererProps {
  registerId: string;
  fields: RegisterField[];
  onReorderSuccess?: (reorderedFields: RegisterField[]) => void;
}

export function FieldReorderer({
  registerId,
  fields,
  onReorderSuccess,
}: FieldReordererProps) {
  const [items, setItems] = useState<RegisterField[]>(fields);
  const [status, setStatus] = useState<{
    type: 'idle' | 'success' | 'error';
    message: string;
  }>({ type: 'idle', message: '' });

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

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      const oldIndex = items.findIndex((item) => item.id === active.id);
      const newIndex = items.findIndex((item) => item.id === over.id);

      const newItems = arrayMove(items, oldIndex, newIndex);
      setItems(newItems);

      // Save to backend
      await saveReorder(newItems);
    }
  };

  const saveReorder = async (reorderedFields: RegisterField[]) => {
    setStatus({ type: 'idle', message: '' });

    try {
      const payload = reorderedFields.map((field, index) => ({
        id: field.id,
        sort_order: index + 1,
      }));

      await registersApi.reorderFields(registerId, payload);

      // Update fields with new sort_order
      const updatedFields = reorderedFields.map((field, index) => ({
        ...field,
        sort_order: index + 1,
      }));

      setItems(updatedFields);
      setStatus({
        type: 'success',
        message: 'تم حفظ ترتيب الحقول بنجاح',
      });

      onReorderSuccess?.(updatedFields);

      // Clear success message after 3 seconds
      setTimeout(() => {
        setStatus({ type: 'idle', message: '' });
      }, 3000);
    } catch (error: any) {
      const message =
        error?.response?.data?.message ||
        'حدث خطأ أثناء حفظ ترتيب الحقول';

      setStatus({
        type: 'error',
        message,
      });

      // Revert to original items
      setItems(fields);

      // Clear error message after 5 seconds
      setTimeout(() => {
        setStatus({ type: 'idle', message: '' });
      }, 5000);
    }
  };

  if (!items || items.length === 0) {
    return (
      <div className="text-center py-12 text-gray-500">
        <p>لا توجد حقول لهذا السجل</p>
      </div>
    );
  }

  return (
    <div className="w-full">
      {/* Status Messages */}
      {status.type === 'success' && (
        <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-3">
          <svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clipRule="evenodd"
            />
          </svg>
          <span className="text-green-800">{status.message}</span>
        </div>
      )}

      {status.type === 'error' && (
        <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3">
          <svg className="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
              clipRule="evenodd"
            />
          </svg>
          <span className="text-red-800">{status.message}</span>
        </div>
      )}

      {/* Drag and Drop Container */}
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={handleDragEnd}
      >
        <div className="space-y-3">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-gray-900">
              ترتيب الحقول
            </h3>
            <p className="text-sm text-gray-500">
              اسحب الحقول لإعادة ترتيبها
            </p>
          </div>

          <SortableContext
            items={items.map((item) => item.id)}
            strategy={verticalListSortingStrategy}
          >
            {items.map((field) => (
              <SortableFieldItem
                key={field.id}
                field={field}
              />
            ))}
          </SortableContext>
        </div>
      </DndContext>

      {/* Help Text */}
      <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <h4 className="font-medium text-blue-900 mb-2">💡 نصائح:</h4>
        <ul className="text-sm text-blue-800 space-y-1">
          <li>• اسحب الحقل من أيقونة الـ drag handle (الخطوط الثلاث)</li>
          <li>• الترتيب المحفوظ سيكون الترتيب الافتراضي في جميع الوصولات الجديدة</li>
          <li>• الحقول المخفية لن تظهر في نماذج الوصولات</li>
          <li>• الحقول المالية تُضاف تلقائياً إلى الإجمالي</li>
        </ul>
      </div>
    </div>
  );
}
