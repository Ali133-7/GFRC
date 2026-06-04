import { useState, useCallback, useRef, useEffect } from 'react';
import apiClient from '@/services/apiClient';
import { logError } from '@/utils/errorHandler';
import type { ReceiptTemplate, TemplateElement, TemplateStyle } from '@/types/template';

interface HistoryEntry {
  template: ReceiptTemplate;
  selectedId: string | null;
  action: string;
}

const createDefaultStyle = (elementId: string): TemplateStyle => ({
  id: `style-${elementId}`,
  element_id: elementId,
  font_family: "'Noto Sans Arabic', Arial, sans-serif",
  font_size: 13,
  font_weight: 'normal',
  font_color: '#1f293b',
  border_width: 0,
  border_color: '#cbd5e1',
  text_align: 'right',
  padding: { top: 4, right: 8, bottom: 4, left: 8 },
  opacity: 1,
  display: 'block',
  line_height: 1.4,
});

export function useCanvasDesigner(initialTemplate: ReceiptTemplate | null) {
  const [template, setTemplate] = useState<ReceiptTemplate | null>(initialTemplate);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [historyIndex, setHistoryIndex] = useState(-1);
  const [isSaving, setIsSaving] = useState(false);
  const isUndoing = useRef(false);
  const prevInitial = useRef<string | null>(null);

  // Sync when initial template loads from API
  useEffect(() => {
    if (initialTemplate && initialTemplate.id !== prevInitial.current) {
      prevInitial.current = initialTemplate.id;
      setTemplate(initialTemplate);
      setSelectedId(null);
      const entry: HistoryEntry = {
        template: JSON.parse(JSON.stringify(initialTemplate)),
        selectedId: null,
        action: 'تحميل القالب',
      };
      setHistory([entry]);
      setHistoryIndex(0);
    }
  }, [initialTemplate]);

  const pushHistory = useCallback((nextTemplate: ReceiptTemplate, action: string, selId: string | null = selectedId) => {
    if (isUndoing.current) return;
    setHistory((prev) => {
      const trimmed = prev.slice(0, historyIndex + 1);
      return [...trimmed, { template: JSON.parse(JSON.stringify(nextTemplate)), selectedId: selId, action }];
    });
    setHistoryIndex((prev) => prev + 1);
  }, [historyIndex, selectedId]);

  const commit = useCallback((action: string, updater: (prev: ReceiptTemplate) => ReceiptTemplate, selId: string | null = selectedId) => {
    setTemplate((prev) => {
      if (!prev) return prev;
      const next = updater(prev);
      pushHistory(next, action, selId);
      return next;
    });
  }, [pushHistory]);

  // Element CRUD
  const selectElement = useCallback((id: string | null) => setSelectedId(id), []);

  const updateElement = useCallback((elementId: string, updates: Partial<TemplateElement>, skipHistory = false) => {
    setTemplate((prev) => {
      if (!prev) return prev;
      const next = {
        ...prev,
        elements: prev.elements.map((el) => (el.id === elementId ? { ...el, ...updates } : el)),
      };
      if (!skipHistory) pushHistory(next, 'تعديل عنصر');
      return next;
    });
  }, [pushHistory]);

  const addElement = useCallback((type: TemplateElement['element_type'], label?: string, overrides?: Partial<TemplateElement>) => {
    const id = `el-${Date.now()}-${Math.random().toString(36).slice(2, 5)}`;
    const newEl: TemplateElement = {
      id,
      template_id: template?.id || '',
      element_type: type,
      label: label || (type === 'text' ? 'نص جديد' : type === 'field' ? 'حقل جديد' : type === 'image' ? 'شعار' : type === 'divider' ? 'فاصل' : type === 'qr' ? 'رمز QR' : type === 'signature' ? 'توقيع' : type === 'total' ? 'المجموع' : 'عنصر'),
      x: 40,
      y: 40,
      width: type === 'divider' ? 300 : type === 'qr' || type === 'image' ? 100 : type === 'total' ? 250 : 200,
      height: type === 'divider' ? 10 : type === 'qr' || type === 'image' ? 100 : type === 'total' ? 50 : 35,
      sort_order: (template?.elements.length || 0) * 10,
      is_visible: true,
      style: createDefaultStyle(id),
      ...overrides,
    };
    commit('إضافة عنصر', (prev) => ({ ...prev, elements: [...prev.elements, newEl] }), id);
  }, [template?.id, template?.elements.length, commit]);

  const deleteElement = useCallback((elementId: string) => {
    commit('حذف عنصر', (prev) => ({ ...prev, elements: prev.elements.filter((e) => e.id !== elementId) }), null);
  }, [commit]);

  const duplicateElement = useCallback((elementId: string) => {
    const el = template?.elements.find((e) => e.id === elementId);
    if (!el || !template) return;
    const newId = `el-${Date.now()}-${Math.random().toString(36).slice(2, 5)}`;
    const clone: TemplateElement = {
      ...el,
      id: newId,
      label: (el.label || el.element_type) + ' (نسخة)',
      x: el.x + 20,
      y: el.y + 20,
      sort_order: el.sort_order + 1,
      style: el.style ? { ...el.style, id: `style-${newId}`, element_id: newId } : createDefaultStyle(newId),
    };
    commit('تكرار عنصر', (prev) => ({ ...prev, elements: [...prev.elements, clone] }), newId);
  }, [template, commit]);

  const bringForward = useCallback((elementId: string) => {
    const el = template?.elements.find((e) => e.id === elementId);
    if (!el) return;
    updateElement(elementId, { sort_order: (el.sort_order || 0) + 1 });
  }, [template, updateElement]);

  const sendBackward = useCallback((elementId: string) => {
    const el = template?.elements.find((e) => e.id === elementId);
    if (!el) return;
    updateElement(elementId, { sort_order: Math.max(0, (el.sort_order || 0) - 1) });
  }, [template, updateElement]);

  // Style
  const updateStyle = useCallback((elementId: string, styleUpdates: Partial<TemplateStyle>) => {
    setTemplate((prev) => {
      if (!prev) return prev;
      const next = {
        ...prev,
        elements: prev.elements.map((el) =>
          el.id === elementId
            ? { ...el, style: { ...(el.style || createDefaultStyle(el.id)), ...styleUpdates } as TemplateStyle }
            : el
        ),
      };
      return next;
    });
  }, []);

  // Template meta
  const updateMeta = useCallback((updates: Partial<ReceiptTemplate>) => {
    commit('تعديل القالب', (prev) => ({ ...prev, ...updates }));
  }, [commit]);

  // Undo / Redo
  const undo = useCallback(() => {
    if (historyIndex <= 0) return;
    isUndoing.current = true;
    const idx = historyIndex - 1;
    const entry = history[idx];
    setTemplate(entry.template);
    setSelectedId(entry.selectedId);
    setHistoryIndex(idx);
    setTimeout(() => { isUndoing.current = false; }, 0);
  }, [history, historyIndex]);

  const redo = useCallback(() => {
    if (historyIndex >= history.length - 1) return;
    isUndoing.current = true;
    const idx = historyIndex + 1;
    const entry = history[idx];
    setTemplate(entry.template);
    setSelectedId(entry.selectedId);
    setHistoryIndex(idx);
    setTimeout(() => { isUndoing.current = false; }, 0);
  }, [history, historyIndex]);

  const canUndo = historyIndex > 0;
  const canRedo = historyIndex < history.length - 1;

  // Keyboard shortcuts
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLSelectElement) return;
      if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
        e.preventDefault();
        if (e.shiftKey) redo(); else undo();
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
        e.preventDefault();
        redo();
      }
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (selectedId) deleteElement(selectedId);
      }
      if (e.key === 'Escape') {
        setSelectedId(null);
      }
      // Nudge with arrow keys
      if (selectedId && template) {
        const el = template.elements.find((x) => x.id === selectedId);
        if (!el) return;
        const step = e.shiftKey ? 10 : 1;
        if (e.key === 'ArrowRight') { e.preventDefault(); updateElement(selectedId, { x: el.x + step }, true); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); updateElement(selectedId, { x: Math.max(0, el.x - step) }, true); }
        if (e.key === 'ArrowDown') { e.preventDefault(); updateElement(selectedId, { y: el.y + step }, true); }
        if (e.key === 'ArrowUp') { e.preventDefault(); updateElement(selectedId, { y: Math.max(0, el.y - step) }, true); }
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [selectedId, template, undo, redo, deleteElement, updateElement]);

  // Server sync helpers
  const syncElementPosition = useCallback(async (templateId: string, elementId: string, x: number, y: number) => {
    if (templateId === 'demo-template-id') return;
    try { await apiClient.put(`/templates/${templateId}/elements/${elementId}`, { x, y }); } catch (err) { console.error(err); }
  }, []);

  const syncElementSize = useCallback(async (templateId: string, elementId: string, width: number, height: number) => {
    if (templateId === 'demo-template-id') return;
    try { await apiClient.put(`/templates/${templateId}/elements/${elementId}`, { width, height }); } catch (err) { console.error(err); }
  }, []);

  const syncElementStyle = useCallback(async (templateId: string, elementId: string, styleData: any) => {
    if (templateId === 'demo-template-id') return;
    try { await apiClient.put(`/elements/${elementId}/styles`, styleData); } catch (err) { console.error(err); }
  }, []);

  const syncDeleteElement = useCallback(async (templateId: string, elementId: string) => {
    if (templateId === 'demo-template-id') return;
    try { await apiClient.delete(`/templates/${templateId}/elements/${elementId}`); } catch (err) { logError(err, 'حذف العنصر'); }
  }, []);

  const syncClearElements = useCallback(async (templateId: string) => {
    if (templateId === 'demo-template-id') return;
    try { await apiClient.delete(`/templates/${templateId}/elements`); } catch (err) { logError(err, 'مسح العناصر'); }
  }, []);

  const saveTemplateMeta = useCallback(async (templateId: string, meta: Partial<ReceiptTemplate>) => {
    if (templateId === 'demo-template-id') return;
    setIsSaving(true);
    try {
      await apiClient.put(`/templates/${templateId}`, meta);
    } catch (err) {
      logError(err, 'حفظ القالب');
    } finally {
      setIsSaving(false);
    }
  }, []);

  // Export / Import
  const exportTemplate = useCallback(() => {
    if (!template) return;
    const data = { template: JSON.parse(JSON.stringify(template)), exportedAt: new Date().toISOString(), version: '2.0' };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `template_${template.name || 'design'}_${Date.now()}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }, [template]);

  const importTemplate = useCallback((json: any) => {
    if (!json?.template) return false;
    const imported = json.template as ReceiptTemplate;
    setTemplate(imported);
    setSelectedId(null);
    setHistory([{ template: JSON.parse(JSON.stringify(imported)), selectedId: null, action: 'استيراد قالب' }]);
    setHistoryIndex(0);
    return true;
  }, []);

  return {
    template,
    selectedId,
    selectElement,
    updateElement,
    addElement,
    deleteElement,
    duplicateElement,
    bringForward,
    sendBackward,
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
  };
}
