import React, { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { usePermissions } from "@/hooks/usePermissions";
import type { TransactionTemplate } from "@/types/transactionTemplate";

export default function TransactionTemplateListPage() {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { can } = usePermissions();
  const [search, setSearch] = useState("");
  const canManage = can("manage-settings");

  const { data: templates, isLoading } = useQuery({
    queryKey: ["transaction-templates", search],
    queryFn: async () => {
      const r = await client.get("/transaction-templates", { params: { search } });
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : []) as TransactionTemplate[];
    },
  });

  const toggleMut = useMutation({
    mutationFn: (id: string) => client.patch(`/transaction-templates/${id}/toggle`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["transaction-templates"] }),
  });

  const cloneMut = useMutation({
    mutationFn: (id: string) => client.post(`/transaction-templates/${id}/clone`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["transaction-templates"] }),
  });

  const delMut = useMutation({
    mutationFn: (id: string) => client.delete(`/transaction-templates/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["transaction-templates"] }),
  });

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader title="قوالب المعاملات" />
      <div style={{ display: "flex", gap: "12px", marginBottom: "16px", alignItems: "center" }}>
        <input
          type="text"
          placeholder="بحث..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={{ flex: 1, padding: "8px 12px", borderRadius: "6px", border: "0.5px solid var(--color-border-secondary)", fontFamily: "inherit" }}
        />
        {canManage && (
          <button
            onClick={() => navigate("/transaction-templates/new")}
            style={{ padding: "8px 16px", borderRadius: "6px", border: "none", background: "var(--color-background-info)", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit" }}
          >
            + قالب جديد
          </button>
        )}
      </div>

      {isLoading ? <LoadingSpinner /> : (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))", gap: "12px" }}>
          {(templates ?? []).map((t) => (
            <div key={t.id} style={{ background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "12px", padding: "16px", display: "flex", flexDirection: "column", gap: "8px", opacity: t.is_active ? 1 : 0.6 }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <span style={{ fontSize: "14px", fontWeight: 700, color: "var(--color-text-primary)" }}>{t.name_ar}</span>
                <span style={{ fontSize: "11px", padding: "2px 8px", borderRadius: "999px", background: t.is_active ? "#f0fdf4" : "#f1f5f9", color: t.is_active ? "#166534" : "#64748b", border: `1px solid ${t.is_active ? "#bbf7d0" : "#e2e8f0"}` }}>
                  {t.is_active ? "مفعّل" : "معطّل"}
                </span>
              </div>
              <div style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>{t.description ?? "—"}</div>
              <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>الاستخدام: {t.usage_count} | {t.register?.name_ar ?? "—"}</div>
              <div style={{ display: "flex", gap: "6px", marginTop: "4px" }}>
                <button onClick={() => navigate(`/transaction-templates/${t.id}/edit`)} style={{ fontSize: "11px", padding: "4px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit" }}>تعديل</button>
                {canManage && (
                  <>
                    <button onClick={() => toggleMut.mutate(t.id)} style={{ fontSize: "11px", padding: "4px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-secondary)", background: "none", color: "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}>{t.is_active ? "تعطيل" : "تفعيل"}</button>
                    <button onClick={() => cloneMut.mutate(t.id)} style={{ fontSize: "11px", padding: "4px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-secondary)", background: "none", color: "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}>نسخ</button>
                    <button onClick={() => { if (confirm("حذف القالب؟")) delMut.mutate(t.id); }} style={{ fontSize: "11px", padding: "4px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-danger)", background: "none", color: "var(--color-text-danger)", cursor: "pointer", fontFamily: "inherit" }}>حذف</button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
