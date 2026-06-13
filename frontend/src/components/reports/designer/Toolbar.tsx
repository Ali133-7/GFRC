import React from "react";

interface ToolbarProps {
  activeTab: "design" | "data" | "filters" | "formulas" | "charts" | "pivot" | "formatting" | "theme" | "schedule" | "history";
  onTabChange: (tab: any) => void;
  theme: string;
  onThemeChange: (theme: string) => void;
  onSave?: (data: any) => void;
}

export function Toolbar({ activeTab, onTabChange, theme, onThemeChange, onSave }: ToolbarProps) {
  return (
    <div style={{
      padding: "12px 20px",
      background: "var(--color-background-primary)",
      borderBottom: "1px solid var(--color-border-tertiary)",
      display: "flex",
      justifyContent: "space-between",
      alignItems: "center",
    }}>
      {/* Left - Tabs */}
      <div style={{ display: "flex", gap: "8px" }}>
        <button
          onClick={() => onTabChange("design")}
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: activeTab === "design" ? 600 : 400,
            background: activeTab === "design" ? "var(--color-background-info)" : "transparent",
            color: activeTab === "design" ? "var(--color-text-info)" : "var(--color-text-secondary)",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          📐 Designer
        </button>
        <button
          onClick={() => onTabChange("data")}
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: activeTab === "data" ? 600 : 400,
            background: activeTab === "data" ? "var(--color-background-info)" : "transparent",
            color: activeTab === "data" ? "var(--color-text-info)" : "var(--color-text-secondary)",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          🔗 Data Model
        </button>
        <button
          onClick={() => onTabChange("filters")}
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: activeTab === "filters" ? 600 : 400,
            background: activeTab === "filters" ? "var(--color-background-info)" : "transparent",
            color: activeTab === "filters" ? "var(--color-text-info)" : "var(--color-text-secondary)",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          🔍 Filters
        </button>
        <button
          onClick={() => onTabChange("charts")}
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: activeTab === "charts" ? 600 : 400,
            background: activeTab === "charts" ? "var(--color-background-info)" : "transparent",
            color: activeTab === "charts" ? "var(--color-text-info)" : "var(--color-text-secondary)",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          📊 Charts
        </button>
        <button
          onClick={() => onTabChange("pivot")}
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: activeTab === "pivot" ? 600 : 400,
            background: activeTab === "pivot" ? "var(--color-background-info)" : "transparent",
            color: activeTab === "pivot" ? "var(--color-text-info)" : "var(--color-text-secondary)",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          📋 Pivot
        </button>
      </div>

      {/* Center - Theme Selector */}
      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
        <span style={{ fontSize: "12px", color: "var(--color-text-secondary)" }}>Theme:</span>
        <select
          value={theme}
          onChange={(e) => onThemeChange(e.target.value)}
          style={{
            padding: "6px 12px",
            fontSize: "12px",
            border: "0.5px solid var(--color-border-secondary)",
            borderRadius: "6px",
            background: "var(--color-background-primary)",
            cursor: "pointer",
          }}
        >
          <option value="classic">📜 Classic</option>
          <option value="modern">✨ Modern</option>
          <option value="corporate">🏢 Corporate</option>
          <option value="dark">🌙 Dark</option>
        </select>
      </div>

      {/* Right - Actions */}
      <div style={{ display: "flex", gap: "8px" }}>
        <button
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: 500,
            background: "var(--color-background-secondary)",
            color: "var(--color-text-secondary)",
            border: "0.5px solid var(--color-border-secondary)",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          👁️ Preview
        </button>
        <button
          onClick={onSave}
          style={{
            padding: "8px 16px",
            fontSize: "13px",
            fontWeight: 600,
            background: "var(--color-background-success)",
            color: "var(--color-text-success)",
            border: "0.5px solid var(--color-border-success)",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          💾 Save Report
        </button>
      </div>
    </div>
  );
}
