import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { useWidgetData } from '../hooks/useWidgetData';
import type { DashboardWidgetItem } from '../types';

interface ChartPieWidgetProps {
  widget: DashboardWidgetItem;
}

const DEFAULT_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#9333ea', '#0891b2'];

export default function ChartPieWidget({ widget }: ChartPieWidgetProps) {
  const { data, isLoading } = useWidgetData(widget);
  const labels = Array.isArray(data?.labels) ? data.labels : Array.isArray(data?.data?.labels) ? data.data.labels : [];
  const values = Array.isArray(data?.values) ? data.values : Array.isArray(data?.data?.values) ? data.data.values : [];

  const items = labels.map((label: string, index: number) => ({
    name: label,
    value: values[index] ?? 0,
  }));

  const chartColors = Array.isArray(widget.display_config?.chart_colors)
    ? widget.display_config.chart_colors
    : DEFAULT_COLORS;
  const showLegend = widget.display_config?.show_legend !== false;

  if (isLoading) {
    return (
      <div className="flex h-full w-full items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="flex h-full w-full items-center justify-center text-sm text-gray-500">
        لا توجد بيانات
      </div>
    );
  }

  return (
    <div className="h-full w-full p-2">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={items}
            dataKey="value"
            nameKey="name"
            outerRadius="80%"
            label
          >
            {items.map((_: unknown, index: number) => (
              <Cell key={`cell-${index}`} fill={chartColors[index % chartColors.length]} />
            ))}
          </Pie>
          <Tooltip />
          {showLegend && <Legend />}
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}
