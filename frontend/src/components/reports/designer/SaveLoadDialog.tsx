import React, { useState, useEffect } from "react";
import { ReportDesignerAPI, ReportDesign } from "@/api/reportDesigner";

interface SaveLoadDialogProps {
  reportId?: string;
  isOpen: boolean;
  onClose: () => void;
  onLoad: (design: ReportDesign) => void;
  onSave: (design: ReportDesign) => void;
}

export function SaveLoadDialog({ reportId, isOpen, onClose, onLoad, onSave }: SaveLoadDialogProps) {
  const [mode, setMode] = useState<"save" | "load" | "templates" | "history">("save");
  const [designName, setDesignName] = useState("");
  const [designNameAr, setDesignNameAr] = useState("");
  const [description, setDescription] = useState("");
  const [templates, setTemplates] = useState<ReportDesign[]>([]);
  const [history, setHistory] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (isOpen) {
      loadTemplates();
      if (reportId) loadHistory();
    }
  }, [isOpen, reportId]);

  const loadTemplates = async () => {
    try {
      setLoading(true);
      const data = await ReportDesignerAPI.getTemplates();
      setTemplates(data);
    } catch (err) {
      console.error("Failed to load templates:", err);
    } finally {
      setLoading(false);
    }
  };

  const loadHistory = async () => {
    if (!reportId) return;
    try {
      setLoading(true);
      const data = await ReportDesignerAPI.getVersionHistory(reportId);
      setHistory(data);
    } catch (err) {
      console.error("Failed to load history:", err);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!designName || !designNameAr) {
      setError("Please enter report name in both Arabic and English");
      return;
    }

    try {
      setLoading(true);
      setError("");
      
      const design: ReportDesign = {
        id: reportId,
        name: designName,
        name_ar: designNameAr,
        description,
        data_source: "receipts",
        sections: [],
        joins: [],
        filters: [],
        calculatedFields: [],
        charts: [],
        theme: "modern",
        version: 1,
        status: "draft",
      };

      if (reportId) {
        await ReportDesignerAPI.saveDesign(reportId, design);
      }
      
      onSave(design);
      onClose();
    } catch (err: any) {
      setError(err.response?.data?.message || "Failed to save report");
    } finally {
      setLoading(false);
    }
  };

  const handleLoad = async (template: ReportDesign) => {
    try {
      setLoading(true);
      setError("");
      
      if (template.id) {
        const design = await ReportDesignerAPI.loadDesign(template.id);
        onLoad(design);
      } else {
        onLoad(template);
      }
      
      onClose();
    } catch (err: any) {
      setError(err.response?.data?.message || "Failed to load report");
    } finally {
      setLoading(false);
    }
  };

  const handleRestoreVersion = async (versionId: string) => {
    if (!reportId) return;
    try {
      setLoading(true);
      setError("");
      
      const design = await ReportDesignerAPI.restoreVersion(reportId, versionId);
      onLoad(design);
      onClose();
    } catch (err: any) {
      setError(err.response?.data?.message || "Failed to restore version");
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div style={{
      position: "fixed",
      inset: 0,
      background: "rgba(0,0,0,0.5)",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      zIndex: 9999,
    }} onClick={onClose}>
      <div style={{
        background: "var(--color-background-primary)",
        borderRadius: "12px",
        padding: "24px",
        maxWidth: "600px",
        width: "90%",
        maxHeight: "80vh",
        overflow: "auto",
      }} onClick={(e) => e.stopPropagation()}>
        <h3 style={{ fontSize: "18px", fontWeight: 600, marginBottom: "20px", color: "var(--color-text-primary)" }}>
          💾 Report Designer
        </h3>

        {/* Mode Tabs */}
        <div style={{ display: "flex", gap: "8px", marginBottom: "20px" }}>
          <button
            onClick={() => setMode("save")}
            style={{
              flex: 1,
              padding: "8px",
              fontSize: "12px",
              fontWeight: mode === "save" ? 600 : 400,
              background: mode === "save" ? "var(--color-background-info)" : "var(--color-background-secondary)",
              color: mode === "save" ? "var(--color-text-info)" : "var(--color-text-secondary)",
              border: "none",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            💾 Save
          </button>
          <button
            onClick={() => setMode("load")}
            style={{
              flex: 1,
              padding: "8px",
              fontSize: "12px",
              fontWeight: mode === "load" ? 600 : 400,
              background: mode === "load" ? "var(--color-background-info)" : "var(--color-background-secondary)",
              color: mode === "load" ? "var(--color-text-info)" : "var(--color-text-secondary)",
              border: "none",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            📂 Load
          </button>
          <button
            onClick={() => setMode("templates")}
            style={{
              flex: 1,
              padding: "8px",
              fontSize: "12px",
              fontWeight: mode === "templates" ? 600 : 400,
              background: mode === "templates" ? "var(--color-background-info)" : "var(--color-background-secondary)",
              color: mode === "templates" ? "var(--color-text-info)" : "var(--color-text-secondary)",
              border: "none",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            📋 Templates
          </button>
          {reportId && (
            <button
              onClick={() => setMode("history")}
              style={{
                flex: 1,
                padding: "8px",
                fontSize: "12px",
                fontWeight: mode === "history" ? 600 : 400,
                background: mode === "history" ? "var(--color-background-info)" : "var(--color-background-secondary)",
                color: mode === "history" ? "var(--color-text-info)" : "var(--color-text-secondary)",
                border: "none",
                borderRadius: "6px",
                cursor: "pointer",
              }}
            >
              📜 History
            </button>
          )}
        </div>

        {/* Error Message */}
        {error && (
          <div style={{
            padding: "10px",
            background: "var(--color-background-danger)",
            color: "var(--color-text-danger)",
            borderRadius: "6px",
            fontSize: "12px",
            marginBottom: "16px",
          }}>
            ⚠️ {error}
          </div>
        )}

        {/* Save Mode */}
        {mode === "save" && (
          <div style={{ display: "grid", gap: "12px" }}>
            <div>
              <label style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                Report Name (English) *
              </label>
              <input
                type="text"
                value={designName}
                onChange={(e) => setDesignName(e.target.value)}
                placeholder="e.g., Monthly Revenue Report"
                style={{ width: "100%", padding: "8px 12px", fontSize: "13px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px" }}
              />
            </div>

            <div>
              <label style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                اسم التقرير (عربي) *
              </label>
              <input
                type="text"
                value={designNameAr}
                onChange={(e) => setDesignNameAr(e.target.value)}
                placeholder="مثال: تقرير الإيرادات الشهري"
                style={{ width: "100%", padding: "8px 12px", fontSize: "13px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px" }}
              />
            </div>

            <div>
              <label style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                Description
              </label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Report description..."
                style={{ width: "100%", padding: "8px 12px", fontSize: "13px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", minHeight: "80px", resize: "vertical" }}
              />
            </div>

            <button
              onClick={handleSave}
              disabled={loading || !designName || !designNameAr}
              style={{
                width: "100%",
                padding: "10px",
                fontSize: "14px",
                fontWeight: 600,
                background: loading || !designName || !designNameAr ? "var(--color-background-secondary)" : "var(--color-background-success)",
                color: loading || !designName || !designNameAr ? "var(--color-text-tertiary)" : "var(--color-text-success)",
                border: "0.5px solid var(--color-border-success)",
                borderRadius: "6px",
                cursor: loading || !designName || !designNameAr ? "not-allowed" : "pointer",
              }}
            >
              {loading ? "⏳ Saving..." : "💾 Save Report"}
            </button>
          </div>
        )}

        {/* Load Mode */}
        {mode === "load" && (
          <div>
            {loading ? (
              <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                Loading...
              </div>
            ) : templates.length === 0 ? (
              <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                No saved reports found
              </div>
            ) : (
              <div style={{ display: "grid", gap: "8px" }}>
                {templates.map(template => (
                  <button
                    key={template.id}
                    onClick={() => handleLoad(template)}
                    style={{
                      padding: "12px",
                      background: "var(--color-background-secondary)",
                      border: "0.5px solid var(--color-border-tertiary)",
                      borderRadius: "6px",
                      cursor: "pointer",
                      textAlign: "left",
                    }}
                  >
                    <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                      {template.name_ar || template.name}
                    </div>
                    <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
                      {template.description || "No description"}
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Templates Mode */}
        {mode === "templates" && (
          <div>
            {loading ? (
              <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                Loading templates...
              </div>
            ) : templates.length === 0 ? (
              <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                No templates available
              </div>
            ) : (
              <div style={{ display: "grid", gap: "8px" }}>
                {templates.map(template => (
                  <button
                    key={template.id}
                    onClick={() => handleLoad(template)}
                    style={{
                      padding: "12px",
                      background: "var(--color-background-secondary)",
                      border: "0.5px solid var(--color-border-tertiary)",
                      borderRadius: "6px",
                      cursor: "pointer",
                      textAlign: "left",
                    }}
                  >
                    <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                      📋 {template.name_ar || template.name}
                    </div>
                    <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
                      {template.description || "Template"}
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* History Mode */}
        {mode === "history" && (
          <div>
            {loading ? (
              <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                Loading history...
              </div>
            ) : history.length === 0 ? (
              <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                No version history
              </div>
            ) : (
              <div style={{ display: "grid", gap: "8px" }}>
                {history.map(version => (
                  <button
                    key={version.id}
                    onClick={() => handleRestoreVersion(version.id)}
                    style={{
                      padding: "12px",
                      background: "var(--color-background-secondary)",
                      border: "0.5px solid var(--color-border-tertiary)",
                      borderRadius: "6px",
                      cursor: "pointer",
                      textAlign: "left",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                      <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                        Version {version.version}
                      </div>
                      <span style={{
                        padding: "2px 8px",
                        fontSize: "10px",
                        borderRadius: "10px",
                        background: version.status === "published" ? "var(--color-background-success)" : "var(--color-background-secondary)",
                        color: version.status === "published" ? "var(--color-text-success)" : "var(--color-text-secondary)",
                      }}>
                        {version.status}
                      </span>
                    </div>
                    <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
                      {new Date(version.created_at).toLocaleString()} • {version.created_by}
                    </div>
                    {version.change_summary && (
                      <div style={{ fontSize: "11px", color: "var(--color-text-secondary)", marginTop: "4px" }}>
                        {version.change_summary}
                      </div>
                    )}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Close Button */}
        <button
          onClick={onClose}
          style={{
            width: "100%",
            padding: "10px",
            fontSize: "13px",
            fontWeight: 500,
            background: "var(--color-background-secondary)",
            color: "var(--color-text-secondary)",
            border: "0.5px solid var(--color-border-secondary)",
            borderRadius: "6px",
            cursor: "pointer",
            marginTop: "16px",
          }}
        >
          Close
        </button>
      </div>
    </div>
  );
}
