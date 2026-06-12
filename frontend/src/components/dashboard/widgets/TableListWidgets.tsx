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
 * Table Widget
 * Displays data in a table format
 */
export function TableWidget({
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
  const columns = displayConfig.columns || [];

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
        <div className="animate-pulse space-y-2">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-8 bg-gray-100 rounded"></div>
          ))}
        </div>
      ) : data?.data?.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200">
                {columns.map((col: any, idx: number) => (
                  <th key={idx} className="text-right py-2 px-3 font-medium text-gray-600">
                    {col.label_ar || col.label || col.field}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.data.slice(0, displayConfig.limit || 10).map((row: any, rowIdx: number) => (
                <tr key={rowIdx} className="border-b border-gray-100 hover:bg-gray-50">
                  {columns.map((col: any, colIdx: number) => (
                    <td key={colIdx} className="py-2 px-3 text-gray-700">
                      {formatCellValue(row[col.field], col.format)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="h-32 flex items-center justify-center text-gray-400">
          لا توجد بيانات
        </div>
      )}
    </div>
  );
}

/**
 * List Widget
 * Displays a list of items
 */
export function ListWidget({
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
        <div className="animate-pulse space-y-2">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-10 bg-gray-100 rounded"></div>
          ))}
        </div>
      ) : data?.data?.length > 0 ? (
        <div className="space-y-2">
          {data.data.slice(0, displayConfig.limit || 10).map((item: any, idx: number) => (
            <div key={idx} className="p-2 bg-gray-50 rounded hover:bg-gray-100">
              <div className="text-sm font-medium text-gray-900">{item.title || item.name || item.name_ar}</div>
              <div className="text-xs text-gray-500">{item.subtitle || item.description}</div>
            </div>
          ))}
        </div>
      ) : (
        <div className="h-32 flex items-center justify-center text-gray-400">
          لا توجد بيانات
        </div>
      )}
    </div>
  );
}

/**
 * Notes Widget
 * Personal notes widget
 */
export function NotesWidget({
  widget,
  isLoading = false,
  onEdit,
  onRemove,
  canEdit = false,
}: WidgetProps) {
  const displayConfig = widget.display_config ?? {};
  const title = displayConfig.title || 'ملاحظات';

  return (
    <div className="relative p-4 bg-amber-50 rounded-lg border border-amber-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
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

      <div className="text-sm font-medium text-amber-900 mb-2">{title}</div>
      <textarea
        className="w-full bg-white/50 border-0 rounded p-2 text-sm text-gray-700 focus:ring-1 focus:ring-amber-300"
        rows={4}
        placeholder="اكتب ملاحظاتك هنا..."
        readOnly={!canEdit}
      />
    </div>
  );
}

/**
 * Shortcuts Widget
 * Quick access shortcuts
 */
export function ShortcutsWidget({
  widget,
  onEdit,
  onRemove,
  canEdit = false,
}: WidgetProps) {
  const displayConfig = widget.display_config ?? {};
  const title = displayConfig.title || 'اختصارات';
  const links = displayConfig.links || [];

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
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

      <div className="text-sm font-medium text-gray-700 mb-3">{title}</div>

      <div className="grid grid-cols-2 gap-2">
        {links.map((link: any, idx: number) => (
          <a
            key={idx}
            href={link.url}
            className="p-2 bg-gray-50 rounded hover:bg-gray-100 text-center text-sm text-gray-700 transition-colors"
          >
            <div className="text-lg mb-1">{link.icon || '📌'}</div>
            <div>{link.label_ar || link.label}</div>
          </a>
        ))}
        {links.length === 0 && (
          <div className="col-span-2 text-center text-gray-400 py-4">
            لا توجد اختصارات
          </div>
        )}
      </div>
    </div>
  );
}

function formatCellValue(value: any, format?: string): string {
  if (value === null || value === undefined) return '—';
  
  if (format === 'currency') {
    return formatCurrency(value);
  }
  
  if (format === 'number') {
    return formatNumber(value);
  }
  
  return String(value);
}
