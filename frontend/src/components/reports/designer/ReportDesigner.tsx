import React, { useState, useCallback } from "react";
import { DataSourcesPanel } from "./panels/DataSourcesPanel";
import { ReportCanvas } from "./canvas/ReportCanvas";
import { PropertiesPanel } from "./panels/PropertiesPanel";
import { DataPreviewPanel } from "./panels/DataPreviewPanel";
import { Toolbar } from "./Toolbar";
import type { ReportSection, ReportField, ReportObject } from "@/types/report";

interface ReportDesignerProps {
  reportId?: string;
  onSave?: (data: any) => void;
}

export function ReportDesigner({ reportId, onSave }: ReportDesignerProps) {
  const [activeTab, setActiveTab] = useState<"design" | "data" | "filters" | "charts" | "pivot">("design");
  const [sections, setSections] = useState<ReportSection[]>([
    { id: "header", type: "report_header", name: "Report Header", height: 80, objects: [] },
    { id: "page_header", type: "page_header", name: "Page Header", height: 60, objects: [] },
    { id: "details", type: "details", name: "Details", height: 40, objects: [] },
    { id: "page_footer", type: "page_footer", name: "Page Footer", height: 60, objects: [] },
    { id: "footer", type: "report_footer", name: "Report Footer", height: 80, objects: [] },
  ]);
  const [selectedObject, setSelectedObject] = useState<ReportObject | null>(null);
  const [selectedRegisterIds, setSelectedRegisterIds] = useState<string[]>([]);
  const [previewData, setPreviewData] = useState<any[]>([]);
  const [theme, setTheme] = useState<"classic" | "modern" | "corporate" | "dark">("modern");

  const handleDropField = useCallback((field: ReportField, sectionId: string) => {
    setSections(prev => prev.map(section => {
      if (section.id === sectionId) {
        return {
          ...section,
          objects: [...section.objects, {
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
          }],
        };
      }
      return section;
    }));
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

  return (
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
        onSave={() => onSave?.({})}
      />

      {/* Main Designer Area */}
      <div style={{ display: "flex", flex: 1, overflow: "hidden" }}>
        
        {/* LEFT SIDEBAR - Data Sources */}
        <div style={{ 
          width: "280px", 
          borderRight: "1px solid var(--color-border-tertiary)", 
          background: "var(--color-background-primary)",
          overflow: "hidden",
          display: "flex",
          flexDirection: "column",
        }}>
          <DataSourcesPanel
            selectedRegisterIds={selectedRegisterIds}
            onSelectedRegistersChange={setSelectedRegisterIds}
            onDropField={handleDropField}
          />
        </div>

        {/* CENTER - Report Canvas */}
        <div style={{ 
          flex: 1, 
          overflow: "auto", 
          background: "var(--color-background-secondary)",
          padding: "20px",
        }}>
          <ReportCanvas
            sections={sections}
            onSectionChange={setSections}
            selectedObject={selectedObject}
            onSelectObject={handleSelectObject}
            onUpdateObject={handleUpdateObject}
            onDeleteObject={handleDeleteObject}
            theme={theme}
          />
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
          onDataChange={setPreviewData}
        />
      </div>
    </div>
  );
}
