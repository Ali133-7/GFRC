import React from "react";
import { useDraggable } from "@dnd-kit/core";
import type { BusinessRegister, BusinessField, ReportField } from "@/types/report";
import { fieldToReportField } from "./BusinessFieldBrowser";

interface RegisterTreeProps {
  registers: BusinessRegister[];
  fieldsByRegister: Map<string, BusinessField[]>;
  selectedRegisterIds: string[];
  favoriteIds: string[];
  expandedRegisterIds: Set<string>;
  fieldTypeIcons: Record<string, string>;
  onToggleRegister: (id: string) => void;
  onSelectRegister: (id: string) => void;
  onToggleFavorite: (fieldId: string) => void;
  onRecordUsage: (fieldId: string) => void;
  onDropField: (field: ReportField, sectionId: string) => void;
}

function DraggableFieldItem({
  field,
  isFavorite,
  fieldTypeIcons,
  onToggleFavorite,
  onRecordUsage,
}: {
  field: BusinessField;
  isFavorite: boolean;
  fieldTypeIcons: Record<string, string>;
  onToggleFavorite: (fieldId: string) => void;
  onRecordUsage: (fieldId: string) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `field-${field.id}`,
    data: { field: fieldToReportField(field), source: "field-browser" },
  });

  const style: React.CSSProperties = {
    transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      style={style}
      onClick={() => onRecordUsage(field.id)}
      className="field-browser-field"
      title={field.description || field.label}
    >
      <span className="field-icon">{fieldTypeIcons[field.data_type] || "📝"}</span>
      <span className="field-label">{field.label}</span>
      <button
        type="button"
        className="field-favorite-btn"
        onClick={(e) => {
          e.stopPropagation();
          onToggleFavorite(field.id);
        }}
        title={isFavorite ? "إزالة من المفضلة" : "إضافة للمفضلة"}
      >
        {isFavorite ? "★" : "☆"}
      </button>
    </div>
  );
}

export function RegisterTree({
  registers,
  fieldsByRegister,
  selectedRegisterIds,
  favoriteIds,
  expandedRegisterIds,
  fieldTypeIcons,
  onToggleRegister,
  onSelectRegister,
  onToggleFavorite,
  onRecordUsage,
  onDropField,
}: RegisterTreeProps) {
  const favoriteSet = new Set(favoriteIds);

  return (
    <div className="register-tree" role="tree">
      {(registers ?? []).map((register) => {
        const isSelected = selectedRegisterIds.includes(register.id);
        const isExpanded = expandedRegisterIds.has(register.id);
        const fields = fieldsByRegister.get(register.id) ?? [];

        return (
          <div key={register.id} className="register-node" role="treeitem">
            <div
              className={`register-header ${isSelected ? "selected" : ""}`}
              onClick={() => onToggleRegister(register.id)}
            >
              <span className="register-chevron">{isExpanded ? "▼" : "▶"}</span>
              <span className="register-icon">📁</span>
              <span className="register-name">{register.name}</span>
              <span className="register-count">{register.record_count}</span>
              <button
                type="button"
                className={`register-select-btn ${isSelected ? "selected" : ""}`}
                onClick={(e) => {
                  e.stopPropagation();
                  onSelectRegister(register.id);
                }}
                title={isSelected ? "إلغاء اختيار السجل" : "اختيار السجل للتقرير"}
              >
                {isSelected ? "✓" : "+"}
              </button>
            </div>

            {isExpanded && (
              <div className="register-fields">
                {fields.length === 0 ? (
                  <div className="empty-fields">لا توجد حقول مطابقة</div>
                ) : (
                  fields.map((field) => (
                    <DraggableFieldItem
                      key={field.id}
                      field={field}
                      isFavorite={favoriteSet.has(field.id)}
                      fieldTypeIcons={fieldTypeIcons}
                      onToggleFavorite={onToggleFavorite}
                      onRecordUsage={onRecordUsage}
                    />
                  ))
                )}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
