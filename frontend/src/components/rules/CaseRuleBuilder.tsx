import { useState, useMemo, useCallback } from "react";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import type { WorkflowRule, RuleCase, RuleAction, WorkflowField } from "@/types/workflow";
import { workflowVersionApi } from "@/api/workflows";
import { useOfficialFees } from "@/hooks/useFees";
import type { OfficialFee } from "@/api/fees";

interface CaseRuleBuilderProps {
  workflowId: string;
  versionId: string;
  rule?: WorkflowRule | null;
  fields: WorkflowField[];
  onSave: () => void;
  onCancel: () => void;
}

const ALL_ACTIONS = [
  { value: "set_value", label: "تعيين قيمة" },
  { value: "set_fee", label: "تعيين رسوم" },
  { value: "calculate", label: "حساب صيغة" },
  { value: "set_visibility", label: "إظهار/إخفاء" },
  { value: "set_required", label: "إلزامي" },
  { value: "set_readonly", label: "منع التعديل" },
  { value: "set_lock", label: "قفل الحقل" },
  { value: "set_editable", label: "قابل للتعديل" },
  { value: "set_field_type", label: "تغيير نوع الحقل" },
  { value: "set_options", label: "تغيير الخيارات" },
  { value: "apply_discount", label: "تطبيق خصم" },
  { value: "override_value", label: "تجاوز القيمة" },
  { value: "skip_step", label: "تخطي خطوة" },
];

const MATCH_MODES: { value: "exact" | "contains" | "pattern" | "in"; label: string }[] = [
  { value: "exact", label: "مطابقة تامة" },
  { value: "contains", label: "يحتوي على" },
  { value: "pattern", label: "نمط (Regex)" },
  { value: "in", label: "ضمن قائمة" },
];

function SortableCaseItem({
  caseItem,
  index,
  triggerField,
  onUpdate,
  onRemove,
  allFields,
  officialFees,
  isDefault,
}: {
  caseItem: RuleCase;
  index: number;
  triggerField?: WorkflowField | null;
  onUpdate: (updated: RuleCase) => void;
  onRemove: () => void;
  allFields: WorkflowField[];
  officialFees?: OfficialFee[];
  isDefault?: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: `case-${index}` });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  const [expanded, setExpanded] = useState(true);
  const [showActionPicker, setShowActionPicker] = useState(false);

  const isSelectType = triggerField && ["select", "multi_select", "radio"].includes(
    triggerField.field_type ?? triggerField.registerField?.field_type ?? "text"
  );
  const options = triggerField?.options ?? triggerField?.registerField?.options ?? [];

  const normalizedOptions = useMemo(() => {
    if (!options) return [];
    return options.map((o: any) => {
      if (typeof o === "string") return { label: o, value: o };
      return {
        label: o.label_ar ?? o.label ?? o.value ?? "",
        value: o.value ?? o,
      };
    });
  }, [options]);

  const caseLabel = isDefault
    ? "الحالة الافتراضية (Default)"
    : typeof caseItem.value === "string"
      ? normalizedOptions.find((o: any) => o.value === caseItem.value)?.label ?? caseItem.value
      : Array.isArray(caseItem.value)
        ? caseItem.value.map((v) => normalizedOptions.find((o: any) => o.value === v)?.label ?? v).join("، ")
        : String(caseItem.value);

  return (
    <div ref={setNodeRef} style={style} {...attributes}>
      <div
        style={{
          background: "var(--color-background-primary)",
          border: isDefault
            ? "1px dashed var(--color-border-tertiary)"
            : "0.5px solid var(--color-border-secondary)",
          borderRadius: "var(--border-radius-md)",
          marginBottom: "8px",
          overflow: "hidden",
        }}
      >
        {/* Case header */}
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "8px",
            padding: "10px 14px",
            background: isDefault ? "var(--color-background-secondary)" : "transparent",
            cursor: "pointer",
          }}
          onClick={() => setExpanded(!expanded)}
        >
          {!isDefault && (
            <div
              {...listeners}
              style={{
                cursor: "grab",
                fontSize: "14px",
                color: "var(--color-text-tertiary)",
                padding: "2px",
              }}
            >
              ⠿
            </div>
          )}
          <span
            style={{
              width: "22px",
              height: "22px",
              borderRadius: "50%",
              background: isDefault ? "var(--color-background-warning)" : "var(--color-background-info)",
              color: isDefault ? "var(--color-text-warning)" : "var(--color-text-info)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontSize: "11px",
              fontWeight: 600,
              flexShrink: 0,
            }}
          >
            {isDefault ? "D" : index + 1}
          </span>
          <div style={{ flex: 1, fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)" }}>
            {caseLabel}
          </div>
          {!isDefault && (
            <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
              الأولوية: {caseItem.priority ?? (index + 1) * 100}
            </span>
          )}
          <span style={{ fontSize: "10px", color: "var(--color-text-tertiary)" }}>
            {(caseItem.actions ?? []).length} إجراء
          </span>
          <span style={{ fontSize: "12px", color: "var(--color-text-tertiary)", transition: "transform 0.2s", transform: expanded ? "rotate(180deg)" : "rotate(0deg)" }}>
            ▼
          </span>
          {!isDefault && (
            <button
              onClick={(e) => { e.stopPropagation(); onRemove(); }}
              style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)", padding: "2px" }}
            >
              ×
            </button>
          )}
        </div>

        {/* Case body */}
        {expanded && (
          <div style={{ padding: "12px 14px", borderTop: "0.5px solid var(--color-border-secondary)" }}>
            {/* Case value editor */}
            {!isDefault && (
              <div style={{ marginBottom: "12px" }}>
                <label style={labelStyle}>قيمة الحالة</label>
                {isSelectType && normalizedOptions.length > 0 ? (
                  <div style={{ display: "flex", flexWrap: "wrap", gap: "6px" }}>
                    {normalizedOptions.map((opt: any) => {
                      const isSelected = Array.isArray(caseItem.value)
                        ? caseItem.value.includes(opt.value)
                        : caseItem.value === opt.value;
                      return (
                        <button
                          key={opt.value}
                          onClick={() => {
                            const newVal = Array.isArray(caseItem.value)
                              ? isSelected
                                ? caseItem.value.filter((v: string) => v !== opt.value)
                                : [...caseItem.value, opt.value]
                              : isSelected ? "" : opt.value;
                            onUpdate({ ...caseItem, value: newVal });
                          }}
                          style={{
                            padding: "4px 10px",
                            fontSize: "12px",
                            borderRadius: "16px",
                            border: isSelected ? "1px solid var(--color-border-info)" : "0.5px solid var(--color-border-tertiary)",
                            background: isSelected ? "var(--color-background-info)" : "var(--color-background-secondary)",
                            color: isSelected ? "var(--color-text-info)" : "var(--color-text-secondary)",
                            cursor: "pointer",
                            fontFamily: "inherit",
                            fontWeight: isSelected ? 500 : 400,
                          }}
                        >
                          {opt.label}
                          {isSelected && " ✓"}
                        </button>
                      );
                    })}
                  </div>
                ) : (
                  <input
                    value={typeof caseItem.value === "string" ? caseItem.value : ""}
                    onChange={(e) => onUpdate({ ...caseItem, value: e.target.value })}
                    placeholder="أدخل القيمة..."
                    style={inputStyle}
                  />
                )}
              </div>
            )}

            {/* Actions list */}
            <div>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                <label style={labelStyle}>الإجراءات</label>
                <button
                  onClick={() => setShowActionPicker(!showActionPicker)}
                  style={btnGhost}
                >
                  + إضافة إجراء
                </button>
              </div>

              {showActionPicker && (
                <div style={actionPickerStyle}>
                  {ALL_ACTIONS.map((act) => (
                    <button
                      key={act.value}
                      onClick={() => {
                        const newAction: RuleAction = { action: act.value as RuleAction["action"] };
                        onUpdate({ ...caseItem, actions: [...(caseItem.actions ?? []), newAction] });
                        setShowActionPicker(false);
                      }}
                      style={actionPickerItemStyle}
                    >
                      {act.label}
                    </button>
                  ))}
                </div>
              )}

              <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
                {(caseItem.actions ?? []).map((action, actIdx) => (
                  <ActionPill
                    key={actIdx}
                    action={action}
                    index={actIdx}
                    allFields={allFields}
                    officialFees={officialFees}
                    onChange={(updated) => {
                      const newActions = [...(caseItem.actions ?? [])];
                      newActions[actIdx] = updated;
                      onUpdate({ ...caseItem, actions: newActions });
                    }}
                    onRemove={() => {
                      const newActions = (caseItem.actions ?? []).filter((_, i) => i !== actIdx);
                      onUpdate({ ...caseItem, actions: newActions });
                    }}
                  />
                ))}
                {(caseItem.actions ?? []).length === 0 && (
                  <div style={{ fontSize: "12px", color: "var(--color-text-tertiary)", textAlign: "center", padding: "8px" }}>
                    لا توجد إجراءات بعد
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function ActionPill({
  action,
  index,
  allFields,
  officialFees,
  onChange,
  onRemove,
}: {
  action: RuleAction;
  index: number;
  allFields: WorkflowField[];
  officialFees?: OfficialFee[];
  onChange: (updated: RuleAction) => void;
  onRemove: () => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const actionMeta = ALL_ACTIONS.find((a) => a.value === action.action);

  const needsTarget = ["set_value", "set_fee", "set_visibility", "set_required", "set_readonly", "set_lock", "set_editable", "set_field_type", "set_options", "apply_discount", "override_value"].includes(action.action);
  const needsValue = ["set_value", "set_fee", "calculate", "apply_discount", "override_value", "set_field_type"].includes(action.action);
  const needsFormula = ["calculate"].includes(action.action);
  const needsFee = ["set_fee"].includes(action.action);
  const needsFieldType = ["set_field_type"].includes(action.action);

  return (
    <div
      style={{
        background: "var(--color-background-secondary)",
        border: "0.5px solid var(--color-border-tertiary)",
        borderRadius: "6px",
        overflow: "hidden",
      }}
    >
      <div
        style={{
          display: "flex",
          alignItems: "center",
          gap: "8px",
          padding: "6px 10px",
          cursor: "pointer",
        }}
        onClick={() => setExpanded(!expanded)}
      >
        <span
          style={{
            width: "6px",
            height: "6px",
            borderRadius: "50%",
            background: "var(--color-text-info)",
            flexShrink: 0,
          }}
        />
        <span style={{ fontSize: "12px", fontWeight: 500, color: "var(--color-text-primary)", flex: 1 }}>
          {actionMeta?.label ?? action.action}
        </span>
        {action.target_field_id && (
          <span style={{ fontSize: "11px", color: "var(--color-text-secondary)" }}>
            → {allFields.find((f) => f.id === action.target_field_id)?.label ?? action.target_field_id}
          </span>
        )}
        {action.value && (
          <span style={{ fontSize: "11px", color: "var(--color-text-info)", fontFamily: "monospace" }}>
            = {String(action.value)}
          </span>
        )}
        <button
          onClick={(e) => { e.stopPropagation(); onRemove(); }}
          style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)", padding: "0 2px" }}
        >
          ×
        </button>
        <span style={{ fontSize: "10px", color: "var(--color-text-tertiary)", transition: "transform 0.2s", transform: expanded ? "rotate(180deg)" : "rotate(0deg)" }}>
          ▼
        </span>
      </div>

      {expanded && (
        <div style={{ padding: "10px", borderTop: "0.5px solid var(--color-border-tertiary)" }}>
          <div style={{ display: "grid", gap: "8px" }}>
            {needsTarget && (
              <div>
                <label style={labelStyle}>الحقل الهدف</label>
                <select
                  value={action.target_field_id ?? ""}
                  onChange={(e) => onChange({ ...action, target_field_id: e.target.value || undefined })}
                  style={inputStyle}
                >
                  <option value="">اختر الحقل...</option>
                  {allFields.map((f) => (
                    <option key={f.id} value={f.id}>{f.label}</option>
                  ))}
                </select>
              </div>
            )}
            {needsValue && (
              <div>
                <label style={labelStyle}>القيمة</label>
                <input
                  value={typeof action.value === "string" || typeof action.value === "number" ? String(action.value) : ""}
                  onChange={(e) => onChange({ ...action, value: e.target.value })}
                  placeholder="أدخل القيمة..."
                  style={inputStyle}
                />
              </div>
            )}
            {needsFormula && (
              <div>
                <label style={labelStyle}>الصيغة</label>
                <input
                  value={action.formula ?? ""}
                  onChange={(e) => onChange({ ...action, formula: e.target.value })}
                  placeholder="مثال: field_a * 0.15"
                  style={{ ...inputStyle, fontFamily: "monospace" }}
                />
              </div>
            )}
            {needsFee && (
              <div>
                <label style={labelStyle}>اختر الرسم</label>
                <select
                  value={action.fee_code ?? ""}
                  onChange={(e) => {
                    const selected = officialFees?.find((f) => f.fee_code === e.target.value);
                    onChange({
                      ...action,
                      fee_code: selected?.fee_code ?? e.target.value,
                      fee_name: selected?.name_ar ?? "",
                      value: selected?.amount ?? 0,
                    });
                  }}
                  style={inputStyle}
                >
                  <option value="">اختر الرسم...</option>
                  {officialFees?.map((fee) => (
                    <option key={fee.id} value={fee.fee_code}>
                      {fee.name_ar} ({fee.fee_code}) — {fee.amount.toLocaleString("en")} د.ع
                    </option>
                  ))}
                </select>
                {action.fee_code && (
                  <div style={{ fontSize: "11px", color: "var(--color-text-success)", marginTop: "4px" }}>
                    المبلغ: {(action.value ?? 0).toLocaleString("en")} د.ع
                  </div>
                )}
              </div>
            )}
            {needsFieldType && (
              <div>
                <label style={labelStyle}>نوع الحقل الجديد</label>
                <select
                  value={action.field_type ?? ""}
                  onChange={(e) => onChange({ ...action, field_type: e.target.value })}
                  style={inputStyle}
                >
                  <option value="">اختر النوع...</option>
                  {FIELD_TYPES.map((t) => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                  ))}
                </select>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

const FIELD_TYPES = [
  { value: "text", label: "نص" },
  { value: "textarea", label: "نص طويل" },
  { value: "number", label: "رقم" },
  { value: "decimal", label: "رقم عشري" },
  { value: "select", label: "قائمة منسدلة" },
  { value: "multi_select", label: "اختيار متعدد" },
  { value: "checkbox", label: "مربع اختيار" },
  { value: "radio", label: "زر اختيار" },
  { value: "date", label: "تاريخ" },
  { value: "datetime", label: "تاريخ ووقت" },
  { value: "email", label: "بريد إلكتروني" },
  { value: "phone", label: "هاتف" },
  { value: "url", label: "رابط" },
];

function FieldChip({ field }: { field: WorkflowField }) {
  const fieldType = field.field_type ?? field.registerField?.field_type ?? "text";
  const typeMeta = FIELD_TYPES.find((t) => t.value === fieldType);
  const options = field.options ?? field.registerField?.options ?? [];

  const typeIcon = fieldType === "select" || fieldType === "multi_select" || fieldType === "radio" ? "▾"
    : fieldType === "number" || fieldType === "decimal" ? "#"
      : fieldType === "checkbox" ? "☑"
        : fieldType === "date" || fieldType === "datetime" ? "📅"
          : "Aa";

  return (
    <div
      style={{
        display: "flex",
        alignItems: "center",
        gap: "8px",
        padding: "8px 12px",
        background: "var(--color-background-info)",
        border: "1px solid var(--color-border-info)",
        borderRadius: "var(--border-radius-md)",
        marginBottom: "12px",
      }}
    >
      <span
        style={{
          width: "26px",
          height: "26px",
          borderRadius: "6px",
          background: "rgba(59, 130, 246, 0.15)",
          color: "var(--color-text-info)",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          fontSize: "13px",
          fontWeight: 600,
        }}
      >
        {typeIcon}
      </span>
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)" }}>
          {field.label}
        </div>
        <div style={{ fontSize: "11px", color: "var(--color-text-secondary)" }}>
          {typeMeta?.label} · {fieldType}
          {options && options.length > 0 && ` · ${options.length} خيار`}
        </div>
      </div>
    </div>
  );
}

export default function CaseRuleBuilder({
  workflowId,
  versionId,
  rule,
  fields,
  onSave,
  onCancel,
}: CaseRuleBuilderProps) {
  const [name, setName] = useState(rule?.name ?? "");
  const [description, setDescription] = useState(rule?.description ?? "");
  const [triggerFieldId, setTriggerFieldId] = useState(rule?.trigger_field_id ?? "");
  const [matchMode, setMatchMode] = useState(rule?.match_mode ?? "exact");
  const [cases, setCases] = useState<RuleCase[]>(rule?.cases ?? [{ value: "", actions: [], priority: 100 }]);
  const [defaultActions, setDefaultActions] = useState<RuleAction[]>(rule?.default_actions ?? []);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  const [simMode, setSimMode] = useState(false);
  const [simValue, setSimValue] = useState("");
  const [simResult, setSimResult] = useState<{ matchedCase: RuleCase | null; actions: RuleAction[] } | null>(null);

  const { data: officialFees } = useOfficialFees();

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  const triggerField = useMemo(
    () => fields.find((f) => f.id === triggerFieldId) ?? null,
    [fields, triggerFieldId]
  );

  const handleDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = cases.findIndex((_, i) => `case-${i}` === active.id);
    const newIndex = cases.findIndex((_, i) => `case-${i}` === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const reordered = arrayMove(cases, oldIndex, newIndex).map((c, i) => ({ ...c, priority: (i + 1) * 100 }));
    setCases(reordered);
  }, [cases]);

  const addCase = () => {
    setCases([...cases, { value: "", actions: [], priority: (cases.length + 1) * 100 }]);
  };

  const removeCase = (index: number) => {
    setCases(cases.filter((_, i) => i !== index).map((c, i) => ({ ...c, priority: (i + 1) * 100 })));
  };

  const updateCase = (index: number, updated: RuleCase) => {
    setCases(cases.map((c, i) => (i === index ? updated : c)));
  };

  const validate = (): boolean => {
    const errs: string[] = [];
    if (!name.trim()) errs.push("اسم القاعدة مطلوب");
    if (!triggerFieldId) errs.push("حقل التشغيل مطلوب");
    if (cases.length === 0) errs.push("يجب إضافة حالة واحدة على الأقل");
    cases.forEach((c, i) => {
      if (!c.value) errs.push(`الحالة ${i + 1}: القيمة مطلوبة`);
      if ((c.actions ?? []).length === 0) errs.push(`الحالة ${i + 1}: يجب إضافة إجراء واحد على الأقل`);
    });
    const values = cases.map((c) => JSON.stringify(c.value));
    const uniqueValues = new Set(values);
    if (uniqueValues.size !== values.length) errs.push("توجد قيم حالات مكررة");
    setErrors(errs);
    return errs.length === 0;
  };

  const handleSave = async () => {
    if (!validate()) return;
    setSaving(true);
    try {
      const payload = {
        name: name.trim(),
        description: description.trim() || null,
        rule_type: "case_based" as const,
        trigger_field_id: triggerFieldId,
        match_mode: matchMode,
        cases: cases.map((c, i) => ({ ...c, priority: (i + 1) * 100 })),
        default_actions: defaultActions,
        condition_logic: { operator: "and" as const },
        actions: [],
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

  const handleSimulate = () => {
    if (!triggerField || !simValue) return;
    const matched = cases.find((c) => {
      const caseVal = typeof c.value === "string" ? c.value : "";
      switch (matchMode) {
        case "exact": return caseVal === simValue;
        case "contains": return caseVal.includes(simValue);
        case "pattern": return new RegExp(caseVal).test(simValue);
        case "in": return simValue.split(",").map((s) => s.trim()).includes(caseVal);
        default: return caseVal === simValue;
      }
    });
    setSimResult({ matchedCase: matched ?? null, actions: matched?.actions ?? defaultActions });
  };

  return (
    <div
      style={{
        background: "var(--color-background-primary)",
        border: "1px solid var(--color-border-info)",
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
          {rule?.id ? "تعديل القاعدة" : "قاعدة جديدة"}
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
      {simMode && triggerField && (
        <div style={{ padding: "12px 18px", background: "var(--color-background-success)", borderBottom: "0.5px solid var(--color-border-success)" }}>
          <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-success)", marginBottom: "8px" }}>
            محاكاة — الحقل: {triggerField.label}
          </div>
          <div style={{ display: "flex", gap: "8px", alignItems: "center" }}>
            <input
              value={simValue}
              onChange={(e) => setSimValue(e.target.value)}
              placeholder="أدخل قيمة للاختبار..."
              style={{ ...inputStyle, flex: 1 }}
              onKeyDown={(e) => e.key === "Enter" && handleSimulate()}
            />
            <button onClick={handleSimulate} style={btnPrimary}>تشغيل</button>
          </div>
          {simResult && (
            <div style={{ marginTop: "10px", fontSize: "12px", color: "var(--color-text-success)" }}>
              {simResult.matchedCase
                ? `✓ تطابق مع الحالة: ${JSON.stringify(simResult.matchedCase.value)}`
                : "⚠ لم يتم التطابق — سيتم استخدام الإجراءات الافتراضية"}
              <div style={{ marginTop: "4px" }}>
                الإجراءات: {(simResult.actions ?? []).map((a) => a.action).join("، ") || "لا توجد"}
              </div>
            </div>
          )}
        </div>
      )}

      <div style={{ padding: "18px" }}>
        {/* Rule name & description */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "16px" }}>
          <div>
            <label style={labelStyle}>اسم القاعدة</label>
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="مثال: قواعد رسوم النقل" style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>الوصف (اختياري)</label>
            <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="وصف مختصر..." style={inputStyle} />
          </div>
        </div>

        {/* Trigger field selector */}
        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>حقل التشغيل (Trigger Field)</label>
          <select value={triggerFieldId} onChange={(e) => setTriggerFieldId(e.target.value)} style={{ ...inputStyle, width: "100%" }}>
            <option value="">اختر الحقل...</option>
            {fields.map((f) => (
              <option key={f.id} value={f.id}>{f.label} ({f.field_type ?? f.registerField?.field_type ?? "text"})</option>
            ))}
          </select>
        </div>

        {/* Match mode */}
        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>وضع المطابقة</label>
          <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
            {MATCH_MODES.map((m) => (
              <button
                key={m.value}
                onClick={() => setMatchMode(m.value)}
                style={{
                  padding: "4px 10px",
                  fontSize: "12px",
                  borderRadius: "16px",
                  border: matchMode === m.value ? "1px solid var(--color-border-info)" : "0.5px solid var(--color-border-tertiary)",
                  background: matchMode === m.value ? "var(--color-background-info)" : "var(--color-background-secondary)",
                  color: matchMode === m.value ? "var(--color-text-info)" : "var(--color-text-secondary)",
                  cursor: "pointer",
                  fontFamily: "inherit",
                  fontWeight: matchMode === m.value ? 500 : 400,
                }}
              >
                {m.label}
              </button>
            ))}
          </div>
        </div>

        {/* Field chip */}
        {triggerField && <FieldChip field={triggerField} />}

        {/* Cases tree */}
        <div style={{ marginBottom: "16px" }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "10px" }}>
            <label style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)" }}>
              الحالات ({cases.length})
            </label>
            <button onClick={addCase} style={btnPrimary}>+ إضافة حالة</button>
          </div>

          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={cases.map((_, i) => `case-${i}`)} strategy={verticalListSortingStrategy}>
              {cases.map((c, i) => (
                <SortableCaseItem
                  key={i}
                  caseItem={c}
                  index={i}
                  triggerField={triggerField}
                  onUpdate={(updated) => updateCase(i, updated)}
                  onRemove={() => removeCase(i)}
                  allFields={fields}
                  officialFees={officialFees}
                />
              ))}
            </SortableContext>
          </DndContext>
        </div>

        {/* Default fallback */}
        <div style={{ marginBottom: "16px" }}>
          <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)", marginBottom: "10px" }}>
            الإجراءات الافتراضية (Default Fallback)
          </div>
          <DefaultActionsEditor
            actions={defaultActions}
            onChange={setDefaultActions}
            allFields={fields}
            officialFees={officialFees}
          />
        </div>

        {/* Action buttons */}
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

function DefaultActionsEditor({
  actions,
  onChange,
  allFields,
  officialFees,
}: {
  actions: RuleAction[];
  onChange: (actions: RuleAction[]) => void;
  allFields: WorkflowField[];
  officialFees?: OfficialFee[];
}) {
  const [showPicker, setShowPicker] = useState(false);

  return (
    <div
      style={{
        background: "var(--color-background-secondary)",
        border: "1px dashed var(--color-border-tertiary)",
        borderRadius: "var(--border-radius-md)",
        padding: "12px",
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
        <span style={{ fontSize: "12px", color: "var(--color-text-tertiary)" }}>
          تُنفَّذ عندما لا تتطابق أي حالة
        </span>
        <button onClick={() => setShowPicker(!showPicker)} style={btnGhost}>+ إضافة</button>
      </div>

      {showPicker && (
        <div style={actionPickerStyle}>
          {ALL_ACTIONS.map((act) => (
            <button
              key={act.value}
              onClick={() => {
                onChange([...actions, { action: act.value as RuleAction["action"] }]);
                setShowPicker(false);
              }}
              style={actionPickerItemStyle}
            >
              {act.label}
            </button>
          ))}
        </div>
      )}

      <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
        {actions.map((action, idx) => (
          <ActionPill
            key={idx}
            action={action}
            index={idx}
            allFields={allFields}
            officialFees={officialFees}
            onChange={(updated) => {
              const newActions = [...actions];
              newActions[idx] = updated;
              onChange(newActions);
            }}
            onRemove={() => onChange(actions.filter((_, i) => i !== idx))}
          />
        ))}
        {actions.length === 0 && (
          <div style={{ fontSize: "12px", color: "var(--color-text-tertiary)", textAlign: "center", padding: "8px" }}>
            لا توجد إجراءات افتراضية
          </div>
        )}
      </div>
    </div>
  );
}

// --- Shared styles ---
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
  background: "var(--color-background-info)",
  color: "var(--color-text-info)",
  border: "0.5px solid var(--color-border-info)",
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
  color: "var(--color-text-info)",
  border: "0.5px dashed var(--color-border-info)",
  borderRadius: "4px",
  cursor: "pointer",
  fontFamily: "inherit",
};

const actionPickerStyle: React.CSSProperties = {
  display: "flex",
  flexWrap: "wrap",
  gap: "4px",
  padding: "8px",
  background: "var(--color-background-primary)",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  marginBottom: "8px",
};

const actionPickerItemStyle: React.CSSProperties = {
  padding: "4px 8px",
  fontSize: "11px",
  background: "var(--color-background-secondary)",
  color: "var(--color-text-secondary)",
  border: "0.5px solid var(--color-border-tertiary)",
  borderRadius: "4px",
  cursor: "pointer",
  fontFamily: "inherit",
};
