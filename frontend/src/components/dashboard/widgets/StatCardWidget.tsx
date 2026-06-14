import { useWidgetData } from '../hooks/useWidgetData';
import type { DashboardWidgetItem } from '../types';
import { formatCurrency, formatNumber } from '@/utils/formatNumber';
import { getColorClass, getTextColorClass } from '../widgetDefaults';

interface StatCardWidgetProps {
  widget: DashboardWidgetItem;
}

export default function StatCardWidget({ widget }: StatCardWidgetProps) {
  const { data, isLoading } = useWidgetData(widget);

  const rawValue = data?.value ?? data?.data?.value ?? 0;
  const format = widget.display_config?.format as string | undefined;

  const formattedValue =
    format === 'currency' ? formatCurrency(rawValue) : formatNumber(rawValue);

  const colorClass = getColorClass(widget.color_theme);
  const textColorClass = getTextColorClass(widget.color_theme);

  return (
    <div className="flex flex-col items-center justify-center h-full w-full p-4 text-center">
      <div className={`mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full text-white ${colorClass}`}>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M12 2v20M2 12h20" />
        </svg>
      </div>
      {isLoading ? (
        <div className="h-8 w-24 animate-pulse rounded bg-gray-200" />
      ) : (
        <div className={`text-3xl font-bold ${textColorClass}`}>{formattedValue}</div>
      )}
      <div className="mt-1 text-sm text-gray-600">{widget.data_source?.aggregation || ''}</div>
    </div>
  );
}
