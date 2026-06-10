import { useState } from "react";
import type { WorkflowRule, RuleAction, WorkflowField, ConditionLogic } from "@/types/workflow";
import { workflowVersionApi } from "@/api/workflows";
import { useOfficialFees } from "@/hooks/useFees";
import { fieldKey, findFieldByKey, fieldDisplayLabel, isChoiceField, getFieldOptions } from "./fieldKey";

interface SimpleRuleBuilderProps {
  workflowId: string;
  versionId: string;
  rule?: WorkflowRule | null;
  fields: WorkflowField[];
  registers?: any[];
  onSave: () => void;
  onCancel: () => void;
}

const CONDITION_OPERATORS = [
  { value: "equals", label: "يساوي" },
  { value: "not_equals", label: "لا يساوي" },
  { value: "gt", label: "أكبر من" },
  { value: "gte", label: "أكبر أو يساوي" },
  { value: "lt", label: "أصغر من" },
  { value: "lte", label: "أصغر أو يساوي" },
  { value: "contains", label: "يحتوي على" },
  { value: "is_empty", label: "فارغ" },
  { value: "is_not_empty", label: "غير فارغ" },
];

const ACTION_TYPES: { value: string; label: string }[] = [
  { value: "set_value", label: "تعيين قيمة" },
  { value: "set_fee", label: "تعيين رسوم" },
  { value: "calculate", label: "حساب صيغة" },
  { value: "show", label: "إظهار" },
  { value: "hide", label: "إخفاء" },
  { value: "set_required", label: "إلزامي" },
  { value: "set_readonly", label: "منع التعديل" },
];

const NO_VALUE_OPERATORS = ["is_empty", "is_not_empty"];

interface SimpleCond {
  field_id: string;
  operator: string;
  value: string;
}

const inputStyle: React.CSSProperties = {
  padding: "6px 10px",
  fontSize: "13px",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  fontFamily: "inherit",
  background: "var(--color-background-primary)",
  color: "var(--color-text-primary)",
};

/**
 * Editor for SIMPLE workflow rules (workflow_rules table, rule_type = 'simple').
 * Reads/writes condition_logic (an object) + actions, and persists via the
 * workflow-rules endpoints — NOT the validation-rules endpoints. A simple rule
 * created or edited here stays a simple rule for its whole lifecycle.
 */
export default function SimpleRuleBuilder({
  workflowId,
  versionId,
  rule,
  fields,
  onSave,
  onCancel,
}: SimpleRuleBuilderProps) {
  const [name, setName] = useState(rule?.name ?? "");
  const [description, setDescription] = useState(rule?.description ?? "");

  // Normalize the stored condition_logic (flat single OR grouped) into a flat list.
  const initial = normalizeConditions(rule?.condition_logic ?? null);
  const [logicOperator, setLogicOperator] = useState<"and" | "or">(initial.operator);
  const [conditions, setConditions] = useState<SimpleCond[]>(initial.conditions);
  const [actions, setActions] = useState<RuleAction[]>(rule?.actions ?? []);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  const { data: officialFees } = useOfficialFees();

  const addCondition = () =>
    setConditions([...conditions, { field_id: "", operator: "equals", value: "" }]);
  const removeCondition = (i: number) => setConditions(conditions.filter((_, idx) => idx !== i));
  const updateCondition = (i: number, key: keyof SimpleCond, val: string) =>
    setConditions(conditions.map((c, idx) => (idx === i ? { ...c, [key]: val } : c)));

  const addAction = () =>
    setActions([...actions, { action: "set_value", target_field_id: "", value: "" }]);
  const removeAction = (i: number) => setActions(actions.filter((_, idx) => idx !== i));
  const updateAction = (i: number, key: keyof RuleAction, val: any) =>
    setActions(actions.map((a, idx) => (idx === i ? { ...a, [key]: val } : a)));

  const validate = (): boolean => {
    const errs: string[] = [];
    if (!name.trim()) errs.push("اسم القاعدة مطلوب");
    if (conditions.length === 0) errs.push("يجب إضافة شرط واحد على الأقل");
    if (conditions.some((c) => !c.field_id)) errs.push("كل شرط يحتاج حقلاً");
    if (actions.length === 0) errs.push("يجب إضافة إجراء واحد على الأقل");
    if (actions.some((a) => !a.target_field_id && a.action !== "skip_step")) errs.push("كل إجراء يحتاج حقلاً هدفاً");
    setErrors(errs);
    return errs.length === 0;
  };

  const handleSave = async () => {
    if (!validate()) return;
    setSaving(true);
    try {
      const condition_logic: ConditionLogic = {
        operator: logicOperator,
        conditions: conditions.map((c) => ({
          field_id: c.field_id,
          operator: c.operator,
          value: NO_VALUE_OPERATORS.includes(c.operator) ? undefined : c.value,
        })) as ConditionLogic[],
      };

      const payload: Partial<WorkflowRule> = {
        name: name.trim(),
        description: description.trim() || null,
        rule_type: "simple",
        condition_logic,
        actions,
        sort_order: rule?.sort_order ?? 0,
        is_active: true,
      };

      if (rule?.id) {
        await workflowVersionApi.updateRule(workflowId, versionId, rule.id, payload);
      } else {
        await workflowVersionApi.createRule(workflowId, versionId, payload);
      }
      onSave();
    } catch (err: any) {
      setErrors([err?.response?.data?.message ?? "فشل حفظ القاعدة"]);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ background: "var(--color-background-primary)", border: "1px solid var(--color-border-secondary)", borderRadius: "var(--border-radius-lg)" }}>
      <div style={{ padding: "14px 18px", borderBottom: "0.5px solid var(--color-border-tertiary)", fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)" }}>
        {rule?.id ? "تعديل قاعدة بسيطة" : "قاعدة بسيطة جديدة"}
      </div>

      {errors.length > 0 && (
        <div style={{ padding: "10px 18px", background: "var(--color-background-danger)", borderBottom: "0.5px solid var(--color-border-danger)" }}>
          {errors.map((e, i) => (
            <div key={i} style={{ fontSize: "12px", color: "var(--color-text-danger)" }}>• {e}</div>
          ))}
        </div>
      )}

      <div style={{ padding: "16px 18px", display: "flex", flexDirection: "column", gap: "16px" }}>
        <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="اسم القاعدة" style={{ ...inputStyle, flex: 2, minWidth: "180px" }} />
          <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="الوصف (اختياري)" style={{ ...inputStyle, flex: 3, minWidth: "180px" }} />
        </div>

        {/* Conditions */}
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "8px" }}>
            <span style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)" }}>الشروط (IF)</span>
            <select value={logicOperator} onChange={(e) => setLogicOperator(e.target.value as "and" | "or")} style={{ ...inputStyle, padding: "4px 8px" }}>
              <option value="and">كل الشروط (AND)</option>
              <option value="or">أي شرط (OR)</option>
            </select>
          </div>
          {conditions.map((c, i) => {
            const condField = findFieldByKey(fields, c.field_id);
            const choice = condField && isChoiceField(condField);
            const options = condField ? getFieldOptions(condField) : [];
            return (
            <div key={i} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
              <select value={c.field_id} onChange={(e) => updateCondition(i, "field_id", e.target.value)} style={{ ...inputStyle, flex: 2 }}>
                <option value="">اختر الحقل...</option>
                {fields.map((f) => (
                  <option key={f.id} value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>
                ))}
              </select>
              <select value={c.operator} onChange={(e) => updateCondition(i, "operator", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                {CONDITION_OPERATORS.map((op) => (
                  <option key={op.value} value={op.value}>{op.label}</option>
                ))}
              </select>
              {!NO_VALUE_OPERATORS.includes(c.operator) && (
                choice && options.length > 0 ? (
                  <select value={c.value} onChange={(e) => updateCondition(i, "value", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                    <option value="">اختر القيمة...</option>
                    {options.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                ) : (
                  <input value={c.value} onChange={(e) => updateCondition(i, "value", e.target.value)} placeholder="القيمة" style={{ ...inputStyle, flex: 1 }} />
                )
              )}
              <button onClick={() => removeCondition(i)} style={{ background: "none", border: "none", cursor: "pointer", color: "var(--color-text-danger)", fontSize: "16px" }}>×</button>
            </div>
            );
          })}
          <button onClick={addCondition} style={{ ...inputStyle, cursor: "pointer", background: "var(--color-background-secondary)" }}>+ إضافة شرط</button>
        </div>

        {/* Actions */}
        <div>
          <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "8px" }}>الإجراءات (THEN)</div>
          {actions.map((a, i) => {
            const targetField = findFieldByKey(fields, a.target_field_id);
            const targetIsChoice = targetField && isChoiceField(targetField);
            const targetOptions = targetField ? getFieldOptions(targetField) : [];
            return (
            <div key={i} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
              <select value={a.action} onChange={(e) => updateAction(i, "action", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                {ACTION_TYPES.map((act) => (
                  <option key={act.value} value={act.value}>{act.label}</option>
                ))}
              </select>
              <select value={a.target_field_id ?? ""} onChange={(e) => updateAction(i, "target_field_id", e.target.value)} style={{ ...inputStyle, flex: 2 }}>
                <option value="">اختر الحقل الهدف...</option>
                {fields.map((f) => (
                  <option key={f.id} value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>
                ))}
              </select>
              {a.action === "set_fee" ? (
                <select value={a.fee_code ?? ""} onChange={(e) => updateAction(i, "fee_code", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                  <option value="">اختر الرسم من المكتبة...</option>
                  {officialFees?.map((fee) => {
                    const displayAmount = fee.resolved_amount ?? fee.amount;
                    return <option key={fee.fee_code} value={fee.fee_code}>{fee.name_ar} ({fee.fee_code}){displayAmount != null ? ` — ${Number(displayAmount).toLocaleString("en")} د.ع` : ""}</option>;
                  })}
                </select>
              ) : a.action === "calculate" ? (
                <input value={(a.value as string) ?? ""} onChange={(e) => updateAction(i, "value", e.target.value)} placeholder="الصيغة" style={{ ...inputStyle, flex: 1 }} />
              ) : ["set_value", "override_value"].includes(a.action) ? (
                targetIsChoice && targetOptions.length > 0 ? (
                  <select value={(a.value as string) ?? ""} onChange={(e) => updateAction(i, "value", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                    <option value="">اختر القيمة...</option>
                    {targetOptions.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                ) : (
                  <input value={(a.value as string) ?? ""} onChange={(e) => updateAction(i, "value", e.target.value)} placeholder="القيمة" style={{ ...inputStyle, flex: 1 }} />
                )
              ) : null}
              <button onClick={() => removeAction(i)} style={{ background: "none", border: "none", cursor: "pointer", color: "var(--color-text-danger)", fontSize: "16px" }}>×</button>
            </div>
            );
          })}
          <button onClick={addAction} style={{ ...inputStyle, cursor: "pointer", background: "var(--color-background-secondary)" }}>+ إضافة إجراء</button>
        </div>
      </div>

      <div style={{ padding: "12px 18px", borderTop: "0.5px solid var(--color-border-tertiary)", display: "flex", gap: "8px", justifyContent: "flex-end" }}>
        <button onClick={onCancel} style={{ ...inputStyle, cursor: "pointer" }}>إلغاء</button>
        <button onClick={handleSave} disabled={saving} style={{ ...inputStyle, cursor: "pointer", background: "var(--color-background-info)", color: "var(--color-text-info)", border: "0.5px solid var(--color-border-info)" }}>
          {saving ? "جارٍ الحفظ..." : "حفظ"}
        </button>
      </div>
    </div>
  );
}

/**
 * Normalize a stored condition_logic into a flat editor model.
 * Supports both the grouped form ({operator, conditions:[...]}) and the
 * flat single-condition form ({operator, field_id, value}).
 */
export function normalizeConditions(cl: ConditionLogic | null): { operator: "and" | "or"; conditions: SimpleCond[] } {
  if (!cl) return { operator: "and", conditions: [{ field_id: "", operator: "equals", value: "" }] };

  if (Array.isArray(cl.conditions)) {
    const op = cl.operator === "or" ? "or" : "and";
    const conds = cl.conditions.map((c: any) => ({
      field_id: c.field_id ?? "",
      operator: c.operator ?? "equals",
      value: c.value != null ? String(c.value) : "",
    }));
    return { operator: op, conditions: conds.length ? conds : [{ field_id: "", operator: "equals", value: "" }] };
  }

  // Flat single condition: { operator, field_id, value }
  if (cl.field_id) {
    return {
      operator: "and",
      conditions: [{ field_id: cl.field_id, operator: (cl.operator as string) ?? "equals", value: cl.value != null ? String(cl.value) : "" }],
    };
  }

  return { operator: "and", conditions: [{ field_id: "", operator: "equals", value: "" }] };
}
