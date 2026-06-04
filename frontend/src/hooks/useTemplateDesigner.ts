import { useState, useEffect, useCallback } from "react";
import client from "@/api/client";
import type { ReceiptTemplate, TemplateElement } from "@/types/template";

interface TemplateData {
  id?: string;
  name: string;
  register_id?: string;
  layout: Record<string, unknown>;
  is_default?: boolean;
}

interface UseTemplateDesignerReturn {
  templates: TemplateData[];
  currentTemplate: TemplateData | null;
  loading: boolean;
  saving: boolean;
  error: string;
  loadTemplates: (registerId?: string) => Promise<void>;
  loadTemplate: (id: string) => Promise<void>;
  saveTemplate: (data: TemplateData) => Promise<void>;
  deleteTemplate: (id: string) => Promise<void>;
  setCurrentTemplate: (template: TemplateData | null) => void;
  // Old designer interface (backward compatibility)
  template: ReceiptTemplate | null;
  updateElementLocal: (elementId: string, updates: Partial<TemplateElement>) => void;
  saveElementToServer: (elementId: string, updates: Partial<TemplateElement>) => Promise<void>;
  selectedElement: string | null;
  setSelectedElement: (id: string | null) => void;
  deleteElement: (elementId: string) => Promise<void>;
  clearElements: () => Promise<void>;
  isLoading: boolean;
}

export const useTemplateDesigner = (templateId?: string): UseTemplateDesignerReturn => {
  const [templates, setTemplates] = useState<TemplateData[]>([]);
  const [currentTemplate, setCurrentTemplate] = useState<TemplateData | null>(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const loadTemplates = useCallback(async (registerId?: string) => {
    setLoading(true);
    setError("");
    try {
      const params = registerId ? `?register_id=${registerId}` : "";
      const res = await client.get(`/receipt-templates${params}`);
      const data = res.data?.data ?? res.data ?? [];
      setTemplates(Array.isArray(data) ? data : []);
    } catch {
      setError("تعذّر تحميل القوالب");
      setTemplates([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadTemplate = useCallback(async (id: string) => {
    setLoading(true);
    setError("");
    try {
      const res = await client.get(`/receipt-templates/${id}`);
      setCurrentTemplate(res.data?.data ?? res.data ?? null);
    } catch {
      setError("تعذّر تحميل القالب");
    } finally {
      setLoading(false);
    }
  }, []);

  const saveTemplate = useCallback(async (data: TemplateData) => {
    setSaving(true);
    setError("");
    try {
      if (data.id) {
        const res = await client.put(`/receipt-templates/${data.id}`, data);
        setCurrentTemplate(res.data?.data ?? res.data ?? data);
      } else {
        const res = await client.post("/receipt-templates", data);
        const saved = res.data?.data ?? res.data ?? data;
        setCurrentTemplate(saved);
        setTemplates((prev) => [...prev, saved]);
      }
    } catch {
      setError("تعذّر حفظ القالب");
      throw new Error("save failed");
    } finally {
      setSaving(false);
    }
  }, []);

  const deleteTemplate = useCallback(async (id: string) => {
    setError("");
    try {
      await client.delete(`/receipt-templates/${id}`);
      setTemplates((prev) => prev.filter((t) => t.id !== id));
      if (currentTemplate?.id === id) setCurrentTemplate(null);
    } catch {
      setError("تعذّر حذف القالب");
    }
  }, [currentTemplate]);

  useEffect(() => {
    if (templateId) {
      loadTemplate(templateId);
    }
  }, [templateId, loadTemplate]);

  return {
    templates,
    currentTemplate,
    loading,
    saving,
    error,
    loadTemplates,
    loadTemplate,
    saveTemplate,
    deleteTemplate,
    setCurrentTemplate,
    // Old designer stubs (backward compatibility)
    template: null,
    updateElementLocal: () => {},
    saveElementToServer: async () => {},
    selectedElement: null,
    setSelectedElement: () => {},
    deleteElement: async () => {},
    clearElements: async () => {},
    isLoading: loading,
  };
};

export const useStyleUpdate = (elementId: string, templateId: string) => {
  const [isLoading, setIsLoading] = useState(false);
  const updateStyle = async (_styleData: unknown) => {
    setIsLoading(true);
    try {
      if (templateId === "demo-template-id") return true;
      await client.put(`/elements/${elementId}/styles`, _styleData);
      return true;
    } catch {
      return false;
    } finally {
      setIsLoading(false);
    }
  };
  const applyPreset = async (_presetName: string) => {
    setIsLoading(true);
    try {
      if (templateId === "demo-template-id") return true;
      await client.post(`/elements/${elementId}/styles/preset`, { preset: _presetName });
      return true;
    } catch {
      return false;
    } finally {
      setIsLoading(false);
    }
  };
  return { updateStyle, applyPreset, isLoading };
};

export const useFieldsEditor = (templateId: string) => {
  const [registerFields, setRegisterFields] = useState<Array<{ id: string; label: string; name: string; field_type: string }>>([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    const load = async () => {
      if (templateId === "demo-template-id") {
        setRegisterFields([
          { id: "field-1", name: "service_type", label: "نوع الخدمة", field_type: "text" },
          { id: "field-2", name: "amount", label: "المبلغ الأساسي", field_type: "number" },
          { id: "field-3", name: "tax", label: "الضريبة", field_type: "number" },
          { id: "field-4", name: "fees", label: "رسوم إدارية", field_type: "number" },
        ]);
        return;
      }
      setIsLoading(true);
      try {
        const res = await client.get(`/templates/${templateId}`);
        const template = res.data?.data ?? res.data;
        if (template?.register_id) {
          const fieldsRes = await client.get(`/registers/${template.register_id}/fields`);
          const fields = fieldsRes.data?.data ?? fieldsRes.data ?? [];
          setRegisterFields((fields as any[]).map((f: any) => ({
            id: f.id,
            label: f.label || f.label_ar || f.name,
            name: f.name,
            field_type: f.field_type || f.type || "text",
          })));
        }
      } catch {
        setRegisterFields([]);
      } finally {
        setIsLoading(false);
      }
    };
    load();
  }, [templateId]);

  const addElement = async (elementData: Partial<TemplateElement>) => {
    if (templateId === "demo-template-id") {
      return { id: `el-new-${Date.now()}`, template_id: "demo-template-id", ...elementData };
    }
    setIsLoading(true);
    try {
      const res = await client.post(`/templates/${templateId}/elements`, elementData);
      return res.data?.data ?? res.data;
    } catch {
      return null;
    } finally {
      setIsLoading(false);
    }
  };

  return { registerFields, addElement, isLoading };
};

export const useTemplateActions = (templateId: string) => {
  const [history, _setHistory] = useState<any[]>([]);
  const [historyIndex, setHistoryIndex] = useState(-1);
  const [isLoading, setIsLoading] = useState(false);

  const saveTemplate = async () => {
    if (templateId === "demo-template-id") return null;
    setIsLoading(true);
    try {
      const res = await client.get(`/templates/${templateId}`);
      return res.data?.data ?? res.data;
    } catch {
      return null;
    } finally {
      setIsLoading(false);
    }
  };

  const undoAction = () => {
    if (historyIndex > 0) setHistoryIndex(historyIndex - 1);
  };

  const redoAction = () => {
    if (historyIndex < history.length - 1) setHistoryIndex(historyIndex + 1);
  };

  return {
    saveTemplate,
    undoAction,
    redoAction,
    canUndo: historyIndex > 0,
    canRedo: historyIndex < history.length - 1,
    isLoading,
  };
};

export default useTemplateDesigner;
