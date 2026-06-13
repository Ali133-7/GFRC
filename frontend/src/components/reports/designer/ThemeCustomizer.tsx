import React, { useState } from "react";

interface ThemeCustomizerProps {
  currentTheme: string;
  onChange: (theme: string) => void;
}

export function ThemeCustomizer({ currentTheme, onChange }: ThemeCustomizerProps) {
  const [customColors, setCustomColors] = useState({
    primary: "#5470c6",
    secondary: "#91cc75",
    accent: "#fac858",
    background: "#ffffff",
    text: "#000000",
  });

  const themes = [
    {
      id: "classic",
      name: "📜 Classic",
      colors: { primary: "#2c3e50", secondary: "#34495e", accent: "#e67e22", background: "#ecf0f1", text: "#2c3e50" },
      preview: "Traditional business report style",
    },
    {
      id: "modern",
      name: "✨ Modern",
      colors: { primary: "#5470c6", secondary: "#91cc75", accent: "#fac858", background: "#ffffff", text: "#000000" },
      preview: "Clean and contemporary design",
    },
    {
      id: "corporate",
      name: "🏢 Corporate",
      colors: { primary: "#1e3a8a", secondary: "#3b82f6", accent: "#60a5fa", background: "#f8fafc", text: "#1e293b" },
      preview: "Professional corporate identity",
    },
    {
      id: "dark",
      name: "🌙 Dark",
      colors: { primary: "#8b5cf6", secondary: "#10b981", accent: "#f59e0b", background: "#1e1e1e", text: "#e5e7eb" },
      preview: "Dark mode for reduced eye strain",
    },
    {
      id: "government",
      name: "🏛️ Government",
      colors: { primary: "#1e40af", secondary: "#059669", accent: "#d97706", background: "#fefce8", text: "#1e3a8a" },
      preview: "Official government style",
    },
    {
      id: "financial",
      name: "💰 Financial",
      colors: { primary: "#0f766e", secondary: "#0ea5e9", accent: "#eab308", background: "#f0fdfa", text: "#134e4a" },
      preview: "Financial sector optimized",
    },
  ];

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        🎨 Theme Customizer
      </h3>

      {/* Theme Selection */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: "12px", marginBottom: "24px" }}>
        {themes.map(theme => (
          <button
            key={theme.id}
            onClick={() => onChange(theme.id)}
            style={{
              padding: "12px",
              background: currentTheme === theme.id ? "var(--color-background-info)" : "var(--color-background-secondary)",
              border: currentTheme === theme.id ? "2px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
              borderRadius: "8px",
              cursor: "pointer",
              textAlign: "left",
            }}
          >
            <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "4px" }}>
              {theme.name}
            </div>
            <div style={{ fontSize: "10px", color: "var(--color-text-tertiary)", marginBottom: "8px" }}>
              {theme.preview}
            </div>
            <div style={{ display: "flex", gap: "4px" }}>
              {Object.values(theme.colors).map((color, index) => (
                <div
                  key={index}
                  style={{
                    width: "20px",
                    height: "20px",
                    borderRadius: "4px",
                    background: color,
                    border: "1px solid var(--color-border-tertiary)",
                  }}
                />
              ))}
            </div>
          </button>
        ))}
      </div>

      {/* Custom Colors */}
      <div style={{ padding: "16px", background: "var(--color-background-secondary)", borderRadius: "8px" }}>
        <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
          🎯 Custom Colors
        </h4>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: "12px" }}>
          {Object.entries(customColors).map(([key, value]) => (
            <div key={key}>
              <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-tertiary)", display: "block", marginBottom: "6px", textTransform: "capitalize" }}>
                {key}
              </label>
              <input
                type="color"
                value={value}
                onChange={(e) => setCustomColors({ ...customColors, [key]: e.target.value })}
                style={{ width: "100%", height: "40px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", cursor: "pointer" }}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Preview */}
      <div style={{ marginTop: "20px", padding: "16px", background: customColors.background, borderRadius: "8px", border: "1px solid var(--color-border-tertiary)" }}>
        <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: customColors.text }}>
          👁️ Theme Preview
        </h4>
        <div style={{ display: "flex", gap: "8px", marginBottom: "12px" }}>
          <div style={{ flex: 1, padding: "12px", background: customColors.primary, color: "#ffffff", borderRadius: "6px", fontSize: "12px" }}>
            Primary Button
          </div>
          <div style={{ flex: 1, padding: "12px", background: customColors.secondary, color: "#ffffff", borderRadius: "6px", fontSize: "12px" }}>
            Secondary Button
          </div>
          <div style={{ flex: 1, padding: "12px", background: customColors.accent, color: "#000000", borderRadius: "6px", fontSize: "12px" }}>
            Accent Button
          </div>
        </div>
        <div style={{ fontSize: "12px", color: customColors.text }}>
          Sample text with theme colors applied. This demonstrates how the report will look with the selected theme.
        </div>
      </div>
    </div>
  );
}
