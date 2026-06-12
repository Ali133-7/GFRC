import React, { useEffect, useState, useCallback } from 'react';
import { dashboardApi } from '@/api/dashboard';
import { WidgetRenderer } from './WidgetRenderer';
import type { Dashboard, DashboardWidget, UserDashboardPreference } from '@/types/dashboard';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';

interface DashboardViewProps {
  dashboardId?: number;
  canEdit?: boolean;
  onEditDashboard?: () => void;
}

/**
 * Dashboard View Component
 * Displays a dashboard with sections and widgets
 */
export function DashboardView({
  dashboardId,
  canEdit = false,
  onEditDashboard,
}: DashboardViewProps) {
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [widgetData, setWidgetData] = useState<Record<number, any>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [loadingWidgets, setLoadingWidgets] = useState<Set<number>>(new Set());
  const [error, setError] = useState<string | null>(null);

  // Load dashboard
  const loadDashboard = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const result = await dashboardApi.getEffectiveDashboard(dashboardId);
      setDashboard(result.dashboard);
      
      // Load widget data
      if (result.dashboard?.sections) {
        const allWidgetIds: number[] = [];
        result.dashboard.sections.forEach((section: any) => {
          section.widgets?.forEach((widget: any) => {
            allWidgetIds.push(widget.id);
          });
        });
        
        if (allWidgetIds.length > 0) {
          const widgetResults = await dashboardApi.getBatchWidgetData(
            result.dashboard.id,
            allWidgetIds
          );
          setWidgetData(widgetResults.widgets ?? {});
        }
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load dashboard');
      console.error('[DashboardView] Error loading dashboard:', err);
    } finally {
      setIsLoading(false);
    }
  }, [dashboardId]);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  // Refresh widget data
  const refreshWidget = async (widgetId: number) => {
    if (!dashboard) return;
    
    setLoadingWidgets(prev => new Set(prev).add(widgetId));
    try {
      const result = await dashboardApi.getWidgetData(dashboard.id, widgetId);
      setWidgetData(prev => ({ ...prev, [widgetId]: result }));
    } catch (err: any) {
      console.error('[DashboardView] Error refreshing widget:', err);
    } finally {
      setLoadingWidgets(prev => {
        const next = new Set(prev);
        next.delete(widgetId);
        return next;
      });
    }
  };

  // Refresh all widgets
  const refreshAllWidgets = async () => {
    if (!dashboard) return;
    
    const allWidgetIds: number[] = [];
    dashboard.sections?.forEach((section: any) => {
      section.widgets?.forEach((widget: any) => {
        allWidgetIds.push(widget.id);
      });
    });
    
    if (allWidgetIds.length > 0) {
      try {
        const result = await dashboardApi.getBatchWidgetData(dashboard.id, allWidgetIds);
        setWidgetData(result.widgets ?? {});
      } catch (err: any) {
        console.error('[DashboardView] Error refreshing all widgets:', err);
      }
    }
  };

  // Remove widget
  const removeWidget = async (widgetId: number) => {
    if (!window.confirm('هل أنت متأكد من إزالة هذا widget؟')) return;
    
    try {
      await dashboardApi.removeWidget(widgetId);
      await loadDashboard();
    } catch (err: any) {
      console.error('[DashboardView] Error removing widget:', err);
      alert('فشل إزالة widget');
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-4 text-center text-red-600 bg-red-50 rounded-lg">
        {error}
        <button
          onClick={loadDashboard}
          className="mt-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
        >
          إعادة المحاولة
        </button>
      </div>
    );
  }

  if (!dashboard) {
    return (
      <div className="p-4 text-center text-gray-500">
        لا يوجد داشبورد
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Dashboard Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{dashboard.name_ar}</h1>
          {dashboard.description && (
            <p className="text-sm text-gray-500 mt-1">{dashboard.description}</p>
          )}
        </div>
        
        <div className="flex gap-2">
          <button
            onClick={refreshAllWidgets}
            className="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded"
            title="تحديث الكل"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
          
          {canEdit && onEditDashboard && (
            <button
              onClick={onEditDashboard}
              className="px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
            >
              تعديل الداشبورد
            </button>
          )}
        </div>
      </div>

      {/* Sections */}
      {dashboard.sections?.map((section: any) => (
        <div
          key={section.id}
          className="p-4 bg-white rounded-lg border border-gray-200"
          style={{
            backgroundColor: section.background_color || undefined,
            borderColor: section.border_color || undefined,
          }}
        >
          {/* Section Header */}
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-gray-800">{section.name_ar}</h2>
            {section.is_collapsible && (
              <button
                onClick={() => {
                  // Toggle collapsed state
                }}
                className="p-1 hover:bg-gray-100 rounded"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            )}
          </div>

          {/* Widgets Grid */}
          <div
            className="grid gap-4"
            style={{
              gridTemplateColumns: `repeat(12, minmax(0, 1fr))`,
            }}
          >
            {section.widgets?.map((widget: any) => {
              const widgetDataItem = widgetData[widget.id];
              const isLoadingWidget = loadingWidgets.has(widget.id);
              
              return (
                <div
                  key={widget.id}
                  className=""
                  style={{
                    gridColumn: `span ${widget.grid_width || 6}`,
                    gridRow: `span ${widget.grid_height || 4}`,
                  }}
                >
                  <WidgetRenderer
                    widget={widget}
                    data={widgetDataItem}
                    isLoading={isLoadingWidget}
                    onRefresh={() => refreshWidget(widget.id)}
                    onEdit={() => {
                      // Open widget editor
                    }}
                    onRemove={() => removeWidget(widget.id)}
                    canEdit={canEdit}
                  />
                </div>
              );
            })}
          </div>

          {(!section.widgets || section.widgets.length === 0) && (
            <div className="text-center text-gray-400 py-8">
              لا توجد widgets في هذا القسم
            </div>
          )}
        </div>
      ))}

      {(!dashboard.sections || dashboard.sections.length === 0) && (
        <div className="text-center text-gray-400 py-12">
          لا توجد أقسام في هذا الداشبورد
        </div>
      )}
    </div>
  );
}
