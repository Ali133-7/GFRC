import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import type { TransactionTemplate, TransactionTemplateField, TemplateRule, TransactionTemplateSection } from "@/types/transactionTemplate";
import type { Register, RegisterField } from "@/types/register";

export default function TransactionTemplateFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const isEdit = Boolean(id && id !== "new");

  const [registerId, setRegisterId] = useState("");
  const [nameAr, setNameAr] = useState("");
  const [nameEn, setNameEn] = useState("");
  const [description, setDescription] = useState("");
  const [icon, setIcon] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [sortOrder, setSortOrder] = useState(0);
  const [fields, setFields] = useState<TransactionTemplateField[]>([]);
  const [rules, setRules] = useState<TemplateRule[]>([]);
  const [sections, setSections] = useState<TransactionTemplateSection[]>([]);

  const { data: registers } = useQuery({
    queryKey: ["registers"],
    queryFn: async () => {
      const r = await client.get("/registers");
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : d?.data ?? []) as Register[];
    },
  });

  const { data: registerFields } = useQuery({
    queryKey: ["register-fields", registerId],
    queryFn: async () => {
      if (!registerId) return [] as RegisterField[];
      const r = await client.get(`/registers/${registerId}/fields`);
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : []) as RegisterField[];
    },
    enabled: !!registerId,
  });

  const { data: existing, isLoading: loadingExisting } = useQuery({
    queryKey: ["transaction-template", id],
    queryFn: async () => {
      const r = await client.get(`/transaction-templates/${id}`);
      return (r.data?.data ?? r.data) as TransactionTemplate;
    },
    enabled: isEdit,
  });

  useEffect(() => {
    if (existing) {
      setRegisterId(existing.register_id);
      setNameAr(existing.name_ar);
      setNameEn(existing.name_en ?? "");
      setDescription(existing.description ?? "");
      setIcon(existing.icon ?? "");
      setIsActive(existing.is_active);
      setSortOrder(existing.sort_order);
      setFields(existing.fields ?? []);
      setRules(existing.rules ?? []);
      setSections(existing.sections ?? []);
    }
  }, [existing]);

  const saveMut = useMutation({
    mutationFn: async () => {
      const payload = {
        register_id: registerId,
        name_ar: nameAr,
        name_en: nameEn || null,
        description: description || null,
        sections: sections.length > 0 ? sections : null,
        icon: icon || null,
        is_active: isActive,
        sort_order: sortOrder,
        fields: fields.map((f) => ({
          id: f.id,
          register_field_id: f.register_field_id,
          label_override: f.label_override,
          placeholder: f.placeholder,
          default_value: f.default_value,
          is_required: f.is_required,
          is_visible: f.is_visible,
          is_readonly: f.is_readonly,
          sort_order: f.sort_order,
        })),
        rules: rules.map((r) => ({
          id: r.id,
          name: r.name,
          trigger_field_id: r.trigger_field_id,
          trigger_operator: r.trigger_operator,
          trigger_value: r.trigger_value,
          target_field_id: r.target_field_id,
          action: r.action,
          action_value: r.action_value,
          sort_order: r.sort_order,
        })),
      };
      if (isEdit) {
        await client.put(`/transaction-templates/${id}`, payload);
      } else {
        await client.post("/transaction-templates", payload);
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["transaction-templates"] });
      navigate("/transaction-templates");
    },
  });

  const addField = (rf: RegisterField) => {
    if (fields.some((f) => f.register_field_id === rf.id)) return;
    setFields((prev) => [
      ...prev,
      {
        register_field_id: rf.id,
        registerField: rf,
        label_override: null,
        placeholder: null,
        default_value: null,
        is_required: false,
        is_visible: true,
        is_readonly: false,
        sort_order: prev.length + 1,
      },
    ]);
  };

  const removeField = (idx: number) => setFields((prev) => prev.filter((_, i) => i !== idx));

  const moveField = (idx: number, dir: number) => {
    setFields((prev) => {
      const arr = [...prev];
      const target = idx + dir;
      if (target < 0 || target >= arr.length) return prev;
      [arr[idx], arr[target]] = [arr[target], arr[idx]];
      return arr.map((f, i) => ({ ...f, sort_order: i + 1 }));
    });
  };

  const addRule = () => {
    setRules((prev) => [
      ...prev,
      {
        trigger_field_id: registerFields?.[0]?.id ?? "",
        trigger_operator: "equals",
        trigger_value: "",
        target_field_id: registerFields?.[0]?.id ?? "",
        action: "set_amount",
        action_value: "",
        sort_order: prev.length,
        is_active: true,
      },
    ]);
  };

  const removeRule = (idx: number) => setRules((prev) => prev.filter((_, i) => i !== idx));

  const addSection = () => {
    setSections((prev) => [
      ...prev,
      { id: crypto.randomUUID(), title: `القسم ${prev.length + 1}`, field_ids: [], condition: null },
    ]);
  };

  const removeSection = (sid: string) => setSections((prev) => prev.filter((s) => s.id !== sid));

  const moveSection = (idx: number, dir: number) => {
    setSections((prev) => {
      const arr = [...prev];
      const target = idx + dir;
      if (target < 0 || target >= arr.length) return prev;
      [arr[idx], arr[target]] = [arr[target], arr[idx]];
      return arr;
    });
  };

  const toggleFieldInSection = (sid: string, fid: string) => {
    setSections((prev) => prev.map((s) => {
      if (s.id !== sid) return s;
      const has = s.field_ids.includes(fid);
      return { ...s, field_ids: has ? s.field_ids.filter((x) => x !== fid) : [...s.field_ids, fid] };
    }));
  };

  const updateSectionTitle = (sid: string, title: string) => {
    setSections((prev) => prev.map((s) => s.id === sid ? { ...s, title } : s));
  };

  const inputStyle: React.CSSProperties = { width: "100%", padding: "8px 10px", fontSize: "13px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", fontFamily: "inherit" };
  const labelStyle: React.CSSProperties = { fontSize: "12px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "4px", display: "block" };

  if (isEdit && loadingExisting) return <div style={{ padding: 40 }}><LoadingSpinner /></div>;

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader title={isEdit ? "تعديل قالب" : "قالب جديد"} />

      <div style={{ maxWidth: 900, display: "flex", flexDirection: "column", gap: 16 }}>
        {/* Basic Info */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <div>
            <label style={labelStyle}>السجل</label>
            <select value={registerId} onChange={(e) => setRegisterId(e.target.value)} disabled={isEdit} style={inputStyle}>
              <option value="">اختر السجل...</option>
              {(registers ?? []).map((r) => (
                <option key={r.id} value={r.id}>{r.name_ar}</option>
              ))}
            </select>
          </div>
          <div>
            <label style={labelStyle}>اسم القالب (عربي) *</label>
            <input type="text" value={nameAr} onChange={(e) => setNameAr(e.target.value)} style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>اسم القالب (إنجليزي)</label>
            <input type="text" value={nameEn} onChange={(e) => setNameEn(e.target.value)} style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>الأيقونة</label>
            <input type="text" value={icon} onChange={(e) => setIcon(e.target.value)} placeholder="emoji مثل 📋" style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>الترتيب</label>
            <input type="number" value={sortOrder} onChange={(e) => setSortOrder(Number(e.target.value))} style={inputStyle} />
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 8, paddingTop: 20 }}>
            <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
            <span style={{ fontSize: "13px" }}>مفعّل</span>
          </div>
        </div>

        <div>
          <label style={labelStyle}>الوصف</label>
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={2} style={{ ...inputStyle, resize: "vertical" }} />
        </div>

        {/* Fields Pool */}
        {registerId && (
          <div style={{ border: "0.5px solid var(--color-border-tertiary)", borderRadius: "12px", padding: "16px", background: "var(--color-background-primary)" }}>
            <div style={{ fontSize: "14px", fontWeight: 700, marginBottom: "12px" }}>حقول السجل المتاحة</div>
            <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
              {(registerFields ?? []).map((rf) => (
                <button
                  key={rf.id}
                  onClick={() => addField(rf)}
                  disabled={fields.some((f) => f.register_field_id === rf.id)}
                  style={{ fontSize: "11px", padding: "4px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-info)", background: fields.some((f) => f.register_field_id === rf.id) ? "#f1f5f9" : "none", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit", opacity: fields.some((f) => f.register_field_id === rf.id) ? 0.5 : 1 }}
                >
                  + {rf.label_ar}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Selected Fields */}
        {fields.length > 0 && (
          <div style={{ border: "0.5px solid var(--color-border-tertiary)", borderRadius: "12px", padding: "16px", background: "var(--color-background-primary)" }}>
            <div style={{ fontSize: "14px", fontWeight: 700, marginBottom: "12px" }}>الحقول المختارة</div>
            {fields.map((f, idx) => (
              <div key={f.register_field_id} style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr auto auto", gap: 8, alignItems: "center", padding: "8px", borderBottom: "1px solid #f1f5f9" }}>
                <span style={{ fontSize: "13px", fontWeight: 600 }}>{f.registerField?.label_ar ?? f.register_field_id}</span>
                <input type="text" placeholder="تسمية بديلة" value={f.label_override ?? ""} onChange={(e) => setFields((prev) => prev.map((x, i) => i === idx ? { ...x, label_override: e.target.value || null } : x))} style={{ ...inputStyle, fontSize: "12px" }} />
                <input type="text" placeholder="placeholder" value={f.placeholder ?? ""} onChange={(e) => setFields((prev) => prev.map((x, i) => i === idx ? { ...x, placeholder: e.target.value || null } : x))} style={{ ...inputStyle, fontSize: "12px" }} />
                <div style={{ display: "flex", gap: 6, fontSize: "11px" }}>
                  <label><input type="checkbox" checked={f.is_required} onChange={(e) => setFields((prev) => prev.map((x, i) => i === idx ? { ...x, is_required: e.target.checked } : x))} /> مطلوب</label>
                  <label><input type="checkbox" checked={f.is_readonly} onChange={(e) => setFields((prev) => prev.map((x, i) => i === idx ? { ...x, is_readonly: e.target.checked } : x))} /> للقراءة</label>
                </div>
                <div style={{ display: "flex", gap: 4 }}>
                  <button onClick={() => moveField(idx, -1)} disabled={idx === 0} style={{ fontSize: "11px", padding: "2px 6px" }}>▲</button>
                  <button onClick={() => moveField(idx, 1)} disabled={idx === fields.length - 1} style={{ fontSize: "11px", padding: "2px 6px" }}>▼</button>
                  <button onClick={() => removeField(idx)} style={{ fontSize: "11px", padding: "2px 6px", color: "#dc2626" }}>✕</button>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Sections Designer */}
        {fields.length > 0 && (
          <div style={{ border: "0.5px solid var(--color-border-tertiary)", borderRadius: "12px", padding: "16px", background: "var(--color-background-primary)" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
              <div style={{ fontSize: "14px", fontWeight: 700 }}>تصميم الأقسام (الخطوات)</div>
              <button onClick={addSection} style={{ fontSize: "12px", padding: "4px 12px", borderRadius: "4px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit" }}>+ قسم</button>
            </div>

            {sections.length === 0 && (
              <div style={{ fontSize: "12px", color: "var(--color-text-tertiary)", padding: "12px", textAlign: "center" }}>
                لم يُضف أي قسم. أضف أقساماً لتقسيم نموذج الوصل إلى خطوات.
              </div>
            )}

            {sections.map((s, idx) => (
              <div key={s.id} style={{ border: "1px solid #e2e8f0", borderRadius: "8px", padding: "12px", marginBottom: "10px", background: "#fff" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "10px" }}>
                  <input
                    type="text"
                    value={s.title}
                    onChange={(e) => updateSectionTitle(s.id, e.target.value)}
                    style={{ ...inputStyle, maxWidth: 300, fontWeight: 600, fontSize: "13px" }}
                  />
                  <div style={{ display: "flex", gap: 4 }}>
                    <button onClick={() => moveSection(idx, -1)} disabled={idx === 0} style={{ fontSize: "11px", padding: "2px 6px" }}>▲</button>
                    <button onClick={() => moveSection(idx, 1)} disabled={idx === sections.length - 1} style={{ fontSize: "11px", padding: "2px 6px" }}>▼</button>
                    <button onClick={() => removeSection(s.id)} style={{ fontSize: "11px", padding: "2px 6px", color: "#dc2626" }}>✕</button>
                  </div>
                </div>
                <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                  {fields.map((f) => {
                    const rf = f.registerField;
                    if (!rf) return null;
                    const isIn = s.field_ids.includes(f.register_field_id);
                    return (
                      <button
                        key={f.register_field_id}
                        onClick={() => toggleFieldInSection(s.id, f.register_field_id)}
                        style={{ fontSize: "11px", padding: "4px 10px", borderRadius: "4px", border: isIn ? "1.5px solid var(--color-border-success)" : "0.5px solid var(--color-border-secondary)", background: isIn ? "var(--color-background-success)" : "var(--color-background-primary)", color: isIn ? "var(--color-text-success)" : "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}
                      >
                        {isIn ? "✓ " : "+ "}{rf.label_ar}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Rules */}
        {fields.length > 0 && (
          <div style={{ border: "0.5px solid var(--color-border-tertiary)", borderRadius: "12px", padding: "16px", background: "var(--color-background-primary)" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
              <div style={{ fontSize: "14px", fontWeight: 700 }}>القواعد التلقائية</div>
              <button onClick={addRule} style={{ fontSize: "12px", padding: "4px 12px", borderRadius: "4px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit" }}>+ قاعدة</button>
            </div>

            {rules.map((r, idx) => (
              <div key={idx} style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr 1fr 1fr 1fr auto", gap: 8, alignItems: "center", padding: "8px", borderBottom: "1px solid #f1f5f9" }}>
                <select value={r.trigger_field_id} onChange={(e) => setRules((prev) => prev.map((x, i) => i === idx ? { ...x, trigger_field_id: e.target.value } : x))} style={{ ...inputStyle, fontSize: "12px" }}>
                  {(registerFields ?? []).map((rf) => <option key={rf.id} value={rf.id}>{rf.label_ar}</option>)}
                </select>
                <select value={r.trigger_operator} onChange={(e) => setRules((prev) => prev.map((x, i) => i === idx ? { ...x, trigger_operator: e.target.value as any } : x))} style={{ ...inputStyle, fontSize: "12px" }}>
                  <option value="equals">=</option>
                  <option value="not_equals">≠</option>
                  <option value="contains">يحتوي</option>
                  <option value="gt">&gt;</option>
                  <option value="lt">&lt;</option>
                </select>
                <input type="text" placeholder="القيمة" value={r.trigger_value} onChange={(e) => setRules((prev) => prev.map((x, i) => i === idx ? { ...x, trigger_value: e.target.value } : x))} style={{ ...inputStyle, fontSize: "12px" }} />
                <select value={r.target_field_id} onChange={(e) => setRules((prev) => prev.map((x, i) => i === idx ? { ...x, target_field_id: e.target.value } : x))} style={{ ...inputStyle, fontSize: "12px" }}>
                  {(registerFields ?? []).map((rf) => <option key={rf.id} value={rf.id}>{rf.label_ar}</option>)}
                </select>
                <select value={r.action} onChange={(e) => setRules((prev) => prev.map((x, i) => i === idx ? { ...x, action: e.target.value as any } : x))} style={{ ...inputStyle, fontSize: "12px" }}>
                  <option value="set_value">تعيين قيمة</option>
                  <option value="set_amount">تعيين مبلغ</option>
                  <option value="hide">إخفاء</option>
                  <option value="show">إظهار</option>
                </select>
                <input type="text" placeholder="القيمة/المبلغ" value={r.action_value ?? ""} onChange={(e) => setRules((prev) => prev.map((x, i) => i === idx ? { ...x, action_value: e.target.value || null } : x))} style={{ ...inputStyle, fontSize: "12px" }} />
                <button onClick={() => removeRule(idx)} style={{ fontSize: "11px", padding: "2px 6px", color: "#dc2626" }}>✕</button>
              </div>
            ))}
          </div>
        )}

        <div style={{ display: "flex", gap: 12 }}>
          <button onClick={() => saveMut.mutate()} disabled={saveMut.isPending || !nameAr || !registerId} style={{ padding: "10px 24px", fontSize: "13px", fontWeight: 500, borderRadius: "6px", border: "none", background: "var(--color-background-info)", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit", opacity: saveMut.isPending ? 0.6 : 1 }}>
            {saveMut.isPending ? "جاري الحفظ..." : "حفظ القالب"}
          </button>
          <button onClick={() => navigate("/transaction-templates")} style={{ padding: "10px 24px", fontSize: "13px", fontWeight: 500, borderRadius: "6px", border: "0.5px solid var(--color-border-secondary)", background: "none", color: "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}>
            إلغاء
          </button>
        </div>
      </div>
    </div>
  );
}
