import React, { useState, useCallback, useEffect } from "react";
import { DndContext, DragOverlay, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { DataSourcesPanel } from "./panels/DataSourcesPanel";
import { DraggableReportCanvas } from "./canvas/DraggableReportCanvas";
import { PropertiesPanel } from "./panels/PropertiesPanel";
import { DataPreviewPanel } from "./panels/DataPreviewPanel";
import { Toolbar } from "./Toolbar";
import { DataModelDesigner } from "./builders/DataModelDesigner";
import { AdvancedFilterBuilder } from "./builders/AdvancedFilterBuilder";
import { FormulaBuilder } from "./builders/FormulaBuilder";
import { ChartBuilder } from "./builders/ChartBuilder";
import { PivotBuilder } from "./builders/PivotBuilder";
import { ConditionalFormattingUI } from "./ConditionalFormattingUI";
import { ThemeCustomizer } from "./ThemeCustomizer";
import { ScheduleConfig } from "./ScheduleConfig";
import { VersionHistoryUI } from "./VersionHistoryUI";
import { SaveLoadDialog } from "./SaveLoadDialog";
import { useBusinessRegisters, useBusinessFields, useBusinessPreview, useRegisterRelationships } from "@/hooks/useBusinessReportData";
import type { ReportSection, ReportObject, ReportField, ReportFilter, CalculatedField, ChartConfig, PivotConfig, ConditionalFormat, ReportSchedule, RegisterRelationship } from "@/types/report";

const EMPTY_ARRAY: never[] = [];

interface EnterpriseReportDesignerProps {
  reportId?: string;
  onSave?: (data: any) => void;
}

export function EnterpriseReportDesigner({ reportId, onSave }: EnterpriseReportDesignerProps) {
  const [activeTab, setActiveTab] = useState<"design" | "data" | "filters" | "formulas" | "charts" | "pivot" | "formatting" | "theme" | "schedule" | "history">("design");
  const [sections, setSections] = useState<ReportSection[]>([
    { id: "header", type: "report_header", name: "Report Header", height: 80, objects: [] },
    { id: "page_header", type: "page_header", name: "Page Header", height: 60, objects: [] },
    { id: "details", type: "details", name: "Details", height: 40, objects: [] },
    { id: "page_footer", type: "page_footer", name: "Page Footer", height: 60, objects: [] },
    { id: "footer", type: "report_footer", name: "Report Footer", height: 80, objects: [] },
  ]);
  const [selectedObject, setSelectedObject] = useState<ReportObject | null>(null);
  const [selectedRegisterIds, setSelectedRegisterIds] = useState<string[]>([]);
  const [relationships, setRelationships] = useState<RegisterRelationship[]>([]);
  const [previewData, setPreviewData] = useState<any[]>([]);
  const [previewTotal, setPreviewTotal] = useState(0);
  const [theme, setTheme] = useState<"classic" | "modern" | "corporate" | "dark" | "government" | "financial">("modern");
  const [filters, setFilters] = useState<ReportFilter[]>([]);
  const [calculatedFields, setCalculatedFields] = useState<CalculatedField[]>([]);
  const [charts, setCharts] = useState<ChartConfig[]>([]);
  const [pivotConfig, setPivotConfig] = useState<PivotConfig | null>(null);
  const [conditionalFormats, setConditionalFormats] = useState<ConditionalFormat[]>([]);
  const [schedule, setSchedule] = useState<ReportSchedule | null>(null);
  const [showSaveLoad, setShowSaveLoad] = useState(false);
  const [activeDragField, setActiveDragField] = useState<ReportField | null>(null);

  const { data: registers = [] } = useBusinessRegisters();
  const { data: fieldsData } = useBusinessFields(selectedRegisterIds);
  const businessFields = fieldsData ?? EMPTY_ARRAY;
  const { data: analyzedRelationships = [] } = useRegisterRelationships(selectedRegisterIds);
  const previewMutation = useBusinessPreview();
  const mutateAsyncRef = React.useRef(previewMutation.mutateAsync);
  mutateAsyncRef.current = previewMutation.mutateAsync;
  const isFetchingRef = React.useRef(false);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 5 },
    })
  );

  useEffect(() => {
    if (analyzedRelationships.length > 0) {
      setRelationships((prev) => {
        const existingIds = new Set(prev.map((r) => r.id));
        const newItems = analyzedRelationships.filter((r) => !existingIds.has(r.id));
        if (newItems.length === 0) return prev; // Prevent unnecessary re-render
        return [...prev, ...newItems];
      });
    }
  }, [analyzedRelationships]);

  const refreshPreview = useCallback(async () => {
    if (isFetchingRef.current) return;
    if (selectedRegisterIds.length === 0 || businessFields.length === 0) {
      setPreviewData([]);
      setPreviewTotal(0);
      return;
    }

    const visibleFieldIds = businessFields.slice(0, 12).map((f) => f.id);
    try {
      isFetchingRef.current = true;
      const result = await mutateAsyncRef.current({
        registerIds: selectedRegisterIds,
        fieldIds: visibleFieldIds,
        filters,
        limit: 20,
      });
      setPreviewData(result.data);
      setPreviewTotal(result.total);
    } catch {
      setPreviewData([]);
      setPreviewTotal(0);
    } finally {
      isFetchingRef.current = false;
    }
  }, [selectedRegisterIds, businessFields, filters]);

  useEffect(() => {
    refreshPreview();
  }, [refreshPreview]);

  const availableFields = React.useMemo<ReportField[]>(() => businessFields.map((field) => ({
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
  })), [businessFields]);

  const handleDropField = useCallback((field: ReportField, sectionId: string) => {
    setSections((prev) =>
      prev.map((section) => {
        if (section.id === sectionId) {
          return {
            ...section,
            objects: [
              ...section.objects,
              {
                id: `obj_${Date.now()}`,
                type: "field",
                field: field,
                x: 50,
                y: 10,
                width: 150,
                height: 30,
                properties: {
                  fontSize: 12,
                  fontFamily: "Arial",
                  color: "#000000",
                  backgroundColor: "transparent",
                  border: "1px solid #cccccc",
                },
              },
            ],
          };
        }
        return section;
      })
    );
  }, []);

  const handleUpdateObject = useCallback((objectId: string, updates: Partial<ReportObject>) => {
    setSections(prev => prev.map(section => ({
      ...section,
      objects: section.objects.map(obj => 
        obj.id === objectId ? { ...obj, ...updates } : obj
      ),
    })));
  }, []);

  const handleDeleteObject = useCallback((objectId: string) => {
    setSections(prev => prev.map(section => ({
      ...section,
      objects: section.objects.filter(obj => obj.id !== objectId),
    })));
    if (selectedObject?.id === objectId) {
      setSelectedObject(null);
    }
  }, [selectedObject]);

  const handleSelectObject = useCallback((object: ReportObject) => {
    setSelectedObject(object);
  }, []);

  const handleSave = () => {
    setShowSaveLoad(true);
  };

  const handleSaveDesign = (design: any) => {
    if (onSave) {
      onSave({
        ...design,
        sections,
        filters,
        calculatedFields,
        charts,
        pivotConfig,
        conditionalFormats,
        schedule,
        theme,
      });
    }
    setShowSaveLoad(false);
  };

  const handleDragStart = (event: any) => {
    const field = event.active.data.current?.field as ReportField | undefined;
    if (field) setActiveDragField(field);
  };

  const handleDragEnd = (event: any) => {
    const { active, over } = event;
    setActiveDragField(null);

    if (!over) return;

    const field = active.data.current?.field as ReportField | undefined;
    const sectionId = over.data.current?.sectionId as string | undefined;

    if (field && sectionId) {
      handleDropField(field, sectionId);
    }
  };

  return (
    <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
      <div style={{
        display: "flex",
        flexDirection: "column",
        height: "calc(100vh - 120px)",
        background: "var(--color-background)",
        overflow: "hidden",
      }}>
        {/* Top Toolbar */}
        <Toolbar
          activeTab={activeTab}
          onTabChange={setActiveTab}
          theme={theme}
          onThemeChange={(t: any) => setTheme(t)}
          onSave={handleSave}
        />

        {/* Main Designer Area */}
        <div style={{ display: "flex", flex: 1, overflow: "hidden" }}>

          {/* LEFT SIDEBAR - Context Sensitive */}
          <div style={{
            width: "300px",
            borderRight: "1px solid var(--color-border-tertiary)",
            background: "var(--color-background-primary)",
            overflow: "hidden",
            display: "flex",
            flexDirection: "column",
          }}>
            {activeTab === "design" && (
              <DataSourcesPanel
                selectedRegisterIds={selectedRegisterIds}
                onSelectedRegistersChange={setSelectedRegisterIds}
                onDropField={handleDropField}
              />
            )}
            {activeTab === "data" && (
              <DataModelDesigner
                registers={registers}
                selectedRegisterIds={selectedRegisterIds}
                relationships={relationships}
                onRelationshipsChange={setRelationships}
              />
            )}
            {activeTab === "filters" && (
              <AdvancedFilterBuilder
                filters={filters}
                onChange={setFilters}
                availableFields={availableFields}
              />
            )}
            {activeTab === "formulas" && (
              <FormulaBuilder
                fields={calculatedFields}
                onChange={setCalculatedFields}
                availableFields={availableFields}
              />
            )}
            {activeTab === "charts" && (
              <ChartBuilder
                charts={charts}
                onChange={setCharts}
                availableFields={availableFields}
              />
            )}
            {activeTab === "pivot" && (
              <PivotBuilder
                config={pivotConfig}
                onChange={setPivotConfig}
                availableFields={availableFields}
              />
            )}
            {activeTab === "formatting" && (
              <ConditionalFormattingUI
                formats={conditionalFormats}
                onChange={setConditionalFormats}
                availableFields={availableFields}
              />
            )}
            {activeTab === "theme" && (
              <ThemeCustomizer
                currentTheme={theme}
                onChange={(t: any) => setTheme(t)}
              />
            )}
            {activeTab === "schedule" && (
              <ScheduleConfig
                schedule={schedule}
                onChange={setSchedule}
              />
            )}
            {activeTab === "history" && (
              <VersionHistoryUI
                versions={[]}
                onRestore={(id) => console.log("Restore", id)}
                onCompare={(id1, id2) => console.log("Compare", id1, id2)}
              />
            )}
          </div>

          {/* CENTER - Report Canvas or Builder */}
          <div style={{
            flex: 1,
            overflow: "auto",
            background: "var(--color-background-secondary)",
            padding: "20px",
          }}>
            {activeTab === "design" ? (
              <DraggableReportCanvas
                sections={sections}
                onSectionChange={setSections}
                selectedObject={selectedObject}
                onSelectObject={handleSelectObject}
                onUpdateObject={handleUpdateObject}
                onDeleteObject={handleDeleteObject}
                theme={theme}
                availableFields={availableFields}
              />
            ) : activeTab === "data" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <DataModelDesigner
                  registers={registers}
                  selectedRegisterIds={selectedRegisterIds}
                  relationships={relationships}
                  onRelationshipsChange={setRelationships}
                />
              </div>
            ) : activeTab === "filters" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <AdvancedFilterBuilder
                  filters={filters}
                  onChange={setFilters}
                  availableFields={availableFields}
                />
              </div>
            ) : activeTab === "formulas" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <FormulaBuilder
                  fields={calculatedFields}
                  onChange={setCalculatedFields}
                  availableFields={availableFields}
                />
              </div>
            ) : activeTab === "charts" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <ChartBuilder
                  charts={charts}
                  onChange={setCharts}
                  availableFields={availableFields}
                />
              </div>
            ) : activeTab === "pivot" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <PivotBuilder
                  config={pivotConfig}
                  onChange={setPivotConfig}
                  availableFields={availableFields}
                />
              </div>
            ) : activeTab === "formatting" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <ConditionalFormattingUI
                  formats={conditionalFormats}
                  onChange={setConditionalFormats}
                  availableFields={availableFields}
                />
              </div>
            ) : activeTab === "theme" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <ThemeCustomizer
                  currentTheme={theme}
                  onChange={(t: any) => setTheme(t)}
                />
              </div>
            ) : activeTab === "schedule" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <ScheduleConfig
                  schedule={schedule}
                  onChange={setSchedule}
                />
              </div>
            ) : activeTab === "history" ? (
              <div style={{ maxWidth: "1000px", margin: "0 auto" }}>
                <VersionHistoryUI
                  versions={[]}
                  onRestore={(id) => console.log("Restore", id)}
                  onCompare={(id1, id2) => console.log("Compare", id1, id2)}
                />
              </div>
            ) : null}
          </div>

          {/* RIGHT SIDEBAR - Properties */}
          <div style={{
            width: "300px",
            borderLeft: "1px solid var(--color-border-tertiary)",
            background: "var(--color-background-primary)",
            overflow: "auto",
          }}>
            <PropertiesPanel
              selectedObject={selectedObject}
              onUpdateObject={handleUpdateObject}
              onDeleteObject={handleDeleteObject}
            />
          </div>
        </div>

        {/* BOTTOM PANEL - Live Data Preview */}
        <div style={{
          height: "200px",
          borderTop: "2px solid var(--color-border-info)",
          background: "var(--color-background-primary)",
          overflow: "hidden",
        }}>
          <DataPreviewPanel
            data={previewData}
            total={previewTotal}
            onDataChange={setPreviewData}
          />
        </div>

        {/* Save/Load Dialog */}
        <SaveLoadDialog
          reportId={reportId}
          isOpen={showSaveLoad}
          onClose={() => setShowSaveLoad(false)}
          onLoad={(design) => console.log("Load", design)}
          onSave={handleSaveDesign}
        />
      </div>

      <DragOverlay dropAnimation={null}>
        {activeDragField ? (
          <div
            style={{
              padding: "6px 10px",
              background: "var(--color-background-info)",
              color: "var(--color-text-info)",
              borderRadius: "4px",
              fontSize: "12px",
              boxShadow: "0 2px 8px rgba(0,0,0,0.15)",
            }}
          >
            {activeDragField.label || activeDragField.name}
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  );
}
