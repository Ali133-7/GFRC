import React, { useState } from "react";
import type { ReportSchedule } from "@/types/report";

interface ScheduleConfigProps {
  schedule: ReportSchedule | null;
  onChange: (schedule: ReportSchedule) => void;
}

export function ScheduleConfig({ schedule, onChange }: ScheduleConfigProps) {
  const [localSchedule, setLocalSchedule] = useState<ReportSchedule>(schedule || {
    enabled: false,
    frequency: "daily",
    cron: "",
    recipients: [],
    format: "pdf",
    delivery: "email",
  });

  const [newRecipient, setNewRecipient] = useState("");

  const handleUpdate = (updates: Partial<ReportSchedule>) => {
    const updated = { ...localSchedule, ...updates };
    setLocalSchedule(updated);
    onChange(updated);
  };

  const handleAddRecipient = () => {
    if (newRecipient && newRecipient.includes("@")) {
      handleUpdate({ recipients: [...localSchedule.recipients, newRecipient] });
      setNewRecipient("");
    }
  };

  const handleRemoveRecipient = (index: number) => {
    handleUpdate({ recipients: localSchedule.recipients.filter((_, i) => i !== index) });
  };

  const frequencyOptions = [
    { value: "daily", label: "📅 Daily", description: "Every day at specified time" },
    { value: "weekly", label: "📆 Weekly", description: "Every week on selected day" },
    { value: "monthly", label: "🗓️ Monthly", description: "Every month on selected date" },
    { value: "custom", label: "⚙️ Custom (Cron)", description: "Custom cron expression" },
  ];

  const formatOptions = [
    { value: "pdf", label: "📄 PDF", description: "Portable Document Format" },
    { value: "excel", label: "📊 Excel", description: "Microsoft Excel Workbook" },
    { value: "csv", label: "📋 CSV", description: "Comma-Separated Values" },
  ];

  const deliveryOptions = [
    { value: "email", label: "📧 Email", description: "Send via email" },
    { value: "notification", label: "🔔 Notification", description: "System notification" },
    { value: "dashboard", label: "📊 Dashboard", description: "Save to dashboard" },
  ];

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        ⏰ Report Scheduling
      </h3>

      {/* Enable/Disable Toggle */}
      <div style={{ padding: "12px", background: localSchedule.enabled ? "var(--color-background-success)" : "var(--color-background-secondary)", borderRadius: "8px", marginBottom: "20px" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div>
            <div style={{ fontSize: "13px", fontWeight: 600, color: localSchedule.enabled ? "var(--color-text-success)" : "var(--color-text-secondary)" }}>
              {localSchedule.enabled ? "✅ Scheduling Enabled" : "⏸️ Scheduling Disabled"}
            </div>
            <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
              {localSchedule.enabled ? "Report will be generated automatically" : "Enable to configure automatic report generation"}
            </div>
          </div>
          <button
            onClick={() => handleUpdate({ enabled: !localSchedule.enabled })}
            style={{
              padding: "8px 16px",
              fontSize: "12px",
              fontWeight: 600,
              background: localSchedule.enabled ? "var(--color-background-danger)" : "var(--color-background-success)",
              color: localSchedule.enabled ? "var(--color-text-danger)" : "var(--color-text-success)",
              border: "0.5px solid var(--color-border-secondary)",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            {localSchedule.enabled ? "Disable" : "Enable"}
          </button>
        </div>
      </div>

      {localSchedule.enabled && (
        <>
          {/* Frequency */}
          <div style={{ marginBottom: "20px" }}>
            <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
              📆 Frequency
            </h4>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: "8px" }}>
              {frequencyOptions.map(opt => (
                <button
                  key={opt.value}
                  onClick={() => handleUpdate({ frequency: opt.value as any })}
                  style={{
                    padding: "12px",
                    background: localSchedule.frequency === opt.value ? "var(--color-background-info)" : "var(--color-background-secondary)",
                    border: localSchedule.frequency === opt.value ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
                    borderRadius: "6px",
                    cursor: "pointer",
                    textAlign: "left",
                  }}
                >
                  <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                    {opt.label}
                  </div>
                  <div style={{ fontSize: "10px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
                    {opt.description}
                  </div>
                </button>
              ))}
            </div>

            {localSchedule.frequency === "custom" && (
              <div style={{ marginTop: "12px" }}>
                <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                  Cron Expression
                </label>
                <input
                  type="text"
                  value={localSchedule.cron || ""}
                  onChange={(e) => handleUpdate({ cron: e.target.value })}
                  placeholder="e.g., 0 9 * * 1 (Every Monday at 9 AM)"
                  style={{ width: "100%", padding: "8px 12px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", fontFamily: "var(--font-mono)" }}
                />
              </div>
            )}
          </div>

          {/* Format */}
          <div style={{ marginBottom: "20px" }}>
            <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
              📄 Output Format
            </h4>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "8px" }}>
              {formatOptions.map(opt => (
                <button
                  key={opt.value}
                  onClick={() => handleUpdate({ format: opt.value as any })}
                  style={{
                    padding: "12px",
                    background: localSchedule.format === opt.value ? "var(--color-background-info)" : "var(--color-background-secondary)",
                    border: localSchedule.format === opt.value ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
                    borderRadius: "6px",
                    cursor: "pointer",
                    textAlign: "left",
                  }}
                >
                  <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                    {opt.label}
                  </div>
                  <div style={{ fontSize: "10px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
                    {opt.description}
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Delivery */}
          <div style={{ marginBottom: "20px" }}>
            <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
              📤 Delivery Method
            </h4>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "8px" }}>
              {deliveryOptions.map(opt => (
                <button
                  key={opt.value}
                  onClick={() => handleUpdate({ delivery: opt.value as any })}
                  style={{
                    padding: "12px",
                    background: localSchedule.delivery === opt.value ? "var(--color-background-info)" : "var(--color-background-secondary)",
                    border: localSchedule.delivery === opt.value ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
                    borderRadius: "6px",
                    cursor: "pointer",
                    textAlign: "left",
                  }}
                >
                  <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                    {opt.label}
                  </div>
                  <div style={{ fontSize: "10px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
                    {opt.description}
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Recipients */}
          {localSchedule.delivery === "email" && (
            <div style={{ marginBottom: "20px" }}>
              <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
                📧 Email Recipients
              </h4>
              <div style={{ display: "flex", gap: "8px", marginBottom: "12px" }}>
                <input
                  type="email"
                  value={newRecipient}
                  onChange={(e) => setNewRecipient(e.target.value)}
                  placeholder="email@example.com"
                  style={{ flex: 1, padding: "8px 12px", fontSize: "12px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px" }}
                />
                <button
                  onClick={handleAddRecipient}
                  style={{
                    padding: "8px 16px",
                    fontSize: "12px",
                    background: "var(--color-background-info)",
                    color: "var(--color-text-info)",
                    border: "0.5px solid var(--color-border-info)",
                    borderRadius: "6px",
                    cursor: "pointer",
                  }}
                >
                  + Add
                </button>
              </div>
              {localSchedule.recipients.length > 0 && (
                <div style={{ display: "grid", gap: "6px" }}>
                  {localSchedule.recipients.map((email, index) => (
                    <div
                      key={index}
                      style={{
                        padding: "8px 12px",
                        background: "var(--color-background-secondary)",
                        borderRadius: "6px",
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                      }}
                    >
                      <span style={{ fontSize: "12px", color: "var(--color-text-primary)" }}>{email}</span>
                      <button
                        onClick={() => handleRemoveRecipient(index)}
                        style={{
                          background: "none",
                          border: "none",
                          cursor: "pointer",
                          fontSize: "16px",
                          color: "var(--color-text-danger)",
                        }}
                      >
                        ×
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Summary */}
          <div style={{ padding: "12px", background: "var(--color-background-secondary)", borderRadius: "8px" }}>
            <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "8px", color: "var(--color-text-secondary)" }}>
              📋 Schedule Summary
            </h4>
            <div style={{ fontSize: "12px", color: "var(--color-text-primary)", lineHeight: 1.8 }}>
              <div>📆 Frequency: <strong>{frequencyOptions.find(f => f.value === localSchedule.frequency)?.label}</strong></div>
              <div>📄 Format: <strong>{formatOptions.find(f => f.value === localSchedule.format)?.label}</strong></div>
              <div>📤 Delivery: <strong>{deliveryOptions.find(d => d.value === localSchedule.delivery)?.label}</strong></div>
              {localSchedule.recipients.length > 0 && (
                <div>📧 Recipients: <strong>{localSchedule.recipients.length} email(s)</strong></div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
