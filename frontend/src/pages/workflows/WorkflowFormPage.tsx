import { useState, useEffect } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useCreateWorkflow, useWorkflow, useUpdateWorkflow } from "@/hooks/useWorkflows";
import { useRegisters } from "@/hooks/useRegisters";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";

interface ApiError {
  response?: {
    data?: {
      message?: string;
      errors?: Record<string, string[]>;
    };
  };
}

export default function WorkflowFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const isEdit = !!id;

  const { data: workflow, isLoading: wfLoading } = useWorkflow(id ?? "");
  const { data: registers } = useRegisters();
  const createMut = useCreateWorkflow();
  const updateMut = useUpdateWorkflow();

  const [form, setForm] = useState({
    register_id: "",
    code: "",
    name_ar: "",
    name_en: "",
    description: "",
    icon: "",
    sort_order: 0,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [serverError, setServerError] = useState<string>("");

  // Load existing data
  useEffect(() => {
    if (workflow) {
      setForm({
        register_id: workflow.register_id,
        code: workflow.code,
        name_ar: workflow.name_ar,
        name_en: workflow.name_en ?? "",
        description: workflow.description ?? "",
        icon: workflow.icon ?? "",
        sort_order: workflow.sort_order,
      });
    }
  }, [workflow]);

  const extractErrors = (err: unknown): Record<string, string> => {
    const apiErr = err as ApiError;
    const backendErrors = apiErr?.response?.data?.errors;
    if (!backendErrors) return {};
    const result: Record<string, string> = {};
    for (const [key, msgs] of Object.entries(backendErrors)) {
      result[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
    }
    return result;
  };

  const extractMessage = (err: unknown): string => {
    const apiErr = err as ApiError;
    return apiErr?.response?.data?.message ?? "حدث خطأ غير متوقع";
  };

  const validateFrontend = (): boolean => {
    const newErrors: Record<string, string> = {};
    if (!form.register_id) newErrors.register_id = "السجل مطلوب";
    if (!form.code) newErrors.code = "الرمز مطلوب";
    if (form.code && !/^[A-Za-z0-9_]+$/.test(form.code)) newErrors.code = "الرمز يجب أن يحتوي على حروف إنجليزية وأرقام وشرطات سفلية فقط";
    if (!form.name_ar) newErrors.name_ar = "الاسم بالعربية مطلوب";
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setServerError("");

    if (!validateFrontend()) return;

    if (isEdit && id) {
      updateMut.mutate(
        { id, payload: form },
        {
          onSuccess: () => navigate(`/workflows/${id}`),
          onError: (err) => {
            setErrors(extractErrors(err));
            setServerError(extractMessage(err));
          },
        }
      );
    } else {
      createMut.mutate(form, {
        onSuccess: (data) => {
          // Full page reload to clear all cache
          if (data?.id) {
            window.location.href = `/workflows/${data.id}`;
          }
        },
        onError: (err) => {
          setErrors(extractErrors(err));
          setServerError(extractMessage(err));
        },
      });
    }
  };

  const inputStyle = (fieldName: string): React.CSSProperties => ({
    padding: "8px 10px",
    fontSize: "13px",
    border: `0.5px solid ${errors[fieldName] ? "var(--color-border-danger)" : "var(--color-border-secondary)"}`,
    borderRadius: "6px",
    background: errors[fieldName] ? "var(--color-background-danger)" : "var(--color-background-primary)",
    fontFamily: "inherit",
    width: "100%",
    outline: "none",
  });

  const labelStyle: React.CSSProperties = {
    fontSize: "12px",
    color: "var(--color-text-secondary)",
    marginBottom: "4px",
    display: "block",
  };

  const errorTextStyle: React.CSSProperties = {
    fontSize: "11px",
    color: "var(--color-text-danger)",
    marginTop: "3px",
  };

  if (isEdit && wfLoading) {
    return (
      <div dir="rtl" style={{ padding: "48px", textAlign: "center" }}>
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader
        title={isEdit ? "تعديل Workflow" : "Workflow جديد"}
        back={{ label: "← رجوع", onClick: () => navigate("/workflows") }}
      />

      {serverError && (
        <div
          style={{
            padding: "10px 14px",
            background: "var(--color-background-danger)",
            color: "var(--color-text-danger)",
            border: "0.5px solid var(--color-border-danger)",
            borderRadius: "var(--border-radius-md)",
            marginBottom: "14px",
            fontSize: "13px",
          }}
        >
          {serverError}
        </div>
      )}

      <form
        onSubmit={handleSubmit}
        style={{
          maxWidth: "600px",
          background: "var(--color-background-primary)",
          border: "0.5px solid var(--color-border-tertiary)",
          borderRadius: "var(--border-radius-lg)",
          padding: "20px",
        }}
      >
        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>السجل المالي *</label>
          <select
            required
            value={form.register_id}
            onChange={(e) => setForm({ ...form, register_id: e.target.value })}
            style={inputStyle("register_id")}
            disabled={isEdit}
          >
            <option value="">اختر السجل</option>
            {registers?.map((r: { id: string; name_ar: string }) => (
              <option key={r.id} value={r.id}>{r.name_ar}</option>
            ))}
          </select>
          {errors.register_id && <div style={errorTextStyle}>{errors.register_id}</div>}
        </div>

        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>الرمز (Code) *</label>
          <input
            required
            value={form.code}
            onChange={(e) => setForm({ ...form, code: e.target.value })}
            style={inputStyle("code")}
            placeholder="مثال: MERCHANT_REG"
            disabled={isEdit}
          />
          {errors.code && <div style={errorTextStyle}>{errors.code}</div>}
        </div>

        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>الاسم بالعربية *</label>
          <input
            required
            value={form.name_ar}
            onChange={(e) => setForm({ ...form, name_ar: e.target.value })}
            style={inputStyle("name_ar")}
          />
          {errors.name_ar && <div style={errorTextStyle}>{errors.name_ar}</div>}
        </div>

        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>الاسم بالإنجليزية</label>
          <input
            value={form.name_en}
            onChange={(e) => setForm({ ...form, name_en: e.target.value })}
            style={inputStyle("name_en")}
          />
        </div>

        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>الأيقونة (Emoji)</label>
          <input
            value={form.icon}
            onChange={(e) => setForm({ ...form, icon: e.target.value })}
            style={{ ...inputStyle("icon"), maxWidth: "80px" }}
            placeholder="⚙️"
          />
        </div>

        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>الوصف</label>
          <textarea
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            style={{ ...inputStyle("description"), minHeight: "80px", resize: "vertical" }}
          />
        </div>

        <div style={{ marginBottom: "14px" }}>
          <label style={labelStyle}>ترتيب العرض</label>
          <input
            type="number"
            value={form.sort_order}
            onChange={(e) => setForm({ ...form, sort_order: parseInt(e.target.value) || 0 })}
            style={{ ...inputStyle("sort_order"), maxWidth: "100px" }}
          />
        </div>

        <div style={{ display: "flex", gap: "10px", marginTop: "20px" }}>
          <button
            type="submit"
            disabled={createMut.isPending || updateMut.isPending}
            style={{
              padding: "8px 20px",
              fontSize: "13px",
              fontWeight: 500,
              background: "var(--color-background-info)",
              color: "var(--color-text-info)",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "var(--border-radius-md)",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            {createMut.isPending || updateMut.isPending ? "جارٍ الحفظ..." : isEdit ? "حفظ التعديلات" : "إنشاء Workflow"}
          </button>
          <button
            type="button"
            onClick={() => navigate("/workflows")}
            style={{
              padding: "8px 20px",
              fontSize: "13px",
              background: "none",
              color: "var(--color-text-secondary)",
              border: "0.5px solid var(--color-border-secondary)",
              borderRadius: "var(--border-radius-md)",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            إلغاء
          </button>
        </div>
      </form>
    </div>
  );
}
