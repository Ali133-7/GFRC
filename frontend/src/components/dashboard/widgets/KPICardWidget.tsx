import React from 'react';
import { formatNumber } from '@/utils/formatNumber';
import { formatCurrency } from '@/utils/formatCurrency';
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
 * KPI Card Widget
 * Displays a single metric or KPI value
 */
export function KPICardWidget({
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
  const color = displayConfig.color || 'blue';
  const prefix = displayConfig.prefix || '';
  const suffix = displayConfig.suffix || '';

  const colorClasses = {
    blue: 'bg-blue-50 border-blue-200 text-blue-900',
    green: 'bg-emerald-50 border-emerald-200 text-emerald-900',
    amber: 'bg-amber-50 border-amber-200 text-amber-900',
    red: 'bg-red-50 border-red-200 text-red-900',
    purple: 'bg-purple-50 border-purple-200 text-purple-900',
    gray: 'bg-gray-50 border-gray-200 text-gray-900',
  };

  const valueColorClasses = {
    blue: 'text-blue-600',
    green: 'text-emerald-600',
    amber: 'text-amber-600',
    red: 'text-red-600',
    purple: 'text-purple-600',
    gray: 'text-gray-600',
  };

  const value = data?.data?.total ?? data?.data?.count ?? data?.data?.value ?? 0;
  const formattedValue = displayConfig.format === 'currency' 
    ? formatCurrency(value) 
    : formatNumber(value);

  return (
    <div className={`relative p-4 rounded-lg border ${colorClasses[color as keyof typeof colorClasses]}`}>
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onRefresh && (
            <button
              onClick={onRefresh}
              className="p-1 hover:bg-white/50 rounded"
              title="تحديث"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
          )}
          {onEdit && canEdit && (
            <button
              onClick={onEdit}
              className="p-1 hover:bg-white/50 rounded"
              title="تعديل"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
          )}
          {onRemove && canEdit && (
            <button
              onClick={onRemove}
              className="p-1 hover:bg-red-100 rounded text-red-600"
              title="إزالة"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          )}
        </div>
      )}

      {isLoading ? (
        <div className="animate-pulse">
          <div className="h-4 bg-current opacity-20 rounded w-24 mb-2"></div>
          <div className="h-8 bg-current opacity-20 rounded w-32"></div>
        </div>
      ) : (
        <>
          <div className="text-sm font-medium opacity-75 mb-2">{title}</div>
          <div className={`text-2xl font-bold ${valueColorClasses[color as keyof typeof valueColorClasses]}`}>
            {prefix}{formattedValue}{suffix}
          </div>
          {data?.data?.count !== undefined && data?.data?.total !== undefined && (
            <div className="text-xs opacity-60 mt-2">
              {formatNumber(data.data.count)} عملية
            </div>
          )}
        </>
      )}
    </div>
  );
}
