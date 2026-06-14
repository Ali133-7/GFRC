import { useWidgetData } from '../hooks/useWidgetData';
import type { DashboardWidgetItem } from '../types';
import { getWidgetTitle } from '../types';

interface ProgressWidgetProps {
  widget: DashboardWidgetItem;
}

export default function ProgressWidget({ widget }: ProgressWidgetProps) {
  const { data, isLoading } = useWidgetData(widget);
  const value = Number(data?.value ?? data?.data?.value ?? 0);
  const target = Number(widget.data_source?.target ?? data?.target ?? data?.data?.target ?? 100);
  const percentage = Math.min(100, Math.max(0, target ? (value / target) * 100 : 0));

  return (
    <div className="flex h-full w-full flex-col justify-center p-4">
      <div className="mb-2 flex items-center justify-between text-sm">
        <span className="font-medium text-gray-700">{getWidgetTitle(widget)}</span>
        <span className="text-gray-600">
          {isLoading ? '-' : `${Math.round(percentage)}%`}
        </span>
      </div>
      <div className="h-4 w-full overflow-hidden rounded-full bg-gray-200">
        <div
          className="h-full rounded-full bg-blue-600 transition-all duration-500"
          style={{ width: `${percentage}%` }}
        />
      </div>
      {!isLoading && (
        <div className="mt-2 text-right text-xs text-gray-500">
          {value} / {target}
        </div>
      )}
    </div>
  );
}
