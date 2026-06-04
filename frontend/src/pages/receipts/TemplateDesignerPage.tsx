import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { usePermissions } from '@/hooks/usePermissions';
import { useAuthStore } from '@/stores/authStore';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import apiClient from '@/services/apiClient';
import { useCanvasDesigner } from '@/hooks/useCanvasDesigner';
import CanvasDesignerPro from '@/components/designer/CanvasDesignerPro';
import ToolboxPanel from '@/components/designer/ToolboxPanel';
import PropertiesPanel from '@/components/designer/PropertiesPanel';
import PreviewBeforeSaveModal from '@/components/designer/PreviewBeforeSaveModal';
import type { ReceiptTemplate } from '@/types/template';

const mockDemoTemplate: ReceiptTemplate = {
  id: 'demo-template-id',
  register_id: 'demo-reg',
  name: 'قالب تجريبي',
  description: '',
  is_active: true,
  is_default: true,
  layout_type: 'portrait',
  page_width: 210,
  page_height: 297,
  background_color: '#ffffff',
  created_by: 'demo-user',
  created_at: new Date().toISOString(),
  updated_at: new Date().toISOString(),
  elements: [
    {
      id: 'el-logo', template_id: 'demo-template-id', element_type: 'image', label: 'الشعار',
      x: 350, y: 20, width: 100, height: 80, sort_order: 0, is_visible: true,
      style: { id: 'style-logo', element_id: 'el-logo', font_family: "'Noto Sans Arabic', Arial, sans-serif", font_size: 13, font_weight: 'normal', font_color: '#1e293b', border_width: 0, text_align: 'center', padding: { top: 0, right: 0, bottom: 0, left: 0 }, opacity: 1, display: 'block', line_height: 1 }
    },
    {
      id: 'el-title', template_id: 'demo-template-id', element_type: 'text', label: 'جمهورية العراق - نظام الإيصالات المالية',
      x: 100, y: 110, width: 600, height: 35, sort_order: 10, is_visible: true,
      style: { id: 'style-title', element_id: 'el-title', font_family: "'Noto Sans Arabic', Arial, sans-serif", font_size: 18, font_weight: 'bold', font_color: '#1e293b', border_width: 0, text_align: 'center', padding: { top: 0, right: 0, bottom: 0, left: 0 }, opacity: 1, display: 'block', line_height: 1 }
    },
    {
      id: 'el-divider-1', template_id: 'demo-template-id', element_type: 'divider', label: 'فاصل',
      x: 40, y: 150, width: 720, height: 10, sort_order: 20, is_visible: true,
    },
    {
      id: 'el-num', template_id: 'demo-template-id', element_type: 'text', label: 'رقم الإيصال: GEN-2026-000001',
      x: 480, y: 170, width: 280, height: 30, sort_order: 30, is_visible: true,
      style: { id: 'style-num', element_id: 'el-num', font_family: "'Noto Sans Arabic', Arial, sans-serif", font_size: 14, font_weight: 'bold', font_color: '#1e293b', border_width: 0, text_align: 'right', padding: { top: 0, right: 0, bottom: 0, left: 0 }, opacity: 1, display: 'block', line_height: 1 }
    },
    {
      id: 'el-date', template_id: 'demo-template-id', element_type: 'text', label: 'التاريخ: 2026-05-29',
      x: 40, y: 170, width: 250, height: 30, sort_order: 40, is_visible: true,
      style: { id: 'style-date', element_id: 'el-date', font_family: "'Noto Sans Arabic', Arial, sans-serif", font_size: 12, font_weight: 'normal', font_color: '#1e293b', border_width: 0, text_align: 'left', padding: { top: 0, right: 0, bottom: 0, left: 0 }, opacity: 1, display: 'block', line_height: 1 }
    },
    {
      id: 'el-total', template_id: 'demo-template-id', element_type: 'total', label: 'المجموع',
      x: 400, y: 470, width: 360, height: 60, sort_order: 100, is_visible: true,
      style: { id: 'style-total', element_id: 'el-total', font_family: "'Noto Sans Arabic', Arial, sans-serif", font_size: 14, font_weight: 'bold', font_color: '#1e293b', border_width: 0, text_align: 'right', padding: { top: 0, right: 0, bottom: 0, left: 0 }, opacity: 1, display: 'block', line_height: 1 }
    },
    {
      id: 'el-qr', template_id: 'demo-template-id', element_type: 'qr', label: 'رمز التحقق',
      x: 40, y: 470, width: 100, height: 100, sort_order: 110, is_visible: true,
    },
    {
      id: 'el-sig', template_id: 'demo-template-id', element_type: 'signature', label: 'أمين الصندوق',
      x: 450, y: 560, width: 250, height: 60, sort_order: 120, is_visible: true,
      style: { id: 'style-sig', element_id: 'el-sig', font_family: "'Noto Sans Arabic', Arial, sans-serif", font_size: 13, font_weight: 'bold', font_color: '#1e293b', border_width: 0, text_align: 'center', padding: { top: 0, right: 0, bottom: 0, left: 0 }, opacity: 1, display: 'block', line_height: 1 }
    },
  ],
};

export default function TemplateDesignerPage() {
  usePermissions();
  const { user } = useAuthStore();
  const navigate = useNavigate();
  const { registerId } = useParams<{ registerId: string }>();
  const [baseTemplate, setBaseTemplate] = useState<ReceiptTemplate | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [snapToGrid, setSnapToGrid] = useState(true);
  const [zoom, setZoom] = useState(1);
  const [showPreviewModal, setShowPreviewModal] = useState(false);
  const [showImportModal, setShowImportModal] = useState(false);
  const importInputRef = useRef<HTMLInputElement>(null);

  const {
    template,
    selectedId,
    selectElement,
    updateElement,
    addElement,
    deleteElement,
    duplicateElement,
    updateStyle,
    updateMeta,
    undo,
    redo,
    canUndo,
    canRedo,
    isSaving,
    syncElementPosition,
    syncElementSize,
    syncElementStyle,
    syncDeleteElement,
    syncClearElements,
    saveTemplateMeta,
    exportTemplate,
    importTemplate,
  } = useCanvasDesigner(baseTemplate);

  // Load template
  useEffect(() => {
    if (!user) return;
    const load = async () => {
      if (!registerId) { setError('لم يتم تحديد السجل'); setLoading(false); return; }
      if (registerId === 'demo-reg') { setBaseTemplate(mockDemoTemplate); setLoading(false); return; }
      try {
        const res = await apiClient.get(`/registers/${registerId}/template`);
        setBaseTemplate(res.data.data);
      } catch (err: any) {
        setError(err.response?.data?.message || err.message || 'فشل تحميل القالب');
      } finally { setLoading(false); }
    };
    load();
  }, [registerId, user]);

  const handleSave = useCallback(async () => {
    if (!template) return;
    if (template.id === 'demo-template-id') { alert('✅ تم حفظ القالب التجريبي'); return; }
    await saveTemplateMeta(template.id, {
      name: template.name,
      page_width: template.page_width,
      page_height: template.page_height,
      background_color: template.background_color,
      layout_type: template.layout_type,
    });
    alert('✅ تم حفظ التصميم بنجاح');
  }, [template, saveTemplateMeta]);

  const handleImportFile = (file: File) => {
    const reader = new FileReader();
    reader.onload = () => {
      try {
        const json = JSON.parse(reader.result as string);
        if (importTemplate(json)) { alert('✅ تم استيراد القالب'); setShowImportModal(false); }
        else alert('❌ ملف غير صالح');
      } catch { alert('❌ ملف JSON غير صالح'); }
    };
    reader.readAsText(file);
  };

  const handleClear = async () => {
    if (!confirm('هل أنت متأكد من مسح جميع العناصر والبدء من الصفر؟')) return;
    if (template) { await syncClearElements(template.id); updateMeta({ elements: [] }); }
  };

  const selectedEl = template?.elements.find((e) => e.id === selectedId) || null;

  if (!user) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-gray-500">
        <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-4" />
        <p className="text-xl font-bold">جاري تحميل البيانات...</p>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex h-96 items-center justify-center text-gray-500">
        <span className="text-xl font-bold">جاري تحميل القالب...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-red-500">
        <p className="text-xl font-bold">خطأ</p>
        <p className="text-sm">{error}</p>
        <Button onClick={() => navigate('/registers')} variant="secondary" className="mt-4">العودة إلى السجلات</Button>
      </div>
    );
  }

  if (!template) {
    return (
      <div className="flex h-96 items-center justify-center text-gray-500">
        <span className="text-xl font-bold">جارٍ تحميل المصمم...</span>
      </div>
    );
  }

  return (
    <div className="h-[calc(100vh-64px)] flex flex-col">
      <PageHeader title={`مصمم الوصولات — ${template.name}`} />

      {/* Toolbar */}
      <div className="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-2 flex-wrap shrink-0 no-print">
        <Button size="sm" variant="primary" onClick={() => setShowPreviewModal(true)}>👁️ معاينة</Button>
        <Button size="sm" onClick={handleSave} disabled={isSaving}>💾 {isSaving ? 'جاري...' : 'حفظ'}</Button>
        <div className="w-px h-6 bg-gray-200 mx-1" />
        <Button size="sm" variant="secondary" onClick={undo} disabled={!canUndo} title="تراجع (Ctrl+Z)">↶</Button>
        <Button size="sm" variant="secondary" onClick={redo} disabled={!canRedo} title="إعادة (Ctrl+Y)">↷</Button>
        <div className="w-px h-6 bg-gray-200 mx-1" />
        <Button size="sm" variant="secondary" onClick={() => setSnapToGrid((v) => !v)} className={snapToGrid ? 'bg-blue-50 text-blue-700 border-blue-200' : ''}>🧲 شبكة</Button>
        <Button size="sm" variant="secondary" onClick={exportTemplate}>📤 تصدير</Button>
        <Button size="sm" variant="secondary" onClick={() => setShowImportModal(true)}>📥 استيراد</Button>
        <Button size="sm" variant="secondary" onClick={handleClear} className="text-red-600 hover:text-red-700">🗑️ مسح</Button>
        <div className="flex-1" />
        <div className="flex items-center gap-2">
          <span className="text-xs text-gray-500">تكبير:</span>
          <input type="range" min={25} max={200} step={5} value={zoom * 100} onChange={(e) => setZoom(parseInt(e.target.value) / 100)} className="w-24" />
          <span className="text-xs font-mono text-gray-600 w-8">{Math.round(zoom * 100)}%</span>
        </div>
        <Button size="sm" variant="ghost" onClick={() => navigate('/registers')}>← رجوع</Button>
      </div>

      {/* Workspace */}
      <div className="flex-1 flex overflow-hidden">
        <ToolboxPanel
          elements={template.elements}
          selectedId={selectedId}
          onSelect={selectElement}
          onToggleVisibility={(id) => {
            const el = template.elements.find((e) => e.id === id);
            if (el) updateElement(id, { is_visible: el.is_visible === false ? true : false });
          }}
          onDelete={(id) => { if (template) syncDeleteElement(template.id, id); deleteElement(id); }}
          onDuplicate={duplicateElement}
          onReorder={(id, dir) => {
            const el = template.elements.find((e) => e.id === id);
            if (!el) return;
            const delta = dir === 'up' ? -1 : 1;
            updateElement(id, { sort_order: (el.sort_order || 0) + delta * 10 });
          }}
          onAdd={addElement}
          onClear={handleClear}
        />

        <CanvasDesignerPro
          template={template}
          selectedId={selectedId}
          onSelect={selectElement}
          onUpdate={updateElement}
          onSyncPosition={(id, x, y) => { if (template) syncElementPosition(template.id, id, x, y); }}
          onSyncSize={(id, w, h) => { if (template) syncElementSize(template.id, id, w, h); }}
          snapToGrid={snapToGrid}
          zoom={zoom}
        />

        <PropertiesPanel
          element={selectedEl}
          template={template}
          onUpdateElement={updateElement}
          onUpdateStyle={updateStyle}
          onUpdateMeta={updateMeta}
          onSyncStyle={(elementId, data) => { if (template) syncElementStyle(template.id, elementId, data); }}
        />
      </div>

      <PreviewBeforeSaveModal
        template={template}
        open={showPreviewModal}
        onClose={() => setShowPreviewModal(false)}
        onConfirmSave={() => { setShowPreviewModal(false); handleSave(); }}
      />

      {showImportModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 no-print">
          <div className="bg-white rounded-2xl shadow-2xl p-6 w-80">
            <h3 className="font-bold text-lg mb-3">استيراد قالب</h3>
            <input ref={importInputRef} type="file" accept=".json,application/json" className="w-full mb-4" onChange={(e) => { const f = e.target.files?.[0]; if (f) handleImportFile(f); }} />
            <button onClick={() => setShowImportModal(false)} className="w-full text-sm py-2 rounded-lg border hover:bg-gray-50">إلغاء</button>
          </div>
        </div>
      )}
    </div>
  );
}
