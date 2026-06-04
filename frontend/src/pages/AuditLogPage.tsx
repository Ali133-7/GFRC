import React, { useState } from "react";
import { useQuery, keepPreviousData } from "@tanstack/react-query";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { useDebounce } from "@/hooks/useDebounce";
import { formatDateTime } from "@/utils/formatDate";

interface AuditLog {
  id: number;
  log_name: string;
  description: string;
  subject_type: string;
  subject_id: string;
  causer_id: string;
  causer?: { name: string };
  properties: {
    old?: Record<string, unknown>;
    new?: Record<string, unknown>;
    attributes?: Record<string, unknown>;
  };
  ip_address: string;
  created_at: string;
}

const SENSITIVE_KEYS = [
  'password',
  'password_confirmation',
  'current_password',
  'token',
  'remember_token',
  'api_token',
  'secret',
  'credit_card',
  'cvv',
  'otp',
  'pin',
  'private_key',
  'access_token',
  'refresh_token',
];

function maskSensitive(data: unknown): unknown {
  if (!data || typeof data !== 'object') return data;
  if (Array.isArray(data)) return data.map(maskSensitive);
  const result: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(data)) {
    if (SENSITIVE_KEYS.includes(key.toLowerCase())) {
      result[key] = '••••••••';
    } else if (value && typeof value === 'object') {
      result[key] = maskSensitive(value);
    } else {
      result[key] = value;
    }
  }
  return result;
}

const actionColors: Record<string, { bg: string; color: string }> = {
  created:   { bg: "var(--color-background-success)", color: "var(--color-text-success)" },
  updated:   { bg: "var(--color-background-warning)", color: "var(--color-text-warning)" },
  deleted:   { bg: "var(--color-background-danger)",  color: "var(--color-text-danger)"  },
  issued:    { bg: "var(--color-background-info)",    color: "var(--color-text-info)"    },
  cancelled: { bg: "var(--color-background-danger)",  color: "var(--color-text-danger)"  },
  printed:   { bg: "var(--color-background-info)",    color: "var(--color-text-info)"    },
  login:     { bg: "var(--color-background-success)", color: "var(--color-text-success)" },
  logout:    { bg: "var(--color-background-secondary)", color: "var(--color-text-secondary)" },
};

const modelLabel = (t: string) =>
  t?.split("\\").pop()?.replace(/([A-Z])/g, " $1").trim() ?? t;

export default function AuditLogPage() {
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [actionFilter, setActionFilter] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo]     = useState("");
  const [search, setSearch]     = useState("");
  const [page, setPage]         = useState(1);

  const debouncedSearch = useDebounce(search, 300);

  const buildParams = () => {
    const p = new URLSearchParams({ page: String(page), per_page: "25" });
    if (actionFilter)    p.set("description", actionFilter);
    if (dateFrom)        p.set("date_from", dateFrom);
    if (dateTo)          p.set("date_to", dateTo);
    if (debouncedSearch) p.set("search", debouncedSearch);
    return p.toString();
  };

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ["audit-logs", page, actionFilter, dateFrom, dateTo, debouncedSearch],
    queryFn: async () => {
      const r = await client.get(`/audit-logs?${buildParams()}`);
      return r.data?.data ?? r.data;
    },
    placeholderData: keepPreviousData,
  });

  const logs: AuditLog[] = Array.isArray(data) ? data : (data?.data ?? []);
  const meta = data?.meta ?? data?.pagination ?? {};
  const totalPages = meta.last_page ?? 1;
  const totalCount = meta.total ?? 0;

  const inputStyle: React.CSSProperties = {
    padding: "6px 10px",
    fontSize: "12px",
    border: "0.5px solid var(--color-border-secondary)",
    borderRadius: "6px",
    fontFamily: "inherit",
    direction: "rtl",
  };

  const resetFilters = () => {
    setActionFilter("");
    setDateFrom("");
    setDateTo("");
    setSearch("");
    setPage(1);
  };

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader
        title="سجل التدقيق"
        subtitle={totalCount > 0 ? `${totalCount.toLocaleString("ar-IQ")} سجل` : "كل العمليات المنفذة في النظام"}
      />

      {/* Filters */}
      <div
        style={{
          display: "flex",
          flexWrap: "wrap",
          gap: "10px",
          marginBottom: "16px",
          alignItems: "center",
          background: "var(--color-background-primary)",
          border: "0.5px solid var(--color-border-tertiary)",
          borderRadius: "var(--border-radius-md)",
          padding: "12px 14px",
        }}
      >
        <input
          type="text"
          placeholder="بحث في السجلات..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          style={{ ...inputStyle, minWidth: "180px", flex: 1 }}
        />
        <select
          value={actionFilter}
          onChange={(e) => { setActionFilter(e.target.value); setPage(1); }}
          style={{ ...inputStyle, minWidth: "140px" }}
        >
          <option value="">كل العمليات</option>
          {["created", "updated", "deleted", "issued", "cancelled", "printed", "login", "logout"].map((a) => (
            <option key={a} value={a}>{a}</option>
          ))}
        </select>
        <input
          type="date"
          value={dateFrom}
          onChange={(e) => { setDateFrom(e.target.value); setPage(1); }}
          style={inputStyle}
        />
        <span style={{ fontSize: "12px", color: "var(--color-text-tertiary)" }}>إلى</span>
        <input
          type="date"
          value={dateTo}
          onChange={(e) => { setDateTo(e.target.value); setPage(1); }}
          style={inputStyle}
        />
        {(actionFilter || dateFrom || dateTo || search) && (
          <button
            onClick={resetFilters}
            style={{
              fontSize: "12px",
              color: "var(--color-text-danger)",
              background: "none",
              border: "0.5px solid var(--color-border-danger)",
              borderRadius: "6px",
              padding: "5px 10px",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            مسح الفلاتر
          </button>
        )}
      </div>

      {/* Table */}
      <div
        style={{
          background: "var(--color-background-primary)",
          border: "0.5px solid var(--color-border-tertiary)",
          borderRadius: "var(--border-radius-lg)",
          overflow: "hidden",
          opacity: isFetching && !isLoading ? 0.7 : 1,
          transition: "opacity .2s",
        }}
      >
        {isLoading ? (
          <div style={{ padding: "48px", textAlign: "center" }}>
            <LoadingSpinner />
          </div>
        ) : logs.length === 0 ? (
          <div style={{ padding: "48px", textAlign: "center", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
            لا توجد سجلات مطابقة
          </div>
        ) : (
          <>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
              <thead>
                <tr style={{ background: "var(--color-background-secondary)" }}>
                  {["العملية", "النموذج", "المعرّف", "المستخدم", "عنوان IP", "التاريخ والوقت", ""].map((h) => (
                    <th
                      key={h}
                      style={{
                        padding: "9px 12px",
                        textAlign: "right",
                        fontWeight: 500,
                        fontSize: "12px",
                        color: "var(--color-text-secondary)",
                        borderBottom: "0.5px solid var(--color-border-tertiary)",
                        whiteSpace: "nowrap",
                      }}
                    >
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => {
                  const ac = actionColors[log.description] ?? {
                    bg: "var(--color-background-secondary)",
                    color: "var(--color-text-secondary)",
                  };
                  const isExpanded = expandedId === log.id;
                  const hasProps =
                    log.properties?.old ||
                    log.properties?.new ||
                    log.properties?.attributes;

                  return (
                    <React.Fragment key={log.id}>
                      <tr
                        style={{
                          borderBottom: "0.5px solid var(--color-border-tertiary)",
                          cursor: hasProps ? "pointer" : "default",
                          transition: "background .15s",
                        }}
                        onClick={() => hasProps && setExpandedId(isExpanded ? null : log.id)}
                        onMouseEnter={(e) => {
                          if (hasProps) e.currentTarget.style.background = "var(--color-background-secondary)";
                        }}
                        onMouseLeave={(e) => {
                          e.currentTarget.style.background = "";
                        }}
                      >
                        <td style={{ padding: "8px 12px" }}>
                          <span
                            style={{
                              fontSize: "11px",
                              fontWeight: 500,
                              padding: "2px 8px",
                              borderRadius: "10px",
                              background: ac.bg,
                              color: ac.color,
                              whiteSpace: "nowrap",
                            }}
                          >
                            {log.description}
                          </span>
                        </td>
                        <td style={{ padding: "8px 12px", color: "var(--color-text-secondary)", fontSize: "12px" }}>
                          {modelLabel(log.subject_type)}
                        </td>
                        <td
                          style={{
                            padding: "8px 12px",
                            fontFamily: "var(--font-mono)",
                            fontSize: "11px",
                            color: "var(--color-text-tertiary)",
                            maxWidth: "80px",
                            overflow: "hidden",
                            textOverflow: "ellipsis",
                            whiteSpace: "nowrap",
                          }}
                        >
                          {log.subject_id ? log.subject_id.substring(0, 8) + "..." : "—"}
                        </td>
                        <td style={{ padding: "8px 12px", fontSize: "12px" }}>
                          {log.causer?.name ?? log.causer_id ?? "—"}
                        </td>
                        <td
                          style={{
                            padding: "8px 12px",
                            fontFamily: "var(--font-mono)",
                            fontSize: "11px",
                            color: "var(--color-text-tertiary)",
                          }}
                        >
                          {log.ip_address ?? "—"}
                        </td>
                        <td style={{ padding: "8px 12px", fontSize: "11px", color: "var(--color-text-tertiary)", whiteSpace: "nowrap" }}>
                          {formatDateTime(log.created_at)}
                        </td>
                        <td style={{ padding: "8px 12px", textAlign: "center", width: "32px" }}>
                          {hasProps && (
                            <span style={{ fontSize: "10px", color: "var(--color-text-info)" }}>
                              {isExpanded ? "▲" : "▼"}
                            </span>
                          )}
                        </td>
                      </tr>

                      {isExpanded && hasProps && (
                        <tr>
                          <td
                            colSpan={7}
                            style={{ padding: 0, borderBottom: "0.5px solid var(--color-border-tertiary)" }}
                          >
                            <div
                              style={{
                                padding: "14px 16px",
                                background: "var(--color-background-secondary)",
                              }}
                            >
                              <div
                                style={{
                                  display: "grid",
                                  gridTemplateColumns: "1fr 1fr",
                                  gap: "12px",
                                }}
                              >
                                {log.properties?.old && (
                                  <div>
                                    <div
                                      style={{
                                        fontSize: "11px",
                                        fontWeight: 500,
                                        color: "var(--color-text-danger)",
                                        marginBottom: "6px",
                                      }}
                                    >
                                      قبل التعديل
                                    </div>
                                    <pre
                                      style={{
                                        fontSize: "11px",
                                        background: "var(--color-background-danger)",
                                        color: "var(--color-text-danger)",
                                        padding: "8px 10px",
                                        borderRadius: "6px",
                                        overflow: "auto",
                                        maxHeight: "160px",
                                        direction: "ltr",
                                        margin: 0,
                                        lineHeight: 1.5,
                                        border: "0.5px solid var(--color-border-danger)",
                                      }}
                                    >
                                      {JSON.stringify(maskSensitive(log.properties.old), null, 2)}
                                    </pre>
                                  </div>
                                )}
                                {(log.properties?.new ?? log.properties?.attributes) && (
                                  <div>
                                    <div
                                      style={{
                                        fontSize: "11px",
                                        fontWeight: 500,
                                        color: "var(--color-text-success)",
                                        marginBottom: "6px",
                                      }}
                                    >
                                      {log.properties?.new ? "بعد التعديل" : "البيانات المسجّلة"}
                                    </div>
                                    <pre
                                      style={{
                                        fontSize: "11px",
                                        background: "var(--color-background-success)",
                                        color: "var(--color-text-success)",
                                        padding: "8px 10px",
                                        borderRadius: "6px",
                                        overflow: "auto",
                                        maxHeight: "160px",
                                        direction: "ltr",
                                        margin: 0,
                                        lineHeight: 1.5,
                                        border: "0.5px solid var(--color-border-success)",
                                      }}
                                    >
                                      {JSON.stringify(
                                        maskSensitive(log.properties.new ?? log.properties.attributes),
                                        null,
                                        2
                                      )}
                                    </pre>
                                  </div>
                                )}
                                {!log.properties?.old && !log.properties?.new && log.properties?.attributes && (
                                  <div style={{ gridColumn: "1 / -1" }}>
                                    <div
                                      style={{
                                        fontSize: "11px",
                                        fontWeight: 500,
                                        color: "var(--color-text-info)",
                                        marginBottom: "6px",
                                      }}
                                    >
                                      البيانات
                                    </div>
                                    <pre
                                      style={{
                                        fontSize: "11px",
                                        background: "var(--color-background-info)",
                                        color: "var(--color-text-info)",
                                        padding: "8px 10px",
                                        borderRadius: "6px",
                                        overflow: "auto",
                                        maxHeight: "160px",
                                        direction: "ltr",
                                        margin: 0,
                                        lineHeight: 1.5,
                                        border: "0.5px solid var(--color-border-info)",
                                      }}
                                    >
                                      {JSON.stringify(maskSensitive(log.properties.attributes), null, 2)}
                                    </pre>
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>

            {/* Pagination */}
            {totalPages > 1 && (
              <div
                style={{
                  display: "flex",
                  justifyContent: "center",
                  alignItems: "center",
                  gap: "10px",
                  padding: "12px 16px",
                  borderTop: "0.5px solid var(--color-border-tertiary)",
                }}
              >
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  style={{
                    padding: "5px 14px",
                    fontSize: "12px",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "6px",
                    background: "none",
                    cursor: page <= 1 ? "not-allowed" : "pointer",
                    color: "var(--color-text-secondary)",
                    fontFamily: "inherit",
                    opacity: page <= 1 ? 0.5 : 1,
                  }}
                >
                  ← السابق
                </button>
                <span style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>
                  صفحة {page} من {totalPages}
                </span>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages}
                  style={{
                    padding: "5px 14px",
                    fontSize: "12px",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "6px",
                    background: "none",
                    cursor: page >= totalPages ? "not-allowed" : "pointer",
                    color: "var(--color-text-secondary)",
                    fontFamily: "inherit",
                    opacity: page >= totalPages ? 0.5 : 1,
                  }}
                >
                  التالي →
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
