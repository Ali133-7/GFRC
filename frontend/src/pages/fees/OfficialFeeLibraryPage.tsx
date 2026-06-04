import React, { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { usePermissions } from "@/hooks/usePermissions";
import type { OfficialFee } from "@/types/transactionTemplate";

export default function OfficialFeeLibraryPage() {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { can } = usePermissions();
  const [search, setSearch] = useState("");
  const canManage = can("manage-settings");

  const { data: fees, isLoading } = useQuery({
    queryKey: ["official-fees", search],
    queryFn: async () => {
      const r = await client.get("/official-fees", { params: { search } });
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : []) as OfficialFee[];
    },
  });

  const delMut = useMutation({
    mutationFn: (id: string) => client.delete(`/official-fees/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["official-fees"] }),
  });

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader title="مكتبة الرسوم الرسمية" />
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
            onClick={() => navigate("/official-fees/new")}
            style={{ padding: "8px 16px", borderRadius: "6px", border: "none", background: "var(--color-background-info)", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit" }}
          >
            + رسم جديد
          </button>
        )}
      </div>

      {isLoading ? <LoadingSpinner /> : (
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px", background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "12px", overflow: "hidden" }}>
          <thead>
            <tr style={{ background: "var(--color-background-secondary)" }}>
              {["التصنيف", "الاسم", "كود الرسم", "المبلغ", "من", "إلى", "الحالة", "إجراءات"].map((h) => (
                <th key={h} style={{ padding: "10px 12px", textAlign: "right", fontWeight: 600, fontSize: "12px", color: "var(--color-text-secondary)", borderBottom: "0.5px solid var(--color-border-tertiary)" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {(fees ?? []).map((f) => (
              <tr key={f.id} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                <td style={{ padding: "10px 12px" }}>{f.category?.name_ar ?? "—"}</td>
                <td style={{ padding: "10px 12px", fontWeight: 600 }}>{f.name_ar}</td>
                <td style={{ padding: "10px 12px", fontFamily: "'Courier New', monospace", fontSize: "12px", color: "var(--color-text-info)" }}>{(f as any).fee_code ?? "—"}</td>
                <td style={{ padding: "10px 12px", fontFamily: "'Courier New', monospace" }}>{Number(f.amount).toLocaleString('ar-IQ')} د.ع</td>
                <td style={{ padding: "10px 12px", color: "var(--color-text-tertiary)", fontSize: "12px" }}>{f.effective_from ?? "—"}</td>
                <td style={{ padding: "10px 12px", color: "var(--color-text-tertiary)", fontSize: "12px" }}>{f.effective_to ?? "—"}</td>
                <td style={{ padding: "10px 12px" }}>
                  <span style={{ fontSize: "11px", padding: "2px 8px", borderRadius: "999px", background: f.is_active ? "#f0fdf4" : "#f1f5f9", color: f.is_active ? "#166534" : "#64748b" }}>
                    {f.is_active ? "مفعّل" : "معطّل"}
                  </span>
                </td>
                <td style={{ padding: "10px 12px" }}>
                  <div style={{ display: "flex", gap: "6px" }}>
                    <button onClick={() => navigate(`/official-fees/${f.id}/edit`)} style={{ fontSize: "11px", padding: "3px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit" }}>تعديل</button>
                    {canManage && (
                      <button onClick={() => { if (confirm("حذف الرسم؟")) delMut.mutate(f.id); }} style={{ fontSize: "11px", padding: "3px 10px", borderRadius: "4px", border: "0.5px solid var(--color-border-danger)", background: "none", color: "var(--color-text-danger)", cursor: "pointer", fontFamily: "inherit" }}>حذف</button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
