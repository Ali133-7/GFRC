import React, { useState } from "react";

interface ConfirmDialogProps {
  isOpen: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  requireReason?: boolean;
  reasonLabel?: string;
  reasonMinLength?: number;
  variant?: "danger" | "warning" | "info";
  onConfirm: (reason?: string) => void;
  onCancel: () => void;
}

export default function ConfirmDialog({
  isOpen,
  title,
  message,
  confirmLabel = "تأكيد",
  cancelLabel = "إلغاء",
  requireReason = false,
  reasonLabel = "السبب",
  reasonMinLength = 10,
  variant = "danger",
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const [reason, setReason] = useState("");
  const [reasonError, setReasonError] = useState("");

  if (!isOpen) return null;

  const variantColors: Record<string, string> = {
    danger:  "var(--color-background-danger)",
    warning: "var(--color-background-warning)",
    info:    "var(--color-background-info)",
  };

  const variantTextColors: Record<string, string> = {
    danger:  "var(--color-text-danger)",
    warning: "var(--color-text-warning)",
    info:    "var(--color-text-info)",
  };

  const handleConfirm = () => {
    if (requireReason && reason.trim().length < reasonMinLength) {
      setReasonError(`يجب أن يكون السبب ${reasonMinLength} أحرف على الأقل`);
      return;
    }
    onConfirm(requireReason ? reason.trim() : undefined);
    setReason("");
    setReasonError("");
  };

  const handleCancel = () => {
    setReason("");
    setReasonError("");
    onCancel();
  };

  return (
    <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", display: "flex", alignItems: "center", justifyContent: "center", zIndex: 9999, direction: "rtl" }}>
      <div style={{ background: "var(--color-background-primary)", borderRadius: "12px", padding: "24px", width: "420px", maxWidth: "90vw", border: "0.5px solid var(--color-border-secondary)" }}>
        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "12px" }}>
          <div style={{ width: "36px", height: "36px", borderRadius: "50%", background: variantColors[variant], display: "flex", alignItems: "center", justifyContent: "center" }}>
            <span style={{ color: variantTextColors[variant], fontSize: "18px" }}>!</span>
          </div>
          <h3 style={{ fontSize: "15px", fontWeight: 500, color: "var(--color-text-primary)", margin: 0 }}>{title}</h3>
        </div>

        <p style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: requireReason ? "16px" : "24px", lineHeight: 1.6 }}>{message}</p>

        {requireReason && (
          <div style={{ marginBottom: "20px" }}>
            <label style={{ display: "block", fontSize: "13px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "6px" }}>
              {reasonLabel} <span style={{ color: "var(--color-text-danger)" }}>*</span>
            </label>
            <textarea
              value={reason}
              onChange={(e) => { setReason(e.target.value); setReasonError(""); }}
              rows={3}
              placeholder={`يرجى كتابة ${reasonLabel.toLowerCase()} (${reasonMinLength} أحرف على الأقل)`}
              style={{ width: "100%", padding: "8px 12px", fontSize: "13px", border: `0.5px solid ${reasonError ? "var(--color-border-danger)" : "var(--color-border-secondary)"}`, borderRadius: "6px", background: "var(--color-background-primary)", color: "var(--color-text-primary)", outline: "none", fontFamily: "inherit", resize: "vertical", direction: "rtl", boxSizing: "border-box" }}
            />
            {reasonError && <p style={{ fontSize: "11px", color: "var(--color-text-danger)", marginTop: "4px" }}>{reasonError}</p>}
          </div>
        )}

        <div style={{ display: "flex", gap: "10px", justifyContent: "flex-start" }}>
          <button
            onClick={handleConfirm}
            style={{ background: variantColors[variant], color: variantTextColors[variant], border: `0.5px solid ${variantTextColors[variant]}`, borderRadius: "6px", padding: "8px 20px", fontSize: "13px", fontWeight: 500, cursor: "pointer", fontFamily: "inherit" }}
          >
            {confirmLabel}
          </button>
          <button
            onClick={handleCancel}
            style={{ background: "var(--color-background-secondary)", color: "var(--color-text-secondary)", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", padding: "8px 20px", fontSize: "13px", cursor: "pointer", fontFamily: "inherit" }}
          >
            {cancelLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
