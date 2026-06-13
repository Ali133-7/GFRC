import React from "react";
import { ReportSection, ReportObject } from "@/types/report";

interface ReportCanvasProps {
  sections: ReportSection[];
  onSectionChange: (sections: ReportSection[]) => void;
  selectedObject: ReportObject | null;
  onSelectObject: (object: ReportObject) => void;
  onUpdateObject: (id: string, updates: Partial<ReportObject>) => void;
  onDeleteObject: (id: string) => void;
  theme: string;
}

export function ReportCanvas({
  sections,
  onSectionChange,
  selectedObject,
  onSelectObject,
  onUpdateObject,
  onDeleteObject,
  theme,
}: ReportCanvasProps) {
  return (
    <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
      {sections.map((section) => (
        <div
          key={section.id}
          style={{
            marginBottom: "8px",
            border: "2px solid var(--color-border-tertiary)",
            borderRadius: "6px",
            background: "var(--color-background-primary)",
            overflow: "hidden",
          }}
        >
          {/* Section Header */}
          <div style={{
            padding: "8px 12px",
            background: "var(--color-background-secondary)",
            borderBottom: "1px solid var(--color-border-tertiary)",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
          }}>
            <span style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
              {section.name}
            </span>
            <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
              {section.objects.length} objects
            </span>
          </div>

          {/* Section Canvas */}
          <div style={{
            height: `${section.height}px`,
            position: "relative",
            background: theme === "dark" ? "#1e1e1e" : "#ffffff",
          }}>
            {/* Grid Lines */}
            <div style={{
              position: "absolute",
              inset: 0,
              backgroundImage: `
                linear-gradient(var(--color-border-tertiary) 1px, transparent 1px),
                linear-gradient(90deg, var(--color-border-tertiary) 1px, transparent 1px)
              `,
              backgroundSize: "20px 20px",
              opacity: 0.3,
              pointerEvents: "none",
            }} />

            {/* Objects */}
            {section.objects.map((obj) => (
              <div
                key={obj.id}
                onClick={() => onSelectObject(obj)}
                style={{
                  position: "absolute",
                  left: `${obj.x}px`,
                  top: `${obj.y}px`,
                  width: `${obj.width}px`,
                  height: `${obj.height}px`,
                  border: selectedObject?.id === obj.id 
                    ? "2px solid var(--color-border-info)" 
                    : obj.properties?.border || "1px solid #cccccc",
                  background: obj.properties?.backgroundColor || "transparent",
                  color: obj.properties?.color || "#000000",
                  fontSize: `${obj.properties?.fontSize || 12}px`,
                  fontFamily: obj.properties?.fontFamily || "Arial",
                  cursor: "move",
                  display: "flex",
                  alignItems: "center",
                  padding: "4px",
                  overflow: "hidden",
                }}
              >
                {obj.type === "field" && (
                  <span style={{ fontSize: "11px" }}>
                    {obj.field?.label || obj.field?.name || "Field"}
                  </span>
                )}
                {obj.type === "text" && <span>{obj.content}</span>}
                {obj.type === "image" && <span>🖼️ Image</span>}
                {obj.type === "chart" && <span>📊 Chart</span>}
                {obj.type === "table" && <span>📋 Table</span>}

                {/* Resize Handle */}
                {selectedObject?.id === obj.id && (
                  <div style={{
                    position: "absolute",
                    right: "-4px",
                    bottom: "-4px",
                    width: "10px",
                    height: "10px",
                    background: "var(--color-border-info)",
                    cursor: "se-resize",
                  }} />
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
