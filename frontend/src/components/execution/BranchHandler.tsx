import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { workflowExecutionBranchApi } from "@/api/workflows";

interface BranchHandlerProps {
  executionId: string;
  effect: string;
  data: {
    message?: string;
    target_workflow_id?: string;
    target_workflow_name?: string;
    target_version_id?: string;
    target_step_id?: string;
    target_mode?: string;
    from_mode?: string;
    to_mode?: string;
    preserved_values?: Record<string, string>;
    state_mapping?: Record<string, string>;
    existing_record?: { id: string; register_id: string; created_at?: string };
    actions?: string[];
    rule_id?: string;
    rule_name?: string;
    warnings?: Array<{ message: string; rule_name?: string }>;
    field_effects?: Array<{ action: string; field_id: string; value?: string }>;
  };
  onContinue: () => void;
  onBlock: () => void;
  onApplyFieldEffects?: (effects: Array<{ action: string; field_id: string; value?: string }>) => void;
}

const ACTION_LABELS: Record<string, { label: string; icon: string; color: string }> = {
  view_existing: { label: "عرض السجل القديم", icon: "👁️", color: "info" },
  continue_update: { label: "متابعة في التحديث", icon: "✏️", color: "warning" },
  start_renewal: { label: "بدء تجديد", icon: "🔄", color: "success" },
  route_workflow: { label: "تحويل لسير عمل آخر", icon: "🔀", color: "info" },
  continue_workflow: { label: "متابعة كمعاملة جديدة", icon: "➕", color: "success" },
};

export default function BranchHandler({ executionId, effect, data, onContinue, onBlock, onApplyFieldEffects }: BranchHandlerProps) {
  const navigate = useNavigate();
  const [processing, setProcessing] = useState(false);
  const [showWarnings, setShowWarnings] = useState(true);

  const handleAction = async (action: string) => {
    setProcessing(true);
    try {
      switch (action) {
        case "view_existing":
          // Navigate to existing record
          if (data.existing_record) {
            navigate(`/registers/${data.existing_record.register_id}/records/${data.existing_record.id}`);
          }
          break;

        case "continue_update":
          // Switch to update mode and continue
          await workflowExecutionBranchApi.switchMode(executionId, "update", "existing_record_found");
          onContinue();
          break;

        case "start_renewal":
          // Switch to renewal mode
          await workflowExecutionBranchApi.switchMode(executionId, "renewal", "existing_record_found");
          onContinue();
          break;

        case "route_workflow":
          // Redirect to target workflow
          if (data.target_workflow_id) {
            // Save draft first
            if (data.preserved_values && Object.keys(data.preserved_values).length > 0) {
              await workflowExecutionBranchApi.saveDraft(executionId, data.preserved_values);
            }

            // Redirect
            await workflowExecutionBranchApi.redirect(
              executionId,
              data.target_workflow_id,
              data.target_step_id,
              data.state_mapping,
              "field_existence_check"
            );

            // Navigate to target workflow
            navigate(`/workflows/${data.target_workflow_id}/execute?version=${data.target_version_id}&mode=${data.target_mode ?? "update"}`);
          }
          break;

        case "continue_workflow":
          onContinue();
          break;

        default:
          onContinue();
      }
    } catch (err) {
      console.error("Branch action failed:", err);
    } finally {
      setProcessing(false);
    }
  };

  // Block effect
  if (effect === "block") {
    return (
      <div
        style={{
          margin: "12px 0",
          padding: "16px",
          background: "var(--color-background-danger)",
          border: "1px solid var(--color-border-danger)",
          borderRadius: "var(--border-radius-lg)",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "8px" }}>
          <span style={{ fontSize: "20px" }}>🚫</span>
          <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-danger)" }}>
            {data.rule_name || "تم منع العملية"}
          </div>
        </div>
        <div style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: "12px" }}>
          {data.message}
        </div>
        <button onClick={onBlock} style={btnDanger}>
          حسناً
        </button>
      </div>
    );
  }

  // Redirect effect
  if (effect === "redirect") {
    return (
      <div
        style={{
          margin: "12px 0",
          padding: "16px",
          background: "var(--color-background-info)",
          border: "1px solid var(--color-border-info)",
          borderRadius: "var(--border-radius-lg)",
          boxShadow: "0 2px 8px rgba(59, 130, 246, 0.15)",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "12px" }}>
          <span
            style={{
              width: "32px",
              height: "32px",
              borderRadius: "50%",
              background: "rgba(59, 130, 246, 0.2)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontSize: "16px",
            }}
          >
            🔀
          </span>
          <div>
            <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-info)" }}>
              تحويل مسار سير العمل
            </div>
            <div style={{ fontSize: "12px", color: "var(--color-text-secondary)", marginTop: "2px" }}>
              {data.message}
            </div>
          </div>
        </div>

        {data.target_workflow_name && (
          <div style={{ padding: "8px 12px", background: "var(--color-background-primary)", borderRadius: "6px", marginBottom: "12px", fontSize: "13px" }}>
            سير العمل الهدف: <strong style={{ color: "var(--color-text-info)" }}>{data.target_workflow_name}</strong>
            {data.target_mode && (
              <span style={{ marginLeft: "8px", fontSize: "11px", padding: "2px 6px", background: "var(--color-background-warning)", color: "var(--color-text-warning)", borderRadius: "4px" }}>
                الوضع: {data.target_mode}
              </span>
            )}
          </div>
        )}

        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
          <button
            onClick={() => handleAction("route_workflow")}
            disabled={processing}
            style={btnPrimary}
          >
            {processing ? "جارٍ التحويل..." : "🔀 تحويل الآن"}
          </button>
          <button onClick={onContinue} style={btnSecondary}>
            متابعة هنا
          </button>
        </div>
      </div>
    );
  }

  // Mode switch effect
  if (effect === "mode_switch") {
    return (
      <div
        style={{
          margin: "12px 0",
          padding: "16px",
          background: "var(--color-background-warning)",
          border: "1px solid var(--color-border-warning)",
          borderRadius: "var(--border-radius-lg)",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "8px" }}>
          <span style={{ fontSize: "20px" }}>🔄</span>
          <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-warning)" }}>
            تم تغيير مسار التنفيذ
          </div>
        </div>
        <div style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: "12px" }}>
          {data.message}
          {data.to_mode && (
            <span style={{ marginLeft: "8px", fontSize: "11px", padding: "2px 6px", background: "var(--color-background-warning)", color: "var(--color-text-warning)", borderRadius: "4px" }}>
              الوضع الجديد: {data.to_mode}
            </span>
          )}
        </div>
        <button onClick={onContinue} style={btnPrimary}>
          متابعة
        </button>
      </div>
    );
  }

  // Warn effect
  if (effect === "warn") {
    return (
      <div
        style={{
          margin: "12px 0",
          padding: "16px",
          background: "var(--color-background-warning)",
          border: "1px solid var(--color-border-warning)",
          borderRadius: "var(--border-radius-lg)",
          boxShadow: "0 2px 8px rgba(245, 158, 11, 0.15)",
        }}
      >
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "12px" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
            <span style={{ fontSize: "20px" }}>⚠️</span>
            <div>
              <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-warning)" }}>
                {data.rule_name || "تحذير"}
              </div>
              <div style={{ fontSize: "12px", color: "var(--color-text-secondary)", marginTop: "2px" }}>
                {data.message}
              </div>
            </div>
          </div>
        </div>

        {/* Action buttons */}
        {data.actions && data.actions.length > 0 && (
          <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", marginBottom: "12px" }}>
            {data.actions.map((action) => {
              const actionMeta = ACTION_LABELS[action] ?? { label: action, icon: "•", color: "secondary" };
              return (
                <button
                  key={action}
                  onClick={() => handleAction(action)}
                  disabled={processing}
                  style={{
                    padding: "8px 14px",
                    fontSize: "12px",
                    fontWeight: 500,
                    borderRadius: "6px",
                    border: `0.5px solid var(--color-border-${actionMeta.color})`,
                    background: `var(--color-background-${actionMeta.color})`,
                    color: `var(--color-text-${actionMeta.color})`,
                    cursor: processing ? "not-allowed" : "pointer",
                    fontFamily: "inherit",
                    display: "flex",
                    alignItems: "center",
                    gap: "6px",
                    opacity: processing ? 0.6 : 1,
                  }}
                >
                  <span>{actionMeta.icon}</span>
                  {actionMeta.label}
                </button>
              );
            })}
          </div>
        )}

        {/* Field effects indicator */}
        {data.field_effects && data.field_effects.length > 0 && (
          <div style={{ marginTop: "8px", padding: "8px 12px", background: "var(--color-background-primary)", borderRadius: "6px" }}>
            <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginBottom: "4px" }}>
              تأثيرات على الحقول:
            </div>
            {data.field_effects.map((fe, i) => (
              <div key={i} style={{ fontSize: "12px", color: "var(--color-text-secondary)", marginBottom: "2px" }}>
                {fe.action === "hide" && "👁️‍🗨️ إخفاء"}
                {fe.action === "show" && "👁️ إظهار"}
                {fe.action === "set_value" && `✏️ تعيين قيمة: ${fe.value}`}
                {fe.action === "set_required" && "⭐ تعيين مطلوب"}
                {fe.action === "set_readonly" && "🔒 للقراءة فقط"}
                {" "}— {fe.field_id}
              </div>
            ))}
            <button
              onClick={() => data.field_effects && onApplyFieldEffects?.(data.field_effects)}
              style={{ fontSize: "11px", color: "var(--color-text-warning)", background: "none", border: "none", cursor: "pointer", fontFamily: "inherit", marginTop: "4px" }}
            >
              تطبيق التأثيرات ▼
            </button>
          </div>
        )}

        {/* Warnings list */}
        {data.warnings && data.warnings.length > 0 && showWarnings && (
          <div style={{ marginTop: "8px", padding: "8px 12px", background: "var(--color-background-primary)", borderRadius: "6px" }}>
            <button
              onClick={() => setShowWarnings(false)}
              style={{ fontSize: "11px", color: "var(--color-text-tertiary)", background: "none", border: "none", cursor: "pointer", fontFamily: "inherit", marginBottom: "4px" }}
            >
              إخفاء التحذيرات ▲
            </button>
            {data.warnings.map((w, i) => (
              <div key={i} style={{ fontSize: "12px", color: "var(--color-text-secondary)", marginBottom: "2px" }}>
                ⚠️ {w.message} {w.rule_name && <span style={{ fontSize: "10px", color: "var(--color-text-tertiary)" }}>({w.rule_name})</span>}
              </div>
            ))}
          </div>
        )}

        <div style={{ display: "flex", gap: "8px", marginTop: "8px" }}>
          <button onClick={onContinue} style={btnSecondary}>
            متابعة على أي حال
          </button>
        </div>
      </div>
    );
  }

  // Confirm effect
  if (effect === "confirm") {
    return (
      <div
        style={{
          margin: "12px 0",
          padding: "16px",
          background: "var(--color-background-info)",
          border: "1px solid var(--color-border-info)",
          borderRadius: "var(--border-radius-lg)",
        }}
      >
        <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-info)", marginBottom: "8px" }}>
          تأكيد مطلوب
        </div>
        <div style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: "12px" }}>
          {data.message}
        </div>
        <div style={{ display: "flex", gap: "8px" }}>
          <button onClick={onContinue} style={btnPrimary}>
            تأكيد والمتابعة
          </button>
          <button onClick={onBlock} style={btnSecondary}>
            إلغاء
          </button>
        </div>
      </div>
    );
  }

  return null;
}

const btnPrimary: React.CSSProperties = {
  padding: "8px 16px",
  fontSize: "13px",
  fontWeight: 500,
  background: "var(--color-background-info)",
  color: "var(--color-text-info)",
  border: "0.5px solid var(--color-border-info)",
  borderRadius: "6px",
  cursor: "pointer",
  fontFamily: "inherit",
};

const btnSecondary: React.CSSProperties = {
  padding: "8px 16px",
  fontSize: "13px",
  background: "none",
  color: "var(--color-text-secondary)",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  cursor: "pointer",
  fontFamily: "inherit",
};

const btnDanger: React.CSSProperties = {
  padding: "8px 16px",
  fontSize: "13px",
  fontWeight: 500,
  background: "var(--color-background-danger)",
  color: "var(--color-text-danger)",
  border: "0.5px solid var(--color-border-danger)",
  borderRadius: "6px",
  cursor: "pointer",
  fontFamily: "inherit",
};
