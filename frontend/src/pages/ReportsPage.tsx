import React, { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/Button";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { usePermissions } from "@/hooks/usePermissions";
import { formatCurrency } from "@/utils/formatCurrency";
import { formatNumber } from "@/utils/formatNumber";
import { todayISO } from "@/utils/formatDate";

type TabKey = "daily" | "monthly" | "user" | "register" | "custom";

interface DailySummary { date: string; total_amount: string; receipts_count: number; issued_count: number; by_register?: Array<{ register_name: string; register_code: string; total: string; count: number }>; }
interface MonthlySummary { year: number; month: number; total_amount: string; receipts_count: number; by_day?: Array<{ date: string; total: string; count: number }>; }
interface UserActivity { user_id: string; user_name: string; receipts_count: number; total_amount: string; issued_count: number; }
interface RegisterSummary { register_id: string; register_name: string; register_code: string; total_amount: string; receipts_count: number; }

export default function ReportsPage() {
  const [tab, setTab] = useState<TabKey>("daily");
  const [date, setDate] = useState(todayISO());
  const [year, setYear] = useState(new Date().getFullYear());
  const [month, setMonth] = useState(new Date().getMonth() + 1);
  const [dateFrom, setDateFrom] = useState(() => { const d = new Date(); d.setDate(1); return d.toISOString().split("T")[0]; });
  const [dateTo, setDateTo] = useState(todayISO());
  const { can } = usePermissions();

  const dailyQ = useQuery({
    queryKey: ["report-daily", date],
    queryFn: async () => { const r = await client.get(`/reports/daily?date=${date}`); return (r.data?.data ?? r.data) as DailySummary; },
    enabled: tab === "daily",
  });

  const monthlyQ = useQuery({
    queryKey: ["report-monthly", year, month],
    queryFn: async () => { const r = await client.get(`/reports/monthly?year=${year}&month=${month}`); return (r.data?.data ?? r.data) as MonthlySummary; },
    enabled: tab === "monthly",
  });

  const userQ = useQuery({
    queryKey: ["report-user", dateFrom, dateTo],
    queryFn: async () => { const r = await client.get(`/reports/user-activity?date_from=${dateFrom}&date_to=${dateTo}`); const d = r.data?.data ?? r.data; return (Array.isArray(d) ? d : d?.data ?? []) as UserActivity[]; },
    enabled: tab === "user",
  });

  const registerQ = useQuery({
    queryKey: ["report-register", dateFrom, dateTo],
    queryFn: async () => { const r = await client.get(`/reports/register-summary?date_from=${dateFrom}&date_to=${dateTo}`); const d = r.data?.data ?? r.data; return (Array.isArray(d) ? d : d?.data ?? []) as RegisterSummary[]; },
    enabled: tab === "register",
  });

  const { data: dynamicReports } = useQuery({
    queryKey: ["dynamic-reports"],
    queryFn: async () => {
      const r = await client.get("/reports");
      return (r.data?.data ?? r.data) as any[];
    },
  });

  const handleExport = async () => {
    const params = tab === "daily" ? `type=daily&date=${date}` : tab === "monthly" ? `type=monthly&year=${year}&month=${month}` : `type=${tab}&date_from=${dateFrom}&date_to=${dateTo}`;
    try {
      const r = await client.get(`/reports/export-csv?${params}`, { responseType: "blob" });
      const url = URL.createObjectURL(r.data);
      const a = document.createElement("a"); a.href = url; a.download = `report-${tab}-${Date.now()}.csv`; a.click(); URL.revokeObjectURL(url);
    } catch { alert("تعذّر التصدير"); }
  };

  const tabStyle = (t: TabKey): React.CSSProperties => ({
    padding: "8px 16px", fontSize: "13px", fontWeight: tab === t ? 500 : 400,
    border: "none", background: "none", cursor: "pointer", fontFamily: "inherit",
    color: tab === t ? "var(--color-text-info)" : "var(--color-text-secondary)",
    borderBottom: tab === t ? "2px solid var(--color-border-info)" : "2px solid transparent",
  });

  const inputStyle: React.CSSProperties = { padding: "6px 10px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", fontFamily: "inherit" };

  const StatBox = ({ label, value }: { label: string; value: string }) => (
    <div style={{ background: "var(--color-background-secondary)", borderRadius: "var(--border-radius-md)", padding: "14px 16px", textAlign: "center" }}>
      <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginBottom: "4px" }}>{label}</div>
      <div style={{ fontSize: "18px", fontWeight: 500, fontFamily: "var(--font-mono)", color: "var(--color-text-primary)" }}>{value}</div>
    </div>
  );

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader 
        title="التقارير"
        subtitle="التقارير الجاهزة والمخصصة"
        action={{
          label: "✨ تصميم تقرير جديد",
          onClick: () => window.location.href = "/reports/builder",
          variant: "primary",
        }}
      >
        {can("export-reports") && <Button variant="secondary" onClick={handleExport}>⬇ تصدير CSV</Button>}
      </PageHeader>

      {/* Tabs */}
      <div style={{ display: "flex", borderBottom: "0.5px solid var(--color-border-tertiary)", marginBottom: "20px" }}>
        <button style={tabStyle("daily")}     onClick={() => setTab("daily")}>📅 يومي</button>
        <button style={tabStyle("monthly")}   onClick={() => setTab("monthly")}>📆 شهري</button>
        <button style={tabStyle("user")}      onClick={() => setTab("user")}>👥 المستخدمين</button>
        <button style={tabStyle("register")}  onClick={() => setTab("register")}>📒 السجل</button>
        <button style={tabStyle("custom")}    onClick={() => setTab("custom")}>🎯 مخصصة</button>
      </div>

      {/* Custom Reports Tab */}
      {tab === "custom" && (
        <div>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "16px" }}>
            <h2 style={{ fontSize: "16px", fontWeight: 600, color: "var(--color-text-primary)" }}>
              🎯 التقارير المخصصة ({dynamicReports?.length ?? 0})
            </h2>
            <button
              onClick={() => window.location.href = "/reports/builder"}
              style={{
                padding: "8px 16px",
                fontSize: "13px",
                fontWeight: 500,
                background: "var(--color-background-success)",
                color: "var(--color-text-success)",
                border: "0.5px solid var(--color-border-success)",
                borderRadius: "6px",
                cursor: "pointer",
                fontFamily: "inherit",
              }}
            >
              ✨ إنشاء تقرير جديد
            </button>
          </div>
          
          {dynamicReports && dynamicReports.length > 0 ? (
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(350px, 1fr))", gap: "16px" }}>
              {dynamicReports.map((report: any) => (
                <div
                  key={report.id}
                  style={{
                    padding: "16px",
                    background: "var(--color-background-primary)",
                    border: "0.5px solid var(--color-border-tertiary)",
                    borderRadius: "8px",
                  }}
                >
                  <div style={{ marginBottom: "12px" }}>
                    <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "4px" }}>
                      {report.name_ar || report.name || 'تقرير'}
                    </div>
                    <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
                      {report.code}
                    </div>
                  </div>
                  
                  <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginBottom: "12px" }}>
                    <div>📊 المصدر: {report.data_source}</div>
                    <div>📑 الحقول: {report.fields?.length ?? 0}</div>
                    <div>🔢 المقاييس: {report.aggregations?.length ?? 0}</div>
                  </div>
                  
                  <div style={{ display: "flex", gap: "8px" }}>
                    <button
                      onClick={() => window.location.href = `/reports/view/${report.id}`}
                      style={{
                        flex: 1,
                        padding: "6px 12px",
                        fontSize: "12px",
                        fontWeight: 500,
                        background: "var(--color-background-info)",
                        color: "var(--color-text-info)",
                        border: "0.5px solid var(--color-border-info)",
                        borderRadius: "6px",
                        cursor: "pointer",
                        fontFamily: "inherit",
                      }}
                    >
                      ▶️ عرض
                    </button>
                    <button
                      onClick={() => window.location.href = `/reports/builder?id=${report.id}`}
                      style={{
                        padding: "6px 12px",
                        fontSize: "12px",
                        fontWeight: 500,
                        background: "none",
                        color: "var(--color-text-secondary)",
                        border: "0.5px solid var(--color-border-secondary)",
                        borderRadius: "6px",
                        cursor: "pointer",
                        fontFamily: "inherit",
                      }}
                    >
                      ✏️
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div style={{ textAlign: "center", padding: "48px", color: "var(--color-text-tertiary)" }}>
              <div style={{ fontSize: "48px", marginBottom: "16px" }}>📊</div>
              <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "8px" }}>لا توجد تقارير مخصصة</div>
              <button
                onClick={() => window.location.href = "/reports/builder"}
                style={{
                  padding: "10px 24px",
                  fontSize: "13px",
                  fontWeight: 600,
                  background: "var(--color-background-success)",
                  color: "var(--color-text-success)",
                  border: "0.5px solid var(--color-border-success)",
                  borderRadius: "8px",
                  cursor: "pointer",
                  fontFamily: "inherit",
                }}
              >
                ✨ إنشاء أول تقرير
              </button>
            </div>
          )}
        </div>
      )}

      {/* Daily Tab */}
      {tab === "daily" && (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "16px" }}>
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>التاريخ:</label>
            <input type="date" value={date} onChange={e => setDate(e.target.value)} style={inputStyle} />
          </div>
          {dailyQ.isLoading ? <LoadingSpinner /> : dailyQ.data ? (
            <>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(4,1fr)", gap: "10px", marginBottom: "16px" }}>
                <StatBox label="إجمالي المقبوضات" value={formatCurrency(parseFloat(dailyQ.data.total_amount ?? "0"))} />
                <StatBox label="عدد الوصولات"      value={formatNumber(dailyQ.data.receipts_count ?? 0)} />
                <StatBox label="المرحّلة"          value={formatNumber(dailyQ.data.issued_count ?? 0)} />
                <StatBox label="اليوم"              value={date} />
              </div>
              {dailyQ.data.by_register && dailyQ.data.by_register.length > 0 && (
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px", background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-md)", overflow: "hidden" }}>
                  <thead><tr style={{ background: "var(--color-background-secondary)" }}>
                    {["السجل","الكود","عدد الوصولات","الإجمالي"].map(h => <th key={h} style={{ padding: "8px 12px", textAlign: "right", fontWeight: 500, fontSize: "12px", color: "var(--color-text-secondary)" }}>{h}</th>)}
                  </tr></thead>
                  <tbody>{dailyQ.data.by_register.map((row, i) => (
                    <tr key={i} style={{ borderTop: "0.5px solid var(--color-border-tertiary)" }}>
                      <td style={{ padding: "8px 12px" }}>{row.register_name}</td>
                      <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", fontSize: "12px" }}>{row.register_code}</td>
                      <td style={{ padding: "8px 12px" }}>{formatNumber(row.count)}</td>
                      <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", fontWeight: 500 }}>{formatCurrency(parseFloat(row.total ?? "0"))}</td>
                    </tr>
                  ))}</tbody>
                </table>
              )}
            </>
          ) : <div style={{ color: "var(--color-text-tertiary)", fontSize: "13px", textAlign: "center", padding: "24px" }}>لا توجد بيانات</div>}
        </div>
      )}

      {/* Monthly Tab */}
      {tab === "monthly" && (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "16px" }}>
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>السنة:</label>
            <input type="number" value={year} onChange={e => setYear(+e.target.value)} style={{ ...inputStyle, width: "80px" }} />
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>الشهر:</label>
            <select value={month} onChange={e => setMonth(+e.target.value)} style={inputStyle}>
              {["يناير","فبراير","مارس","أبريل","مايو","يونيو","يوليو","أغسطس","سبتمبر","أكتوبر","نوفمبر","ديسمبر"].map((m,i) => (
                <option key={i+1} value={i+1}>{m}</option>
              ))}
            </select>
          </div>
          {monthlyQ.isLoading ? <LoadingSpinner /> : monthlyQ.data ? (
            <>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: "10px", marginBottom: "16px" }}>
                <StatBox label="إجمالي الشهر" value={formatCurrency(parseFloat(monthlyQ.data.total_amount ?? "0"))} />
                <StatBox label="عدد الوصولات"  value={formatNumber(monthlyQ.data.receipts_count ?? 0)} />
                <StatBox label="الشهر"          value={`${month}/${year}`} />
              </div>
              {monthlyQ.data.by_day && monthlyQ.data.by_day.length > 0 && (
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px", background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-md)" }}>
                  <thead><tr style={{ background: "var(--color-background-secondary)" }}>
                    {["اليوم","عدد الوصولات","الإجمالي"].map(h => <th key={h} style={{ padding: "8px 12px", textAlign: "right", fontWeight: 500, fontSize: "12px", color: "var(--color-text-secondary)" }}>{h}</th>)}
                  </tr></thead>
                  <tbody>{monthlyQ.data.by_day.map((row, i) => (
                    <tr key={i} style={{ borderTop: "0.5px solid var(--color-border-tertiary)" }}>
                      <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)" }}>{row.date}</td>
                      <td style={{ padding: "8px 12px" }}>{formatNumber(row.count)}</td>
                      <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", fontWeight: 500 }}>{formatCurrency(parseFloat(row.total ?? "0"))}</td>
                    </tr>
                  ))}</tbody>
                </table>
              )}
            </>
          ) : null}
        </div>
      )}

      {/* User Activity Tab */}
      {tab === "user" && (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "16px" }}>
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>من:</label>
            <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} style={inputStyle} />
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>إلى:</label>
            <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} style={inputStyle} />
          </div>
          {userQ.isLoading ? <LoadingSpinner /> : (userQ.data ?? []).length === 0 ? (
            <div style={{ color: "var(--color-text-tertiary)", fontSize: "13px", textAlign: "center", padding: "24px" }}>لا توجد بيانات</div>
          ) : (
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px", background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-md)" }}>
              <thead><tr style={{ background: "var(--color-background-secondary)" }}>
                {["المستخدم","عدد الوصولات","المرحّلة","الإجمالي"].map(h => <th key={h} style={{ padding: "8px 12px", textAlign: "right", fontWeight: 500, fontSize: "12px", color: "var(--color-text-secondary)" }}>{h}</th>)}
              </tr></thead>
              <tbody>{(userQ.data ?? []).map((row) => (
                <tr key={row.user_id} style={{ borderTop: "0.5px solid var(--color-border-tertiary)" }}>
                  <td style={{ padding: "8px 12px", fontWeight: 500 }}>{row.user_name}</td>
                  <td style={{ padding: "8px 12px" }}>{formatNumber(row.receipts_count)}</td>
                  <td style={{ padding: "8px 12px" }}>{formatNumber(row.issued_count)}</td>
                  <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", fontWeight: 500 }}>{formatCurrency(parseFloat(row.total_amount ?? "0"))}</td>
                </tr>
              ))}</tbody>
            </table>
          )}
        </div>
      )}

      {/* Register Summary Tab */}
      {tab === "register" && (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "16px" }}>
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>من:</label>
            <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} style={inputStyle} />
            <label style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>إلى:</label>
            <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} style={inputStyle} />
          </div>
          {registerQ.isLoading ? <LoadingSpinner /> : (registerQ.data ?? []).length === 0 ? (
            <div style={{ color: "var(--color-text-tertiary)", fontSize: "13px", textAlign: "center", padding: "24px" }}>لا توجد بيانات</div>
          ) : (
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px", background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-md)" }}>
              <thead><tr style={{ background: "var(--color-background-secondary)" }}>
                {["السجل","الكود","عدد الوصولات","الإجمالي"].map(h => <th key={h} style={{ padding: "8px 12px", textAlign: "right", fontWeight: 500, fontSize: "12px", color: "var(--color-text-secondary)" }}>{h}</th>)}
              </tr></thead>
              <tbody>{(registerQ.data ?? []).map((row) => (
                <tr key={row.register_id} style={{ borderTop: "0.5px solid var(--color-border-tertiary)" }}>
                  <td style={{ padding: "8px 12px", fontWeight: 500 }}>{row.register_name}</td>
                  <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", fontSize: "12px" }}>{row.register_code}</td>
                  <td style={{ padding: "8px 12px" }}>{formatNumber(row.receipts_count)}</td>
                  <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", fontWeight: 500 }}>{formatCurrency(parseFloat(row.total_amount ?? "0"))}</td>
                </tr>
              ))}</tbody>
            </table>
          )}
        </div>
      )}
    </div>
  );
}
