import React, { useMemo, useState, useCallback } from "react";
import type { BusinessRegister, BusinessField, ReportField } from "@/types/report";
import { RegisterTree } from "./RegisterTree";

export type FieldBrowserTab = "all" | "favorites" | "recent" | "selected";

interface BusinessFieldBrowserProps {
  registers: BusinessRegister[];
  fields: BusinessField[];
  selectedRegisterIds: string[];
  favoriteIds: string[];
  recentIds: string[];
  isLoadingRegisters: boolean;
  isLoadingFields: boolean;
  onSelectRegister: (registerId: string) => void;
  onToggleFavorite: (fieldId: string) => void;
  onRecordUsage: (fieldId: string) => void;
  onDropField: (field: ReportField, sectionId: string) => void;
}

const FIELD_TYPE_ICONS: Record<string, string> = {
  string: "📝",
  number: "🔢",
  currency: "💰",
  date: "📅",
  datetime: "🕒",
  boolean: "☑️",
};

export function fieldToReportField(field: BusinessField): ReportField {
  return {
    id: field.id,
    name: field.name,
    label: field.label,
    label_ar: field.label,
    type: field.data_type,
    registerId: field.register_id,
    registerName: field.register_name,
    category: field.category,
    description: field.description,
    sourceType: "register_field",
    isSearchable: field.is_searchable,
    isFilterable: field.is_filterable,
    isAggregatable: field.is_aggregatable,
  };
}

export function BusinessFieldBrowser({
  registers,
  fields,
  selectedRegisterIds,
  favoriteIds,
  recentIds,
  isLoadingRegisters,
  isLoadingFields,
  onSelectRegister,
  onToggleFavorite,
  onRecordUsage,
  onDropField,
}: BusinessFieldBrowserProps) {
  const [search, setSearch] = useState("");
  const [activeTab, setActiveTab] = useState<FieldBrowserTab>("all");
  const [expandedRegisters, setExpandedRegisters] = useState<Set<string>>(
    () => new Set((registers ?? []).map((r) => r.id))
  );

  const toggleRegister = useCallback((id: string) => {
    setExpandedRegisters((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const filteredFields = useMemo(() => {
    const term = search.trim().toLowerCase();
    let base = fields;

    if (activeTab === "favorites") {
      base = fields.filter((f) => favoriteIds.includes(f.id));
    } else if (activeTab === "recent") {
      const recentSet = new Set(recentIds);
      base = fields.filter((f) => recentSet.has(f.id));
    } else if (activeTab === "selected") {
      const selectedSet = new Set(selectedRegisterIds);
      base = fields.filter((f) => selectedSet.has(f.register_id));
    }

    if (!term) return base;

    return base.filter(
      (f) =>
        f.label.toLowerCase().includes(term) ||
        f.name.toLowerCase().includes(term) ||
        (f.register_name?.toLowerCase().includes(term) ?? false)
    );
  }, [fields, search, activeTab, favoriteIds, recentIds, selectedRegisterIds]);

  const fieldsByRegister = useMemo(() => {
    const map = new Map<string, BusinessField[]>();
    for (const field of filteredFields) {
      const list = map.get(field.register_id) ?? [];
      list.push(field);
      map.set(field.register_id, list);
    }
    return map;
  }, [filteredFields]);

  const categories = useMemo(() => {
    const set = new Set(fields.map((f) => f.category || "general"));
    return Array.from(set).sort();
  }, [fields]);

  return (
    <div style={{ display: "flex", flexDirection: "column", height: "100%", overflow: "hidden" }}>
      {/* Header */}
      <div style={{ padding: "12px 12px 8px" }}>
        <h3
          style={{
            fontSize: "14px",
            fontWeight: 600,
            marginBottom: "10px",
            color: "var(--color-text-primary)",
          }}
        >
          📁 السجلات
        </h3>

        {/* Search */}
        <div style={{ position: "relative", marginBottom: "8px" }}>
          <span
            style={{
              position: "absolute",
              left: "10px",
              top: "50%",
              transform: "translateY(-50%)",
              fontSize: "12px",
              color: "var(--color-text-tertiary)",
            }}
          >
            🔍
          </span>
          <input
            type="text"
            placeholder="ابحث عن حقل..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            style={{
              width: "100%",
              padding: "7px 10px 7px 30px",
              fontSize: "12px",
              border: "0.5px solid var(--color-border-secondary)",
              borderRadius: "6px",
              background: "var(--color-background-primary)",
              color: "var(--color-text-primary)",
              outline: "none",
            }}
          />
        </div>

        {/* Tabs */}
        <div style={{ display: "flex", gap: "4px", flexWrap: "wrap" }}>
          {[
            { key: "all", label: "الكل" },
            { key: "selected", label: "المختارة" },
            { key: "favorites", label: "المفضلة" },
            { key: "recent", label: "مستخدم حديثاً" },
          ].map((tab) => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key as FieldBrowserTab)}
              style={{
                padding: "4px 8px",
                fontSize: "11px",
                borderRadius: "4px",
                border: "none",
                cursor: "pointer",
                background:
                  activeTab === tab.key
                    ? "var(--color-background-info)"
                    : "var(--color-background-secondary)",
                color:
                  activeTab === tab.key
                    ? "var(--color-text-info)"
                    : "var(--color-text-secondary)",
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {/* Tree */}
      <div style={{ flex: 1, overflow: "auto", padding: "0 8px 8px" }}>
        {isLoadingRegisters || isLoadingFields ? (
          <div
            style={{
              padding: "20px",
              textAlign: "center",
              fontSize: "12px",
              color: "var(--color-text-tertiary)",
            }}
          >
            جاري تحميل السجلات والحقول...
          </div>
        ) : (registers ?? []).length === 0 ? (
          <div
            style={{
              padding: "20px",
              textAlign: "center",
              fontSize: "12px",
              color: "var(--color-text-tertiary)",
            }}
          >
            لا توجد سجلات متاحة
          </div>
        ) : (
          <RegisterTree
            registers={registers}
            fieldsByRegister={fieldsByRegister}
            selectedRegisterIds={selectedRegisterIds}
            favoriteIds={favoriteIds}
            expandedRegisterIds={expandedRegisters}
            fieldTypeIcons={FIELD_TYPE_ICONS}
            onToggleRegister={toggleRegister}
            onSelectRegister={onSelectRegister}
            onToggleFavorite={onToggleFavorite}
            onRecordUsage={onRecordUsage}
            onDropField={onDropField}
          />
        )}
      </div>

      {/* Categories footer */}
      {categories.length > 0 && activeTab !== "favorites" && activeTab !== "recent" && (
        <div
          style={{
            padding: "8px 12px",
            borderTop: "1px solid var(--color-border-tertiary)",
            fontSize: "10px",
            color: "var(--color-text-tertiary)",
          }}
        >
          التصنيفات: {categories.join(" • ")}
        </div>
      )}
    </div>
  );
}
