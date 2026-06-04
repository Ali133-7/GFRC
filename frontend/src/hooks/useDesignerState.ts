import { useState, useCallback, useRef, useEffect } from 'react';
import apiClient from '@/services/apiClient';
import { logError } from '@/utils/errorHandler';
import type { ReceiptTemplate, TemplateElement } from '@/types/template';

interface HistoryEntry {
  template: ReceiptTemplate;
  selectedElement: string | null;
  action: string;
}

export function useDesignerState(baseTemplate: ReceiptTemplate | null) {
  const [template, setTemplate] = useState<ReceiptTemplate | null>(baseTemplate);
  const [selectedElement, setSelectedElement] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [historyIndex, setHistoryIndex] = useState(-1);
  const isUndoingRef = useRef(false);
  const prevBaseRef = useRef<ReceiptTemplate | null>(null);

  // Sync with baseTemplate when it changes from outside (e.g. API load)
  useEffect(() => {
    if (baseTemplate && baseTemplate.id !== prevBaseRef.current?.id) {
      prevBaseRef.current = baseTemplate;
      setTemplate(baseTemplate);
      setHistory([{ template: JSON.parse(JSON.stringify(baseTemplate)), selectedElement: null, action: 'بدء' }]);
      setHistoryIndex(0);
      setSelectedElement(null);
    }
  }, [baseTemplate]);

  const pushHistory = useCallback((newTemplate: ReceiptTemplate, action: string) => {
    if (isUndoingRef.current) return;
    setHistory((prev) => {
      const trimmed = prev.slice(0, historyIndex + 1);
      return [...trimmed, { template: JSON.parse(JSON.stringify(newTemplate)), selectedElement, action }];
    });
    setHistoryIndex((prev) => prev + 1);
  }, [historyIndex, selectedElement]);

  const setTemplateWithHistory = useCallback((updater: (prev: ReceiptTemplate | null) => ReceiptTemplate | null, action: string) => {
    setTemplate((prev) => {
      const next = updater(prev);
      if (next && prev && JSON.stringify(next) !== JSON.stringify(prev)) {
        pushHistory(next, action);
      }
      return next;
    });
  }, [pushHistory]);

  // Element mutations
  const updateElementLocal = useCallback((elementId: string, updates: Partial<TemplateElement>) => {
    setTemplate((prev) => {
      if (!prev) return prev;
      const next = {
        ...prev,
        elements: prev.elements.map((el) => (el.id === elementId ? { ...el, ...updates } : el)),
      };
      return next;
    });
  }, []);

  const saveElementToServer = useCallback(async (templateId: string, elementId: string, updates: Partial<TemplateElement>) => {
    if (templateId === 'demo-template-id') return;
    try {
      await apiClient.put(`/templates/${templateId}/elements/${elementId}`, updates);
    } catch (err) {
      console.error('خطأ في حفظ العنصر:', err);
    }
  }, []);

  const deleteElement = useCallback(async (templateId: string, elementId: string) => {
    setTemplateWithHistory((prev) => {
      if (!prev) return prev;
      return { ...prev, elements: prev.elements.filter((el) => el.id !== elementId) };
    }, 'حذف عنصر');
    setSelectedElement(null);
    if (templateId === 'demo-template-id') return;
    try {
      await apiClient.delete(`/templates/${templateId}/elements/${elementId}`);
    } catch (err) {
      logError(err, 'حذف العنصر');
    }
  }, [setTemplateWithHistory]);

  const duplicateElement = useCallback(async (templateId: string, element: TemplateElement) => {
    const newId = `el-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    const clone: TemplateElement = {
      ...element,
      id: newId,
      label: (element.label || element.element_type) + ' (نسخة)',
      x: element.x + 20,
      y: element.y + 20,
      sort_order: (element.sort_order ?? 0) + 1,
      style: element.style ? { ...element.style, id: `style-${newId}`, element_id: newId } : undefined,
    };
    setTemplateWithHistory((prev) => {
      if (!prev) return prev;
      return { ...prev, elements: [...prev.elements, clone] };
    }, 'تكرار عنصر');
    setSelectedElement(newId);
    if (templateId === 'demo-template-id') return;
    try {
      await apiClient.post(`/templates/${templateId}/elements`, {
        element_type: clone.element_type,
        label: clone.label,
        field_id: clone.field_id,
        x: clone.x,
        y: clone.y,
        width: clone.width,
        height: clone.height,
        is_visible: clone.is_visible,
        sort_order: clone.sort_order,
      });
    } catch (err) {
      logError(err, 'تكرار العنصر');
    }
  }, [setTemplateWithHistory]);

  const addElement = useCallback(async (templateId: string, elementData: Partial<TemplateElement>) => {
    const newId = `el-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    const newElement: TemplateElement = {
      id: newId,
      template_id: templateId,
      element_type: elementData.element_type || 'text',
      label: elementData.label || 'عنصر جديد',
      field_id: elementData.field_id,
      x: elementData.x ?? 40,
      y: elementData.y ?? 40,
      width: elementData.width ?? 200,
      height: elementData.height ?? 40,
      sort_order: elementData.sort_order ?? 0,
      is_visible: true,
      style: {
        id: `style-${newId}`,
        element_id: newId,
        font_family: 'Arial',
        font_size: 13,
        font_weight: 'normal',
        font_color: '#1e293b',
        border_width: 1,
        border_color: '#e2e8f0',
        text_align: 'right',
        padding: { top: 6, right: 10, bottom: 6, left: 10 },
        opacity: 1,
        display: 'block',
        line_height: 1.4,
      },
    };
    setTemplateWithHistory((prev) => {
      if (!prev) return prev;
      return { ...prev, elements: [...prev.elements, newElement] };
    }, 'إضافة عنصر');
    setSelectedElement(newId);
    if (templateId === 'demo-template-id') return newElement;
    try {
      const response = await apiClient.post(`/templates/${templateId}/elements`, elementData);
      return response.data.data as TemplateElement;
    } catch (err) {
      logError(err, 'إضافة العنصر');
      return null;
    }
  }, [setTemplateWithHistory]);

  const clearElements = useCallback(async (templateId: string) => {
    setTemplateWithHistory((prev) => {
      if (!prev) return prev;
      return { ...prev, elements: [] };
    }, 'مسح الكل');
    setSelectedElement(null);
    if (templateId === 'demo-template-id') return;
    try {
      await apiClient.delete(`/templates/${templateId}/elements`);
    } catch (err) {
      logError(err, 'مسح العناصر');
    }
  }, [setTemplateWithHistory]);

  const updateTemplateMeta = useCallback((updates: Partial<ReceiptTemplate>) => {
    setTemplateWithHistory((prev) => {
      if (!prev) return prev;
      return { ...prev, ...updates };
    }, 'تعديل خصائص القالب');
  }, [setTemplateWithHistory]);

  const updateElementStyle = useCallback((elementId: string, styleUpdates: any) => {
    setTemplate((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        elements: prev.elements.map((el) =>
          el.id === elementId
            ? { ...el, style: { ...(el.style || {}), ...styleUpdates } }
            : el
        ),
      };
    });
  }, []);

  const saveElementStyle = useCallback(async (templateId: string, elementId: string, styleData: any) => {
    if (templateId === 'demo-template-id') return;
    try {
      await apiClient.put(`/elements/${elementId}/styles`, styleData);
    } catch (err) {
      logError(err, 'حفظ النمط');
    }
  }, []);

  // Undo / Redo
  const undo = useCallback(() => {
    if (historyIndex <= 0) return;
    isUndoingRef.current = true;
    const idx = historyIndex - 1;
    const entry = history[idx];
    setTemplate(entry.template);
    setSelectedElement(entry.selectedElement);
    setHistoryIndex(idx);
    setTimeout(() => { isUndoingRef.current = false; }, 0);
  }, [history, historyIndex]);

  const redo = useCallback(() => {
    if (historyIndex >= history.length - 1) return;
    isUndoingRef.current = true;
    const idx = historyIndex + 1;
    const entry = history[idx];
    setTemplate(entry.template);
    setSelectedElement(entry.selectedElement);
    setHistoryIndex(idx);
    setTimeout(() => { isUndoingRef.current = false; }, 0);
  }, [history, historyIndex]);

  const canUndo = historyIndex > 0;
  const canRedo = historyIndex < history.length - 1;

  // Import / Export
  const exportTemplate = useCallback(() => {
    if (!template) return null;
    const data = {
      template: JSON.parse(JSON.stringify(template)),
      exportedAt: new Date().toISOString(),
      version: '2.0',
    };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `template_${template.name || 'design'}_${Date.now()}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    return data;
  }, [template]);

  const importTemplate = useCallback((json: any) => {
    if (!json?.template) return false;
    const imported = json.template as ReceiptTemplate;
    setTemplateWithHistory(() => imported, 'استيراد قالب');
    setSelectedElement(null);
    return true;
  }, [setTemplateWithHistory]);

  // Initialize history with base template
  const initHistory = useCallback(() => {
    if (baseTemplate && history.length === 0) {
      setHistory([{ template: JSON.parse(JSON.stringify(baseTemplate)), selectedElement: null, action: 'بدء' }]);
      setHistoryIndex(0);
    }
  }, [baseTemplate, history.length]);

  return {
    template,
    selectedElement,
    setSelectedElement,
    isLoading,
    setIsLoading,
    updateElementLocal,
    saveElementToServer,
    deleteElement,
    duplicateElement,
    addElement,
    clearElements,
    updateTemplateMeta,
    updateElementStyle,
    saveElementStyle,
    undo,
    redo,
    canUndo,
    canRedo,
    exportTemplate,
    importTemplate,
    initHistory,
  };
}
