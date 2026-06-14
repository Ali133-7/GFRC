import { X } from 'lucide-react';
import { WIDGET_LIBRARY } from './widgetDefaults';
import type { WidgetType } from './types';

interface WidgetLibraryPanelProps {
  open: boolean;
  onClose: () => void;
  onAdd: (type: WidgetType) => void;
}

export default function WidgetLibraryPanel({ open, onClose, onAdd }: WidgetLibraryPanelProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl" dir="rtl">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-bold text-gray-900">مكتبة الودجتات</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded p-1 text-gray-500 hover:bg-gray-100"
          >
            <X size={20} />
          </button>
        </div>

        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
          {WIDGET_LIBRARY.map((item) => {
            const Icon = item.icon;
            return (
              <button
                key={item.type}
                type="button"
                onClick={() => {
                  onAdd(item.type);
                  onClose();
                }}
                className="flex flex-col items-center gap-2 rounded-lg border border-gray-200 bg-white p-4 text-center transition-colors hover:border-blue-500 hover:bg-blue-50"
              >
                <Icon className="h-8 w-8 text-blue-600" />
                <span className="text-sm font-medium text-gray-800">{item.title}</span>
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}
