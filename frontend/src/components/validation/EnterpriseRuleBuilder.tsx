import { useState, useCallback } from "react";
import type { WorkflowField } from "@/types/workflow";
import { workflowVersionApi } from "@/api/workflows";
import { useOfficialFees } from "@/hooks/useFees";
import type {
  EnterpriseRule,
  ConditionNode,
  SimpleCondition,
  RuleAction,
  RuleCase,
  ConditionOperator,
  ActionType,
} from "@/types/enterprise-rule-engine";
import { OPERATOR_METADATA as OPERATORS, ACTION_METADATA as ACTIONS } from "@/types/enterprise-rule-engine";

interface EnterpriseRuleBuilderProps {
  workflowId: string;
  versionId: string;
  rule?: EnterpriseRule | null;
  fields: WorkflowField[];
  registers?: any[];
  onSave: () => void;
  onCancel: () => void;
}

function generateId() {
  return Math.random().toString(36).substring(2, 11);
}

function isSimpleCondition(cond: ConditionNode): cond is SimpleCondition {
  return cond.type === "simple";
}

function getFieldOptions(fieldId: string, fields: WorkflowField[], registers?: any[]): Array<{ label: string; value: string }> | null {
  const field = fields.find((f) => f.id === fieldId);
  if (!field) return null;

  const fieldType = field.field_type ?? field.registerField?.field_type ?? "text";
  if (!["select", "multi_select", "radio", "checkbox"].includes(fieldType)) return null;

  const rawOptions = field.options ?? field.registerField?.options ?? null;
  if (!rawOptions) return null;

  if (fieldType === "checkbox") {
    return [
      { label: "نعم", value: "1" },
      { label: "لا", value: "0" },
    ];
  }

  if (Array.isArray(rawOptions)) {
    return rawOptions.map((opt: any) => {
      if (typeof opt === "string") return { label: opt, value: opt };
      return { label: opt.label_ar ?? opt.label ?? opt.value, value: opt.value };
    });
  }

  return null;
}

function renderConditionValue(
  fieldId: string,
  operator: string,
  value: any,
  onChange: (val: any) => void,
  fields: WorkflowField[],
  registers?: any[]
) {
  if (["is_empty", "is_not_empty", "exists", "not_exists"].includes(operator)) {
    return null;
  }

  const options = getFieldOptions(fieldId, fields, registers);
  if (!options) {
    return (
      <input value={String(value ?? "")} onChange={(e) => onChange(e.target.value)} placeholder="القيمة..." style={{ ...inputStyle, flex: 1 }} />
    );
  }

  const fieldType = fields.find((f) => f.id === fieldId)?.field_type ?? fields.find((f) => f.id === fieldId)?.registerField?.field_type ?? "text";

  if (fieldType === "multi_select") {
    const selectedValues = Array.isArray(value) ? value : (value ? JSON.parse(value) : []);
    return (
      <select
        multiple
        value={selectedValues}
        onChange={(e) => {
          const selected = Array.from(e.target.selectedOptions, (o) => o.value);
          onChange(JSON.stringify(selected));
        }}
        style={{ ...inputStyle, flex: 1, minHeight: "60px" }}
      >
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
    );
  }

  return (
    <select value={String(value ?? "")} onChange={(e) => onChange(e.target.value)} style={{ ...inputStyle, flex: 1 }}>
      <option value="">اختر...</option>
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>{opt.label}</option>
      ))}
    </select>
  );
}

export default function EnterpriseRuleBuilder({
  workflowId,
  versionId,
  rule,
  fields,
  registers,
  onSave,
  onCancel,
}: EnterpriseRuleBuilderProps) {
  const [name, setName] = useState(rule?.name ?? "");
  const [description, setDescription] = useState(rule?.description ?? "");
  const [category, setCategory] = useState(rule?.category ?? "validation");
  const [priority, setPriority] = useState(rule?.priority ?? 5000);
  const [conditions, setConditions] = useState<ConditionNode[]>(
    rule?.conditions ?? [{ id: generateId(), type: "simple", field_id: "", operator: "equals", value: "" }]
  );
  const [actions, setActions] = useState<RuleAction[]>(
    rule?.actions ?? []
  );
  const [elseActions, setElseActions] = useState<RuleAction[]>(
    rule?.else_actions ?? []
  );
  const [cases, setCases] = useState<RuleCase[]>(rule?.cases ?? []);
  const [useCases, setUseCases] = useState(rule?.cases ? true : false);
  const [conflictResolution, setConflictResolution] = useState(rule?.conflict_resolution ?? "highest_priority");
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  const { data: officialFees } = useOfficialFees();
  const [simMode, setSimMode] = useState(false);
  const [simValues, setSimValues] = useState<Record<string, string>>({});
  const [simResult, setSimResult] = useState<any>(null);

  // Condition management
  const addCondition = () => {
    setConditions([...conditions, { id: generateId(), type: "simple", field_id: "", operator: "equals", value: "" }]);
  };

  const removeCondition = (index: number) => {
    if (conditions.length > 1) {
      setConditions(conditions.filter((_, i) => i !== index));
    }
  };

  const updateCondition = (index: number, key: string, value: any) => {
    setConditions(conditions.map((c, i) => (i === index ? { ...c, [key]: value } : c)));
  };

  const addConditionGroup = () => {
    setConditions([
      ...conditions,
      {
        id: generateId(),
        type: "group",
        logic: "and",
        conditions: [{ id: generateId(), type: "simple", field_id: "", operator: "equals", value: "" }],
      },
    ]);
  };

  // Action management
  const addAction = () => {
    setActions([...actions, { id: generateId(), type: "set_value", field_id: "", value: "" }]);
  };

  const removeAction = (index: number) => {
    setActions(actions.filter((_, i) => i !== index));
  };

  const updateAction = (index: number, key: string, value: any) => {
    setActions(actions.map((a, i) => (i === index ? { ...a, [key]: value } : a)));
  };

  // Case management
  const addCase = () => {
    setCases([
      ...cases,
      {
        id: generateId(),
        label: `حالة ${cases.length + 1}`,
        conditions: [{ id: generateId(), type: "simple", field_id: "", operator: "equals", value: "" }],
        actions: [],
      },
    ]);
  };

  const removeCase = (index: number) => {
    setCases(cases.filter((_, i) => i !== index));
  };

  const updateCase = (index: number, key: string, value: any) => {
    setCases(cases.map((c, i) => (i === index ? { ...c, [key]: value } : c)));
  };

  const addCaseCondition = (caseIndex: number) => {
    setCases(
      cases.map((c, i) =>
        i === caseIndex
          ? { ...c, conditions: [...c.conditions, { id: generateId(), type: "simple", field_id: "", operator: "equals", value: "" }] }
          : c
      )
    );
  };

  const addCaseAction = (caseIndex: number) => {
    setCases(
      cases.map((c, i) =>
        i === caseIndex ? { ...c, actions: [...c.actions, { id: generateId(), type: "set_value", field_id: "", value: "" }] } : c
      )
    );
  };

  const validate = (): boolean => {
    const errs: string[] = [];
    if (!name.trim()) errs.push("اسم القاعدة مطلوب");
    if (conditions.length === 0 && !useCases) errs.push("يجب إضافة شرط واحد على الأقل");
    if (actions.length === 0 && !useCases) errs.push("يجب إضافة إجراء واحد على الأقل");
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
        category,
        priority,
        conflict_resolution: conflictResolution,
        is_active: true,
        sort_order: rule?.sort_order ?? 0,
        rule_config: {
          conditions: useCases ? [] : conditions,
          actions,
          else_actions: elseActions,
          cases: useCases ? cases : undefined,
        },
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

  const handleSimulate = async () => {
    try {
      const result = await workflowVersionApi.simulateEnterprise(workflowId, versionId, simValues);
      setSimResult(result);
    } catch (err: any) {
      setErrors([err?.response?.data?.message ?? "فشل المحاكاة"]);
    }
  };

  const getOperatorMeta = (op: ConditionOperator) => OPERATORS.find((o) => o.value === op) ?? OPERATORS[0];
  const getActionMeta = (type: ActionType) => ACTIONS.find((a) => a.value === type) ?? ACTIONS[0];

  return (
    <div style={{ background: "var(--color-background-primary)", border: "1px solid var(--color-border-warning)", borderRadius: "var(--border-radius-lg)" }}>
      {/* Header */}
      <div style={{ padding: "14px 18px", borderBottom: "0.5px solid var(--color-border-tertiary)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div style={{ fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)" }}>
          {rule?.id ? "تعديل قاعدة متقدمة" : "قاعدة متقدمة جديدة"}
        </div>
        <button onClick={() => setSimMode(!simMode)} style={{ padding: "5px 12px", fontSize: "12px", background: simMode ? "var(--color-background-success)" : "transparent", color: simMode ? "var(--color-text-success)" : "var(--color-text-secondary)", border: `0.5px solid ${simMode ? "var(--color-border-success)" : "var(--color-border-secondary)"}`, borderRadius: "6px", cursor: "pointer", fontFamily: "inherit" }}>
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

      {/* Simulation */}
      {simMode && (
        <div style={{ padding: "12px 18px", background: "var(--color-background-success)", borderBottom: "0.5px solid var(--color-border-success)" }}>
          <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-success)", marginBottom: "8px" }}>محاكاة القاعدة</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: "8px", marginBottom: "8px" }}>
            {fields.map((f) => (
              <input key={f.id} value={simValues[f.id] ?? ""} onChange={(e) => setSimValues({ ...simValues, [f.id]: e.target.value })} placeholder={f.label} style={inputStyle} />
            ))}
          </div>
          <button onClick={handleSimulate} style={btnPrimary}>تشغيل المحاكاة</button>
          {simResult && (
            <div style={{ marginTop: "10px", fontSize: "12px" }}>
              <div>القواعد الكلية: {simResult.total_rules_evaluated}</div>
              <div>مطابقة: {simResult.matched_rules}</div>
              <div>غير مطابقة: {simResult.failed_rules}</div>
              <div>وقت التنفيذ: {simResult.execution_time_ms}ms</div>
              {simResult.results?.map((r: any, i: number) => (
                <div key={i} style={{ marginTop: "4px", color: r.matched ? "var(--color-text-success)" : "var(--color-text-danger)" }}>
                  {r.matched ? "✓" : "✗"} {r.rule_name}
                  {r.executed_actions?.length > 0 && ` (${r.executed_actions.length} إجراءات)`}
                  {r.messages?.length > 0 && r.messages.map((m: any, j: number) => (
                    <div key={j} style={{ marginLeft: "12px", color: m.type === "error" ? "var(--color-text-danger)" : m.type === "warning" ? "var(--color-text-warning)" : "var(--color-text-info)" }}>
                      {m.message_ar}
                    </div>
                  ))}
                </div>
              ))}
              {simResult.routing_decisions?.length > 0 && (
                <div style={{ marginTop: "8px", color: "var(--color-text-info)" }}>
                  قرارات التوجيه: {simResult.routing_decisions.length}
                  {simResult.routing_decisions.map((rd: any, i: number) => (
                    <div key={i}>• {rd.action}</div>
                  ))}
                </div>
              )}
              {simResult.final_field_states && Object.keys(simResult.final_field_states).length > 0 && (
                <div style={{ marginTop: "8px", color: "var(--color-text-secondary)" }}>
                  حالات الحقول:
                  {Object.entries(simResult.final_field_states).map(([fid, state]: [string, any]) => (
                    <div key={fid}>• {fid}: مرئي={state.is_visible ? "نعم" : "لا"}, إلزامي={state.is_required ? "نعم" : "لا"}, قراءة={state.is_readonly ? "نعم" : "لا"}</div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      )}

      <div style={{ padding: "18px" }}>
        {/* Name & Description */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "16px" }}>
          <div>
            <label style={labelStyle}>اسم القاعدة</label>
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="مثال: توجيه التسجيلات المميزة" style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>الوصف (اختياري)</label>
            <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="وصف مختصر..." style={inputStyle} />
          </div>
        </div>

        {/* Category & Priority */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: "12px", marginBottom: "16px" }}>
          <div>
            <label style={labelStyle}>الفئة</label>
            <select value={category} onChange={(e) => setCategory(e.target.value as any)} style={inputStyle}>
              <option value="validation">تحقق</option>
              <option value="routing">توجيه</option>
              <option value="calculation">حساب</option>
              <option value="field_control">تحكم بالحقل</option>
              <option value="notification">إشعار</option>
              <option value="data_mapping">تعيين بيانات</option>
              <option value="case_based">قائمة حالات</option>
            </select>
          </div>
          <div>
            <label style={labelStyle}>الأولوية (1-10000)</label>
            <input type="number" value={priority} onChange={(e) => setPriority(parseInt(e.target.value))} style={inputStyle} min={1} max={10000} />
          </div>
          <div>
            <label style={labelStyle}>حل التعارض</label>
            <select value={conflictResolution} onChange={(e) => setConflictResolution(e.target.value as any)} style={inputStyle}>
              <option value="highest_priority">الأعلى أولوية</option>
              <option value="first_match">أول مطابقة</option>
              <option value="execute_all">تنفيذ الكل</option>
              <option value="execute_until_stop">تنفيذ حتى الإيقاف</option>
            </select>
          </div>
        </div>

        {/* Mode Toggle: Standard vs Case-Based */}
        <div style={{ marginBottom: "16px", display: "flex", gap: "8px" }}>
          <button onClick={() => setUseCases(false)} style={{ padding: "6px 14px", fontSize: "12px", borderRadius: "16px", border: !useCases ? "1px solid var(--color-border-warning)" : "0.5px solid var(--color-border-tertiary)", background: !useCases ? "var(--color-background-warning)" : "var(--color-background-secondary)", color: !useCases ? "var(--color-text-warning)" : "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}>
            شروط قياسية
          </button>
          <button onClick={() => setUseCases(true)} style={{ padding: "6px 14px", fontSize: "12px", borderRadius: "16px", border: useCases ? "1px solid var(--color-border-warning)" : "0.5px solid var(--color-border-tertiary)", background: useCases ? "var(--color-background-warning)" : "var(--color-background-secondary)", color: useCases ? "var(--color-text-warning)" : "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}>
            قائمة حالات (Case-Based)
          </button>
        </div>

        {/* Standard Conditions */}
        {!useCases && (
          <div style={{ marginBottom: "16px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
              <label style={{ ...labelStyle, marginBottom: 0 }}>الشروط (Conditions)</label>
              <div style={{ display: "flex", gap: "6px" }}>
                <button onClick={addConditionGroup} style={btnGhost}>+ مجموعة</button>
                <button onClick={addCondition} style={btnGhost}>+ شرط</button>
              </div>
            </div>
            {conditions.map((cond, idx) => (
              <div key={cond.id} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
                <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)", minWidth: "20px" }}>{idx + 1}.</span>
                {cond.type === "group" ? (
                  <div style={{ flex: 1, padding: "8px", background: "var(--color-background-secondary)", borderRadius: "6px", border: "0.5px solid var(--color-border-tertiary)" }}>
                    <div style={{ display: "flex", gap: "6px", marginBottom: "6px" }}>
                      <select value={(cond as any).logic} onChange={(e) => updateCondition(idx, "logic", e.target.value)} style={{ ...inputStyle, width: "80px" }}>
                        <option value="and">AND</option>
                        <option value="or">OR</option>
                      </select>
                      <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>مجموعة شروط</span>
                    </div>
                    {(cond as any).conditions?.map((sub: any, subIdx: number) => (
                      <div key={sub.id} style={{ display: "flex", gap: "4px", marginBottom: "4px", alignItems: "center" }}>
                        <select value={sub.field_id} onChange={(e) => {
                          const newConditions = [...(cond as any).conditions];
                          newConditions[subIdx] = { ...newConditions[subIdx], field_id: e.target.value };
                          updateCondition(idx, "conditions", newConditions);
                        }} style={{ ...inputStyle, flex: 1 }}>
                          <option value="">اختر الحقل...</option>
                          {fields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                        </select>
                        <select value={sub.operator ?? "equals"} onChange={(e) => {
                          const newConditions = [...(cond as any).conditions];
                          newConditions[subIdx] = { ...newConditions[subIdx], operator: e.target.value };
                          updateCondition(idx, "conditions", newConditions);
                        }} style={{ ...inputStyle, flex: 1, minWidth: "100px" }}>
                          {OPERATORS.map((op) => <option key={op.value} value={op.value}>{op.icon} {op.label}</option>)}
                        </select>
                        {!["is_empty", "is_not_empty", "exists", "not_exists"].includes(sub.operator ?? "equals") && (
                          renderConditionValue(sub.field_id, sub.operator ?? "equals", sub.value, (val) => {
                            const newConditions = [...(cond as any).conditions];
                            newConditions[subIdx] = { ...newConditions[subIdx], value: val };
                            updateCondition(idx, "conditions", newConditions);
                          }, fields, registers)
                        )}
                        <button onClick={() => {
                          const newConditions = (cond as any).conditions.filter((_: any, i: number) => i !== subIdx);
                          updateCondition(idx, "conditions", newConditions);
                        }} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)" }}>×</button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <>
                    <select value={cond.field_id} onChange={(e) => updateCondition(idx, "field_id", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                      <option value="">اختر الحقل...</option>
                      {fields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                    </select>
                    <select value={cond.operator} onChange={(e) => updateCondition(idx, "operator", e.target.value)} style={{ ...inputStyle, flex: 1, minWidth: "120px" }}>
                      {OPERATORS.map((op) => <option key={op.value} value={op.value}>{op.icon} {op.label}</option>)}
                    </select>
                    {!["is_empty", "is_not_empty", "exists", "not_exists"].includes(cond.operator) && (
                      renderConditionValue(cond.field_id, cond.operator, cond.value, (val) => updateCondition(idx, "value", val), fields, registers)
                    )}
                    {conditions.length > 1 && (
                      <button onClick={() => removeCondition(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "14px", color: "var(--color-text-danger)" }}>×</button>
                    )}
                  </>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Case-Based Rules */}
        {useCases && (
          <div style={{ marginBottom: "16px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
              <label style={{ ...labelStyle, marginBottom: 0 }}>الحالات (Cases)</label>
              <button onClick={addCase} style={btnGhost}>+ إضافة حالة</button>
            </div>
            {cases.map((caseItem, caseIdx) => (
              <div key={caseItem.id} style={{ marginBottom: "12px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "8px", border: "0.5px solid var(--color-border-tertiary)" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                  <input value={caseItem.label} onChange={(e) => updateCase(caseIdx, "label", e.target.value)} placeholder="اسم الحالة..." style={{ ...inputStyle, width: "200px", fontWeight: 500 }} />
                  <button onClick={() => removeCase(caseIdx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "14px", color: "var(--color-text-danger)" }}>×</button>
                </div>
                {/* Case Conditions */}
                <div style={{ marginBottom: "8px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "4px" }}>
                    <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>شروط الحالة:</span>
                    <button onClick={() => addCaseCondition(caseIdx)} style={{ ...btnGhost, fontSize: "10px" }}>+ شرط</button>
                  </div>
                  {caseItem.conditions.map((cond, condIdx) => {
                    const simpleCond = cond as SimpleCondition;
                    return (
                    <div key={cond.id} style={{ display: "flex", gap: "4px", marginBottom: "4px", alignItems: "center" }}>
                      <select value={simpleCond.field_id} onChange={(e) => {
                        const newConditions = [...caseItem.conditions] as SimpleCondition[];
                        newConditions[condIdx] = { ...newConditions[condIdx], field_id: e.target.value };
                        updateCase(caseIdx, "conditions", newConditions as any);
                      }} style={{ ...inputStyle, flex: 1 }}>
                        <option value="">اختر الحقل...</option>
                        {fields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                      </select>
                      <select value={simpleCond.operator} onChange={(e) => {
                        const newConditions = [...caseItem.conditions] as SimpleCondition[];
                        newConditions[condIdx] = { ...newConditions[condIdx], operator: e.target.value as ConditionOperator };
                        updateCase(caseIdx, "conditions", newConditions as any);
                      }} style={{ ...inputStyle, flex: 1, minWidth: "100px" }}>
                        {OPERATORS.map((op) => <option key={op.value} value={op.value}>{op.icon} {op.label}</option>)}
                      </select>
                      {!["is_empty", "is_not_empty", "exists", "not_exists"].includes(simpleCond.operator) && (
                        renderConditionValue(simpleCond.field_id, simpleCond.operator, simpleCond.value, (val) => {
                          const newConditions = [...caseItem.conditions] as SimpleCondition[];
                          newConditions[condIdx] = { ...newConditions[condIdx], value: val };
                          updateCase(caseIdx, "conditions", newConditions as any);
                        }, fields, registers)
                      )}
                    </div>
                    );
                  })}
                </div>
                {/* Case Actions */}
                <div>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "4px" }}>
                    <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>إجراءات الحالة:</span>
                    <button onClick={() => addCaseAction(caseIdx)} style={{ ...btnGhost, fontSize: "10px" }}>+ إجراء</button>
                  </div>
                  {caseItem.actions.map((act, actIdx) => (
                    <div key={act.id} style={{ display: "flex", gap: "4px", marginBottom: "4px", alignItems: "center" }}>
                      <select value={act.type} onChange={(e) => {
                        const newActions = [...caseItem.actions];
                        newActions[actIdx] = { ...newActions[actIdx], type: e.target.value as ActionType };
                        updateCase(caseIdx, "actions", newActions);
                      }} style={{ ...inputStyle, flex: 1 }}>
                        {ACTIONS.map((a) => <option key={a.value} value={a.value}>{a.icon} {a.label}</option>)}
                      </select>
                      {(act.type === "set_value" || act.type === "override_value" || act.type === "calculate") && (
                        <>
                          <select value={act.field_id} onChange={(e) => {
                            const newActions = [...caseItem.actions];
                            newActions[actIdx] = { ...newActions[actIdx], field_id: e.target.value };
                            updateCase(caseIdx, "actions", newActions);
                          }} style={{ ...inputStyle, flex: 1 }}>
                            <option value="">اختر الحقل...</option>
                            {fields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                          </select>
                          <input value={String(act.value ?? "")} onChange={(e) => {
                            const newCaseActions = [...caseItem.actions];
                            newCaseActions[actIdx] = { ...newCaseActions[actIdx], value: e.target.value };
                            updateCase(caseIdx, "actions", newCaseActions);
                          }} placeholder="القيمة..." style={{ ...inputStyle, flex: 1 }} />
                        </>
                      )}
                      {act.type === "set_fee" && (
                        <>
                          <select value={act.field_id} onChange={(e) => {
                            const newActions = [...caseItem.actions];
                            newActions[actIdx] = { ...newActions[actIdx], field_id: e.target.value };
                            updateCase(caseIdx, "actions", newActions);
                          }} style={{ ...inputStyle, flex: 1 }}>
                            <option value="">اختر الحقل المالي...</option>
                            {fields.filter((f) => f.is_financial || f.field_type === "decimal" || f.field_type === "number").map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                          </select>
                          <select value={String(act.value ?? "")} onChange={(e) => {
                            const newCaseActions = [...caseItem.actions];
                            newCaseActions[actIdx] = { ...newCaseActions[actIdx], value: e.target.value };
                            updateCase(caseIdx, "actions", newCaseActions);
                          }} style={{ ...inputStyle, flex: 1 }}>
                            <option value="">اختر الرسم...</option>
                            {officialFees?.map((fee) => (
                              <option key={fee.fee_code} value={fee.fee_code}>{fee.name_ar} ({fee.fee_code}) — {fee.amount?.toLocaleString("en")} د.ع</option>
                            ))}
                          </select>
                        </>
                      )}
                      {act.type === "apply_discount" && (
                        <>
                          <select value={act.field_id} onChange={(e) => {
                            const newActions = [...caseItem.actions];
                            newActions[actIdx] = { ...newActions[actIdx], field_id: e.target.value };
                            updateCase(caseIdx, "actions", newActions);
                          }} style={{ ...inputStyle, flex: 1 }}>
                            <option value="">اختر الحقل المالي...</option>
                            {fields.filter((f) => f.is_financial || f.field_type === "decimal" || f.field_type === "number").map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                          </select>
                          <input value={String(act.value ?? "")} onChange={(e) => {
                            const newCaseActions = [...caseItem.actions];
                            newCaseActions[actIdx] = { ...newCaseActions[actIdx], value: e.target.value };
                            updateCase(caseIdx, "actions", newCaseActions);
                          }} placeholder="نسبة الخصم %" style={{ ...inputStyle, flex: 1 }} />
                        </>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Actions (Standard Mode) */}
        {!useCases && (
          <div style={{ marginBottom: "16px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
              <label style={{ ...labelStyle, marginBottom: 0 }}>الإجراءات (Actions)</label>
              <button onClick={addAction} style={btnGhost}>+ إضافة إجراء</button>
            </div>
            {actions.map((act, idx) => (
              <div key={act.id} style={{ display: "flex", gap: "6px", marginBottom: "6px", alignItems: "center" }}>
                <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)", minWidth: "20px" }}>{idx + 1}.</span>
                <select value={act.type} onChange={(e) => updateAction(idx, "type", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                  {ACTIONS.map((a) => <option key={a.value} value={a.value}>{a.icon} {a.label}</option>)}
                </select>
                {(act.type === "set_value" || act.type === "override_value" || act.type === "calculate" || act.type === "set_visibility") && (
                  <>
                    <select value={act.field_id} onChange={(e) => updateAction(idx, "field_id", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                      <option value="">اختر الحقل...</option>
                      {fields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                    </select>
                    <input value={String(act.value ?? "")} onChange={(e) => updateAction(idx, "value", e.target.value)} placeholder="القيمة..." style={{ ...inputStyle, flex: 1 }} />
                  </>
                )}
                {act.type === "set_fee" && (
                  <>
                    <select value={act.field_id} onChange={(e) => updateAction(idx, "field_id", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                      <option value="">اختر الحقل المالي...</option>
                      {fields.filter((f) => f.is_financial || f.field_type === "decimal" || f.field_type === "number").map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                    </select>
                    <select value={String(act.value ?? "")} onChange={(e) => updateAction(idx, "value", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                      <option value="">اختر الرسم...</option>
                      {officialFees?.map((fee) => (
                        <option key={fee.fee_code} value={fee.fee_code}>{fee.name_ar} ({fee.fee_code}) — {fee.amount?.toLocaleString("en")} د.ع</option>
                      ))}
                    </select>
                  </>
                )}
                {act.type === "apply_discount" && (
                  <>
                    <select value={act.field_id} onChange={(e) => updateAction(idx, "field_id", e.target.value)} style={{ ...inputStyle, flex: 1 }}>
                      <option value="">اختر الحقل المالي...</option>
                      {fields.filter((f) => f.is_financial || f.field_type === "decimal" || f.field_type === "number").map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                    </select>
                    <input value={String(act.value ?? "")} onChange={(e) => updateAction(idx, "value", e.target.value)} placeholder="نسبة الخصم %" style={{ ...inputStyle, flex: 1 }} />
                  </>
                )}
                {actions.length > 0 && (
                  <button onClick={() => removeAction(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "14px", color: "var(--color-text-danger)" }}>×</button>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Save/Cancel */}
        <div style={{ display: "flex", gap: "8px", justifyContent: "flex-end", paddingTop: "12px", borderTop: "0.5px solid var(--color-border-tertiary)" }}>
          <button onClick={onCancel} style={btnSecondary}>إلغاء</button>
          <button onClick={handleSave} disabled={saving} style={btnPrimary}>
            {saving ? "جارٍ الحفظ..." : "حفظ القاعدة"}
          </button>
        </div>
      </div>
    </div>
  );
}

const labelStyle: React.CSSProperties = { display: "block", fontSize: "12px", color: "var(--color-text-secondary)", marginBottom: "4px" };
const inputStyle: React.CSSProperties = { padding: "6px 10px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", fontFamily: "inherit", width: "100%", color: "#111827", backgroundColor: "#ffffff" };
const btnPrimary: React.CSSProperties = { padding: "6px 14px", fontSize: "12px", background: "var(--color-background-warning)", color: "var(--color-text-warning)", border: "0.5px solid var(--color-border-warning)", borderRadius: "6px", cursor: "pointer", fontFamily: "inherit", fontWeight: 500 };
const btnSecondary: React.CSSProperties = { padding: "6px 14px", fontSize: "12px", background: "none", color: "var(--color-text-secondary)", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", cursor: "pointer", fontFamily: "inherit" };
const btnGhost: React.CSSProperties = { padding: "4px 10px", fontSize: "11px", background: "transparent", color: "var(--color-text-warning)", border: "0.5px dashed var(--color-border-warning)", borderRadius: "4px", cursor: "pointer", fontFamily: "inherit" };
