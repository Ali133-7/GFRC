import React, { useState } from "react";
import type { PivotConfig, PivotValue, ReportField } from "@/types/report";

interface PivotBuilderProps {
  config: PivotConfig | null;
  onChange: (config: PivotConfig) => void;
  availableFields: ReportField[];
}

export function PivotBuilder({ config, onChange, availableFields }: PivotBuilderProps) {
  const [rows, setRows] = useState<string[]>(config?.rows || []);
  const [columns, setColumns] = useState<string[]>(config?.columns || []);
  const [values, setValues] = useState<PivotValue[]>(config?.values || []);
  const [filters, setFilters] = useState<any[]>(config?.filters || []);

  const fieldNameSet = new Set(availableFields.map((f) => f.name));
  const fieldLabel = (name: string) => availableFields.find((f) => f.name === name)?.label || name;

  const handleAddRow = (name: string) => {
    if (!rows.includes(name)) {
      setRows([...rows, name]);
    }
  };

  const handleRemoveRow = (name: string) => {
    setRows(rows.filter((f) => f !== name));
  };

  const handleAddColumn = (name: string) => {
    if (!columns.includes(name)) {
      setColumns([...columns, name]);
    }
  };

  const handleRemoveColumn = (name: string) => {
    setColumns(columns.filter((f) => f !== name));
  };

  const handleAddValue = (name: string) => {
    const field = availableFields.find((f) => f.name === name);
    const newValue: PivotValue = {
      field: name,
      aggregation: field?.isAggregatable ? "SUM" : "COUNT",
      label: `${field?.label || name} (Sum)`,
    };
    setValues([...values, newValue]);
  };

  const handleRemoveValue = (index: number) => {
    setValues(values.filter((_, i) => i !== index));
  };

  const handleUpdateValue = (index: number, updates: Partial<PivotValue>) => {
    setValues(values.map((v, i) => i === index ? { ...v, ...updates } : v));
  };

  // Sample pivot data
  const pivotData = [
    { region: "North", product: "A", sales: 15000, quantity: 150 },
    { region: "North", product: "B", sales: 25000, quantity: 250 },
    { region: "South", product: "A", sales: 18000, quantity: 180 },
    { region: "South", product: "B", sales: 22000, quantity: 220 },
    { region: "East", product: "A", sales: 12000, quantity: 120 },
    { region: "East", product: "B", sales: 28000, quantity: 280 },
  ];

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        📋 Pivot Table Builder
      </h3>

      {/* Drag Areas */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "20px" }}>
        {/* Rows */}
        <div style={{ padding: "12px", background: "var(--color-background-secondary)", borderRadius: "6px" }}>
          <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
            📊 Rows
          </div>
          <div style={{ display: "flex", flexWrap: "wrap", gap: "6px", marginBottom: "8px" }}>
            {rows.map((name) => (
              <div
                key={name}
                style={{
                  padding: "4px 8px",
                  fontSize: "11px",
                  background: "var(--color-background-info)",
                  color: "var(--color-text-info)",
                  borderRadius: "4px",
                  display: "flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                {fieldLabel(name)}
                <button
                  onClick={() => handleRemoveRow(name)}
                  style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-info)", padding: 0 }}
                >
                  ×
                </button>
              </div>
            ))}
          </div>
          <select
            onChange={(e) => { handleAddRow(e.target.value); e.target.value = ""; }}
            style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
          >
            <option value="">+ Add Row Field</option>
            {availableFields.filter((f) => !rows.includes(f.name)).map((f) => (
              <option key={f.name} value={f.name}>{f.label || f.name}</option>
            ))}
          </select>
        </div>

        {/* Columns */}
        <div style={{ padding: "12px", background: "var(--color-background-secondary)", borderRadius: "6px" }}>
          <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
            📊 Columns
          </div>
          <div style={{ display: "flex", flexWrap: "wrap", gap: "6px", marginBottom: "8px" }}>
            {columns.map((name) => (
              <div
                key={name}
                style={{
                  padding: "4px 8px",
                  fontSize: "11px",
                  background: "var(--color-background-success)",
                  color: "var(--color-text-success)",
                  borderRadius: "4px",
                  display: "flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                {fieldLabel(name)}
                <button
                  onClick={() => handleRemoveColumn(name)}
                  style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-success)", padding: 0 }}
                >
                  ×
                </button>
              </div>
            ))}
          </div>
          <select
            onChange={(e) => { handleAddColumn(e.target.value); e.target.value = ""; }}
            style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
          >
            <option value="">+ Add Column Field</option>
            {availableFields.filter((f) => !columns.includes(f.name)).map((f) => (
              <option key={f.name} value={f.name}>{f.label || f.name}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Values */}
      <div style={{ padding: "12px", background: "var(--color-background-secondary)", borderRadius: "6px", marginBottom: "20px" }}>
        <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
          🔢 Values
        </div>
        <div style={{ display: "grid", gap: "8px" }}>
          {values.map((value, index) => (
            <div key={index} style={{ display: "flex", gap: "8px", alignItems: "center" }}>
              <span style={{ fontSize: "11px", flex: 1 }}>{fieldLabel(value.field)}</span>
              <select
                value={value.aggregation}
                onChange={(e) => handleUpdateValue(index, { aggregation: e.target.value as any })}
                style={{ padding: "4px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
              >
                <option value="SUM">SUM</option>
                <option value="COUNT">COUNT</option>
                <option value="AVG">AVG</option>
                <option value="MIN">MIN</option>
                <option value="MAX">MAX</option>
              </select>
              <button
                onClick={() => handleRemoveValue(index)}
                style={{ background: "none", border: "none", cursor: "pointer", fontSize: "16px", color: "var(--color-text-danger)" }}
              >
                ×
              </button>
            </div>
          ))}
        </div>
        <select
          onChange={(e) => { handleAddValue(e.target.value); e.target.value = ""; }}
          style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", marginTop: "8px" }}
        >
          <option value="">+ Add Value Field</option>
          {availableFields.filter((f) => !values.some((v) => v.field === f.name)).map((f) => (
            <option key={f.name} value={f.name}>{f.label || f.name}</option>
          ))}
        </select>
      </div>

      {/* Pivot Preview */}
      <div style={{ padding: "12px", background: "var(--color-background-primary)", border: "1px solid var(--color-border-tertiary)", borderRadius: "6px" }}>
        <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
          📊 Pivot Preview
        </div>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "11px" }}>
          <thead>
            <tr style={{ background: "var(--color-background-secondary)" }}>
              <th style={{ padding: "8px", border: "0.5px solid var(--color-border-tertiary)", textAlign: "left" }}>
                {rows[0] || "Row"}
              </th>
              {columns.map(col => (
                <th key={col} style={{ padding: "8px", border: "0.5px solid var(--color-border-tertiary)", textAlign: "right" }}>
                  {col}
                </th>
              ))}
              <th style={{ padding: "8px", border: "0.5px solid var(--color-border-tertiary)", textAlign: "right" }}>
                Total
              </th>
            </tr>
          </thead>
          <tbody>
            {pivotData.map((row, index) => (
              <tr key={index} style={{ background: index % 2 === 0 ? "transparent" : "var(--color-background-secondary)" }}>
                <td style={{ padding: "8px", border: "0.5px solid var(--color-border-tertiary)" }}>{row.region}</td>
                {columns.map(col => (
                  <td key={col} style={{ padding: "8px", border: "0.5px solid var(--color-border-tertiary)", textAlign: "right" }}>
                    {col === "sales" ? row.sales.toLocaleString() : row.quantity}
                  </td>
                ))}
                <td style={{ padding: "8px", border: "0.5px solid var(--color-border-tertiary)", textAlign: "right", fontWeight: 600 }}>
                  {columns.includes("sales") ? row.sales.toLocaleString() : row.quantity}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
