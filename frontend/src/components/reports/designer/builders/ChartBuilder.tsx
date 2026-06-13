import React, { useState, useEffect, useRef } from "react";
import * as echarts from "echarts";
import type { ChartConfig, ReportField } from "@/types/report";

interface ChartBuilderProps {
  charts: ChartConfig[];
  onChange: (charts: ChartConfig[]) => void;
  availableFields: ReportField[];
}

export function ChartBuilder({ charts, onChange, availableFields }: ChartBuilderProps) {
  const chartRef = useRef<HTMLDivElement>(null);
  const [selectedChart, setSelectedChart] = useState<string | null>(null);
  const [editingConfig, setEditingConfig] = useState<ChartConfig | null>(null);

  const chartTypes = [
    { value: "bar", label: "📊 Bar", icon: "📊" },
    { value: "column", label: "📶 Column", icon: "📶" },
    { value: "line", label: "📈 Line", icon: "📈" },
    { value: "area", label: "📉 Area", icon: "📉" },
    { value: "pie", label: "🥧 Pie", icon: "🥧" },
    { value: "donut", label: "🍩 Donut", icon: "🍩" },
    { value: "scatter", label: "⚬ Scatter", icon: "⚬" },
    { value: "radar", label: "🕸️ Radar", icon: "🕸️" },
    { value: "treemap", label: "🗺️ Treemap", icon: "🗺️" },
    { value: "funnel", label: "🔻 Funnel", icon: "🔻" },
  ];

  const handleAddChart = () => {
    const x = availableFields[0];
    const y = availableFields[1] || availableFields[0];
    const newChart: ChartConfig = {
      id: `chart_${Date.now()}`,
      type: "bar",
      title: "New Chart",
      xAxis: x?.name || "",
      yAxis: y?.name || "",
      series: [{ name: "Series 1", field: y?.name || "", aggregation: "SUM" }],
      colors: ["#5470c6", "#91cc75", "#fac858", "#ee6666"],
    };
    onChange([...charts, newChart]);
    setSelectedChart(newChart.id);
    setEditingConfig(newChart);
  };

  const handleUpdateChart = (id: string, updates: Partial<ChartConfig>) => {
    const updated = charts.map(c => c.id === id ? { ...c, ...updates } : c);
    onChange(updated);
    if (editingConfig?.id === id) {
      setEditingConfig({ ...editingConfig, ...updates });
    }
  };

  const handleDeleteChart = (id: string) => {
    onChange(charts.filter(c => c.id !== id));
    if (selectedChart === id) {
      setSelectedChart(null);
      setEditingConfig(null);
    }
  };

  // Render chart preview
  useEffect(() => {
    if (chartRef.current && editingConfig) {
      const chart = echarts.init(chartRef.current);
      
      const option = {
        title: { text: editingConfig.title, left: "center" },
        tooltip: { trigger: "axis" as const },
        legend: { bottom: 0 },
        xAxis: {
          type: "category" as const,
          data: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
        },
        yAxis: { type: "value" as const },
        series: editingConfig.series.map(s => ({
          name: s.name,
          type: editingConfig.type === "column" ? "bar" : editingConfig.type,
          data: [120, 200, 150, 80, 70, 110],
        })),
        color: editingConfig.colors,
      };

      chart.setOption(option);
      
      return () => chart.dispose();
    }
  }, [editingConfig]);

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        📊 Chart Builder
      </h3>

      {/* Add Chart Button */}
      <button
        onClick={handleAddChart}
        style={{
          padding: "6px 12px",
          fontSize: "12px",
          background: "var(--color-background-info)",
          color: "var(--color-text-info)",
          border: "0.5px solid var(--color-border-info)",
          borderRadius: "4px",
          cursor: "pointer",
          marginBottom: "16px",
        }}
      >
        + Add Chart
      </button>

      {/* Charts List */}
      <div style={{ display: "grid", gap: "8px", marginBottom: "20px" }}>
        {charts.map(chart => (
          <div
            key={chart.id}
            onClick={() => { setSelectedChart(chart.id); setEditingConfig(chart); }}
            style={{
              padding: "10px",
              background: selectedChart === chart.id ? "var(--color-background-info)" : "var(--color-background-secondary)",
              border: selectedChart === chart.id ? "1px solid var(--color-border-info)" : "1px solid var(--color-border-tertiary)",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                {chartTypes.find(t => t.value === chart.type)?.icon} {chart.title}
              </div>
              <button
                onClick={(e) => { e.stopPropagation(); handleDeleteChart(chart.id); }}
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
            <div style={{ fontSize: "10px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
              {chart.xAxis} → {chart.yAxis}
            </div>
          </div>
        ))}
      </div>

      {/* Chart Configuration */}
      {editingConfig && (
        <div style={{ padding: "16px", background: "var(--color-background-primary)", border: "1px solid var(--color-border-tertiary)", borderRadius: "6px", marginBottom: "20px" }}>
          <h4 style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
            Configure Chart
          </h4>

          {/* Chart Type */}
          <div style={{ marginBottom: "12px" }}>
            <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
              Chart Type
            </label>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(5, 1fr)", gap: "6px" }}>
              {chartTypes.map(type => (
                <button
                  key={type.value}
                  onClick={() => handleUpdateChart(editingConfig.id, { type: type.value as any })}
                  style={{
                    padding: "6px",
                    fontSize: "10px",
                    background: editingConfig.type === type.value ? "var(--color-background-info)" : "var(--color-background-secondary)",
                    color: editingConfig.type === type.value ? "var(--color-text-info)" : "var(--color-text-secondary)",
                    border: "0.5px solid var(--color-border-secondary)",
                    borderRadius: "4px",
                    cursor: "pointer",
                  }}
                >
                  {type.icon} {type.label.split(" ")[1]}
                </button>
              ))}
            </div>
          </div>

          {/* Title */}
          <div style={{ marginBottom: "12px" }}>
            <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
              Title
            </label>
            <input
              type="text"
              value={editingConfig.title}
              onChange={(e) => handleUpdateChart(editingConfig.id, { title: e.target.value })}
              style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
            />
          </div>

          {/* X and Y Axis */}
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px", marginBottom: "12px" }}>
            <div>
              <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                X-Axis
              </label>
              <select
                value={editingConfig.xAxis}
                onChange={(e) => handleUpdateChart(editingConfig.id, { xAxis: e.target.value })}
                style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
              >
                {availableFields.map((f) => <option key={f.name} value={f.name}>{f.label || f.name}</option>)}
              </select>
            </div>
            <div>
              <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                Y-Axis
              </label>
              <select
                value={editingConfig.yAxis}
                onChange={(e) => handleUpdateChart(editingConfig.id, { yAxis: e.target.value })}
                style={{ width: "100%", padding: "6px 8px", fontSize: "11px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px" }}
              >
                {availableFields.map((f) => <option key={f.name} value={f.name}>{f.label || f.name}</option>)}
              </select>
            </div>
          </div>

          {/* Colors */}
          <div style={{ marginBottom: "12px" }}>
            <label style={{ fontSize: "11px", fontWeight: 600, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
              Colors
            </label>
            <div style={{ display: "flex", gap: "6px" }}>
              {editingConfig.colors?.map((color, index) => (
                <input
                  key={index}
                  type="color"
                  value={color}
                  onChange={(e) => {
                    const newColors = [...(editingConfig.colors || [])];
                    newColors[index] = e.target.value;
                    handleUpdateChart(editingConfig.id, { colors: newColors });
                  }}
                  style={{ width: "30px", height: "30px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "4px", cursor: "pointer" }}
                />
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Chart Preview */}
      {editingConfig && (
        <div style={{ padding: "16px", background: "var(--color-background-secondary)", borderRadius: "6px" }}>
          <div style={{ fontSize: "12px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-secondary)" }}>
            📈 Chart Preview
          </div>
          <div
            ref={chartRef}
            style={{ width: "100%", height: "300px", background: "var(--color-background-primary)", borderRadius: "4px" }}
          />
        </div>
      )}
    </div>
  );
}
