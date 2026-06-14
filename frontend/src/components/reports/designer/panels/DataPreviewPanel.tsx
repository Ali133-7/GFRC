import React, { useState, useMemo } from "react";

interface DataPreviewPanelProps {
  data: any[];
  total?: number;
  onDataChange: (data: any[]) => void;
}

export function DataPreviewPanel({ data, total, onDataChange }: DataPreviewPanelProps) {
  const [page, setPage] = useState(1);
  const perPage = 10;

  const displayData = Array.isArray(data) && data.length > 0 ? data : [];
  const columns = useMemo(() => {
    if (displayData.length === 0) return [];
    return Object.keys(displayData[0]);
  }, [displayData]);

  const handleSort = (field: string) => {
    const sorted = [...displayData].sort((a, b) => {
      const aVal = a[field] ?? "";
      const bVal = b[field] ?? "";
      if (aVal < bVal) return -1;
      if (aVal > bVal) return 1;
      return 0;
    });
    onDataChange(sorted);
  };

  const formatValue = (value: any) => {
    if (value === null || value === undefined) return "-";
    if (typeof value === "number") return value.toLocaleString("en-US", { minimumFractionDigits: 0 });
    if (typeof value === "boolean") return value ? "نعم" : "لا";
    return String(value);
  };

  return (
    <div style={{ padding: "12px", height: "100%", overflow: "auto" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
        <h3 style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-primary)" }}>
          🔍 معاينة البيانات
        </h3>
        <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
          {displayData.length} صفوف {total !== undefined && total > 0 ? `(من أصل ${total})` : ""}
        </div>
      </div>

      {displayData.length === 0 ? (
        <div
          style={{
            padding: "24px",
            textAlign: "center",
            color: "var(--color-text-tertiary)",
            fontSize: "13px",
          }}
        >
          لا توجد بيانات للمعاينة. اختر سجلاً وحقلاً لعرض المعاينة.
        </div>
      ) : (
        <>
          <div style={{ overflow: "auto", maxHeight: "140px" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "11px", minWidth: "600px" }}>
              <thead>
                <tr style={{ background: "var(--color-background-secondary)" }}>
                  {columns.map((col) => (
                    <th
                      key={col}
                      onClick={() => handleSort(col)}
                      style={{
                        padding: "6px 10px",
                        textAlign: "left",
                        border: "0.5px solid var(--color-border-tertiary)",
                        cursor: "pointer",
                        whiteSpace: "nowrap",
                      }}
                    >
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {displayData.slice((page - 1) * perPage, page * perPage).map((row, index) => (
                  <tr
                    key={index}
                    style={{ background: index % 2 === 0 ? "var(--color-background-primary)" : "transparent" }}
                  >
                    {columns.map((col) => (
                      <td
                        key={col}
                        style={{
                          padding: "6px 10px",
                          border: "0.5px solid var(--color-border-tertiary)",
                          whiteSpace: "nowrap",
                        }}
                      >
                        {formatValue(row[col])}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              marginTop: "8px",
              paddingTop: "8px",
              borderTop: "1px solid var(--color-border-tertiary)",
            }}
          >
            <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
              Showing {Math.min(displayData.length, page * perPage)} of {displayData.length} rows
            </div>
            <div style={{ display: "flex", gap: "6px" }}>
              <button
                onClick={() => setPage(Math.max(1, page - 1))}
                disabled={page === 1}
                style={{
                  padding: "4px 10px",
                  fontSize: "11px",
                  background: page === 1 ? "var(--color-background-secondary)" : "var(--color-background-info)",
                  color: page === 1 ? "var(--color-text-tertiary)" : "var(--color-text-info)",
                  border: "0.5px solid var(--color-border-info)",
                  borderRadius: "4px",
                  cursor: page === 1 ? "not-allowed" : "pointer",
                }}
              >
                Previous
              </button>
              <button
                onClick={() => setPage(page + 1)}
                disabled={page * perPage >= displayData.length}
                style={{
                  padding: "4px 10px",
                  fontSize: "11px",
                  background: page * perPage >= displayData.length ? "var(--color-background-secondary)" : "var(--color-background-info)",
                  color: page * perPage >= displayData.length ? "var(--color-text-tertiary)" : "var(--color-text-info)",
                  border: "0.5px solid var(--color-border-info)",
                  borderRadius: "4px",
                  cursor: page * perPage >= displayData.length ? "not-allowed" : "pointer",
                }}
              >
                Next
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
