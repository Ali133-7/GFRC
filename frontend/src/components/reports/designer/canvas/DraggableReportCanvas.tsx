import React from "react";
import { useDroppable } from "@dnd-kit/core";
import type { ReportSection, ReportObject, ReportField } from "@/types/report";

interface DraggableReportCanvasProps {
  sections: ReportSection[];
  onSectionChange: (sections: ReportSection[]) => void;
  selectedObject: ReportObject | null;
  onSelectObject: (object: ReportObject) => void;
  onUpdateObject: (id: string, updates: Partial<ReportObject>) => void;
  onDeleteObject: (id: string) => void;
  theme: string;
  availableFields: ReportField[];
}

export function DraggableReportCanvas({
  sections,
  onSectionChange,
  selectedObject,
  onSelectObject,
  onUpdateObject,
  onDeleteObject,
  theme,
}: DraggableReportCanvasProps) {
  return (
    <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
      {sections.map((section) => (
        <DroppableSection
          key={section.id}
          section={section}
          selectedObject={selectedObject}
          onSelectObject={onSelectObject}
          onUpdateObject={onUpdateObject}
          onDeleteObject={onDeleteObject}
          theme={theme}
        />
      ))}
    </div>
  );
}

function DroppableSection({
  section,
  selectedObject,
  onSelectObject,
  onUpdateObject,
  onDeleteObject,
  theme,
}: {
  section: ReportSection;
  selectedObject: ReportObject | null;
  onSelectObject: (object: ReportObject) => void;
  onUpdateObject: (id: string, updates: Partial<ReportObject>) => void;
  onDeleteObject: (id: string) => void;
  theme: string;
}) {
  const { setNodeRef, isOver } = useDroppable({
    id: section.id,
    data: { sectionId: section.id },
  });

  return (
    <div
      style={{
        marginBottom: "8px",
        border: "2px solid",
        borderColor: isOver ? "var(--color-border-info)" : "var(--color-border-tertiary)",
        borderRadius: "6px",
        background: "var(--color-background-primary)",
        overflow: "hidden",
      }}
    >
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

      <div
        ref={setNodeRef}
        style={{
          height: `${section.height}px`,
          position: "relative",
          background: theme === "dark" ? "#1e1e1e" : "#ffffff",
        }}
      >
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

        {section.objects.map((obj) => (
          <CanvasObject
            key={obj.id}
            object={obj}
            isSelected={selectedObject?.id === obj.id}
            onSelect={() => onSelectObject(obj)}
            onUpdate={(updates) => onUpdateObject(obj.id, updates)}
            onDelete={() => onDeleteObject(obj.id)}
          />
        ))}
      </div>
    </div>
  );
}

function CanvasObject({
  object,
  isSelected,
  onSelect,
  onUpdate,
  onDelete,
}: {
  object: ReportObject;
  isSelected: boolean;
  onSelect: () => void;
  onUpdate: (updates: Partial<ReportObject>) => void;
  onDelete: () => void;
}) {
  return (
    <div
      onClick={onSelect}
      style={{
        position: "absolute",
        left: `${object.x}px`,
        top: `${object.y}px`,
        width: `${object.width}px`,
        height: `${object.height}px`,
        border: isSelected ? "2px solid var(--color-border-info)" : object.properties?.border || "1px solid #cccccc",
        background: object.properties?.backgroundColor || "transparent",
        color: object.properties?.color || "#000000",
        fontSize: `${object.properties?.fontSize || 12}px`,
        fontFamily: object.properties?.fontFamily || "Arial",
        cursor: "move",
        display: "flex",
        alignItems: "center",
        padding: "4px",
        overflow: "hidden",
      }}
    >
      {object.type === "field" && (
        <span style={{ fontSize: "11px" }}>
          {object.field?.label || object.field?.name || "Field"}
        </span>
      )}
      {object.type === "text" && <span>{object.content}</span>}
      {object.type === "image" && <span>🖼️ Image</span>}
      {object.type === "chart" && <span>📊 Chart</span>}
      {object.type === "table" && <span>📋 Table</span>}

      {isSelected && (
        <>
          <div style={{
            position: "absolute",
            right: "-4px",
            bottom: "-4px",
            width: "10px",
            height: "10px",
            background: "var(--color-border-info)",
            cursor: "se-resize",
          }} />
          <button
            onClick={(e) => { e.stopPropagation(); onDelete(); }}
            style={{
              position: "absolute",
              top: "-8px",
              right: "-8px",
              width: "16px",
              height: "16px",
              borderRadius: "50%",
              background: "var(--color-background-danger)",
              color: "var(--color-text-danger)",
              border: "none",
              cursor: "pointer",
              fontSize: "10px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            ×
          </button>
        </>
      )}
    </div>
  );
}
