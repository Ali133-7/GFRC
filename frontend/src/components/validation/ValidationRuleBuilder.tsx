import { useState, useMemo } from "react";
import type { WorkflowField, WorkflowRule } from "@/types/workflow";
import { workflowVersionApi } from "@/api/workflows";
import { GovSelect, GovSelectMulti } from "@/components/ui/GovSelect";
import { fieldKey, findFieldByKey, fieldDisplayLabel } from "@/components/rules/fieldKey";

interface ValidationRuleBuilderProps {
  workflowId: string;
  versionId: string;
  rule?: any | null;
  fields: WorkflowField[];
  registers?: any[];
  onSave: () => void;
  onCancel: () => void;
}

const VALIDATION_TYPES = [
  { value: "duplicate_check", label: "منع التكرار", icon: "🚫", desc: "التأكد من عدم وجود قيمة مكررة في السجل" },
  { value: "exists", label: "التحقق من الوجود", icon: "✅", desc: "التأكد من وجود القيمة مسبقاً" },
  { value: "multi_field", label: "تحقق متعدد الحقول", icon: "🔗", desc: "البحث باستخدام أكثر من حقل" },
  { value: "register_search", label: "بحث في السجل", icon: "🔍", desc: "البحث في سجل معين" },
  { value: "query_builder", label: "منشئ الاستعلامات", icon: "🛠️", desc: "بناء استعلام مرئي بدون SQL" },
  { value: "sql", label: "SQL متقدم", icon: "⚙️", desc: "كتابة استعلام يدوي للمشرفين" },
  { value: "field_existence_check", label: "فحص وجود الحقل وتوجيه سير العمل", icon: "🔄", desc: "البحث وتوجيه المستخدم لمسار مختلف" },
];

const RESPONSE_TYPES = [
  { value: "error", label: "خطأ (منع الحفظ)", color: "danger" },
  { value: "warning", label: "تحذير فقط", color: "warning" },
  { value: "confirm", label: "تأكيد قبل المتابعة", color: "info" },
];

function getFieldOptions(fieldId: string, fields: WorkflowField[]): Array<{ label: string; value: string }> | null {
  const field = findFieldByKey(fields, fieldId);
  if (!field) return null;
  const fieldType = field.field_type ?? field.registerField?.field_type ?? "text";
  if (!["select", "dropdown", "radio", "multi_select"].includes(fieldType)) return null;
  const rawOptions = field.options ?? field.registerField?.options ?? null;
  if (!rawOptions) return null;
  if (Array.isArray(rawOptions)) {
    return rawOptions.map((opt: any) => {
      if (typeof opt === "string") return { label: opt, value: opt };
      return { label: opt.label_ar ?? opt.label ?? opt.value, value: opt.value };
    });
  }
  return null;
}

export default function ValidationRuleBuilder({
  workflowId,
  versionId,
  rule,
  fields,
  registers,
  onSave,
  onCancel,
}: ValidationRuleBuilderProps) {
  const [name, setName] = useState(rule?.name ?? "");
  const [description, setDescription] = useState(rule?.description ?? "");
  const [validationType, setValidationType] = useState(rule?.validation_type ?? "duplicate_check");
  const [targetRegisterId, setTargetRegisterId] = useState(rule?.target_register_id ?? "");
  const [targetFields, setTargetFields] = useState<Array<{ workflow_field_id: string; register_field_name: string }>>(
    rule?.target_fields ?? [{ workflow_field_id: "", register_field_name: "" }]
  );
  const [responseType, setResponseType] = useState(rule?.response_type ?? "error");
  const [errorMessageAr, setErrorMessageAr] = useState(rule?.error_message_ar ?? "");
  const [confirmMessageAr, setConfirmMessageAr] = useState(rule?.confirm_message_ar ?? "");
  const [queryConditions, setQueryConditions] = useState(rule?.query_conditions ?? { operator: "and", conditions: [] });
  const [sqlQuery, setSqlQuery] = useState(rule?.sql_query ?? "");
  const [sqlCondition, setSqlCondition] = useState(rule?.sql_condition ?? "");
  const [triggerConditions, setTriggerConditions] = useState<Array<{ field_id: string; operator: string; value: string }>>(
    rule?.trigger_conditions ?? [{ field_id: "", operator: "exact", value: "" }]
  );
  const [lookupConfig, setLookupConfig] = useState<{ database_column: string; lookup_strategy: string }>(
    rule?.lookup_config ?? { database_column: "", lookup_strategy: "exact" }
  );
  const [routeConfig, setRouteConfig] = useState<{
    on_match: { action: string; target_workflow_id?: string; target_step_id?: string; message_ar?: string; actions?: string[] };
    on_not_found: { action: string; message_ar?: string };
  }>(
    rule?.route_config ?? {
      on_match: { action: "warn", message_ar: "تم العثور على سجل سابق مرتبط بهذه القيمة", actions: ["view_existing", "continue_update", "start_renewal"] },
      on_not_found: { action: "continue_workflow" },
    }
  );
  const [fieldEffects, setFieldEffects] = useState<Array<{ action: string; field_id: string; value?: string }>>(
    rule?.field_effects ?? [{ action: "hide", field_id: "", value: "" }]
  );
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  const [simMode, setSimMode] = useState(false);
  const [simValues, setSimValues] = useState<Record<string, string>>({});
  const [simResult, setSimResult] = useState<any>(null);

  const addTargetField = () => {
    setTargetFields([...targetFields, { workflow_field_id: "", register_field_name: "" }]);
  };

  const removeTargetField = (index: number) => {
    setTargetFields(targetFields.filter((_, i) => i !== index));
  };

  const updateTargetField = (index: number, key: string, value: string) => {
    setTargetFields(targetFields.map((tf, i) => (i === index ? { ...tf, [key]: value } : tf)));
  };

  const addFieldEffect = () => {
    setFieldEffects([...fieldEffects, { action: "hide", field_id: "", value: "" }]);
  };

  const removeFieldEffect = (index: number) => {
    setFieldEffects(fieldEffects.filter((_, i) => i !== index));
  };

  const updateFieldEffect = (index: number, key: string, value: string) => {
    setFieldEffects(fieldEffects.map((fe, i) => (i === index ? { ...fe, [key]: value } : fe)));
  };

  const addTriggerCondition = () => {
    setTriggerConditions([...triggerConditions, { field_id: "", operator: "exact", value: "" }]);
  };

  const removeTriggerCondition = (index: number) => {
    if (triggerConditions.length > 1) {
      setTriggerConditions(triggerConditions.filter((_, i) => i !== index));
    }
  };

  const updateTriggerCondition = (index: number, key: string, value: string) => {
    setTriggerConditions(triggerConditions.map((tc, i) => (i === index ? { ...tc, [key]: value } : tc)));
  };

  const validate = (): boolean => {
    const errs: string[] = [];
    if (!name.trim()) errs.push("اسم القاعدة مطلوب");
    if (!targetRegisterId) errs.push("السجل الهدف مطلوب");
    if (["duplicate_check", "exists", "multi_field", "register_search"].includes(validationType)) {
      targetFields.forEach((tf, i) => {
        if (!tf.workflow_field_id) errs.push(`الحقل ${i + 1}: اختر حقل من سير العمل`);
        if (!tf.register_field_name) errs.push(`الحقل ${i + 1}: أدخل اسم الحقل في السجل`);
      });
    }
    if (validationType === "sql" && !sqlQuery.trim()) errs.push("استعلام SQL مطلوب");
    if (validationType === "field_existence_check") {
      const validTriggers = triggerConditions.filter((tc) => tc.field_id);
      if (validTriggers.length === 0) errs.push("يجب تحديد حقل مشغّل واحد على الأقل");
    }
    if (responseType === "error" && !errorMessageAr.trim()) errs.push("رسالة الخطأ مطلوبة");
    if (responseType === "confirm" && !confirmMessageAr.trim()) errs.push("رسالة التأكيد مطلوبة");
    setErrors(errs);
    return errs.length === 0;
  };

  const handleSave = async () => {
    if (!validate()) return;
    setSaving(true);
    try {
      const payload: any = {
        name: name.trim(),
        description: description.trim() || null,
        validation_type: validationType,
        target_register_id: targetRegisterId,
        response_type: responseType,
        error_message_ar: errorMessageAr.trim() || null,
        confirm_message_ar: confirmMessageAr.trim() || null,
        sort_order: rule?.sort_order ?? 0,
        is_active: true,
      };

      if (["duplicate_check", "exists", "multi_field", "register_search"].includes(validationType)) {
        payload.target_fields = targetFields;
      }
      if (validationType === "query_builder") {
        payload.query_conditions = queryConditions;
      }
      if (validationType === "sql") {
        payload.sql_query = sqlQuery;
        payload.sql_condition = sqlCondition;
      }
      if (validationType === "field_existence_check") {
        payload.trigger_conditions = triggerConditions.filter((tc) => tc.field_id);
        payload.lookup_config = lookupConfig;
        payload.route_config = routeConfig;
        payload.field_effects = fieldEffects.filter((fe) => fe.field_id);
      }

      if (rule?.id) {
        await workflowVersionApi.updateValidationRule(workflowId, versionId, rule.id, payload);
      } else {
        await workflowVersionApi.createValidationRule(workflowId, versionId, payload);
      }
      onSave();
    } catch (err: any) {
      setErrors([err?.response?.data?.message ?? "فشل حفظ القاعدة"]);
    } finally {
      setSaving(false);
    }
  };

  const handleSimulate = async () => {
    try {
      const result = await workflowVersionApi.simulateValidation(workflowId, versionId, simValues);
      setSimResult(result);
    } catch (err: any) {
      setErrors([err?.response?.data?.message ?? "فشل المحاكاة"]);
    }
  };

  const selectedType = VALIDATION_TYPES.find((t) => t.value === validationType);

  return (
    <div
      style={{
        background: "var(--color-background-primary)",
        border: "1px solid var(--color-border-warning)",
        borderRadius: "var(--border-radius-lg)",
      }}
    >
      {/* Header */}
      <div
        style={{
          padding: "14px 18px",
          borderBottom: "0.5px solid var(--color-border-tertiary)",
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
        }}
      >
        <div style={{ fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)" }}>
          {rule?.id ? "تعديل قاعدة التحقق" : "قاعدة تحقق جديدة"}
        </div>
        <button
          onClick={() => setSimMode(!simMode)}
          style={{
            padding: "5px 12px",
            fontSize: "12px",
            background: simMode ? "var(--color-background-success)" : "transparent",
            color: simMode ? "var(--color-text-success)" : "var(--color-text-secondary)",
            border: `0.5px solid ${simMode ? "var(--color-border-success)" : "var(--color-border-secondary)"}`,
            borderRadius: "6px",
            cursor: "pointer",
            fontFamily: "inherit",
          }}
        >
          {simMode ? "إيقاف المحاكاة" : "محاكاة"}
        </button>
      </div>

      {/* Errors */}
      {errors.length > 0 && (
        <div style={{ padding: "10px 18px", background: "var(--color-background-danger)", borderBottom: "0.5px solid var(--color-border-danger)" }}>
          {errors.map((e, i) => (
            <div key={i} style={{ fontSize: "12px", color: "var(--color-text-danger)" }}>• {e}</div>
          ))}
        </div>
      )}

      {/* Simulation panel */}
      {simMode && (
        <div style={{ padding: "12px 18px", background: "var(--color-background-success)", borderBottom: "0.5px solid var(--color-border-success)" }}>
          <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-success)", marginBottom: "8px" }}>
            محاكاة التحقق
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: "8px", marginBottom: "8px" }}>
            {fields.map((f) => (
              <input
                key={f.id}
                value={simValues[f.id] ?? ""}
                onChange={(e) => setSimValues({ ...simValues, [f.id]: e.target.value })}
                placeholder={f.label}
                style={inputStyle}
              />
            ))}
          </div>
          <button onClick={handleSimulate} style={btnPrimary}>تشغيل المحاكاة</button>
          {simResult && (
            <div style={{ marginTop: "10px", fontSize: "12px" }}>
              <div>القواعد الكلية: {simResult.total_rules}</div>
              <div>ناجحة: {simResult.passed_count}</div>
              <div>فاشلة: {simResult.failed_count}</div>
              {simResult.results?.map((r: any, i: number) => (
                <div key={i} style={{ marginTop: "4px", color: r.status === "passed" ? "var(--color-text-success)" : "var(--color-text-danger)" }}>
                  {r.status === "passed" ? "✓" : "✗"} {r.rule_name} ({r.validation_type})
                  {r.message && ` — ${r.message}`}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      <div style={{ padding: "18px" }}>
        {/* Rule name & description */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "16px" }}>
          <div>
            <label style={labelStyle}>اسم القاعدة</label>
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="مثال: منع تكرار رقم الضبارة" style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>الوصف (اختياري)</label>
            <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="وصف مختصر..." style={inputStyle} />
          </div>
        </div>

        {/* Validation type selector */}
        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>نوع التحقق</label>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: "8px" }}>
            {VALIDATION_TYPES.map((t) => (
              <button
                key={t.value}
                onClick={() => setValidationType(t.value)}
                style={{
                  padding: "10px 12px",
                  textAlign: "right",
                  borderRadius: "var(--border-radius-md)",
                  border: validationType === t.value ? "1px solid var(--color-border-warning)" : "0.5px solid var(--color-border-tertiary)",
                  background: validationType === t.value ? "var(--color-background-warning)" : "var(--color-background-secondary)",
                  color: validationType === t.value ? "var(--color-text-warning)" : "var(--color-text-secondary)",
                  cursor: "pointer",
                  fontFamily: "inherit",
                }}
              >
                <div style={{ fontSize: "13px", fontWeight: 500 }}>{t.icon} {t.label}</div>
                <div style={{ fontSize: "11px", marginTop: "2px", opacity: 0.8 }}>{t.desc}</div>
              </button>
            ))}
          </div>
        </div>

        {/* Target register */}
        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>السجل الهدف</label>
          <select value={targetRegisterId} onChange={(e) => setTargetRegisterId(e.target.value)} style={{ ...inputStyle, width: "100%" }}>
            <option value="">اختر السجل...</option>
            {registers?.map((r: any) => (
              <option key={r.id} value={r.id}>{r.name_ar || r.name || r.code || r.id}</option>
            ))}
          </select>
        </div>

        {/* Target fields mapping */}
        {["duplicate_check", "exists", "multi_field", "register_search"].includes(validationType) && (
          <div style={{ marginBottom: "16px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
              <label style={labelStyle}>ربط الحقول</label>
              <button onClick={addTargetField} style={btnGhost}>+ إضافة حقل</button>
            </div>
            {targetFields.map((tf, idx) => (
              <div key={idx} style={{ display: "flex", gap: "8px", marginBottom: "6px", alignItems: "center" }}>
                <select
                  value={tf.workflow_field_id}
                  onChange={(e) => updateTargetField(idx, "workflow_field_id", e.target.value)}
                  style={{ ...inputStyle, flex: 1 }}
                >
                  <option value="">حقل من سير العمل...</option>
                  {fields.map((f) => (
                    <option key={f.id} value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>
                  ))}
                </select>
                <span style={{ color: "var(--color-text-tertiary)", fontSize: "14px" }}>→</span>
                <input
                  value={tf.register_field_name}
                  onChange={(e) => updateTargetField(idx, "register_field_name", e.target.value)}
                  placeholder="اسم الحقل في السجل"
                  style={{ ...inputStyle, flex: 1 }}
                />
                {targetFields.length > 1 && (
                  <button onClick={() => removeTargetField(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "14px", color: "var(--color-text-danger)" }}>×</button>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Query Builder */}
        {validationType === "query_builder" && (
          <div style={{ marginBottom: "16px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "var(--border-radius-md)" }}>
            <label style={labelStyle}>شروط الاستعلام (Query Builder)</label>
            <div style={{ fontSize: "12px", color: "var(--color-text-tertiary)", padding: "8px" }}>
              سيتم دعم منشئ الاستعلامات المرئي في التحديث القادم. حالياً استخدم النوع SQL متقدم.
            </div>
          </div>
        )}

        {/* SQL */}
        {validationType === "sql" && (
          <div style={{ marginBottom: "16px" }}>
            <div>
              <label style={labelStyle}>استعلام SQL</label>
              <textarea
                value={sqlQuery}
                onChange={(e) => setSqlQuery(e.target.value)}
                placeholder={"SELECT COUNT(*) as cnt FROM records WHERE register_id = ? AND file_number = {{file_number}}"}
                style={{ ...inputStyle, minHeight: "80px", fontFamily: "monospace", direction: "ltr" }}
              />
            </div>
            <div style={{ marginTop: "8px" }}>
              <label style={labelStyle}>شرط النجاح (مثال: count = 0)</label>
              <input value={sqlCondition} onChange={(e) => setSqlCondition(e.target.value)} placeholder="count = 0" style={inputStyle} />
            </div>
          </div>
        )}

        {/* Field Existence Check */}
        {validationType === "field_existence_check" && (
          <div style={{ marginBottom: "16px" }}>
            {/* Trigger conditions */}
            <div style={{ marginBottom: "12px" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                <label style={{ ...labelStyle, marginBottom: 0 }}>شروط التشغيل (Trigger Conditions)</label>
                <button onClick={addTriggerCondition} style={btnGhost}>+ إضافة شرط</button>
              </div>
              {fields.length === 0 && (
                <div style={{ fontSize: "12px", color: "var(--color-text-tertiary)", padding: "8px", background: "var(--color-background-secondary)", borderRadius: "6px", marginBottom: "8px" }}>
                  ⚠️ لا توجد حقول في سير العمل. أضف حقولاً أولاً من تبويب "الحقول".
                </div>
              )}
              {triggerConditions.map((tc, idx) => (
                <div key={idx} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
                  <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)", minWidth: "20px" }}>{idx + 1}.</span>
                  <select
                    value={tc.field_id}
                    onChange={(e) => updateTriggerCondition(idx, "field_id", e.target.value)}
                    style={{ ...inputStyle, flex: 1 }}
                  >
                    <option value="">اختر الحقل...</option>
                    {fields.map((f) => (
                      <option key={f.id} value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>
                    ))}
                  </select>
                  <select
                    value={tc.operator}
                    onChange={(e) => updateTriggerCondition(idx, "operator", e.target.value)}
                    style={{ ...inputStyle, flex: 1, minWidth: "120px" }}
                  >
                    <option value="exact">مطابقة تامة</option>
                    <option value="contains">يحتوي على</option>
                    <option value="starts_with">يبدأ بـ</option>
                    <option value="ends_with">ينتهي بـ</option>
                    <option value="not_equals">لا يساوي</option>
                    <option value="empty">فارغ</option>
                    <option value="not_empty">غير فارغ</option>
                  </select>
                  {!["empty", "not_empty"].includes(tc.operator) && (
                    (() => {
                      const opts = getFieldOptions(tc.field_id, fields);
                      if (opts) {
                        return (
                          <GovSelect
                            options={opts}
                            value={tc.value}
                            onChange={(val) => updateTriggerCondition(idx, "value", val)}
                            placeholder="اختر قيمة..."
                            className="flex-1"
                          />
                        );
                      }
                      return (
                        <input
                          value={tc.value}
                          onChange={(e) => updateTriggerCondition(idx, "value", e.target.value)}
                          placeholder="القيمة..."
                          style={{ ...inputStyle, flex: 1 }}
                        />
                      );
                    })()
                  )}
                  {triggerConditions.length > 1 && (
                    <button onClick={() => removeTriggerCondition(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "14px", color: "var(--color-text-danger)" }}>×</button>
                  )}
                </div>
              ))}
            </div>

            {/* Database column mapping */}
            <div style={{ marginBottom: "12px" }}>
              <label style={labelStyle}>العمود في قاعدة البيانات (Database Column)</label>
              <input
                value={lookupConfig.database_column}
                onChange={(e) => setLookupConfig({ ...lookupConfig, database_column: e.target.value })}
                placeholder="مثال: file_number"
                style={inputStyle}
              />
            </div>

            {/* Lookup strategy */}
            <div style={{ marginBottom: "12px" }}>
              <label style={labelStyle}>استراتيجية البحث</label>
              <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
                {[
                  { value: "exact", label: "مطابقة تامة" },
                  { value: "contains", label: "يحتوي على" },
                  { value: "starts_with", label: "يبدأ بـ" },
                  { value: "ends_with", label: "ينتهي بـ" },
                ].map((s) => (
                  <button
                    key={s.value}
                    onClick={() => setLookupConfig({ ...lookupConfig, lookup_strategy: s.value })}
                    style={{
                      padding: "4px 10px",
                      fontSize: "12px",
                      borderRadius: "16px",
                      border: lookupConfig.lookup_strategy === s.value ? "1px solid var(--color-border-warning)" : "0.5px solid var(--color-border-tertiary)",
                      background: lookupConfig.lookup_strategy === s.value ? "var(--color-background-warning)" : "var(--color-background-secondary)",
                      color: lookupConfig.lookup_strategy === s.value ? "var(--color-text-warning)" : "var(--color-text-secondary)",
                      cursor: "pointer",
                      fontFamily: "inherit",
                    }}
                  >
                    {s.label}
                  </button>
                ))}
              </div>
            </div>

            {/* On match routing */}
            <div style={{ marginBottom: "12px", padding: "12px", background: "var(--color-background-warning)", borderRadius: "var(--border-radius-md)", border: "0.5px solid var(--color-border-warning)" }}>
              <label style={{ ...labelStyle, color: "var(--color-text-warning)" }}>عند العثور على سجل (On Match)</label>
              <div style={{ display: "flex", gap: "6px", marginBottom: "8px" }}>
                {[
                  { value: "warn", label: "⚠️ تحذير وخيارات" },
                  { value: "route_workflow", label: "🔄 تحويل لسير عمل آخر" },
                  { value: "block", label: "🚫 منع" },
                ].map((a) => (
                  <button
                    key={a.value}
                    onClick={() => setRouteConfig({ ...routeConfig, on_match: { ...routeConfig.on_match, action: a.value } })}
                    style={{
                      padding: "4px 10px",
                      fontSize: "12px",
                      borderRadius: "16px",
                      border: routeConfig.on_match.action === a.value ? "1px solid var(--color-text-warning)" : "0.5px solid var(--color-border-warning)",
                      background: routeConfig.on_match.action === a.value ? "var(--color-background-primary)" : "transparent",
                      color: "var(--color-text-warning)",
                      cursor: "pointer",
                      fontFamily: "inherit",
                    }}
                  >
                    {a.label}
                  </button>
                ))}
              </div>
              <input
                value={routeConfig.on_match.message_ar ?? ""}
                onChange={(e) => setRouteConfig({ ...routeConfig, on_match: { ...routeConfig.on_match, message_ar: e.target.value } })}
                placeholder="رسالة للمستخدم..."
                style={inputStyle}
              />
              {routeConfig.on_match.action === "route_workflow" && (
                <div style={{ marginTop: "8px" }}>
                  <label style={labelStyle}>سير العمل الهدف</label>
                  <input
                    value={routeConfig.on_match.target_workflow_id ?? ""}
                    onChange={(e) => setRouteConfig({ ...routeConfig, on_match: { ...routeConfig.on_match, target_workflow_id: e.target.value } })}
                    placeholder="UUID لسير العمل"
                    style={{ ...inputStyle, fontFamily: "monospace" }}
                  />
                </div>
              )}
            </div>

            {/* On not found */}
            <div style={{ padding: "12px", background: "var(--color-background-success)", borderRadius: "var(--border-radius-md)", border: "0.5px solid var(--color-border-success)" }}>
              <label style={{ ...labelStyle, color: "var(--color-text-success)" }}>عند عدم العثور (On Not Found)</label>
              <div style={{ fontSize: "13px", color: "var(--color-text-success)" }}>
                ✓ متابعة سير العمل الطبيعي
              </div>
            </div>

            {/* Field Effects */}
            <div style={{ marginTop: "12px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "var(--border-radius-md)", border: "0.5px solid var(--color-border-tertiary)" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                <label style={{ ...labelStyle, marginBottom: 0 }}>تأثيرات على الحقول (Field Effects)</label>
                <button onClick={addFieldEffect} style={btnGhost}>+ إضافة تأثير</button>
              </div>
              {fieldEffects.map((fe, idx) => (
                <div key={idx} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
                  <select
                    value={fe.action}
                    onChange={(e) => updateFieldEffect(idx, "action", e.target.value)}
                    style={{ ...inputStyle, flex: 1 }}
                  >
                    <option value="hide">إخفاء</option>
                    <option value="show">إظهار</option>
                    <option value="set_value">تعيين قيمة</option>
                    <option value="set_required">تعيين مطلوب</option>
                    <option value="set_readonly">تعيين للقراءة فقط</option>
                  </select>
                  <select
                    value={fe.field_id}
                    onChange={(e) => updateFieldEffect(idx, "field_id", e.target.value)}
                    style={{ ...inputStyle, flex: 1 }}
                  >
                    <option value="">اختر الحقل...</option>
                    {fields.map((f) => (
                      <option key={f.id} value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>
                    ))}
                  </select>
                  {fe.action === "set_value" && (
                    <input
                      value={fe.value ?? ""}
                      onChange={(e) => updateFieldEffect(idx, "value", e.target.value)}
                      placeholder="القيمة"
                      style={{ ...inputStyle, flex: 1 }}
                    />
                  )}
                  {fieldEffects.length > 1 && (
                    <button onClick={() => removeFieldEffect(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "14px", color: "var(--color-text-danger)" }}>×</button>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Response type */}
        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>نوع الاستجابة</label>
          <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
            {RESPONSE_TYPES.map((r) => (
              <button
                key={r.value}
                onClick={() => setResponseType(r.value)}
                style={{
                  padding: "6px 12px",
                  fontSize: "12px",
                  borderRadius: "16px",
                  border: responseType === r.value ? `1px solid var(--color-border-${r.color})` : "0.5px solid var(--color-border-tertiary)",
                  background: responseType === r.value ? `var(--color-background-${r.color})` : "var(--color-background-secondary)",
                  color: responseType === r.value ? `var(--color-text-${r.color})` : "var(--color-text-secondary)",
                  cursor: "pointer",
                  fontFamily: "inherit",
                  fontWeight: responseType === r.value ? 500 : 400,
                }}
              >
                {r.label}
              </button>
            ))}
          </div>
        </div>

        {/* Error message */}
        {responseType === "error" && (
          <div style={{ marginBottom: "16px" }}>
            <label style={labelStyle}>رسالة الخطأ</label>
            <input value={errorMessageAr} onChange={(e) => setErrorMessageAr(e.target.value)} placeholder="رقم الضبارة موجود مسبقاً" style={inputStyle} />
          </div>
        )}

        {/* Confirm message */}
        {responseType === "confirm" && (
          <div style={{ marginBottom: "16px" }}>
            <label style={labelStyle}>رسالة التأكيد</label>
            <input value={confirmMessageAr} onChange={(e) => setConfirmMessageAr(e.target.value)} placeholder="رقم الضبارة موجود. هل تريد المتابعة؟" style={inputStyle} />
          </div>
        )}

        {/* Action buttons */}
        <div style={{ display: "flex", gap: "8px", justifyContent: "flex-end", paddingTop: "12px", borderTop: "0.5px solid var(--color-border-tertiary)" }}>
          <button onClick={onCancel} style={btnSecondary}>إلغاء</button>
          <button onClick={handleSave} disabled={saving} style={btnPrimary}>
            {saving ? "جارٍ الحفظ..." : "حفظ قاعدة التحقق"}
          </button>
        </div>
      </div>
    </div>
  );
}

const labelStyle: React.CSSProperties = { display: "block", fontSize: "12px", color: "var(--color-text-secondary)", marginBottom: "4px" };
const inputStyle: React.CSSProperties = {
  padding: "6px 10px",
  fontSize: "12px",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  fontFamily: "inherit",
  width: "100%",
};
const btnPrimary: React.CSSProperties = {
  padding: "6px 14px",
  fontSize: "12px",
  background: "var(--color-background-warning)",
  color: "var(--color-text-warning)",
  border: "0.5px solid var(--color-border-warning)",
  borderRadius: "6px",
  cursor: "pointer",
  fontFamily: "inherit",
  fontWeight: 500,
};
const btnSecondary: React.CSSProperties = {
  padding: "6px 14px",
  fontSize: "12px",
  background: "none",
  color: "var(--color-text-secondary)",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  cursor: "pointer",
  fontFamily: "inherit",
};
const btnGhost: React.CSSProperties = {
  padding: "4px 10px",
  fontSize: "11px",
  background: "transparent",
  color: "var(--color-text-warning)",
  border: "0.5px dashed var(--color-border-warning)",
  borderRadius: "4px",
  cursor: "pointer",
  fontFamily: "inherit",
};
