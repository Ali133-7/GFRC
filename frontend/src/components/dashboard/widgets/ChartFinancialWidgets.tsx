// Chart & Financial Widgets
import React from 'react';
import type { DashboardWidget } from '@/types/dashboard';
import { formatCurrency } from '@/utils/formatNumber';

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
 * Chart Widget (Generic)
 */
export function ChartWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || widget.name_ar;
  const chartType = displayConfig.chart_type || 'bar';

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="h-48 animate-pulse bg-gray-100 rounded"></div>
      ) : data?.data ? (
        <div className="h-48 flex items-center justify-center text-gray-400">
          📊 Chart: {chartType} (Data: {JSON.stringify(data.data)})
        </div>
      ) : (
        <div className="h-48 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-4xl mb-2">📊</div>
            <div className="text-sm">لا توجد بيانات</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Pie Chart Widget
 */
export function PieChartWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || widget.name_ar;
  const showPercentages = displayConfig.show_percentages ?? true;

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="h-48 animate-pulse bg-gray-100 rounded-full"></div>
      ) : (
        <div className="h-48 flex items-center justify-center">
          <div className="text-center text-gray-400">
            <div className="text-4xl mb-2">🥧</div>
            <div className="text-sm">رسم دائري</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Revenue Chart Widget
 */
export function RevenueChartWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'الإيرادات';
  const chartType = displayConfig.chart_type || 'bar';
  const groupBy = displayConfig.group_by || 'day';

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="h-48 animate-pulse bg-gray-100 rounded"></div>
      ) : data?.data ? (
        <div className="space-y-2">
          {Array.isArray(data.data) && data.data.map((item: any, idx: number) => (
            <div key={idx} className="flex items-center justify-between">
              <div className="text-sm text-gray-700">{item.label || item.date}</div>
              <div className="font-bold text-green-600">{formatCurrency(item.amount || item.value)}</div>
            </div>
          ))}
        </div>
      ) : (
        <div className="h-48 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-4xl mb-2">💰</div>
            <div className="text-sm">لا توجد بيانات إيرادات</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Fee Breakdown Widget
 */
export function FeeBreakdownWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'تفصيل الرسوم';
  const showPercentages = displayConfig.show_percentages ?? true;

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="space-y-2">
          {[1, 2, 3].map(i => (
            <div key={i} className="h-8 animate-pulse bg-gray-100 rounded"></div>
          ))}
        </div>
      ) : data?.data ? (
        <div className="space-y-3">
          {Array.isArray(data.data) && data.data.map((fee: any, idx: number) => (
            <div key={idx} className="flex items-center justify-between">
              <div className="text-sm text-gray-700">{fee.name || fee.fee_name}</div>
              <div className="font-bold text-gray-900">{formatCurrency(fee.amount)}</div>
            </div>
          ))}
        </div>
      ) : (
        <div className="h-32 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-3xl mb-2">🧾</div>
            <div className="text-sm">لا توجد رسوم</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Workflow Status Widget
 */
export function WorkflowStatusWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'حالة سير العمل';

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="space-y-2">
          {[1, 2, 3].map(i => (
            <div key={i} className="h-8 animate-pulse bg-gray-100 rounded"></div>
          ))}
        </div>
      ) : data?.data ? (
        <div className="space-y-2">
          {Array.isArray(data.data) && data.data.map((status: any, idx: number) => (
            <div key={idx} className="flex items-center justify-between p-2 bg-gray-50 rounded">
              <div className="text-sm text-gray-700">{status.name || status.workflow_name}</div>
              <span className={`px-2 py-1 text-xs rounded ${
                status.status === 'completed' ? 'bg-green-100 text-green-800' :
                status.status === 'pending' ? 'bg-amber-100 text-amber-800' :
                'bg-gray-100 text-gray-800'
              }`}>
                {status.status || 'unknown'}
              </span>
            </div>
          ))}
        </div>
      ) : (
        <div className="h-32 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-3xl mb-2">🔄</div>
            <div className="text-sm">لا توجد سير عمل</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Task List Widget
 */
export function TaskListWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'المهام';
  const limit = displayConfig.limit || 10;

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="space-y-2">
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="h-10 animate-pulse bg-gray-100 rounded"></div>
          ))}
        </div>
      ) : data?.data ? (
        <div className="space-y-2">
          {Array.isArray(data.data) && data.data.slice(0, limit).map((task: any, idx: number) => (
            <div key={idx} className="flex items-center gap-3 p-2 bg-gray-50 rounded hover:bg-gray-100">
              <input type="checkbox" className="w-4 h-4" />
              <div className="flex-1">
                <div className="text-sm font-medium text-gray-900">{task.title || task.name}</div>
                <div className="text-xs text-gray-500">{task.description || task.workflow_name}</div>
              </div>
              <span className="px-2 py-1 text-xs bg-amber-100 text-amber-800 rounded">معلقة</span>
            </div>
          ))}
          {data.data.length === 0 && (
            <div className="text-center text-gray-400 py-4">لا توجد مهام</div>
          )}
        </div>
      ) : (
        <div className="h-32 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-3xl mb-2">✅</div>
            <div className="text-sm">لا توجد مهام</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Audit Log Widget
 */
export function AuditLogWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'سجل التدقيق';
  const limit = displayConfig.limit || 20;
  const showUser = displayConfig.show_user ?? true;

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4">{title}</div>
      
      {isLoading ? (
        <div className="space-y-2">
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="h-12 animate-pulse bg-gray-100 rounded"></div>
          ))}
        </div>
      ) : data?.data ? (
        <div className="space-y-2">
          {Array.isArray(data.data) && data.data.slice(0, limit).map((log: any, idx: number) => (
            <div key={idx} className="p-2 bg-gray-50 rounded border-l-4 border-blue-400">
              <div className="text-sm font-medium text-gray-900">{log.description || log.action}</div>
              <div className="text-xs text-gray-500 mt-1">
                {showUser && log.user_name && <span>{log.user_name} • </span>}
                {new Date(log.created_at).toLocaleString('ar-IQ')}
              </div>
            </div>
          ))}
          {data.data.length === 0 && (
            <div className="text-center text-gray-400 py-4">لا توجد سجلات تدقيق</div>
          )}
        </div>
      ) : (
        <div className="h-32 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-3xl mb-2">🔍</div>
            <div className="text-sm">لا توجد سجلات</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * System Health Widget
 */
export function SystemHealthWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'صحة النظام';
  const showMetrics = displayConfig.show_metrics ?? true;

  const metrics = [
    { name: 'قاعدة البيانات', status: 'good', value: '98%' },
    { name: 'الذاكرة', status: 'good', value: '45%' },
    { name: 'المعالج', status: 'good', value: '23%' },
    { name: 'التخزين', status: 'warning', value: '78%' },
  ];

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      
      <div className="text-sm font-medium text-gray-700 mb-4 flex items-center gap-2">
        <span className="text-green-600">💚</span>
        {title}
      </div>
      
      {showMetrics && (
        <div className="space-y-3">
          {metrics.map((metric, idx) => (
            <div key={idx}>
              <div className="flex items-center justify-between text-xs mb-1">
                <span className="text-gray-600">{metric.name}</span>
                <span className={`font-bold ${
                  metric.status === 'good' ? 'text-green-600' : 'text-amber-600'
                }`}>{metric.value}</span>
              </div>
              <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div 
                  className={`h-full ${
                    metric.status === 'good' ? 'bg-green-500' : 'bg-amber-500'
                  }`}
                  style={{ width: metric.value }}
                ></div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
