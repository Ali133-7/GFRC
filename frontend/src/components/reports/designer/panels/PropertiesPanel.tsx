import React, { useState, useEffect } from "react";
import { ReportObject } from "@/types/report";

interface PropertiesPanelProps {
  selectedObject: ReportObject | null;
  onUpdateObject: (id: string, updates: Partial<ReportObject>) => void;
  onDeleteObject: (id: string) => void;
}

export function PropertiesPanel({ selectedObject, onUpdateObject, onDeleteObject }: PropertiesPanelProps) {
  const [localProps, setLocalProps] = useState<any>({});

  useEffect(() => {
    if (selectedObject) {
      setLocalProps(selectedObject.properties || {});
    }
  }, [selectedObject]);

  if (!selectedObject) {
    return (
      <div style={{ padding: "20px", textAlign: "center", color: "var(--color-text-tertiary)" }}>
        <div style={{ fontSize: "32px", marginBottom: "12px" }}>📋</div>
        <div style={{ fontSize: "13px" }}>Select an object to edit properties</div>
      </div>
    );
  }

  const handleChange = (key: string, value: any) => {
    const updates = { properties: { ...localProps, [key]: value } };
    setLocalProps(updates.properties);
    onUpdateObject(selectedObject.id, updates);
  };

  return (
    <div style={{ padding: "12px" }}>
      <h3 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        ⚙️ Properties
      </h3>

      {/* Name */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Name
        </label>
                  <input
                    type="text"
                    value={selectedObject.field?.name || ""}
                    onChange={(e) => onUpdateObject(selectedObject.id, { field: { ...selectedObject.field, name: e.target.value, type: selectedObject.field?.type || "string" } as any })}
                    style={{ width: "100%", padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
                  />
      </div>

      {/* Position */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Position (X, Y)
        </label>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px" }}>
          <input
            type="number"
            value={selectedObject.x}
            onChange={(e) => onUpdateObject(selectedObject.id, { x: parseInt(e.target.value) })}
            style={{ padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
          />
          <input
            type="number"
            value={selectedObject.y}
            onChange={(e) => onUpdateObject(selectedObject.id, { y: parseInt(e.target.value) })}
            style={{ padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
          />
        </div>
      </div>

      {/* Size */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Size (Width, Height)
        </label>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px" }}>
          <input
            type="number"
            value={selectedObject.width}
            onChange={(e) => onUpdateObject(selectedObject.id, { width: parseInt(e.target.value) })}
            style={{ padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
          />
          <input
            type="number"
            value={selectedObject.height}
            onChange={(e) => onUpdateObject(selectedObject.id, { height: parseInt(e.target.value) })}
            style={{ padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
          />
        </div>
      </div>

      {/* Font */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Font Size
        </label>
        <input
          type="number"
          value={localProps.fontSize || 12}
          onChange={(e) => handleChange("fontSize", parseInt(e.target.value))}
          style={{ width: "100%", padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
        />
      </div>

      {/* Font Family */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Font Family
        </label>
        <select
          value={localProps.fontFamily || "Arial"}
          onChange={(e) => handleChange("fontFamily", e.target.value)}
          style={{ width: "100%", padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
        >
          <option value="Arial">Arial</option>
          <option value="Times New Roman">Times New Roman</option>
          <option value="Courier New">Courier New</option>
          <option value="Georgia">Georgia</option>
          <option value="Verdana">Verdana</option>
        </select>
      </div>

      {/* Colors */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Text Color
        </label>
        <input
          type="color"
          value={localProps.color || "#000000"}
          onChange={(e) => handleChange("color", e.target.value)}
          style={{ width: "100%", height: "30px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", cursor: "pointer" }}
        />
      </div>

      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Background Color
        </label>
        <input
          type="color"
          value={localProps.backgroundColor || "#ffffff"}
          onChange={(e) => handleChange("backgroundColor", e.target.value)}
          style={{ width: "100%", height: "30px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", cursor: "pointer" }}
        />
      </div>

      {/* Border */}
      <div style={{ marginBottom: "12px" }}>
        <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
          Border
        </label>
        <input
          type="text"
          value={localProps.border || "1px solid #cccccc"}
          onChange={(e) => handleChange("border", e.target.value)}
          style={{ width: "100%", padding: "6px 8px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
        />
      </div>

      {/* Delete Button */}
      <button
        onClick={() => onDeleteObject(selectedObject.id)}
        style={{
          width: "100%",
          padding: "8px",
          fontSize: "12px",
          fontWeight: 600,
          background: "var(--color-background-danger)",
          color: "var(--color-text-danger)",
          border: "0.5px solid var(--color-border-danger)",
          borderRadius: "4px",
          cursor: "pointer",
          marginTop: "16px",
        }}
      >
        🗑️ Delete Object
      </button>
    </div>
  );
}
