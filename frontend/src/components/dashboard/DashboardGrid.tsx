import { useMemo, useState } from 'react';
import { GridLayout, useContainerWidth, type Layout } from 'react-grid-layout';
import { Plus, Save, Settings, X } from 'lucide-react';
import { useDashboardLayout } from './hooks/useDashboardLayout';
import { useSaveLayout } from './hooks/useSaveLayout';
import WidgetLibraryPanel from './WidgetLibraryPanel';
import WidgetEditorModal from './WidgetEditorModal';
import WidgetWrapper from './WidgetWrapper';
import WidgetRenderer from './WidgetRenderer';
import { getDefaultWidget } from './widgetDefaults';
import type { DashboardWidgetItem, WidgetType } from './types';

import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';

export default function DashboardGrid() {
  const { widgets, setWidgets, isLoading, error, isError } = useDashboardLayout();
  const saveLayout = useSaveLayout();
  const { width, containerRef, mounted } = useContainerWidth();
  const [isEdit, setIsEdit] = useState(false);
  const [showLibrary, setShowLibrary] = useState(false);
  const [editingWidget, setEditingWidget] = useState<DashboardWidgetItem | null>(null);

  const layout = useMemo(
    () =>
      widgets.map((w) => ({
        i: w.id,
        x: w.position_x,
        y: w.position_y,
        w: w.width,
        h: w.height,
      })),
    [widgets]
  );

  const handleLayoutChange = (nextLayout: Layout) => {
    setWidgets((prev) => {
      const map = new Map(nextLayout.map((l) => [l.i, l]));
      return prev.map((w) => {
        const l = map.get(w.id);
        if (!l) return w;
        return { ...w, position_x: l.x, position_y: l.y, width: l.w, height: l.h };
      });
    });
  };

  const handleAdd = (type: WidgetType) => {
    const maxY = widgets.reduce((max, w) => Math.max(max, w.position_y + w.height), 0);
    const newWidget = getDefaultWidget(type, { position_x: 0, position_y: maxY });
    setWidgets((prev) => [...prev, newWidget]);
  };

  const handleRemove = (widgetId: string) => {
    setWidgets((prev) => prev.filter((w) => w.id !== widgetId));
  };

  const handleEditSave = (updated: DashboardWidgetItem) => {
    setWidgets((prev) => prev.map((w) => (w.id === updated.id ? updated : w)));
  };

  const handleSaveLayout = () => {
    saveLayout.mutate(widgets);
  };

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-50">
        <div className="h-10 w-10 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-50" dir="rtl">
        <div className="rounded-lg bg-white p-6 text-center shadow-sm">
          <h2 className="text-lg font-bold text-red-600">حدث خطأ</h2>
          <p className="mt-2 text-gray-600">{error instanceof Error ? error.message : 'تعذر تحميل تخطيط الداشبورد'}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50" dir="rtl">
      <header className="border-b border-gray-200 bg-white px-6 py-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">لوحة التحكم</h1>
            <p className="mt-1 text-sm text-gray-500">تخصيص وإدارة الودجتات</p>
          </div>

          <div className="flex items-center gap-2">
            {isEdit ? (
              <>
                <button
                  type="button"
                  onClick={() => setShowLibrary(true)}
                  className="inline-flex items-center gap-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                  <Plus size={16} />
                  إضافة ودجت
                </button>
                <button
                  type="button"
                  onClick={handleSaveLayout}
                  disabled={saveLayout.isPending}
                  className="inline-flex items-center gap-1 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                >
                  <Save size={16} />
                  {saveLayout.isPending ? 'جاري الحفظ...' : 'حفظ التخطيط'}
                </button>
                <button
                  type="button"
                  onClick={() => setIsEdit(false)}
                  className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  <X size={16} />
                  إلغاء
                </button>
              </>
            ) : (
              <button
                type="button"
                onClick={() => setIsEdit(true)}
                className="inline-flex items-center gap-1 rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
              >
                <Settings size={16} />
                تعديل
              </button>
            )}
          </div>
        </div>
      </header>

      <main className="p-6">
        <div className="h-full w-full" ref={containerRef as React.RefObject<HTMLDivElement>}>
          {widgets.length === 0 ? (
            <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
              <h3 className="text-lg font-medium text-gray-900">الداشبورد فارغة</h3>
              <p className="mt-2 text-gray-500">اضغط "تعديل" ثم "إضافة ودجت" لبدء التخصيص</p>
            </div>
          ) : (
            mounted && (
              <GridLayout
                className="layout"
                layout={layout}
                width={width}
                gridConfig={{ cols: 12, rowHeight: 80, margin: [16, 16] }}
                dragConfig={{ enabled: isEdit, handle: '.react-grid-dragHandle', bounded: false }}
                resizeConfig={{ enabled: isEdit, handles: ['se'] }}
                onLayoutChange={handleLayoutChange}
              >
                {widgets.map((widget) => (
                  <div key={widget.id}>
                    <WidgetWrapper
                      widget={widget}
                      isEdit={isEdit}
                      onEdit={setEditingWidget}
                      onRemove={handleRemove}
                    >
                      <WidgetRenderer widget={widget} />
                    </WidgetWrapper>
                  </div>
                ))}
              </GridLayout>
            )
          )}
        </div>
      </main>

      <WidgetLibraryPanel
        open={showLibrary}
        onClose={() => setShowLibrary(false)}
        onAdd={handleAdd}
      />

      <WidgetEditorModal
        widget={editingWidget}
        open={!!editingWidget}
        onClose={() => setEditingWidget(null)}
        onSave={handleEditSave}
      />
    </div>
  );
}
