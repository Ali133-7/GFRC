import React from 'react';
import type { DashboardWidget } from '@/types/dashboard';

interface WidgetProps {
  widget: DashboardWidget;
  data?: any;
  isLoading?: boolean;
  onRefresh?: () => void;
  onEdit?: () => void;
  onRemove?: () => void;
  canEdit?: boolean;
}

/**
 * Chart Widget
 * Displays data as a chart (placeholder for now)
 */
export function ChartWidget({
  widget,
  data,
  isLoading = false,
  onRefresh,
  onEdit,
  onRemove,
  canEdit = false,
}: WidgetProps) {
  const displayConfig = widget.display_config ?? {};
  const title = displayConfig.title || widget.name_ar;
  const chartType = displayConfig.chart_type || 'bar';

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onRefresh && (
            <button onClick={onRefresh} className="p-1 hover:bg-gray-100 rounded" title="تحديث">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
          )}
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded" title="تعديل">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600" title="إزالة">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          )}
        </div>
      )}

      <div className="text-sm font-medium text-gray-700 mb-3">{title}</div>

      {isLoading ? (
        <div className="animate-pulse h-48 bg-gray-100 rounded"></div>
      ) : data?.data ? (
        <div className="h-48 flex items-center justify-center text-gray-400">
          Chart: {chartType} (Data: {JSON.stringify(data.data)})
        </div>
      ) : (
        <div className="h-48 flex items-center justify-center text-gray-400">
          No data available
        </div>
      )}
    </div>
  );
}
