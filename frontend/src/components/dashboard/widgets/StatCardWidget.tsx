import React from 'react';
import type { DashboardWidget } from '@/types/dashboard';
import { formatCurrency, formatNumber } from '@/utils/formatNumber';

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
 * Stat Card Widget
 * Displays a statistic with trend comparison
 */
export function StatCardWidget({
  widget,
  data,
  isLoading = false,
  onRefresh,
  onEdit,
  onRemove,
  canEdit = false,
}: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || widget.name_ar;
  const color = displayConfig.color || 'blue';
  const comparisonPeriod = displayConfig.comparison_period || 'previous';

  const value = data?.data?.value || data?.data?.count || 0;
  const previousValue = data?.data?.previous_value || 0;
  const trend = previousValue > 0 ? ((value - previousValue) / previousValue) * 100 : 0;
  const isPositive = trend >= 0;

  const colorClasses = {
    blue: 'bg-blue-50 border-blue-200 text-blue-900',
    green: 'bg-emerald-50 border-emerald-200 text-emerald-900',
    amber: 'bg-amber-50 border-amber-200 text-amber-900',
    red: 'bg-red-50 border-red-200 text-red-900',
    purple: 'bg-purple-50 border-purple-200 text-purple-900',
  };

  return (
    <div className={`relative p-4 rounded-lg border ${colorClasses[color as keyof typeof colorClasses]}`}>
      {/* Controls */}
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onRefresh && (
            <button onClick={onRefresh} className="p-1 hover:bg-white/50 rounded" title="تحديث">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
          )}
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/50 rounded" title="تعديل">
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

      {isLoading ? (
        <div className="animate-pulse">
          <div className="h-4 bg-current opacity-20 rounded w-24 mb-2"></div>
          <div className="h-8 bg-current opacity-20 rounded w-32"></div>
        </div>
      ) : (
        <>
          <div className="text-sm font-medium opacity-75 mb-2">{title}</div>
          <div className="text-3xl font-bold mb-2">{formatNumber(value)}</div>
          
          {displayConfig.show_trend !== false && previousValue > 0 && (
            <div className={`text-xs flex items-center gap-1 ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
              <svg className={`w-3 h-3 ${isPositive ? 'transform rotate-180' : ''}`} fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
              </svg>
              <span>{Math.abs(trend).toFixed(1)}%</span>
              <span className="opacity-75">مقارنة بالفترة السابقة</span>
            </div>
          )}
        </>
      )}
    </div>
  );
}

/**
 * Gauge Widget
 * Displays a gauge/meter chart
 */
export function GaugeWidget({
  widget,
  data,
  isLoading = false,
  canEdit = false,
  onEdit,
  onRemove,
}: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || widget.name_ar;
  const minValue = displayConfig.min_value || 0;
  const maxValue = displayConfig.max_value || 100;
  const targetValue = displayConfig.target_value;
  
  const value = data?.data?.value || 0;
  const percentage = Math.min(100, Math.max(0, ((value - minValue) / (maxValue - minValue)) * 100));
  
  const rotation = (percentage / 100) * 180 - 90;

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {/* Controls */}
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
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

      {isLoading ? (
        <div className="animate-pulse h-40 flex items-center justify-center">
          <div className="h-20 w-20 bg-gray-200 rounded-full"></div>
        </div>
      ) : (
        <>
          <div className="text-sm font-medium text-gray-700 mb-4 text-center">{title}</div>
          
          {/* Gauge */}
          <div className="relative h-40 flex items-end justify-center">
            <div className="relative w-40 h-20 overflow-hidden">
              {/* Background arc */}
              <div className="absolute bottom-0 left-0 w-40 h-40 rounded-full border-8 border-gray-200" style={{ clipPath: 'polygon(0 50%, 100% 50%, 100% 0, 0 0)' }}></div>
              
              {/* Value arc */}
              <div 
                className="absolute bottom-0 left-0 w-40 h-40 rounded-full border-8 border-blue-600 transition-all duration-500"
                style={{ 
                  clipPath: 'polygon(0 50%, 100% 50%, 100% 0, 0 0)',
                  transform: `rotate(${rotation}deg)`,
                  transformOrigin: 'center bottom'
                }}
              ></div>
              
              {/* Center circle */}
              <div className="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 w-4 h-4 bg-white rounded-full border-2 border-gray-300"></div>
            </div>
          </div>
          
          {/* Value display */}
          <div className="text-center mt-4">
            <div className="text-3xl font-bold text-gray-900">{value}</div>
            <div className="text-sm text-gray-500">من {maxValue}</div>
            
            {targetValue && (
              <div className={`text-xs mt-2 ${value >= targetValue ? 'text-green-600' : 'text-amber-600'}`}>
                الهدف: {targetValue} {value >= targetValue ? '✓ تم تحقيقه' : '⏳ قيد التحقيق'}
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}
