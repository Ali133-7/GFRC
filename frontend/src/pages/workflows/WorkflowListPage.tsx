import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useWorkflows, useDeleteWorkflow } from "@/hooks/useWorkflows";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { useRegisters } from "@/hooks/useRegisters";

export default function WorkflowListPage() {
  const navigate = useNavigate();
  const [search, setSearch] = useState("");
  const [registerFilter, setRegisterFilter] = useState("");

  const { data: workflows, isLoading } = useWorkflows({
    search: search || undefined,
    register_id: registerFilter || undefined,
  });

  const { data: registers } = useRegisters();
  const deleteMut = useDeleteWorkflow();

  const statusBadge = (status: string) => {
    const map: Record<string, { bg: string; color: string }> = {
      draft: { bg: "var(--color-background-warning)", color: "var(--color-text-warning)" },
      active: { bg: "var(--color-background-success)", color: "var(--color-text-success)" },
      archived: { bg: "var(--color-background-secondary)", color: "var(--color-text-secondary)" },
    };
    const s = map[status] ?? map.draft;
    return (
      <span
        style={{
          fontSize: "11px",
          fontWeight: 500,
          padding: "2px 8px",
          borderRadius: "10px",
          background: s.bg,
          color: s.color,
        }}
      >
        {status === "draft" ? "مسودة" : status === "active" ? "منشورة" : "مؤرشفة"}
      </span>
    );
  };

  const cardStyle: React.CSSProperties = {
    background: "var(--color-background-primary)",
    border: "0.5px solid var(--color-border-tertiary)",
    borderRadius: "var(--border-radius-lg)",
    padding: "16px",
    cursor: "pointer",
    transition: "box-shadow .15s, transform .15s",
    direction: "rtl",
    fontFamily: "'Noto Sans Arabic', sans-serif",
  };

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader
        title="محرك سير العمل"
        subtitle="تصميم وإدارة قوالب المعاملات المالية بإصدارات متعددة"
        action={{ label: "+ Workflow جديد", onClick: () => navigate("/workflows/new"), variant: "primary" }}
      />

      {/* Filters */}
      <div style={{ display: "flex", gap: "10px", marginBottom: "16px", flexWrap: "wrap" }}>
        <input
          type="text"
          placeholder="بحث..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={{
            padding: "6px 10px",
            fontSize: "12px",
            border: "0.5px solid var(--color-border-secondary)",
            borderRadius: "6px",
            background: "var(--color-background-primary)",
            color: "var(--color-text-primary)",
            fontFamily: "inherit",
            minWidth: "200px",
          }}
        />
        <select
          value={registerFilter}
          onChange={(e) => setRegisterFilter(e.target.value)}
          style={{
            padding: "6px 10px",
            fontSize: "12px",
            border: "0.5px solid var(--color-border-secondary)",
            borderRadius: "6px",
            background: "var(--color-background-primary)",
            color: "var(--color-text-primary)",
            fontFamily: "inherit",
            minWidth: "160px",
          }}
        >
          <option value="">كل السجلات</option>
          {registers?.map((r: { id: string; name_ar: string }) => (
            <option key={r.id} value={r.id}>{r.name_ar}</option>
          ))}
        </select>
      </div>

      {/* Grid */}
      {isLoading ? (
        <div style={{ padding: "48px", textAlign: "center" }}>
          <LoadingSpinner />
        </div>
      ) : !workflows || workflows.length === 0 ? (
        <div style={{ padding: "48px", textAlign: "center", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
          لا توجد Workflows
        </div>
      ) : (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))", gap: "14px" }}>
          {workflows.map((wf: any) => {
            const activeVersion = wf.versions?.find((v: any) => v.status === "active");
            return (
              <div
                key={wf.id}
                style={cardStyle}
                onClick={() => navigate(`/workflows/${wf.id}`)}
                onMouseEnter={(e) => {
                  e.currentTarget.style.boxShadow = "0 2px 8px rgba(0,0,0,0.06)";
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.boxShadow = "none";
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                  <div style={{ fontSize: "20px" }}>{wf.icon || "⚙️"}</div>
                  <div style={{ display: "flex", gap: "6px", alignItems: "center" }}>
                    {activeVersion ? statusBadge("active") : statusBadge("draft")}
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        if (confirm("هل أنت متأكد من الحذف؟")) deleteMut.mutate(wf.id);
                      }}
                      style={{
                        background: "none",
                        border: "none",
                        cursor: "pointer",
                        fontSize: "12px",
                        color: "var(--color-text-danger)",
                        padding: "2px 6px",
                      }}
                    >
                      🗑️
                    </button>
                  </div>
                </div>
                <div style={{ fontSize: "15px", fontWeight: 500, color: "var(--color-text-primary)", marginBottom: "4px" }}>
                  {wf.name_ar}
                </div>
                <div style={{ fontSize: "11px", color: "var(--color-text-secondary)", marginBottom: "8px" }}>
                  {wf.code} · {wf.register?.name_ar}
                </div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: "11px", color: "var(--color-text-tertiary)" }}>
                  <span>الإصدار الحالي: V{wf.current_version}</span>
                  <span>{wf.versions?.length ?? 0} إصدارات</span>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
