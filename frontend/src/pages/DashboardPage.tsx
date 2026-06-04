import React from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/Button";
import { StatCard } from "@/components/ui/StatCard";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { usePermissions } from "@/hooks/usePermissions";
import { formatCurrency } from "@/utils/formatCurrency";
import { formatDateTime } from "@/utils/formatDate";
import { getStatusConfig } from "@/utils/statusColors";
import { todayISO } from "@/utils/formatDate";
import { Receipt } from "@/types/receipt";

interface DailyReport {
  total_amount: string | number;
  receipts_count: number;
  issued_count: number;
  pending_count: number;
  by_register?: Array<{ register_name: string; total: string | number; count: number }>;
}

export default function DashboardPage() {
  const navigate = useNavigate();
  const { can } = usePermissions();

  const { data: dailyData, isLoading: loadingReport } = useQuery({
    queryKey: ["reports", "daily", todayISO()],
    queryFn: async () => {
      const res = await client.get(`/reports/daily?date=${todayISO()}`);
      return (res.data?.data ?? res.data) as DailyReport;
    },
    refetchInterval: 60_000,
    staleTime: 30_000,
  });

  const { data: recentData, isLoading: loadingReceipts } = useQuery({
    queryKey: ["receipts", "recent"],
    queryFn: async () => {
      const res = await client.get("/receipts?per_page=10&sort_by=created_at&order=desc");
      const payload = res.data?.data ?? res.data;
      return (Array.isArray(payload) ? payload : payload?.data ?? []) as Receipt[];
    },
    refetchInterval: 60_000,
    staleTime: 30_000,
  });

  const total    = dailyData?.total_amount  ? parseFloat(String(dailyData.total_amount)) : 0;
  const count    = dailyData?.receipts_count ?? 0;
  const issued   = dailyData?.issued_count  ?? 0;
  const pending  = dailyData?.pending_count ?? 0;
  const receipts = recentData ?? [];

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader title="الرئيسية">
        {can("create-receipt") && (
          <Button onClick={() => navigate("/receipts/create")}>+ وصل جديد</Button>
        )}
      </PageHeader>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(4,1fr)", gap: "12px", marginBottom: "24px" }}>
        <StatCard title="إجمالي اليوم (دينار)" value={loadingReport ? "..." : formatCurrency(total)} color="bg-emerald-50" />
        <StatCard title="عدد الوصولات" value={loadingReport ? "..." : String(count)} color="bg-sky-50" />
        <StatCard title="المرحّلة" value={loadingReport ? "..." : String(issued)} color="bg-emerald-50" />
        <StatCard title="المعلقة" value={loadingReport ? "..." : String(pending)} color="bg-amber-50" />
      </div>

      <div style={{ background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-lg)", overflow: "hidden" }}>
        <div style={{ padding: "14px 16px", borderBottom: "0.5px solid var(--color-border-tertiary)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <span style={{ fontSize: "14px", fontWeight: 500, color: "var(--color-text-primary)" }}>آخر الوصولات</span>
          <button
            onClick={() => navigate("/receipts")}
            style={{ fontSize: "12px", color: "var(--color-text-info)", background: "none", border: "none", cursor: "pointer", fontFamily: "inherit" }}
          >
            عرض الكل ←
          </button>
        </div>

        {loadingReceipts ? (
          <div style={{ padding: "32px", textAlign: "center" }}><LoadingSpinner /></div>
        ) : receipts.length === 0 ? (
          <div style={{ padding: "32px", textAlign: "center", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
            لا توجد وصولات حتى الآن
          </div>
        ) : (
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
            <thead>
              <tr style={{ background: "var(--color-background-secondary)" }}>
                {["رقم الوصل", "السجل", "المبلغ", "الحالة", "التاريخ"].map((h) => (
                  <th key={h} style={{ padding: "10px 14px", textAlign: "right", fontWeight: 500, color: "var(--color-text-secondary)", fontSize: "12px", borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {receipts.map((r) => {
                const sc = getStatusConfig(r.status);
                return (
                  <tr
                    key={r.id}
                    onClick={() => navigate(`/receipts/${r.id}`)}
                    style={{ cursor: "pointer", borderBottom: "0.5px solid var(--color-border-tertiary)", transition: "background .15s" }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--color-background-secondary)")}
                    onMouseLeave={(e) => (e.currentTarget.style.background = "")}
                  >
                    <td style={{ padding: "10px 14px", fontFamily: "var(--font-mono)", fontWeight: 500, color: "var(--color-text-primary)" }}>
                      {r.receipt_number}
                    </td>
                    <td style={{ padding: "10px 14px", color: "var(--color-text-secondary)" }}>
                      {r.register?.name_ar ?? "—"}
                    </td>
                    <td style={{ padding: "10px 14px", fontFamily: "var(--font-mono)" }}>
                      {formatCurrency(parseFloat(r.total_amount))}
                    </td>
                    <td style={{ padding: "10px 14px" }}>
                      <span style={{ fontSize: "11px", fontWeight: 500, padding: "2px 8px", borderRadius: "20px", background: sc.bg, color: sc.color, border: `0.5px solid ${sc.border}` }}>
                        {sc.label}
                      </span>
                    </td>
                    <td style={{ padding: "10px 14px", color: "var(--color-text-tertiary)", fontSize: "12px" }}>
                      {formatDateTime(r.created_at)}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
