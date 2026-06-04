import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQueryClient } from "@tanstack/react-query";
import client from "@/api/client";
import { Receipt } from "@/types/receipt";
import { usePermissions } from "@/hooks/usePermissions";
import ConfirmDialog from "@/components/ui/ConfirmDialog";

interface ReceiptActionsProps {
  receipt: Receipt;
  permissions?: string[];
  onRefresh?: () => void;
}

type DialogType = "cancel" | "issue" | "revise" | null;

export function ReceiptActions({ receipt, permissions: permissionsProp, onRefresh }: ReceiptActionsProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { can: canHook } = usePermissions();
  const [dialog, setDialog] = useState<DialogType>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const can = (p: string) => (permissionsProp ? permissionsProp.includes(p) : canHook(p));
  const status = receipt.status;

  const refresh = () => {
    if (onRefresh) {
      onRefresh();
    } else {
      queryClient.invalidateQueries({ queryKey: ["receipt", receipt.id] });
      queryClient.invalidateQueries({ queryKey: ["receipts"] });
    }
  };

  const handleIssue = async () => {
    setLoading(true); setError("");
    try {
      await client.post(`/receipts/${receipt.id}/issue`);
      refresh();
    } catch (e: unknown) {
      setError((e as { arabicMessage?: string })?.arabicMessage ?? "فشل الترحيل");
    } finally { setLoading(false); setDialog(null); }
  };

  const handleCancel = async (reason: string) => {
    setLoading(true); setError("");
    try {
      await client.post(`/receipts/${receipt.id}/cancel`, { reason });
      refresh();
    } catch (e: unknown) {
      setError((e as { arabicMessage?: string })?.arabicMessage ?? "فشل الإلغاء");
    } finally { setLoading(false); setDialog(null); }
  };

  const handlePrint = () => {
    window.open(`/receipts/${receipt.id}/print`, "_blank");
  };

  const btnStyle = (color: string): React.CSSProperties => ({
    padding: "8px 16px", fontSize: "13px", fontWeight: 500, border: `0.5px solid ${color}`,
    background: "transparent", color, borderRadius: "6px", cursor: loading ? "not-allowed" : "pointer",
    fontFamily: "inherit", opacity: loading ? 0.6 : 1, display: "flex", alignItems: "center", gap: "6px",
  });

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
      {error && (
        <div style={{ background: "var(--color-background-danger)", color: "var(--color-text-danger)", padding: "8px 12px", borderRadius: "6px", fontSize: "12px" }}>
          {error}
        </div>
      )}

      {can("issue-receipt") && (status === "draft" || status === "pending") && (
        <button style={btnStyle("var(--color-text-success)")} onClick={() => setDialog("issue")} disabled={loading}>
          ✓ ترحيل الوصل
        </button>
      )}

      {can("print-receipt") && (status === "issued" || status === "printed") && (
        <button style={btnStyle("var(--color-text-info)")} onClick={handlePrint} disabled={loading}>
          🖨️ طباعة
        </button>
      )}

      {can("revise-receipt") && (status === "issued" || status === "printed") && (
        <button style={btnStyle("var(--color-text-warning)")} onClick={() => setDialog("revise")} disabled={loading}>
          ✎ تعديل (مراجعة)
        </button>
      )}

      {can("cancel-receipt") && status !== "cancelled" && (
        <button style={btnStyle("var(--color-text-danger)")} onClick={() => setDialog("cancel")} disabled={loading}>
          ✕ إلغاء الوصل
        </button>
      )}

      <ConfirmDialog
        isOpen={dialog === "issue"}
        title="تأكيد ترحيل الوصل"
        message={`هل أنت متأكد من ترحيل الوصل ${receipt.receipt_number}؟ لن يمكن تعديله إلا عبر مراجعة رسمية.`}
        confirmLabel="نعم، رحّل الوصل"
        variant="info"
        onConfirm={handleIssue}
        onCancel={() => setDialog(null)}
      />

      <ConfirmDialog
        isOpen={dialog === "cancel"}
        title="إلغاء الوصل"
        message={`سيتم إلغاء الوصل ${receipt.receipt_number}. هذا الإجراء لا يمكن التراجع عنه.`}
        confirmLabel="نعم، ألغِ الوصل"
        requireReason={true}
        reasonLabel="سبب الإلغاء"
        reasonMinLength={10}
        variant="danger"
        onConfirm={(reason) => handleCancel(reason ?? "")}
        onCancel={() => setDialog(null)}
      />

      <ConfirmDialog
        isOpen={dialog === "revise"}
        title="تعديل الوصل المرحّل"
        message="سيتم إنشاء نسخة مراجعة. أدخل سبب التعديل."
        confirmLabel="انتقل للتعديل"
        requireReason={true}
        reasonLabel="سبب التعديل"
        reasonMinLength={10}
        variant="warning"
        onConfirm={(reason) => navigate(`/receipts/${receipt.id}/revise`, { state: { reason } })}
        onCancel={() => setDialog(null)}
      />
    </div>
  );
}
