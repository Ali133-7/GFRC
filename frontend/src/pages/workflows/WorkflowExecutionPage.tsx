import { useState, useEffect, useCallback, useMemo } from "react";
import { toBoolean } from "@/lib/boolean";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import {
  useWorkflow,
  useWorkflowVersions,
  useStartExecution,
  useSubmitStep,
  usePreviewExecution,
  useCompleteExecution,
} from "@/hooks/useWorkflows";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import BranchHandler from "@/components/execution/BranchHandler";
import type { WorkflowField, WorkflowStep } from "@/types/workflow";

function renderConditionTrace(trace: any, depth = 0): string {
  if (!trace) return "";
  if (Array.isArray(trace)) {
    return trace.map((t) => renderConditionTrace(t, depth)).join(" AND ");
  }
  if (trace.type === "group") {
    const sep = trace.logic === "or" ? " OR " : " AND ";
    return `(${trace.conditions.map((c: any) => renderConditionTrace(c, depth + 1)).join(sep)})`;
  }
  if (trace.type === "condition") {
    const actual = trace.actual === null ? "null" : trace.actual === undefined ? "undefined" : String(trace.actual);
    const expected = trace.expected === null ? "null" : trace.expected === undefined ? "undefined" : String(trace.expected);
    return `${trace.field_id.slice(0, 8)}… ${trace.operator} "${expected}" [actual="${actual}"]`;
  }
  if (trace.trigger_field) {
    return `trigger=${trace.trigger_field.slice(0, 8)}… value="${trace.trigger_value}" matched=${trace.matched_case ?? "none"}`;
  }
  return JSON.stringify(trace);
}

export default function WorkflowExecutionPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const versionIdFromUrl = searchParams.get("version");

  const { data: workflow } = useWorkflow(id ?? "");
  const { data: versions } = useWorkflowVersions(id ?? "");
  const startMut = useStartExecution();
  const submitMut = useSubmitStep();
  const previewMut = usePreviewExecution();
  const completeMut = useCompleteExecution();

  const [executionId, setExecutionId] = useState<string | null>(null);
  const [stepIndex, setStepIndex] = useState(0);
  const [values, setValues] = useState<Record<string, string>>({});
  const [fieldStates, setFieldStates] = useState<Record<string, { is_visible: boolean; is_required: boolean; is_readonly: boolean }>>({});
  const [preview, setPreview] = useState<any>(null);
  const [isReview, setIsReview] = useState(false);
  const [notes, setNotes] = useState("");
  const [receiptId, setReceiptId] = useState<string | null>(null);
  const [execVersion, setExecVersion] = useState<any>(null);
  const [routingDecisions, setRoutingDecisions] = useState<any[]>([]);
  const [activeRoutingIndex, setActiveRoutingIndex] = useState<number | null>(null);
  const [debugMode, setDebugMode] = useState(false);
  const [debugTrace, setDebugTrace] = useState<any>(null);
  const [shownFieldIds, setShownFieldIds] = useState<Set<string>>(new Set());

  const activeVersion = execVersion ?? versions?.find((v: any) => v.id === versionIdFromUrl) ?? versions?.find((v: any) => v.status === "active");
  const steps = activeVersion?.steps?.sort((a: WorkflowStep, b: WorkflowStep) => a.sort_order - b.sort_order) ?? [];
  const currentStep = steps[stepIndex];
  const allVersionFields = activeVersion?.fields ?? [];
  const stepFieldsBase = allVersionFields.filter((f: WorkflowField) => f.step_id === currentStep?.id) ?? [];

  const resolveFieldId = (field: WorkflowField): string => {
    return field.register_field_id ?? `custom_${field.id}`;
  };

  // Merge step fields with dynamically shown fields from rule actions
  const stepFields = useMemo(() => {
    const stepFieldIds = new Set(stepFieldsBase.map((f: WorkflowField) => resolveFieldId(f)));
    const shownFields = allVersionFields.filter((f: WorkflowField) => {
      const fid = resolveFieldId(f);
      return shownFieldIds.has(fid) && !stepFieldIds.has(fid);
    });
    return [...stepFieldsBase, ...shownFields];
  }, [stepFieldsBase, allVersionFields, shownFieldIds]);

  // Start execution on mount
  useEffect(() => {
    if (activeVersion && !executionId && !startMut.isPending && !execVersion) {
      startMut.mutate(activeVersion.id, {
        onSuccess: (data: any) => {
          setExecutionId(data.execution.id);
          setStepIndex(data.current_step_index ?? 0);
          if (data.version) setExecVersion(data.version);
        },
      });
    }
  }, [activeVersion?.id, execVersion]);

  const resolveFieldType = (field: WorkflowField): string => {
    const override = field.field_type;
    if (override && override !== '') return override;
    return field.registerField?.field_type ?? 'text';
  };

  const resolveFieldOptions = (field: WorkflowField): Array<{ value: string; label: string }> => {
    // 1. Check workflow field options override
    if (field.options && Array.isArray(field.options) && field.options.length > 0) {
      return field.options.map((opt: any) => {
        if (typeof opt === 'string') return { value: opt, label: opt };
        return { value: opt.value, label: opt.label_ar ?? opt.label ?? opt.value };
      });
    }

    // 2. Check register field options
    const rf = field.registerField;
    if (rf?.options) {
      if (typeof rf.options === 'string') {
        try {
          const parsed = JSON.parse(rf.options);
          if (Array.isArray(parsed)) {
            return parsed.map((opt: any) => {
              if (typeof opt === 'string') return { value: opt, label: opt };
              return { value: opt.value, label: opt.label_ar ?? opt.label ?? opt.value };
            });
          }
        } catch {
          // ignore parse errors
        }
      }
      if (Array.isArray(rf.options)) {
        return rf.options.map((opt: any) => {
          if (typeof opt === 'string') return { value: opt, label: opt };
          return { value: opt.value, label: opt.label_ar ?? opt.label ?? opt.value };
        });
      }
    }

    return [];
  };

  const handleFieldChange = (fieldId: string, value: string) => {
    setValues((prev) => ({ ...prev, [fieldId]: value }));
  };

  const handleNext = useCallback(() => {
    if (!executionId || !currentStep) return;

    const stepValues: Record<string, string> = {};
    stepFields.forEach((f: WorkflowField) => {
      const fid = resolveFieldId(f);
      stepValues[fid] = values[fid] ?? f.default_value ?? "";
    });

    submitMut.mutate(
      { id: executionId, step_index: stepIndex, values: stepValues },
      {
        onSuccess: (data: any) => {
          setStepIndex(data.current_step_index);
          setIsReview(data.is_review);
          if (data.modified_values) {
            setValues((prev) => ({ ...prev, ...data.modified_values }));
          }
          if (data.field_states) {
            setFieldStates(data.field_states);
            // Track fields that have been shown by rule actions
            const newlyShown = new Set<string>();
            for (const [fid, state] of Object.entries(data.field_states)) {
              if ((state as any).is_visible === true) {
                newlyShown.add(fid);
              }
            }
            setShownFieldIds(prev => {
              const next = new Set(prev);
              newlyShown.forEach(id => next.add(id));
              return next;
            });
          }
          if (data.is_review) {
            setPreview({
              items: data.calculated_items,
              total_amount: data.total_amount,
              modified_values: data.modified_values,
              field_states: data.field_states,
            });
          }
          if (data.routing_decisions && data.routing_decisions.length > 0) {
            setRoutingDecisions(data.routing_decisions);
            setActiveRoutingIndex(0);
          }
          if (debugMode) {
            setDebugTrace({
              step_index: stepIndex,
              modified_values: data.modified_values,
              field_states: data.field_states,
              calculated_items: data.calculated_items,
              total_amount: data.total_amount,
              routing_decisions: data.routing_decisions,
              validation_warnings: data.validation_warnings,
              enterprise_stats: data.enterprise_stats,
              enterprise_results: data.enterprise_results,
              version_info: data.version_info,
            });
          }
        },
      }
    );
  }, [executionId, currentStep, stepFields, values, stepIndex]);

  const handlePreview = useCallback(() => {
    if (!activeVersion) return;
    previewMut.mutate(
      { workflow_version_id: activeVersion.id, values },
      { onSuccess: (data) => setPreview(data) }
    );
  }, [activeVersion, values]);

  const handleComplete = () => {
    if (!executionId) return;
    completeMut.mutate(
      { id: executionId, notes },
      {
        onSuccess: (data: any) => {
          setReceiptId(data.receipt?.id);
        },
      }
    );
  };

  const handleApplyFieldEffects = (effects: Array<{ action: string; field_id: string; value?: string }>) => {
    const newFieldStates = { ...fieldStates };
    const newValues = { ...values };
    const newlyShown = new Set<string>();

    for (const effect of effects) {
      switch (effect.action) {
        case "hide":
          newFieldStates[effect.field_id] = {
            ...(newFieldStates[effect.field_id] ?? { is_visible: true, is_required: false, is_readonly: false }),
            is_visible: false,
          };
          break;
        case "show":
          newFieldStates[effect.field_id] = {
            ...(newFieldStates[effect.field_id] ?? { is_visible: true, is_required: false, is_readonly: false }),
            is_visible: true,
          };
          newlyShown.add(effect.field_id);
          break;
        case "set_value":
          newValues[effect.field_id] = effect.value ?? "";
          break;
        case "set_required":
          newFieldStates[effect.field_id] = {
            ...(newFieldStates[effect.field_id] ?? { is_visible: true, is_required: false, is_readonly: false }),
            is_required: true,
          };
          break;
        case "set_readonly":
          newFieldStates[effect.field_id] = {
            ...(newFieldStates[effect.field_id] ?? { is_visible: true, is_required: false, is_readonly: false }),
            is_readonly: true,
          };
          break;
      }
    }

    setFieldStates(newFieldStates);
    setValues(newValues);
    if (newlyShown.size > 0) {
      setShownFieldIds(prev => {
        const next = new Set(prev);
        newlyShown.forEach(id => next.add(id));
        return next;
      });
    }
  };

  const handleRoutingContinue = () => {
    if (activeRoutingIndex !== null && activeRoutingIndex < routingDecisions.length - 1) {
      setActiveRoutingIndex(activeRoutingIndex + 1);
    } else {
      setRoutingDecisions([]);
      setActiveRoutingIndex(null);
    }
  };

  const handleRoutingBlock = () => {
    setRoutingDecisions([]);
    setActiveRoutingIndex(null);
  };

  if (!workflow || !activeVersion) {
    return (
      <div dir="rtl" style={{ padding: "48px", textAlign: "center" }}>
        <LoadingSpinner />
      </div>
    );
  }

  if (receiptId) {
    return (
      <div dir="rtl" style={{ padding: "48px", textAlign: "center", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
        <div style={{ fontSize: "48px", marginBottom: "16px" }}>✅</div>
        <div style={{ fontSize: "18px", fontWeight: 500, color: "var(--color-text-success)", marginBottom: "8px" }}>
          تم إنشاء الوصل بنجاح
        </div>
        <div style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: "20px" }}>
          رقم الوصل: {receiptId}
        </div>
        <div style={{ display: "flex", gap: "10px", justifyContent: "center" }}>
          <button
            onClick={() => navigate(`/receipts/${receiptId}`)}
            style={{
              padding: "8px 20px",
              fontSize: "13px",
              background: "var(--color-background-info)",
              color: "var(--color-text-info)",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "var(--border-radius-md)",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            عرض الوصل
          </button>
          <button
            onClick={() => navigate("/receipts/create")}
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
            وصل جديد
          </button>
        </div>
      </div>
    );
  }

  // Stepper dots
  const stepperDots = () => (
    <div style={{ display: "flex", gap: "6px", marginBottom: "20px", justifyContent: "center" }}>
      {steps.map((s: WorkflowStep, i: number) => (
        <div
          key={s.id}
          style={{
            display: "flex",
            alignItems: "center",
            gap: "6px",
          }}
        >
          <div
            style={{
              width: "28px",
              height: "28px",
              borderRadius: "50%",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontSize: "12px",
              fontWeight: 500,
              background:
                i < stepIndex
                  ? "var(--color-background-success)"
                  : i === stepIndex
                  ? "var(--color-text-primary)"
                  : "var(--color-background-secondary)",
              color:
                i < stepIndex
                  ? "var(--color-text-success)"
                  : i === stepIndex
                  ? "#fff"
                  : "var(--color-text-secondary)",
              border: `0.5px solid ${i === stepIndex ? "var(--color-text-primary)" : "var(--color-border-secondary)"}`,
            }}
          >
            {i < stepIndex ? "✓" : i + 1}
          </div>
          {i < steps.length - 1 && (
            <div
              style={{
                width: "24px",
                height: "2px",
                background: i < stepIndex ? "var(--color-border-success)" : "var(--color-border-secondary)",
              }}
            />
          )}
        </div>
      ))}
      <div
        style={{
          width: "28px",
          height: "28px",
          borderRadius: "50%",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          fontSize: "12px",
          fontWeight: 500,
          background: isReview ? "var(--color-text-primary)" : "var(--color-background-secondary)",
          color: isReview ? "#fff" : "var(--color-text-secondary)",
          border: `0.5px solid ${isReview ? "var(--color-text-primary)" : "var(--color-border-secondary)"}`,
        }}
      >
        {isReview ? "✓" : "★"}
      </div>
    </div>
  );

  const getFieldState = (field: WorkflowField) => {
    const fid = resolveFieldId(field);
    const state = fieldStates[fid];
    return {
      isVisible: state ? state.is_visible : field.is_visible,
      isRequired: state ? state.is_required : field.is_required,
      isReadonly: state ? state.is_readonly : field.is_readonly,
    };
  };

  const fieldInput = (field: WorkflowField) => {
    const fid = resolveFieldId(field);
    const val = values[fid] ?? field.default_value ?? "";
    const label = field.label;
    const state = getFieldState(field);
    const fieldType = resolveFieldType(field);
    const options = resolveFieldOptions(field);

    switch (fieldType) {
      case "select":
      case "radio":
        return (
          <select
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
          >
            <option value="">اختر...</option>
            {options.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        );
      case "multi_select":
        return (
          <select
            multiple
            value={val ? (Array.isArray(val) ? val : JSON.parse(val)) : []}
            onChange={(e) => {
              const selected = Array.from(e.target.selectedOptions, (o) => o.value);
              handleFieldChange(fid, JSON.stringify(selected));
            }}
            style={{ ...inputStyle, minHeight: "80px" }}
            disabled={state.isReadonly}
          >
            {options.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        );
      case "checkbox":
        return (
          <label style={{ display: "flex", alignItems: "center", gap: "10px", cursor: state.isReadonly ? "not-allowed" : "pointer", padding: "8px 0" }}>
            <input
              type="checkbox"
              checked={toBoolean(val)}
              onChange={(e) => handleFieldChange(fid, e.target.checked ? "1" : "0")}
              disabled={state.isReadonly}
              style={{ width: "18px", height: "18px", cursor: state.isReadonly ? "not-allowed" : "pointer" }}
            />
            <span style={{ fontSize: "13px", color: state.isReadonly ? "var(--color-text-tertiary)" : "var(--color-text-primary)" }}>{label}</span>
          </label>
        );
      case "number":
      case "decimal":
        return (
          <input
            type="number"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
            placeholder={field.placeholder ?? ""}
            step={fieldType === "decimal" ? "0.001" : "1"}
          />
        );
      case "date":
        return (
          <input
            type="date"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
          />
        );
      case "datetime":
        return (
          <input
            type="datetime-local"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
          />
        );
      case "email":
        return (
          <input
            type="email"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
            placeholder={field.placeholder ?? ""}
          />
        );
      case "phone":
        return (
          <input
            type="tel"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
            placeholder={field.placeholder ?? ""}
          />
        );
      case "url":
        return (
          <input
            type="url"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
            placeholder={field.placeholder ?? ""}
          />
        );
      case "textarea":
        return (
          <textarea
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={{ ...inputStyle, minHeight: "80px", resize: "vertical" }}
            disabled={state.isReadonly}
            placeholder={field.placeholder ?? ""}
          />
        );
      default:
        return (
          <input
            type="text"
            value={val}
            onChange={(e) => handleFieldChange(fid, e.target.value)}
            style={inputStyle}
            disabled={state.isReadonly}
            placeholder={field.placeholder ?? ""}
          />
        );
    }
  };

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif", maxWidth: "720px", margin: "0 auto" }}>
      <PageHeader
        title={workflow.name_ar}
        subtitle={isReview ? "مراجعة المعاملة" : currentStep?.title_ar}
        back={{ label: "← إلغاء", onClick: () => navigate(-1) }}
      />

      {stepperDots()}

      {/* Debug Mode Toggle */}
      <div style={{ marginBottom: "12px", display: "flex", justifyContent: "flex-end" }}>
        <button
          onClick={() => setDebugMode(!debugMode)}
          style={{
            padding: "4px 10px",
            fontSize: "11px",
            background: debugMode ? "var(--color-background-warning)" : "transparent",
            color: debugMode ? "var(--color-text-warning)" : "var(--color-text-tertiary)",
            border: `0.5px solid ${debugMode ? "var(--color-border-warning)" : "var(--color-border-tertiary)"}`,
            borderRadius: "4px",
            cursor: "pointer",
            fontFamily: "inherit",
          }}
        >
          {debugMode ? "🔧 إخفاء التصحيح" : "🔧 وضع التصحيح"}
        </button>
      </div>

      {/* Debug Trace Panel */}
      {debugMode && debugTrace && (
        <div style={{ marginBottom: "16px", padding: "12px", background: "#1e1e1e", borderRadius: "8px", border: "1px solid #333", fontSize: "11px", fontFamily: "monospace", color: "#d4d4d4", direction: "ltr", textAlign: "left", maxHeight: "600px", overflowY: "auto" }}>
          <div style={{ color: "#569cd6", fontWeight: "bold", marginBottom: "8px" }}>⚡ Rule Execution Trace</div>
          {debugTrace.version_info && (
            <div style={{ marginBottom: "6px", padding: "6px", background: "#2d2d2d", borderRadius: "4px" }}>
              <div><span style={{ color: "#9cdcfe" }}>Version:</span> V{debugTrace.version_info.version} · {debugTrace.version_info.status === "active" ? "🟢 منشورة" : debugTrace.version_info.status === "draft" ? "🟡 مسودة" : "⚪ مؤرشفة"}</div>
              <div><span style={{ color: "#9cdcfe" }}>Validation Rules:</span> {debugTrace.version_info.validation_rules_count}</div>
              <div><span style={{ color: "#9cdcfe" }}>Enterprise Rules:</span> {debugTrace.version_info.enterprise_rules_count}</div>
            </div>
          )}
          <div style={{ marginBottom: "4px" }}>
            <span style={{ color: "#9cdcfe" }}>Step:</span> {debugTrace.step_index}
          </div>
          {debugTrace.enterprise_stats && (
            <div style={{ marginBottom: "6px", padding: "6px", background: "#2d2d2d", borderRadius: "4px" }}>
              <span style={{ color: "#9cdcfe" }}>All Rules:</span> Evaluated=<span style={{ color: "#b5cea8" }}>{debugTrace.enterprise_stats.total_rules_evaluated}</span>, Matched=<span style={{ color: "#4ec9b0" }}>{debugTrace.enterprise_stats.matched_rules}</span>, Failed=<span style={{ color: "#f44747" }}>{debugTrace.enterprise_stats.failed_rules}</span>, Time=<span style={{ color: "#b5cea8" }}>{debugTrace.enterprise_stats.execution_time_ms}</span>ms
            </div>
          )}
          {debugTrace.enterprise_results && debugTrace.enterprise_results.length > 0 && (
            <div style={{ marginBottom: "6px" }}>
              <span style={{ color: "#9cdcfe", fontWeight: "bold" }}>Rule Details:</span>
              {debugTrace.enterprise_results.map((rule: any, i: number) => (
                <div key={i} style={{ marginLeft: "12px", marginTop: "4px", padding: "4px 6px", background: rule.matched ? "#1a3a1a" : "#2d2d2d", borderRadius: "3px", borderLeft: `2px solid ${rule.matched ? "#4ec9b0" : "#555"}` }}>
                  <div style={{ color: rule.matched ? "#4ec9b0" : "#808080" }}>
                    [{rule.matched ? "MATCH" : "SKIP"}] {rule.rule_name ?? rule.rule_id}
                    <span style={{ marginLeft: "8px", fontSize: "10px", padding: "1px 4px", background: rule.rule_type === "enterprise" ? "#3a1a3a" : rule.rule_type === "case_based" ? "#1a3a3a" : "#3a3a1a", borderRadius: "3px", color: rule.rule_type === "enterprise" ? "#c586c0" : rule.rule_type === "case_based" ? "#4ec9b0" : "#dcdcaa" }}>
                      {rule.rule_type === "enterprise" ? "⚡ Enterprise" : rule.rule_type === "case_based" ? "🔀 Case" : "📋 Simple"}
                    </span>
                  </div>
                  {rule.condition_trace && !rule.matched && (
                    <div style={{ marginLeft: "8px", color: "#808080", marginTop: "2px" }}>
                      Conditions: {renderConditionTrace(rule.condition_trace)}
                    </div>
                  )}
                  {rule.executed_actions && rule.executed_actions.length > 0 && (
                    <div style={{ marginLeft: "8px", color: "#dcdcaa" }}>
                      Actions: {rule.executed_actions.join(", ")}
                    </div>
                  )}
                  {rule.field_effects && rule.field_effects.length > 0 && (
                    <div style={{ marginLeft: "8px", color: "#ce9178" }}>
                      Effects: {rule.field_effects.map((e: any) => `${e.action}(${e.field_id}${e.value ? `=${e.value}` : ""}${e.fee_code ? ` fee=${e.fee_code}` : ""}${e.amount ? ` amt=${e.amount}` : ""})`).join(", ")}
                    </div>
                  )}
                  {rule.messages && rule.messages.length > 0 && (
                    <div style={{ marginLeft: "8px", color: "#c586c0" }}>
                      Messages: {rule.messages.map((m: any) => `${m.type}: ${m.message_ar}`).join("; ")}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
          {debugTrace.calculated_items && debugTrace.calculated_items.length > 0 && (
            <div style={{ marginBottom: "4px" }}>
              <span style={{ color: "#9cdcfe" }}>Calculated Items:</span>
              {debugTrace.calculated_items.map((item: any, i: number) => (
                <div key={i} style={{ marginLeft: "12px", color: "#ce9178" }}>
                  {item.label}: {item.amount > 0 ? `${item.amount.toLocaleString("en")} د.ع` : item.text_value ?? "—"} {item.action && `(${item.action})`}
                </div>
              ))}
            </div>
          )}
          <div style={{ marginBottom: "4px", color: "#6a9955", fontWeight: "bold" }}>
            Total: {debugTrace.total_amount?.toLocaleString("en") ?? "0"} د.ع
          </div>
          {debugTrace.field_states && Object.keys(debugTrace.field_states).length > 0 && (
            <div style={{ marginBottom: "4px" }}>
              <span style={{ color: "#9cdcfe" }}>Field States:</span>
              {Object.entries(debugTrace.field_states).map(([fid, state]: [string, any]) => (
                <div key={fid} style={{ marginLeft: "12px", color: "#dcdcaa" }}>
                  {fid}: visible={state.is_visible ? "Y" : "N"}, required={state.is_required ? "Y" : "N"}, readonly={state.is_readonly ? "Y" : "N"}
                </div>
              ))}
            </div>
          )}
          {debugTrace.modified_values && Object.keys(debugTrace.modified_values).length > 0 && (
            <div style={{ marginBottom: "4px" }}>
              <span style={{ color: "#9cdcfe" }}>Modified Values:</span>
              {Object.entries(debugTrace.modified_values).map(([fid, val]: [string, any]) => (
                <div key={fid} style={{ marginLeft: "12px", color: "#ce9178" }}>
                  {fid} = {val}
                </div>
              ))}
            </div>
          )}
          {debugTrace.routing_decisions && debugTrace.routing_decisions.length > 0 && (
            <div style={{ marginBottom: "4px" }}>
              <span style={{ color: "#9cdcfe" }}>Routing Decisions:</span>
              {debugTrace.routing_decisions.map((rd: any, i: number) => (
                <div key={i} style={{ marginLeft: "12px", color: "#c586c0" }}>
                  → {rd.action} {rd.data ? JSON.stringify(rd.data) : ""}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Routing/Branch handlers */}
      {routingDecisions.length > 0 && activeRoutingIndex !== null && (
        <div style={{ marginBottom: "16px" }}>
          {(() => {
            const decision = routingDecisions[activeRoutingIndex];
            const effect = decision.decision ?? "warn";
            return (
              <BranchHandler
                key={decision.rule_id ?? activeRoutingIndex}
                executionId={executionId ?? ""}
                effect={effect}
                data={{
                  message: decision.message,
                  target_workflow_id: decision.target_workflow_id,
                  target_step_id: decision.target_step_id,
                  existing_record: decision.existing_record,
                  actions: decision.actions,
                  rule_id: decision.rule_id,
                  rule_name: decision.rule_name,
                  field_effects: decision.field_effects,
                  warnings: decision.warnings,
                }}
                onContinue={handleRoutingContinue}
                onBlock={handleRoutingBlock}
                onApplyFieldEffects={handleApplyFieldEffects}
              />
            );
          })()}
        </div>
      )}

      {isReview ? (
        <div>
          {/* Review screen */}
          <div
            style={{
              background: "var(--color-background-primary)",
              border: "0.5px solid var(--color-border-tertiary)",
              borderRadius: "var(--border-radius-lg)",
              padding: "16px",
              marginBottom: "16px",
            }}
          >
            <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "12px" }}>ملخص المعاملة</div>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
              <tbody>
                {preview?.items?.map((item: any, i: number) => (
                  <tr key={i} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                    <td style={{ padding: "8px 0", color: "var(--color-text-secondary)" }}>
                      {item.label}
                      {item.action && (
                        <span style={{ fontSize: "10px", marginRight: "6px", padding: "1px 5px", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "3px" }}>
                          {item.action}
                        </span>
                      )}
                    </td>
                    <td style={{ padding: "8px 0", textAlign: "left", fontWeight: 500, direction: "ltr" }}>
                      {item.amount > 0 ? `${item.amount.toLocaleString("en")} د.ع` : item.text_value ?? "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div
              style={{
                marginTop: "12px",
                paddingTop: "12px",
                borderTop: "1px solid var(--color-border-secondary)",
                display: "flex",
                justifyContent: "space-between",
                fontSize: "16px",
                fontWeight: 500,
              }}
            >
              <span>الإجمالي</span>
              <span style={{ direction: "ltr" }}>{preview?.total_amount?.toLocaleString("en")} د.ع</span>
            </div>

            {/* Modified values (set_value actions) */}
            {preview?.modified_values && Object.keys(preview.modified_values).length > 0 && (
              <div style={{ marginTop: "16px" }}>
                <div style={{ fontSize: "13px", fontWeight: 500, marginBottom: "8px", color: "var(--color-text-primary)" }}>✏️ القيم المعدّلة بالقواعد</div>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "12px" }}>
                  <tbody>
                    {Object.entries(preview.modified_values).map(([fieldId, value]: [string, any], i: number) => {
                      const field = activeVersion?.fields?.find((f: WorkflowField) => (f.register_field_id ?? `custom_${f.id}`) === fieldId);
                      return (
                        <tr key={i} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                          <td style={{ padding: "6px 0", color: "var(--color-text-secondary)" }}>
                            {field?.label ?? fieldId}
                            {field?.register_field_id === null && (
                              <span style={{ fontSize: "9px", padding: "1px 4px", background: "var(--color-background-success)", color: "var(--color-text-success)", borderRadius: "3px", marginRight: "4px" }}>مخصص</span>
                            )}
                          </td>
                          <td style={{ padding: "6px 0", textAlign: "left", fontWeight: 500, direction: "ltr" }}>
                            {value ?? "—"}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div style={{ marginBottom: "14px" }}>
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)", display: "block", marginBottom: "4px" }}>
              ملاحظات
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              style={{ ...inputStyle, minHeight: "60px" }}
              placeholder="أي ملاحظات إضافية..."
            />
          </div>

          <div style={{ display: "flex", gap: "10px" }}>
            <button
              onClick={handleComplete}
              disabled={completeMut.isPending}
              style={{
                flex: 1,
                padding: "10px",
                fontSize: "14px",
                fontWeight: 500,
                background: "var(--color-background-success)",
                color: "var(--color-text-success)",
                border: "0.5px solid var(--color-border-success)",
                borderRadius: "var(--border-radius-md)",
                cursor: "pointer",
                fontFamily: "inherit",
              }}
            >
              {completeMut.isPending ? "جارٍ الحفظ..." : "✓ إنشاء الوصل"}
            </button>
            <button
              onClick={() => setIsReview(false)}
              style={{
                padding: "10px 20px",
                fontSize: "14px",
                background: "none",
                color: "var(--color-text-secondary)",
                border: "0.5px solid var(--color-border-secondary)",
                borderRadius: "var(--border-radius-md)",
                cursor: "pointer",
                fontFamily: "inherit",
              }}
            >
              رجوع
            </button>
          </div>
        </div>
      ) : (
        <div>
          {/* Step form */}
          <div
            style={{
              background: "var(--color-background-primary)",
              border: "0.5px solid var(--color-border-tertiary)",
              borderRadius: "var(--border-radius-lg)",
              padding: "16px",
              marginBottom: "16px",
            }}
          >
            <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "12px" }}>{currentStep?.title_ar}</div>
            <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
              {stepFields
                .filter((field: WorkflowField) => getFieldState(field).isVisible)
                .map((field: WorkflowField) => {
                  const state = getFieldState(field);
                  return (
                    <div key={field.id}>
                      <label style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)", display: "flex", alignItems: "center", gap: "4px", marginBottom: "6px" }}>
                        {field.label}
                        {state.isRequired && (
                          <span
                            style={{
                              color: "#dc2626",
                              fontSize: "14px",
                              fontWeight: 700,
                              lineHeight: 1,
                              marginLeft: "2px",
                            }}
                            title="حقل إلزامي"
                          >
                            *
                          </span>
                        )}
                        {field.is_financial && (
                          <span style={{ fontSize: "10px", padding: "1px 6px", background: "var(--color-background-success)", color: "var(--color-text-success)", borderRadius: "4px", fontWeight: 500 }}>مالي</span>
                        )}
                        {state.isReadonly && (
                          <span style={{ fontSize: "10px", padding: "1px 6px", background: "var(--color-background-warning)", color: "var(--color-text-warning)", borderRadius: "4px", fontWeight: 500 }}>للقراءة فقط</span>
                        )}
                        {field.register_field_id === null && (
                          <span style={{ fontSize: "9px", padding: "1px 5px", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "4px", fontWeight: 500 }}>مخصص</span>
                        )}
                      </label>
                      {fieldInput(field)}
                    </div>
                  );
                })}
            </div>
          </div>

          <div style={{ display: "flex", gap: "10px", justifyContent: "space-between" }}>
            <button
              onClick={() => setStepIndex((i) => Math.max(0, i - 1))}
              disabled={stepIndex === 0}
              style={{
                padding: "8px 16px",
                fontSize: "13px",
                background: "none",
                color: stepIndex === 0 ? "var(--color-text-tertiary)" : "var(--color-text-secondary)",
                border: "0.5px solid var(--color-border-secondary)",
                borderRadius: "var(--border-radius-md)",
                cursor: stepIndex === 0 ? "not-allowed" : "pointer",
                fontFamily: "inherit",
              }}
            >
              ← السابق
            </button>
            <button
              onClick={handleNext}
              disabled={submitMut.isPending}
              style={{
                padding: "8px 24px",
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
              {submitMut.isPending ? "جارٍ..." : stepIndex === steps.length - 1 ? "المراجعة →" : "التالي →"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

const inputStyle: React.CSSProperties = {
  padding: "8px 10px",
  fontSize: "13px",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  fontFamily: "inherit",
  width: "100%",
};
