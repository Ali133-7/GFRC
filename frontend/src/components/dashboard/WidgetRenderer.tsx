import React from 'react';
import { KPICardWidget } from './widgets/KPICardWidget';
import { ChartWidget, PieChartWidget, RevenueChartWidget, FeeBreakdownWidget, WorkflowStatusWidget, TaskListWidget, AuditLogWidget, SystemHealthWidget } from './widgets/ChartFinancialWidgets';
import { TableWidget, ListWidget } from './widgets/TableListWidgets';
import { NotesWidget, ShortcutsWidget, QuickActionsWidget, AnnouncementsWidget } from './widgets/UtilityWidgets';
import { CalendarWidget, ClockWidget, ImageWidget, VideoWidget, PdfViewerWidget, WebsiteEmbedWidget } from './widgets/MediaWidgets';
import { StatCardWidget, GaugeWidget } from './widgets/StatCardWidget';
import type { DashboardWidget } from '@/types/dashboard';

interface WidgetRendererProps {
  widget: DashboardWidget;
  data?: any;
  isLoading?: boolean;
  onRefresh?: () => void;
  onEdit?: () => void;
  onRemove?: () => void;
  canEdit?: boolean;
}

/**
 * Enterprise Widget Renderer
 * Renders any widget type from the widget marketplace
 */
export function WidgetRenderer({
  widget,
  data,
  isLoading = false,
  onRefresh,
  onEdit,
  onRemove,
  canEdit = false,
}: WidgetRendererProps) {
  const widgetProps = {
    widget,
    data,
    isLoading,
    onRefresh,
    canEdit,
    onEdit,
    onRemove,
  };

  // KPI & Statistic Widgets
  if (widget.widget_type === 'kpi_card') {
    return <KPICardWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'stat_card') {
    return <StatCardWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'gauge') {
    return <GaugeWidget {...widgetProps} />;
  }

  // Financial Widgets
  if (widget.widget_type === 'revenue_chart') {
    return <RevenueChartWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'fee_breakdown') {
    return <FeeBreakdownWidget {...widgetProps} />;
  }

  // Workflow Widgets
  if (widget.widget_type === 'workflow_status') {
    return <WorkflowStatusWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'task_list') {
    return <TaskListWidget {...widgetProps} />;
  }

  // Audit Widgets
  if (widget.widget_type === 'audit_log') {
    return <AuditLogWidget {...widgetProps} />;
  }

  // Monitoring Widgets
  if (widget.widget_type === 'system_health') {
    return <SystemHealthWidget {...widgetProps} />;
  }

  // Media Widgets
  if (widget.widget_type === 'image') {
    return <ImageWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'video') {
    return <VideoWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'pdf_viewer') {
    return <PdfViewerWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'website_embed') {
    return <WebsiteEmbedWidget {...widgetProps} />;
  }

  // Utility Widgets
  if (widget.widget_type === 'clock_digital') {
    return <ClockWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'calendar') {
    return <CalendarWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'notes') {
    return <NotesWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'shortcuts') {
    return <ShortcutsWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'quick_actions') {
    return <QuickActionsWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'announcements') {
    return <AnnouncementsWidget {...widgetProps} />;
  }

  // Data Widgets
  if (widget.widget_type === 'table') {
    return <TableWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'list') {
    return <ListWidget {...widgetProps} />;
  }

  // Chart Widgets
  if (widget.widget_type === 'chart') {
    return <ChartWidget {...widgetProps} />;
  }
  
  if (widget.widget_type === 'pie_chart') {
    return <PieChartWidget {...widgetProps} />;
  }

  // Fallback for unknown widget types
  return (
    <div className="p-4 text-center text-gray-500 bg-gray-50 rounded-lg">
      <div className="text-4xl mb-2">🔧</div>
      <div className="font-medium">Widget type "{widget.widget_type}" not implemented yet</div>
      <div className="text-sm mt-2">This widget type is in development</div>
      {canEdit && (
        <div className="mt-4 flex justify-center gap-2">
          <button
            onClick={onEdit}
            className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Configure
          </button>
          <button
            onClick={onRemove}
            className="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700"
          >
            Remove
          </button>
        </div>
      )}
    </div>
  );
}
