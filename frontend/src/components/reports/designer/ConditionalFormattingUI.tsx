import React, { useState } from "react";
import type { ConditionalFormat, ReportField } from "@/types/report";

interface ConditionalFormattingUIProps {
  formats: ConditionalFormat[];
  onChange: (formats: ConditionalFormat[]) => void;
  availableFields: ReportField[];
}

export function ConditionalFormattingUI({ formats, onChange, availableFields }: ConditionalFormattingUIProps) {
  const [editingFormat, setEditingFormat] = useState<string | null>(null);

  const handleAddFormat = () => {
    const newFormat: ConditionalFormat = {
      id: `cf_${Date.now()}`,
      condition: "",
      properties: {
        color: "#000000",
        backgroundColor: "#ffffff",
        fontWeight: "normal",
        fontStyle: "normal",
        visible: true,
      },
    };
    onChange([...formats, newFormat]);
    setEditingFormat(newFormat.id);
  };

  const handleUpdateFormat = (id: string, updates: Partial<ConditionalFormat>) => {
    onChange(formats.map(f => f.id === id ? { ...f, ...updates } : f));
  };

  const handleDeleteFormat = (id: string) => {
    onChange(formats.filter(f => f.id !== id));
    if (editingFormat === id) setEditingFormat(null);
  };

  const quickConditions = [
    { label: "Amount > 10,000", value: "amount > 10000" },
    { label: "Status = Completed", value: "status = 'completed'" },
    { label: "Status = Pending", value: "status = 'pending'" },
    { label: "Date is Today", value: "date = TODAY()" },
    { label: "Amount < 0", value: "amount < 0" },
    { label: "Contains 'VIP'", value: "name LIKE '%VIP%'" },
  ];

  const colorPresets = [
    { name: "Red Alert", bg: "#fee2e2", text: "#dc2626" },
    { name: "Green Success", bg: "#dcfce7", text: "#16a34a" },
    { name: "Yellow Warning", bg: "#fef9c3", text: "#ca8a04" },
    { name: "Blue Info", bg: "#dbeafe", text: "#2563eb" },
    { name: "Purple VIP", bg: "#f3e8ff", text: "#9333ea" },
    { name: "Gray Neutral", bg: "#f3f4f6", text: "#6b7280" },
  ];

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        🎨 Conditional Formatting
      </h3>

      {/* Add Button */}
      <button
        onClick={handleAddFormat}
        style={{
          padding: "6px 12px",
          fontSize: "12px",
          background: "var(--color-background-info)",
          color: "var(--color-text-info)",
          border: "0.5px solid var(--color-border-info)",
          borderRadius: "4px",
          cursor: "pointer",
          marginBottom: "16px",
        }}
      >
        + Add Formatting Rule
      </button>

      {/* Quick Presets */}
      <div style={{ marginBottom: "20px" }}>
        <h4 style={{ fontSize: "13px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
          ⚡ Quick Presets
        </h4>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "6px" }}>
          {colorPresets.map(preset => (
            <button
              key={preset.name}
              onClick={() => {
                const newFormat: ConditionalFormat = {
                  id: `cf_${Date.now()}`,
                  condition: "amount > 0",
                  properties: {
                    backgroundColor: preset.bg,
                    color: preset.text,
                    fontWeight: "bold",
                  },
                };
                onChange([...formats, newFormat]);
              }}
              style={{
                padding: "8px",
                fontSize: "10px",
                background: preset.bg,
                color: preset.text,
                border: "0.5px solid var(--color-border-tertiary)",
                borderRadius: "4px",
                cursor: "pointer",
                fontWeight: 600,
              }}
            >
              {preset.name}
            </button>
          ))}
        </div>
      </div>

      {/* Formatting Rules */}
      <div style={{ display: "grid", gap: "12px" }}>
        {formats.map((format, index) => (
          <div
            key={format.id}
            onClick={() => setEditingFormat(format.id)}
            style={{
              padding: "12px",
              background: editingFormat === format.id ? "var(--color-background-info)" : "var(--color-background-secondary)",
              border: editingFormat === format.id ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
              <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                Rule {index + 1}
              </div>
              <button
                onClick={(e) => { e.stopPropagation(); handleDeleteFormat(format.id); }}
                style={{
                  background: "none",
                  border: "none",
                  cursor: "pointer",
                  fontSize: "16px",
                  color: "var(--color-text-danger)",
                }}
              >
                ×
              </button>
            </div>

            {/* Condition Input */}
            <div style={{ marginBottom: "8px" }}>
              <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                Condition
              </label>
              <input
                type="text"
                value={format.condition}
                onChange={(e) => handleUpdateFormat(format.id, { condition: e.target.value })}
                placeholder="e.g., amount > 10000"
                style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", fontFamily: "var(--font-mono)" }}
              />
            </div>

            {/* Quick Conditions */}
            <div style={{ display: "flex", gap: "4px", flexWrap: "wrap", marginBottom: "8px" }}>
              {quickConditions.map(qc => (
                <button
                  key={qc.value}
                  onClick={(e) => {
                    e.stopPropagation();
                    handleUpdateFormat(format.id, { condition: qc.value });
                  }}
                  style={{
                    padding: "2px 6px",
                    fontSize: "9px",
                    background: "var(--color-background-primary)",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "3px",
                    cursor: "pointer",
                  }}
                >
                  {qc.label}
                </button>
              ))}
            </div>

            {/* Properties */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px" }}>
              <div>
                <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                  Text Color
                </label>
                <input
                  type="color"
                  value={format.properties.color || "#000000"}
                  onChange={(e) => handleUpdateFormat(format.id, { properties: { ...format.properties, color: e.target.value } })}
                  style={{ width: "100%", height: "30px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", cursor: "pointer" }}
                />
              </div>
              <div>
                <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                  Background
                </label>
                <input
                  type="color"
                  value={format.properties.backgroundColor || "#ffffff"}
                  onChange={(e) => handleUpdateFormat(format.id, { properties: { ...format.properties, backgroundColor: e.target.value } })}
                  style={{ width: "100%", height: "30px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", cursor: "pointer" }}
                />
              </div>
            </div>

            {/* Font Weight */}
            <div style={{ marginTop: "8px" }}>
              <label style={{ fontSize: "10px", color: "var(--color-text-tertiary)", display: "block", marginBottom: "4px" }}>
                Font Style
              </label>
              <div style={{ display: "flex", gap: "6px" }}>
                <button
                  onClick={() => handleUpdateFormat(format.id, { properties: { ...format.properties, fontWeight: format.properties.fontWeight === "bold" ? "normal" : "bold" } })}
                  style={{
                    flex: 1,
                    padding: "4px",
                    fontSize: "11px",
                    background: format.properties.fontWeight === "bold" ? "var(--color-background-info)" : "var(--color-background-primary)",
                    color: format.properties.fontWeight === "bold" ? "var(--color-text-info)" : "var(--color-text-secondary)",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "4px",
                    cursor: "pointer",
                    fontWeight: format.properties.fontWeight === "bold" ? 700 : 400,
                  }}
                >
                  Bold
                </button>
                <button
                  onClick={() => handleUpdateFormat(format.id, { properties: { ...format.properties, fontStyle: format.properties.fontStyle === "italic" ? "normal" : "italic" } })}
                  style={{
                    flex: 1,
                    padding: "4px",
                    fontSize: "11px",
                    background: format.properties.fontStyle === "italic" ? "var(--color-background-info)" : "var(--color-background-primary)",
                    color: format.properties.fontStyle === "italic" ? "var(--color-text-info)" : "var(--color-text-secondary)",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "4px",
                    cursor: "pointer",
                    fontStyle: format.properties.fontStyle === "italic" ? "italic" : "normal",
                  }}
                >
                  Italic
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Preview */}
      {formats.length > 0 && (
        <div style={{ marginTop: "20px", padding: "12px", background: "var(--color-background-primary)", border: "1px solid var(--color-border-tertiary)", borderRadius: "6px" }}>
          <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
            👁️ Formatting Preview
          </div>
          <div style={{ display: "grid", gap: "8px" }}>
            {formats.slice(0, 3).map((format, index) => (
              <div
                key={format.id}
                style={{
                  padding: "8px 12px",
                  background: format.properties.backgroundColor || "#ffffff",
                  color: format.properties.color || "#000000",
                  fontWeight: format.properties.fontWeight || "normal",
                  fontStyle: format.properties.fontStyle || "normal",
                  borderRadius: "4px",
                  fontSize: "12px",
                }}
              >
                Sample Row {index + 1} - {format.condition || "No condition"}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
