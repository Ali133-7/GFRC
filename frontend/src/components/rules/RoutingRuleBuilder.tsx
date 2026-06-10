import { useState } from "react";
import type { WorkflowField } from "@/types/workflow";
import { workflowVersionApi } from "@/api/workflows";
import { fieldKey, fieldDisplayLabel } from "./fieldKey";

interface RoutingRuleBuilderProps {
  workflowId: string;
  versionId: string;
  rule?: any | null;
  fields: WorkflowField[];
  registers?: any[];
  onSave: () => void;
  onCancel: () => void;
}

const inputStyle: React.CSSProperties = {
  padding: "6px 10px",
  fontSize: "13px",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  fontFamily: "inherit",
  background: "var(--color-background-primary)",
  color: "var(--color-text-primary)",
  width: "100%",
  boxSizing: "border-box",
};

const labelStyle: React.CSSProperties = {
  display: "block",
  fontSize: "12px",
  fontWeight: 600,
  color: "var(--color-text-secondary)",
  marginBottom: "4px",
};

const ON_MATCH_ACTIONS = [
  { value: "warn", label: "⚠️ تحذير وخيارات" },
  { value: "route_workflow", label: "🔄 تحويل لسير عمل آخر" },
  { value: "block", label: "🚫 منع" },
];

const LOOKUP_STRATEGIES = [
  { value: "exact", label: "مطابقة تامة" },
  { value: "contains", label: "يحتوي على" },
  { value: "starts_with", label: "يبدأ بـ" },
  { value: "ends_with", label: "ينتهي بـ" },
];

/**
 * Editor for ROUTING rules — validation_rules with validation_type =
 * 'field_existence_check' and a route_config. It searches a register and routes the
 * workflow on a match. Distinct first-class editor (no longer buried inside the
 * generic validation builder), and it never carries a rule_config (which would make
 * it classify as enterprise).
 */
export default function RoutingRuleBuilder({
  workflowId,
  versionId,
  rule,
  fields,
  registers,
  onSave,
  onCancel,
}: RoutingRuleBuilderProps) {
  const [name, setName] = useState(rule?.name ?? "");
  const [description, setDescription] = useState(rule?.description ?? "");
  const [targetRegisterId, setTargetRegisterId] = useState(rule?.target_register_id ?? "");
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
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);

  const updateTrigger = (i: number, key: string, val: string) =>
    setTriggerConditions(triggerConditions.map((tc, idx) => (idx === i ? { ...tc, [key]: val } : tc)));
  const addTrigger = () => setTriggerConditions([...triggerConditions, { field_id: "", operator: "exact", value: "" }]);
  const removeTrigger = (i: number) => setTriggerConditions(triggerConditions.filter((_, idx) => idx !== i));

  const validate = (): boolean => {
    const errs: string[] = [];
    if (!name.trim()) errs.push("اسم القاعدة مطلوب");
    if (!targetRegisterId) errs.push("السجل الهدف مطلوب");
    if (!lookupConfig.database_column.trim()) errs.push("عمود البحث في السجل مطلوب");
    if (triggerConditions.filter((tc) => tc.field_id).length === 0) errs.push("يجب تحديد حقل مشغّل واحد على الأقل");
    if (routeConfig.on_match.action === "route_workflow" && !routeConfig.on_match.target_workflow_id?.trim()) {
      errs.push("سير العمل الهدف مطلوب عند اختيار التحويل");
    }
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
        validation_type: "field_existence_check",
        target_register_id: targetRegisterId,
        trigger_conditions: triggerConditions.filter((tc) => tc.field_id),
        lookup_config: lookupConfig,
        route_config: routeConfig,
        response_type: routeConfig.on_match.action === "block" ? "error" : "warning",
        sort_order: rule?.sort_order ?? 0,
        is_active: true,
      };

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

  return (
    <div style={{ background: "var(--color-background-primary)", border: "1px solid var(--color-border-warning)", borderRadius: "var(--border-radius-lg)" }}>
      <div style={{ padding: "14px 18px", borderBottom: "0.5px solid var(--color-border-tertiary)", fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)" }}>
        {rule?.id ? "تعديل قاعدة توجيه" : "قاعدة توجيه جديدة"}
      </div>

      {errors.length > 0 && (
        <div style={{ padding: "10px 18px", background: "var(--color-background-danger)", borderBottom: "0.5px solid var(--color-border-danger)" }}>
          {errors.map((e, i) => (
            <div key={i} style={{ fontSize: "12px", color: "var(--color-text-danger)" }}>• {e}</div>
          ))}
        </div>
      )}

      <div style={{ padding: "16px 18px", display: "flex", flexDirection: "column", gap: "14px" }}>
        <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="اسم القاعدة" style={{ ...inputStyle, flex: 2, minWidth: "180px" }} />
          <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="الوصف (اختياري)" style={{ ...inputStyle, flex: 3, minWidth: "180px" }} />
        </div>

        <div>
          <label style={labelStyle}>السجل الهدف</label>
          <select value={targetRegisterId} onChange={(e) => setTargetRegisterId(e.target.value)} style={inputStyle}>
            <option value="">اختر السجل...</option>
            {(registers ?? []).map((r: any) => (
              <option key={r.id} value={r.id}>{r.name_ar ?? r.code ?? r.id}</option>
            ))}
          </select>
        </div>

        <div>
          <label style={labelStyle}>عمود البحث + الاستراتيجية</label>
          <div style={{ display: "flex", gap: "6px" }}>
            <input value={lookupConfig.database_column} onChange={(e) => setLookupConfig({ ...lookupConfig, database_column: e.target.value })} placeholder="اسم العمود في السجل" style={{ ...inputStyle, flex: 2 }} />
            <select value={lookupConfig.lookup_strategy} onChange={(e) => setLookupConfig({ ...lookupConfig, lookup_strategy: e.target.value })} style={{ ...inputStyle, flex: 1 }}>
              {LOOKUP_STRATEGIES.map((s) => (
                <option key={s.value} value={s.value}>{s.label}</option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label style={labelStyle}>الحقول المشغّلة (Trigger)</label>
          {triggerConditions.map((tc, i) => (
            <div key={i} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
              <select value={tc.field_id} onChange={(e) => updateTrigger(i, "field_id", e.target.value)} style={{ ...inputStyle, flex: 2 }}>
                <option value="">اختر الحقل...</option>
                {fields.map((f) => (
                  <option key={f.id} value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>
                ))}
              </select>
              <button onClick={() => removeTrigger(i)} style={{ background: "none", border: "none", cursor: "pointer", color: "var(--color-text-danger)", fontSize: "16px" }}>×</button>
            </div>
          ))}
          <button onClick={addTrigger} style={{ ...inputStyle, width: "auto", cursor: "pointer", background: "var(--color-background-secondary)" }}>+ إضافة مشغّل</button>
        </div>

        <div style={{ padding: "12px", background: "var(--color-background-warning)", borderRadius: "var(--border-radius-md)", border: "0.5px solid var(--color-border-warning)" }}>
          <label style={{ ...labelStyle, color: "var(--color-text-warning)" }}>عند العثور على سجل (On Match)</label>
          <div style={{ display: "flex", gap: "6px", marginBottom: "8px", flexWrap: "wrap" }}>
            {ON_MATCH_ACTIONS.map((a) => (
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

        <div style={{ padding: "12px", background: "var(--color-background-success)", borderRadius: "var(--border-radius-md)", border: "0.5px solid var(--color-border-success)" }}>
          <label style={{ ...labelStyle, color: "var(--color-text-success)" }}>عند عدم العثور (On Not Found)</label>
          <div style={{ fontSize: "13px", color: "var(--color-text-success)" }}>✓ متابعة سير العمل الطبيعي</div>
        </div>
      </div>

      <div style={{ padding: "12px 18px", borderTop: "0.5px solid var(--color-border-tertiary)", display: "flex", gap: "8px", justifyContent: "flex-end" }}>
        <button onClick={onCancel} style={{ ...inputStyle, width: "auto", cursor: "pointer" }}>إلغاء</button>
        <button onClick={handleSave} disabled={saving} style={{ ...inputStyle, width: "auto", cursor: "pointer", background: "var(--color-background-warning)", color: "var(--color-text-warning)", border: "0.5px solid var(--color-border-warning)" }}>
          {saving ? "جارٍ الحفظ..." : "حفظ"}
        </button>
      </div>
    </div>
  );
}
