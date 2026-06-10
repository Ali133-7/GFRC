import React, { useState, useRef, useEffect, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery, useMutation } from "@tanstack/react-query";
import client from "@/api/client";
import DynamicReceiptForm from "@/components/receipt/DynamicReceiptForm";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { GovSelect } from "@/components/ui/GovSelect";
import { PageHeader } from "@/components/layout/PageHeader";
import { formatCurrency } from "@/utils/formatCurrency";
import { amountToArabicWords } from "@/utils/amountToArabicWords";
import { Register, RegisterField } from "@/types/register";
import type { TransactionTemplate, TransactionTemplateField, GuidedReceiptBuild } from "@/types/transactionTemplate";

const cardBase = {
  background: "#fff",
  borderRadius: "16px",
  boxShadow: "0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02)",
  border: "1px solid #f1f5f9",
  overflow: "hidden" as const,
};

export default function ReceiptCreatePage() {
  const navigate = useNavigate();
  const [selectedRegisterId, setSelectedRegisterId] = useState("");
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null);
  const [selectedWorkflowId, setSelectedWorkflowId] = useState<string | null>(null);
  const [templateValues, setTemplateValues] = useState<Record<string, string>>({});
  const [guidedBuild, setGuidedBuild] = useState<GuidedReceiptBuild | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [financialTotal, setFinancialTotal] = useState(0);
  const [stepIndex, setStepIndex] = useState(0);
  const shouldIssueRef = useRef(false);
  const formContainerRef = useRef<HTMLDivElement>(null);

  const { data: registers, isLoading: loadingRegisters } = useQuery({
    queryKey: ["registers", "active"],
    queryFn: async () => {
      const res = await client.get("/registers?is_active=1");
      const d = res.data?.data ?? res.data;
      return (Array.isArray(d) ? d : d?.data ?? []) as Register[];
    },
  });

  const { data: fields, isLoading: loadingFields } = useQuery({
    queryKey: ["register-fields", selectedRegisterId],
    queryFn: async () => {
      const res = await client.get(`/registers/${selectedRegisterId}/fields`);
      const d = res.data?.data ?? res.data;
      return (Array.isArray(d) ? d : d?.data ?? []) as RegisterField[];
    },
    enabled: !!selectedRegisterId,
  });

  const { data: templates, isLoading: loadingTemplates } = useQuery({
    queryKey: ["register-templates", selectedRegisterId],
    queryFn: async () => {
      const res = await client.get(`/registers/${selectedRegisterId}/transaction-templates`);
      const d = res.data?.data ?? res.data;
      return (Array.isArray(d) ? d : []) as TransactionTemplate[];
    },
    enabled: !!selectedRegisterId,
  });

  const { data: workflows, isLoading: loadingWorkflows } = useQuery({
    queryKey: ["workflows", "register", selectedRegisterId],
    queryFn: async () => {
      const res = await client.get("/workflows", { params: { register_id: selectedRegisterId, is_active: 1, has_active_version: 1 } });
      const d = res.data?.data ?? res.data;
      return (Array.isArray(d) ? d : []) as { id: string; name_ar: string; code: string; current_version: number; register_id: string; active_version_id: string | null }[];
    },
    enabled: !!selectedRegisterId,
  });

  const selectedRegister = registers?.find((r) => r.id === selectedRegisterId);
  const selectedTemplate = templates?.find((t) => t.id === selectedTemplateId);

  const hasSections = selectedTemplate && selectedTemplate.sections && selectedTemplate.sections.length > 0;

  const templateFields = useMemo(() => {
    return (selectedTemplate?.fields ?? []).map((tf) => {
      const rf = fields?.find((f) => f.id === tf.register_field_id);
      return { ...tf, registerField: rf ?? tf.registerField };
    });
  }, [selectedTemplate, fields]);

  useEffect(() => {
    setTemplateValues({});
    setGuidedBuild(null);
    setFinancialTotal(0);
    setStepIndex(0);
    setError("");
    setSelectedWorkflowId(null);
    if (templates && templates.length > 0) {
      setSelectedTemplateId(templates[0].id);
    } else {
      setSelectedTemplateId(null);
    }
  }, [selectedRegisterId, templates]);

  useEffect(() => {
    setStepIndex(0);
    setTemplateValues({});
    setGuidedBuild(null);
  }, [selectedTemplateId]);

  const buildMut = useMutation({
    mutationFn: async (vals: Record<string, string>) => {
      const res = await client.post("/guided-receipts/build", {
        template_id: selectedTemplateId,
        values: vals,
      });
      return (res.data?.data ?? res.data) as GuidedReceiptBuild;
    },
    onSuccess: (data) => {
      setGuidedBuild(data);
      setFinancialTotal(parseFloat(data.total_amount));
    },
  });

  const handleTemplateValueChange = (fieldName: string, value: string) => {
    const next = { ...templateValues, [fieldName]: value };
    setTemplateValues(next);
    if (selectedTemplateId) {
      buildMut.mutate(next);
    }
  };

  const handleGuidedSubmit = async (issue = false) => {
    if (!guidedBuild || !selectedTemplateId) return;
    setSubmitting(true);
    setError("");
    try {
      const res = await client.post("/guided-receipts", {
        template_id: selectedTemplateId,
        values: templateValues,
        notes: "",
      });
      const receipt = res.data?.data ?? res.data;
      if (issue && receipt?.id) {
        await client.post(`/receipts/${receipt.id}/issue`);
      }
      navigate(`/receipts/${receipt.id}`);
    } catch (e: unknown) {
      const err = e as { arabicMessage?: string; response?: { data?: { message?: string } } };
      setError(err.arabicMessage ?? err.response?.data?.message ?? "فشل حفظ الوصل");
    } finally {
      setSubmitting(false);
    }
  };

  const handleSubmit = async (
    values: Record<string, string | number | null>,
    total: number,
    issue = false
  ) => {
    setSubmitting(true);
    setError("");
    try {
      const items = fields
        ?.filter((f) => f.is_visible && f.field_type !== "hidden")
        .map((f) => ({
          field_id: f.id,
          amount: f.is_financial ? (parseFloat(String(values[f.name] ?? "0")) || 0) : null,
          value: !f.is_financial ? String(values[f.name] ?? "") : null,
        })) ?? [];

      const payload = {
        register_id: selectedRegisterId,
        total_amount: total.toFixed(3),
        items,
        notes: String(values["notes"] ?? ""),
      };

      const res = await client.post("/receipts", payload);
      const receipt = res.data?.data ?? res.data;

      if (issue && receipt?.id) {
        await client.post(`/receipts/${receipt.id}/issue`);
      }

      navigate(`/receipts/${receipt.id}`);
    } catch (e: unknown) {
      const err = e as { arabicMessage?: string; response?: { data?: { message?: string } } };
      setError(err.arabicMessage ?? err.response?.data?.message ?? "فشل حفظ الوصل");
    } finally {
      setSubmitting(false);
    }
  };

  const triggerSubmit = (issue: boolean) => {
    shouldIssueRef.current = issue;
    const form = formContainerRef.current?.querySelector("form");
    if (form) (form as HTMLFormElement).requestSubmit();
  };

  const handleFormSubmit = (values: Record<string, string | number | null>, total: number) => {
    handleSubmit(values, total, shouldIssueRef.current);
    shouldIssueRef.current = false;
  };

  const sections = selectedTemplate?.sections ?? [];
  const currentSection = sections[stepIndex];

  const sectionFields = useMemo(() => {
    if (!currentSection) return templateFields;
    return templateFields.filter((tf) => currentSection.field_ids.includes(tf.register_field_id));
  }, [currentSection, templateFields]);

  const canGoNext = useMemo(() => {
    if (!hasSections) return true;
    const requiredFields = sectionFields.filter((tf) => tf.is_required);
    return requiredFields.every((tf) => {
      const rf = tf.registerField;
      if (!rf) return true;
      const val = templateValues[rf.name] ?? tf.default_value ?? "";
      return String(val).trim() !== "";
    });
  }, [sectionFields, templateValues, hasSections]);

  const renderFieldInput = (tf: TransactionTemplateField & { registerField?: RegisterField }) => {
    const rf = tf.registerField;
    if (!rf) return null;
    const val = templateValues[rf.name] ?? tf.default_value ?? "";
    const baseInput: React.CSSProperties = {
      width: "100%",
      padding: "10px 12px",
      fontSize: "14px",
      border: "1px solid #e2e8f0",
      borderRadius: "10px",
      background: tf.is_readonly ? "#f8fafc" : "#fff",
      color: "#0f172a",
      fontFamily: "'Noto Sans Arabic', sans-serif",
      outline: "none",
      transition: "border-color 0.15s",
    };

    if (rf.field_type === "select") {
      return (
        <GovSelect
          value={val}
          onChange={(v) => handleTemplateValueChange(rf.name, v)}
          disabled={tf.is_readonly || submitting}
          options={(rf.options ?? []).map((opt) => ({
            value: typeof opt === "string" ? opt : opt.value,
            label: typeof opt === "string" ? opt : opt.label_ar,
          }))}
          placeholder="— اختر —"
        />
      );
    }
    if (rf.field_type === "textarea") {
      return (
        <textarea
          value={val}
          onChange={(e) => handleTemplateValueChange(rf.name, e.target.value)}
          disabled={tf.is_readonly || submitting}
          placeholder={tf.placeholder ?? ""}
          rows={3}
          style={{ ...baseInput, resize: "vertical" }}
        />
      );
    }
    return (
      <input
        type={rf.field_type === "number" ? "number" : "text"}
        value={val}
        onChange={(e) => handleTemplateValueChange(rf.name, e.target.value)}
        disabled={tf.is_readonly || submitting}
        placeholder={tf.placeholder ?? ""}
        style={baseInput}
      />
    );
  };

  const stepperDots = () => {
    if (!hasSections || !selectedTemplate) return null;
    return (
      <div style={{ display: "flex", gap: "8px", marginBottom: "24px", overflowX: "auto", paddingBottom: "4px" }}>
        {sections.map((s, idx) => (
          <div
            key={s.id}
            onClick={() => { if (idx < stepIndex) setStepIndex(idx); }}
            style={{
              flex: 1,
              minWidth: "110px",
              padding: "12px 8px",
              borderRadius: "12px",
              textAlign: "center",
              cursor: idx < stepIndex ? "pointer" : "default",
              background: idx === stepIndex ? "#0f172a" : idx < stepIndex ? "#059669" : "#f1f5f9",
              color: idx === stepIndex || idx < stepIndex ? "#fff" : "#64748b",
              border: `1px solid ${idx === stepIndex ? "#0f172a" : idx < stepIndex ? "#059669" : "#e2e8f0"}`,
              fontSize: "13px",
              fontWeight: 600,
              transition: "all 0.2s",
            }}
          >
            {idx < stepIndex ? "✓" : idx + 1}. {s.title}
          </div>
        ))}
      </div>
    );
  };

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif", background: "#f8fafc", minHeight: "100vh" }}>
      <PageHeader title="إصدار وصل جديد" />

      <div style={{ maxWidth: 800, margin: "0 auto" }}>
        {/* Register Card */}
        <div style={{ ...cardBase, padding: "20px", marginTop: "16px" }}>
          <div style={{ fontSize: "12px", color: "#94a3b8", fontWeight: 500, marginBottom: "8px", letterSpacing: "0.5px" }}>الخطوة ١ — اختيار السجل</div>
          {loadingRegisters ? <LoadingSpinner /> : (
            <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
              {(registers ?? []).map((r) => (
                <button
                  key={r.id}
                  onClick={() => setSelectedRegisterId(r.id)}
                  style={{
                    padding: "10px 18px",
                    borderRadius: "10px",
                    border: selectedRegisterId === r.id ? "2px solid #0f172a" : "1px solid #e2e8f0",
                    background: selectedRegisterId === r.id ? "#0f172a" : "#fff",
                    color: selectedRegisterId === r.id ? "#fff" : "#334155",
                    cursor: "pointer",
                    fontFamily: "inherit",
                    fontSize: "14px",
                    fontWeight: 600,
                    transition: "all 0.15s",
                  }}
                >
                  {r.name_ar}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Template Card */}
        {selectedRegisterId && (
          <div style={{ ...cardBase, padding: "20px", marginTop: "16px" }}>
            <div style={{ fontSize: "12px", color: "#94a3b8", fontWeight: 500, marginBottom: "8px", letterSpacing: "0.5px" }}>الخطوة ٢ — نوع المعاملة</div>
            {loadingTemplates ? <LoadingSpinner /> : (
              <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
                {(templates ?? []).length === 0 && (workflows ?? []).length === 0 && (
                  <div style={{ fontSize: "13px", color: "#64748b" }}>لا توجد قوالب أو محركات سير عمل لهذا السجل</div>
                )}
                {(templates ?? []).map((t) => (
                  <button
                    key={t.id}
                    onClick={() => { setSelectedTemplateId(t.id); setSelectedWorkflowId(null); }}
                    style={{
                      padding: "10px 18px",
                      borderRadius: "10px",
                      border: selectedTemplateId === t.id && selectedWorkflowId === null ? "2px solid #3b82f6" : "1px solid #e2e8f0",
                      background: selectedTemplateId === t.id && selectedWorkflowId === null ? "#eff6ff" : "#fff",
                      color: selectedTemplateId === t.id && selectedWorkflowId === null ? "#1d4ed8" : "#334155",
                      cursor: "pointer",
                      fontFamily: "inherit",
                      fontSize: "14px",
                      fontWeight: 600,
                      transition: "all 0.15s",
                    }}
                  >
                    {t.icon ?? "📋"} {t.name_ar}
                  </button>
                ))}
              </div>
            )}

            {/* Workflows */}
            {loadingWorkflows ? <LoadingSpinner /> : (
              <div style={{ display: "flex", gap: "10px", flexWrap: "wrap", marginTop: (templates ?? []).length > 0 ? "12px" : "0" }}>
                {(workflows ?? []).filter((w) => w.active_version_id).map((w) => (
                  <button
                    key={w.id}
                    onClick={() => {
                      setSelectedWorkflowId(w.id);
                      setSelectedTemplateId(null);
                    }}
                    style={{
                      padding: "10px 18px",
                      borderRadius: "10px",
                      border: selectedWorkflowId === w.id ? "2px solid #8b5cf6" : "1px solid #e2e8f0",
                      background: selectedWorkflowId === w.id ? "#f5f3ff" : "#fff",
                      color: selectedWorkflowId === w.id ? "#6d28d9" : "#334155",
                      cursor: "pointer",
                      fontFamily: "inherit",
                      fontSize: "14px",
                      fontWeight: 600,
                      transition: "all 0.15s",
                    }}
                  >
                    ⚙️ {w.name_ar}
                  </button>
                ))}
                <button
                  onClick={() => { setSelectedTemplateId(null); setSelectedWorkflowId(null); }}
                  style={{
                    padding: "10px 18px",
                    borderRadius: "10px",
                    border: selectedTemplateId === null && selectedWorkflowId === null ? "2px solid #64748b" : "1px solid #e2e8f0",
                    background: selectedTemplateId === null && selectedWorkflowId === null ? "#f1f5f9" : "#fff",
                    color: "#64748b",
                    cursor: "pointer",
                    fontFamily: "inherit",
                    fontSize: "13px",
                    fontWeight: 500,
                    transition: "all 0.15s",
                  }}
                >
                  📝 وضع يدوي
                </button>
              </div>
            )}
          </div>
        )}

        {/* Workflow Redirect */}
        {selectedWorkflowId && (
          <div style={{ ...cardBase, padding: "24px", marginTop: "16px", textAlign: "center" }}>
            <div style={{ fontSize: "18px", fontWeight: 800, color: "#0f172a", marginBottom: "12px" }}>⚙️ سير عمل</div>
            <div style={{ fontSize: "14px", color: "#64748b", marginBottom: "20px" }}>
              ستتم إعادة توجيهك إلى صفحة تشغيل سير العمل
            </div>
            <button
              onClick={() => {
                const wf = workflows?.find((w) => w.id === selectedWorkflowId);
                if (wf?.active_version_id) {
                  navigate(`/workflows/${selectedWorkflowId}/execute?version=${wf.active_version_id}`);
                }
              }}
              style={{
                padding: "12px 32px",
                fontSize: "14px",
                fontWeight: 700,
                borderRadius: "10px",
                border: "none",
                background: "#8b5cf6",
                color: "#fff",
                cursor: "pointer",
                fontFamily: "inherit",
                boxShadow: "0 4px 12px rgba(139,92,246,0.25)",
              }}
            >
              بدء التشغيل →
            </button>
          </div>
        )}

        {/* Guided Form */}
        {selectedTemplateId && selectedTemplate && (
          <div style={{ ...cardBase, padding: "24px", marginTop: "16px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <div style={{ fontSize: "18px", fontWeight: 800, color: "#0f172a" }}>
                  {selectedTemplate.icon ?? "📋"} {selectedTemplate.name_ar}
                </div>
                <div style={{ fontSize: "12px", color: "#94a3b8", marginTop: "4px" }}>
                  {hasSections ? `الخطوة ${stepIndex + 1} من ${sections.length}` : "أدخل البيانات المطلوبة"}
                </div>
              </div>
              {guidedBuild && (
                <div style={{ textAlign: "left", background: "#f0fdf4", border: "1px solid #bbf7d0", borderRadius: "12px", padding: "10px 16px" }}>
                  <div style={{ fontSize: "11px", color: "#166534" }}>المجموع</div>
                  <div style={{ fontSize: "20px", fontWeight: 800, color: "#0f172a", fontFamily: "'Courier New', monospace" }}>
                    {Number(guidedBuild.total_amount).toLocaleString('ar-IQ')} د.ع
                  </div>
                </div>
              )}
            </div>

            {error && (
              <div style={{ marginBottom: "16px", padding: "12px 16px", background: "#fef2f2", color: "#991b1b", borderRadius: "10px", fontSize: "13px", border: "1px solid #fecaca" }}>
                {error}
              </div>
            )}

            {stepperDots()}

            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))", gap: "16px", marginBottom: "24px" }}>
              {(hasSections ? sectionFields : templateFields).map((tf) => {
                const rf = tf.registerField;
                if (!rf) return null;
                return (
                  <div key={tf.register_field_id}>
                    <label style={{ display: "block", fontSize: "13px", fontWeight: 600, color: "#334155", marginBottom: "6px" }}>
                      {tf.label_override ?? rf.label_ar}
                      {tf.is_required && <span style={{ color: "#dc2626" }}>*</span>}
                      {tf.is_readonly && <span style={{ fontSize: "10px", color: "#64748b", marginRight: "6px", background: "#f1f5f9", padding: "1px 6px", borderRadius: "4px" }}>تلقائي</span>}
                    </label>
                    {renderFieldInput(tf)}
                  </div>
                );
              })}
            </div>

            {/* Review */}
            {hasSections && stepIndex === sections.length - 1 && guidedBuild && (
              <div style={{ marginBottom: "24px", border: "1px solid #e2e8f0", borderRadius: "12px", overflow: "hidden" }}>
                <div style={{ background: "#f8fafc", padding: "12px 16px", fontSize: "13px", fontWeight: 700, color: "#0f172a", borderBottom: "1px solid #e2e8f0" }}>
                  مراجعة المبالغ
                </div>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "14px" }}>
                  <tbody>
                    {(guidedBuild.items ?? []).filter((it) => it.amount !== null || it.text_value).map((item) => (
                      <tr key={item.field_id} style={{ borderBottom: "1px solid #f1f5f9" }}>
                        <td style={{ padding: "10px 16px", color: "#334155" }}>{item.label_ar_snapshot}</td>
                        <td style={{ padding: "10px 16px", textAlign: "left", fontFamily: "'Courier New', monospace", fontWeight: 600, color: "#0f172a" }}>
                          {item.amount !== null ? formatCurrency(parseFloat(item.amount)) : item.text_value}
                        </td>
                      </tr>
                    ))}
                    <tr style={{ background: "#f8fafc", fontWeight: 800 }}>
                      <td style={{ padding: "12px 16px", color: "#0f172a" }}>الإجمالي</td>
                      <td style={{ padding: "12px 16px", textAlign: "left", fontFamily: "'Courier New', monospace", fontSize: "16px", color: "#0f172a" }}>
                        {formatCurrency(parseFloat(guidedBuild.total_amount))}
                      </td>
                    </tr>
                  </tbody>
                </table>
                <div style={{ padding: "12px 16px", background: "#f8fafc", fontSize: "12px", color: "#64748b", textAlign: "center", borderTop: "1px solid #e2e8f0" }}>
                  المبلغ كتابةً: <span style={{ fontWeight: 700, color: "#0f172a" }}>{amountToArabicWords(parseFloat(guidedBuild.total_amount))}</span> دينار عراقي فقط لا غير
                </div>
              </div>
            )}

            {/* Actions */}
            <div style={{ display: "flex", gap: "12px", justifyContent: "space-between", alignItems: "center" }}>
              <div>
                {hasSections && stepIndex > 0 && (
                  <button
                    onClick={() => setStepIndex(stepIndex - 1)}
                    disabled={submitting}
                    style={{ padding: "12px 24px", fontSize: "14px", fontWeight: 600, borderRadius: "10px", border: "1px solid #e2e8f0", background: "#fff", color: "#334155", cursor: "pointer", fontFamily: "inherit" }}
                  >
                    ← السابق
                  </button>
                )}
              </div>
              <div style={{ display: "flex", gap: "10px" }}>
                {hasSections && stepIndex < sections.length - 1 ? (
                  <button
                    onClick={() => setStepIndex(stepIndex + 1)}
                    disabled={!canGoNext}
                    style={{
                      padding: "12px 32px", fontSize: "14px", fontWeight: 700, borderRadius: "10px", border: "none",
                      background: canGoNext ? "#0f172a" : "#cbd5e1", color: "#fff", cursor: canGoNext ? "pointer" : "not-allowed",
                      fontFamily: "inherit", transition: "all 0.15s",
                    }}
                  >
                    التالي →
                  </button>
                ) : (
                  <>
                    <button
                      onClick={() => handleGuidedSubmit(false)}
                      disabled={submitting || !guidedBuild || parseFloat(guidedBuild?.total_amount ?? "0") <= 0}
                      style={{ padding: "12px 24px", fontSize: "14px", fontWeight: 600, borderRadius: "10px", border: "1px solid #e2e8f0", background: "#fff", color: "#334155", cursor: "pointer", fontFamily: "inherit", opacity: submitting || !guidedBuild || parseFloat(guidedBuild?.total_amount ?? "0") <= 0 ? 0.6 : 1 }}
                    >
                      {submitting ? "..." : "💾 حفظ مسودة"}
                    </button>
                    <button
                      onClick={() => handleGuidedSubmit(true)}
                      disabled={submitting || !guidedBuild || parseFloat(guidedBuild?.total_amount ?? "0") <= 0}
                      style={{
                        padding: "12px 32px", fontSize: "14px", fontWeight: 700, borderRadius: "10px", border: "none",
                        background: "#059669", color: "#fff", cursor: "pointer", fontFamily: "inherit",
                        opacity: submitting || !guidedBuild || parseFloat(guidedBuild?.total_amount ?? "0") <= 0 ? 0.6 : 1,
                        boxShadow: "0 4px 12px rgba(5,150,105,0.25)",
                      }}
                    >
                      {submitting ? "..." : "✓ حفظ وترحيل"}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Classic Form */}
        {selectedRegisterId && selectedTemplateId === null && (
          <div style={{ ...cardBase, padding: "24px", marginTop: "16px" }}>
            <div style={{ fontSize: "18px", fontWeight: 800, color: "#0f172a", marginBottom: "16px" }}>📝 وضع الإدخال اليدوي</div>

            {loadingFields ? (
              <div style={{ textAlign: "center", padding: "24px" }}><LoadingSpinner /></div>
            ) : fields && fields.length > 0 ? (
              <>
                {error && (
                  <div style={{ marginBottom: "16px", padding: "12px 16px", background: "#fef2f2", color: "#991b1b", borderRadius: "10px", fontSize: "13px", border: "1px solid #fecaca" }}>
                    {error}
                  </div>
                )}

                <div ref={formContainerRef}>
                  <DynamicReceiptForm
                    fields={fields}
                    disabled={submitting}
                    onTotalChange={setFinancialTotal}
                    onSubmit={handleFormSubmit}
                  />
                </div>

                <div style={{ display: "flex", gap: "10px", marginTop: "16px", paddingTop: "16px", borderTop: "1px solid #f1f5f9" }}>
                  <button
                    onClick={() => triggerSubmit(false)}
                    disabled={submitting || financialTotal <= 0}
                    style={{ padding: "12px 24px", fontSize: "14px", fontWeight: 600, borderRadius: "10px", border: "1px solid #e2e8f0", background: "#fff", color: "#334155", cursor: "pointer", fontFamily: "inherit", opacity: submitting || financialTotal <= 0 ? 0.6 : 1 }}
                  >
                    💾 حفظ مسودة
                  </button>
                  <button
                    onClick={() => triggerSubmit(true)}
                    disabled={submitting || financialTotal <= 0}
                    style={{ padding: "12px 32px", fontSize: "14px", fontWeight: 700, borderRadius: "10px", border: "none", background: "#059669", color: "#fff", cursor: "pointer", fontFamily: "inherit", opacity: submitting || financialTotal <= 0 ? 0.6 : 1, boxShadow: "0 4px 12px rgba(5,150,105,0.25)" }}
                  >
                    ✓ حفظ وترحيل
                  </button>
                </div>
              </>
            ) : (
              <div style={{ fontSize: "14px", color: "#94a3b8", textAlign: "center", padding: "24px" }}>
                لا توجد حقول في هذا السجل
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
