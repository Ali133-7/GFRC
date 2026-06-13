import React, { useState } from "react";

interface VersionHistoryProps {
  versions: Array<{
    id: string;
    version: number;
    status: "draft" | "published" | "archived";
    created_at: string;
    created_by: string;
    change_summary?: string;
  }>;
  onRestore: (versionId: string) => void;
  onCompare: (version1: string, version2: string) => void;
}

export function VersionHistoryUI({ versions, onRestore, onCompare }: VersionHistoryProps) {
  const [selectedVersions, setSelectedVersions] = useState<string[]>([]);
  const [expandedVersion, setExpandedVersion] = useState<string | null>(null);

  const handleToggleSelect = (versionId: string) => {
    if (selectedVersions.includes(versionId)) {
      setSelectedVersions(selectedVersions.filter(id => id !== versionId));
    } else if (selectedVersions.length < 2) {
      setSelectedVersions([...selectedVersions, versionId]);
    }
  };

  const handleCompare = () => {
    if (selectedVersions.length === 2) {
      onCompare(selectedVersions[0], selectedVersions[1]);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case "published": return { bg: "var(--color-background-success)", text: "var(--color-text-success)" };
      case "draft": return { bg: "var(--color-background-warning)", text: "var(--color-text-warning)" };
      case "archived": return { bg: "var(--color-background-secondary)", text: "var(--color-text-secondary)" };
      default: return { bg: "var(--color-background-secondary)", text: "var(--color-text-secondary)" };
    }
  };

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        📜 Version History
      </h3>

      {/* Compare Button */}
      {selectedVersions.length === 2 && (
        <button
          onClick={handleCompare}
          style={{
            width: "100%",
            padding: "10px",
            fontSize: "13px",
            fontWeight: 600,
            background: "var(--color-background-info)",
            color: "var(--color-text-info)",
            border: "0.5px solid var(--color-border-info)",
            borderRadius: "6px",
            cursor: "pointer",
            marginBottom: "16px",
          }}
        >
          🔍 Compare Selected Versions
        </button>
      )}

      {/* Versions List */}
      {versions.length === 0 ? (
        <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
          <div style={{ fontSize: "48px", marginBottom: "12px" }}>📜</div>
          <div style={{ fontSize: "14px", fontWeight: 500 }}>No version history</div>
          <div style={{ fontSize: "12px", marginTop: "4px" }}>Save the report to create versions</div>
        </div>
      ) : (
        <div style={{ display: "grid", gap: "12px" }}>
          {versions.map((version, index) => (
            <div
              key={version.id}
              style={{
                padding: "12px",
                background: expandedVersion === version.id ? "var(--color-background-info)" : "var(--color-background-secondary)",
                border: expandedVersion === version.id ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
                borderRadius: "8px",
                cursor: "pointer",
              }}
              onClick={() => setExpandedVersion(expandedVersion === version.id ? null : version.id)}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
                  {/* Checkbox for compare */}
                  <input
                    type="checkbox"
                    checked={selectedVersions.includes(version.id)}
                    onChange={() => handleToggleSelect(version.id)}
                    onClick={(e) => e.stopPropagation()}
                    style={{ width: "16px", height: "16px", cursor: "pointer" }}
                  />
                  <div>
                    <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                      Version {version.version}
                    </div>
                    <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "2px" }}>
                      {new Date(version.created_at).toLocaleString()} • {version.created_by}
                    </div>
                  </div>
                </div>
                <span
                  style={{
                    padding: "4px 10px",
                    fontSize: "10px",
                    fontWeight: 600,
                    borderRadius: "12px",
                    background: getStatusColor(version.status).bg,
                    color: getStatusColor(version.status).text,
                  }}
                >
                  {version.status.charAt(0).toUpperCase() + version.status.slice(1)}
                </span>
              </div>

              {version.change_summary && (
                <div style={{ fontSize: "12px", color: "var(--color-text-secondary)", paddingLeft: "28px" }}>
                  {version.change_summary}
                </div>
              )}

              {/* Expanded Details */}
              {expandedVersion === version.id && (
                <div style={{ marginTop: "12px", paddingTop: "12px", borderTop: "1px solid var(--color-border-tertiary)", paddingLeft: "28px" }}>
                  <div style={{ display: "flex", gap: "8px" }}>
                    <button
                      onClick={(e) => { e.stopPropagation(); onRestore(version.id); }}
                      style={{
                        padding: "6px 12px",
                        fontSize: "11px",
                        background: "var(--color-background-success)",
                        color: "var(--color-text-success)",
                        border: "0.5px solid var(--color-border-success)",
                        borderRadius: "4px",
                        cursor: "pointer",
                      }}
                    >
                      🔄 Restore This Version
                    </button>
                    {index > 0 && (
                      <button
                        onClick={(e) => { e.stopPropagation(); onCompare(version.id, versions[index - 1].id); }}
                        style={{
                          padding: "6px 12px",
                          fontSize: "11px",
                          background: "var(--color-background-info)",
                          color: "var(--color-text-info)",
                          border: "0.5px solid var(--color-border-info)",
                          borderRadius: "4px",
                          cursor: "pointer",
                        }}
                      >
                        🔍 Compare with Previous
                      </button>
                    )}
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
