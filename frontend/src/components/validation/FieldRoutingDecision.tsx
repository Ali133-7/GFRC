import { useState } from "react";

interface FieldRoutingDecisionProps {
  decision: {
    message: string;
    actions: string[];
    existing_record?: { id: string; register_id: string; created_at?: string };
    target_workflow_id?: string;
    target_step_id?: string;
  };
  onAction: (action: string) => void;
  onDismiss: () => void;
}

const ACTION_LABELS: Record<string, { label: string; icon: string; color: string }> = {
  view_existing: { label: "عرض السجل القديم", icon: "👁️", color: "info" },
  continue_update: { label: "متابعة في التحديث", icon: "✏️", color: "warning" },
  start_renewal: { label: "بدء تجديد", icon: "🔄", color: "success" },
  route_workflow: { label: "تحويل لسير عمل آخر", icon: "🔀", color: "info" },
};

export default function FieldRoutingDecision({ decision, onAction, onDismiss }: FieldRoutingDecisionProps) {
  const [showDetails, setShowDetails] = useState(false);

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
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "12px" }}>
        <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
          <span
            style={{
              width: "32px",
              height: "32px",
              borderRadius: "50%",
              background: "rgba(245, 158, 11, 0.2)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontSize: "16px",
            }}
          >
            ⚠️
          </span>
          <div>
            <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-warning)" }}>
              تم العثور على سجل سابق
            </div>
            <div style={{ fontSize: "12px", color: "var(--color-text-secondary)", marginTop: "2px" }}>
              {decision.message}
            </div>
          </div>
        </div>
        <button
          onClick={onDismiss}
          style={{
            background: "none",
            border: "none",
            cursor: "pointer",
            fontSize: "16px",
            color: "var(--color-text-secondary)",
            padding: "2px 4px",
          }}
        >
          ×
        </button>
      </div>

      {/* Existing record details */}
      {decision.existing_record && (
        <button
          onClick={() => setShowDetails(!showDetails)}
          style={{
            width: "100%",
            padding: "8px 12px",
            fontSize: "12px",
            background: "var(--color-background-primary)",
            border: "0.5px solid var(--color-border-tertiary)",
            borderRadius: "6px",
            cursor: "pointer",
            fontFamily: "inherit",
            color: "var(--color-text-secondary)",
            textAlign: "right",
            marginBottom: "12px",
          }}
        >
          {showDetails ? "إخفاء التفاصيل" : "عرض تفاصيل السجل"} ▼
        </button>
      )}

      {showDetails && decision.existing_record && (
        <div
          style={{
            padding: "10px 12px",
            background: "var(--color-background-primary)",
            borderRadius: "6px",
            marginBottom: "12px",
            fontSize: "12px",
            color: "var(--color-text-secondary)",
          }}
        >
          <div>معرف السجل: <strong style={{ color: "var(--color-text-primary)", fontFamily: "monospace" }}>{decision.existing_record.id}</strong></div>
          {decision.existing_record.created_at && (
            <div>تاريخ الإنشاء: <strong style={{ color: "var(--color-text-primary)" }}>{decision.existing_record.created_at}</strong></div>
          )}
        </div>
      )}

      {/* Action buttons */}
      <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
        {decision.actions.map((action) => {
          const actionMeta = ACTION_LABELS[action] ?? { label: action, icon: "•", color: "secondary" };
          return (
            <button
              key={action}
              onClick={() => onAction(action)}
              style={{
                padding: "8px 14px",
                fontSize: "12px",
                fontWeight: 500,
                borderRadius: "6px",
                border: `0.5px solid var(--color-border-${actionMeta.color})`,
                background: `var(--color-background-${actionMeta.color})`,
                color: `var(--color-text-${actionMeta.color})`,
                cursor: "pointer",
                fontFamily: "inherit",
                display: "flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <span>{actionMeta.icon}</span>
              {actionMeta.label}
            </button>
          );
        })}
      </div>
    </div>
  );
}
