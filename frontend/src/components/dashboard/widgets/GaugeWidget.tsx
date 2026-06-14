import { useWidgetData } from '../hooks/useWidgetData';
import type { DashboardWidgetItem } from '../types';
import { getWidgetTitle } from '../types';

interface GaugeWidgetProps {
  widget: DashboardWidgetItem;
}

export default function GaugeWidget({ widget }: GaugeWidgetProps) {
  const { data, isLoading } = useWidgetData(widget);
  const value = Number(data?.value ?? data?.data?.value ?? 0);
  const target = Number(widget.data_source?.target ?? data?.target ?? data?.data?.target ?? 100);
  const percentage = Math.min(100, Math.max(0, target ? (value / target) * 100 : 0));

  const radius = 36;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (percentage / 100) * circumference;

  return (
    <div className="flex h-full w-full flex-col items-center justify-center p-4">
      <div className="relative h-32 w-32">
        <svg className="h-full w-full -rotate-90" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r={radius} fill="none" stroke="#e5e7eb" strokeWidth="10" />
          {!isLoading && (
            <circle
              cx="50"
              cy="50"
              r={radius}
              fill="none"
              stroke="#2563eb"
              strokeWidth="10"
              strokeDasharray={circumference}
              strokeDashoffset={offset}
              strokeLinecap="round"
              className="transition-all duration-500"
            />
          )}
        </svg>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-xl font-bold text-gray-900">{isLoading ? '-' : `${Math.round(percentage)}%`}</span>
        </div>
      </div>
      <div className="mt-2 text-sm text-gray-600">
        {getWidgetTitle(widget)}
      </div>
    </div>
  );
}
