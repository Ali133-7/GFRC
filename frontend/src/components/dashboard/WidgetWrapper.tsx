import { GripVertical, Pencil, X } from 'lucide-react';
import type { DashboardWidgetItem } from './types';
import { getWidgetTitle } from './types';
import { getColorClass } from './widgetDefaults';

interface WidgetWrapperProps {
  widget: DashboardWidgetItem;
  isEdit: boolean;
  onEdit: (widget: DashboardWidgetItem) => void;
  onRemove: (widgetId: string) => void;
  children: React.ReactNode;
}

export default function WidgetWrapper({
  widget,
  isEdit,
  onEdit,
  onRemove,
  children,
}: WidgetWrapperProps) {
  const colorClass = getColorClass(widget.color_theme);

  return (
    <div className="flex h-full w-full flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <div className={`flex items-center justify-between border-b border-gray-100 px-3 py-2 ${colorClass} bg-opacity-10`}>
        <div className="flex items-center gap-2">
          {isEdit && (
            <span className="react-grid-dragHandle cursor-move text-gray-400 hover:text-gray-600">
              <GripVertical size={16} />
            </span>
          )}
          <span className="text-sm font-medium text-gray-800">{getWidgetTitle(widget)}</span>
        </div>

        {isEdit && (
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => onEdit(widget)}
              className="rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
              title="تعديل"
            >
              <Pencil size={14} />
            </button>
            <button
              type="button"
              onClick={() => onRemove(widget.id)}
              className="rounded p-1 text-red-500 hover:bg-red-50 hover:text-red-700"
              title="حذف"
            >
              <X size={14} />
            </button>
          </div>
        )}
      </div>

      <div className="relative flex-1 overflow-hidden">
        {children}
      </div>
    </div>
  );
}
