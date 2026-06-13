import React, { useState } from "react";
import type { ReportFilter, ReportField } from "@/types/report";

interface AdvancedFilterBuilderProps {
  filters: ReportFilter[];
  onChange: (filters: ReportFilter[]) => void;
  availableFields: ReportField[];
}

export function AdvancedFilterBuilder({ filters, onChange, availableFields }: AdvancedFilterBuilderProps) {
  const [selectedFilter, setSelectedFilter] = useState<string | null>(null);

  const fieldLabel = (name: string) => {
    const field = availableFields.find((f) => f.name === name);
    return field?.label || name;
  };

  const handleAddFilter = () => {
    const first = availableFields[0];
    const newFilter: ReportFilter = {
      id: `filter_${Date.now()}`,
      field: first?.name || "",
      operator: "=",
      value: "",
      valueType: (first?.type as ReportFilter["valueType"]) || "string",
      logic: "AND",
    };
    onChange([...filters, newFilter]);
  };

  const handleUpdateFilter = (id: string, updates: Partial<ReportFilter>) => {
    onChange(filters.map(f => f.id === id ? { ...f, ...updates } : f));
  };

  const handleDeleteFilter = (id: string) => {
    onChange(filters.filter(f => f.id !== id));
    if (selectedFilter === id) setSelectedFilter(null);
  };

  const handleAddGroup = () => {
    const newFilter: ReportFilter = {
      id: `group_${Date.now()}`,
      field: "",
      operator: "=",
      value: "",
      valueType: "string",
      logic: "AND",
      group: `group_${Date.now()}`,
    };
    onChange([...filters, newFilter]);
  };

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        🔍 Advanced Filter Builder
      </h3>

      {/* Action Buttons */}
      <div style={{ display: "flex", gap: "8px", marginBottom: "16px" }}>
        <button
          onClick={handleAddFilter}
          style={{
            padding: "6px 12px",
            fontSize: "12px",
            background: "var(--color-background-info)",
            color: "var(--color-text-info)",
            border: "0.5px solid var(--color-border-info)",
            borderRadius: "4px",
            cursor: "pointer",
          }}
        >
          + Add Condition
        </button>
        <button
          onClick={handleAddGroup}
          style={{
            padding: "6px 12px",
            fontSize: "12px",
            background: "var(--color-background-success)",
            color: "var(--color-text-success)",
            border: "0.5px solid var(--color-border-success)",
            borderRadius: "4px",
            cursor: "pointer",
          }}
        >
          + Add Group
        </button>
      </div>

      {/* Filters List */}
      {filters.length === 0 ? (
        <div style={{ padding: "32px", textAlign: "center", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
          No filters configured. Add conditions to filter report data.
        </div>
      ) : (
        <div style={{ display: "grid", gap: "8px" }}>
          {filters.map((filter, index) => (
            <div
              key={filter.id}
              onClick={() => setSelectedFilter(filter.id)}
              style={{
                padding: "12px",
                background: selectedFilter === filter.id ? "var(--color-background-info)" : "var(--color-background-secondary)",
                border: selectedFilter === filter.id ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
                borderRadius: "6px",
                cursor: "pointer",
              }}
            >
              {/* Logic Operator (AND/OR) */}
              {index > 0 && (
                <div style={{ marginBottom: "8px" }}>
                  <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", marginRight: "8px" }}>Logic:</label>
                  <select
                    value={filter.logic || "AND"}
                    onChange={(e) => handleUpdateFilter(filter.id, { logic: e.target.value as any })}
                    onClick={(e) => e.stopPropagation()}
                    style={{ padding: "4px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
                  >
                    <option value="AND">AND</option>
                    <option value="OR">OR</option>
                  </select>
                </div>
              )}

              {/* Filter Configuration */}
              <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr 2fr", gap: "8px" }}>
                <div>
                  <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                    Field
                  </label>
                  <select
                    value={filter.field}
                    onChange={(e) => handleUpdateFilter(filter.id, { field: e.target.value })}
                    onClick={(e) => e.stopPropagation()}
                    style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
                  >
                    {availableFields.map((f) => (
                      <option key={f.name} value={f.name}>{f.label || f.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                    Operator
                  </label>
                  <select
                    value={filter.operator}
                    onChange={(e) => handleUpdateFilter(filter.id, { operator: e.target.value as any })}
                    onClick={(e) => e.stopPropagation()}
                    style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
                  >
                    <option value="=">= Equals</option>
                    <option value="!=">≠ Not Equals</option>
                    <option value=">">&gt; Greater Than</option>
                    <option value="<">&lt; Less Than</option>
                    <option value=">=">≥ Greater or Equal</option>
                    <option value="<=">≤ Less or Equal</option>
                    <option value="LIKE">LIKE Contains</option>
                    <option value="IN">IN In List</option>
                    <option value="BETWEEN">BETWEEN Range</option>
                  </select>
                </div>

                <div>
                  <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                    Value
                  </label>
                  <input
                    type="text"
                    value={filter.value}
                    onChange={(e) => handleUpdateFilter(filter.id, { value: e.target.value })}
                    onClick={(e) => e.stopPropagation()}
                    placeholder="Enter value..."
                    style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
                  />
                </div>
              </div>

              {/* Delete Button */}
              <button
                onClick={(e) => { e.stopPropagation(); handleDeleteFilter(filter.id); }}
                style={{
                  marginTop: "8px",
                  padding: "4px 8px",
                  fontSize: "10px",
                  background: "var(--color-background-danger)",
                  color: "var(--color-text-danger)",
                  border: "0.5px solid var(--color-border-danger)",
                  borderRadius: "4px",
                  cursor: "pointer",
                }}
              >
                🗑️ Delete
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Filter Preview */}
      {filters.length > 0 && (
        <div style={{ marginTop: "20px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "6px" }}>
          <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
            📝 Filter Preview:
          </div>
          <div style={{ fontSize: "11px", fontFamily: "var(--font-mono)", color: "var(--color-text-primary)" }}>
            WHERE {filters.map((f, i) => {
              const condition = `${fieldLabel(f.field)} ${f.operator} '${f.value}'`;
              return i > 0 ? `${f.logic} ${condition}` : condition;
            }).join(" ")}
          </div>
        </div>
      )}
    </div>
  );
}
