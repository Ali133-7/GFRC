import React, { useState } from "react";
import type { CalculatedField, ReportField } from "@/types/report";

interface FormulaBuilderProps {
  fields: CalculatedField[];
  onChange: (fields: CalculatedField[]) => void;
  availableFields: ReportField[];
}

export function FormulaBuilder({ fields, onChange, availableFields }: FormulaBuilderProps) {
  const [editingField, setEditingField] = useState<CalculatedField | null>(null);
  const [formula, setFormula] = useState("");
  const [validationError, setValidationError] = useState("");

  const handleAddField = () => {
    const newField: CalculatedField = {
      id: `calc_${Date.now()}`,
      name: `field_${fields.length + 1}`,
      label: `Calculated Field ${fields.length + 1}`,
      formula: "",
      type: "number",
    };
    onChange([...fields, newField]);
    setEditingField(newField);
    setFormula("");
  };

  const handleUpdateField = (id: string, updates: Partial<CalculatedField>) => {
    onChange(fields.map(f => f.id === id ? { ...f, ...updates } : f));
  };

  const handleDeleteField = (id: string) => {
    onChange(fields.filter(f => f.id !== id));
    if (editingField?.id === id) {
      setEditingField(null);
      setFormula("");
    }
  };

  const insertFunction = (func: string) => {
    setFormula(prev => prev + func);
  };

  const insertField = (field: ReportField) => {
    setFormula((prev) => prev + `{${field.name}}`);
  };

  const validateFormula = () => {
    // Simple validation - check for balanced parentheses
    const openCount = (formula.match(/\(/g) || []).length;
    const closeCount = (formula.match(/\)/g) || []).length;
    
    if (openCount !== closeCount) {
      setValidationError("Unbalanced parentheses");
      return false;
    }
    
    setValidationError("");
    return true;
  };

  const handleSaveFormula = () => {
    if (editingField && validateFormula()) {
      handleUpdateField(editingField.id, { formula });
    }
  };

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        🧮 Formula Builder
      </h3>

      {/* Action Button */}
      <button
        onClick={handleAddField}
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
        + Add Calculated Field
      </button>

      {/* Fields List */}
      <div style={{ display: "grid", gap: "8px", marginBottom: "20px" }}>
        {fields.map(field => (
          <div
            key={field.id}
            onClick={() => { setEditingField(field); setFormula(field.formula); }}
            style={{
              padding: "10px",
              background: editingField?.id === field.id ? "var(--color-background-info)" : "var(--color-background-secondary)",
              border: editingField?.id === field.id ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                {field.label}
              </div>
              <button
                onClick={(e) => { e.stopPropagation(); handleDeleteField(field.id); }}
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
            <div style={{ fontSize: "10px", color: "var(--color-text-tertiary)", fontFamily: "var(--font-mono)", marginTop: "4px" }}>
              {field.formula || "No formula"}
            </div>
          </div>
        ))}
      </div>

      {/* Formula Editor */}
      {editingField && (
        <div style={{ padding: "16px", background: "var(--color-background-primary)", border: "1px solid var(--color-border-tertiary)", borderRadius: "6px" }}>
          <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
            Edit Formula: {editingField.name}
          </h4>

          {/* Quick Functions */}
          <div style={{ marginBottom: "12px" }}>
            <div style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-tertiary)", marginBottom: "6px" }}>
              Functions:
            </div>
            <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
              {["SUM(", "COUNT(", "AVG(", "MIN(", "MAX(", "IF(", "CASE(", "ROUND(", "CONCAT(", "DATE("].map(func => (
                <button
                  key={func}
                  onClick={() => insertFunction(func)}
                  style={{
                    padding: "4px 8px",
                    fontSize: "10px",
                    background: "var(--color-background-secondary)",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "4px",
                    cursor: "pointer",
                    fontFamily: "var(--font-mono)",
                  }}
                >
                  {func}
                </button>
              ))}
            </div>
          </div>

          {/* Available Fields */}
          <div style={{ marginBottom: "12px" }}>
            <div style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-tertiary)", marginBottom: "6px" }}>
              Fields:
            </div>
            <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
              {availableFields.map((field) => (
                <button
                  key={field.name}
                  onClick={() => insertField(field)}
                  style={{
                    padding: "4px 8px",
                    fontSize: "10px",
                    background: "var(--color-background-info)",
                    color: "var(--color-text-info)",
                    border: "0.5px solid var(--color-border-info)",
                    borderRadius: "4px",
                    cursor: "pointer",
                    fontFamily: "var(--font-mono)",
                  }}
                >
                  {field.label || field.name}
                </button>
              ))}
            </div>
          </div>

          {/* Formula Input */}
          <div style={{ marginBottom: "12px" }}>
            <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
              Formula:
            </label>
            <textarea
              value={formula}
              onChange={(e) => setFormula(e.target.value)}
              placeholder="Enter formula... (e.g., SUM({amount}) * 1.15)"
              style={{
                width: "100%",
                minHeight: "80px",
                padding: "8px",
                fontSize: "12px",
                fontFamily: "var(--font-mono)",
                border: "0.5px solid var(--color-border-secondary)",
                borderRadius: "4px",
                background: "var(--color-background-secondary)",
              }}
            />
            {validationError && (
              <div style={{ fontSize: "10px", color: "var(--color-text-danger)", marginTop: "4px" }}>
                ⚠️ {validationError}
              </div>
            )}
          </div>

          {/* Result Type */}
          <div style={{ marginBottom: "12px" }}>
            <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
              Result Type:
            </label>
            <select
              value={editingField.type}
              onChange={(e) => handleUpdateField(editingField.id, { type: e.target.value as any })}
              style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
            >
              <option value="number">Number</option>
              <option value="currency">Currency</option>
              <option value="string">String</option>
              <option value="date">Date</option>
              <option value="boolean">Boolean</option>
            </select>
          </div>

          {/* Save Button */}
          <button
            onClick={handleSaveFormula}
            style={{
              width: "100%",
              padding: "8px",
              fontSize: "12px",
              fontWeight: 600,
              background: "var(--color-background-success)",
              color: "var(--color-text-success)",
              border: "0.5px solid var(--color-border-success)",
              borderRadius: "4px",
              cursor: "pointer",
            }}
          >
            💾 Save Formula
          </button>
        </div>
      )}

      {/* Formula Examples */}
      <div style={{ marginTop: "20px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "6px" }}>
        <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
          📝 Formula Examples:
        </div>
        <div style={{ fontSize: "11px", fontFamily: "var(--font-mono)", color: "var(--color-text-tertiary)" }}>
          <div>• SUM({"{"}amount{"}"}) - Sum of amount field</div>
          <div>• AVG({"{"}price{"}"}) * 1.15 - Average with 15% tax</div>
          <div>• IF({"{"}status{"}"} = "active", 1, 0) - Conditional value</div>
          <div>• CONCAT({"{"}first_name{"}"}, " ", {"{"}last_name{"}"}) - Combine names</div>
        </div>
      </div>
    </div>
  );
}
