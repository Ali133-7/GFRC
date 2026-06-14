import type { DashboardWidget } from '@/types/dashboard';

import { KPICardWidget } from './widgets/KPICardWidget';
import { ChartWidget } from './widgets/ChartWidget';
import {
  PieChartWidget,
  RevenueChartWidget,
  FeeBreakdownWidget,
  WorkflowStatusWidget,
  TaskListWidget,
  AuditLogWidget,
  SystemHealthWidget,
} from './widgets/ChartFinancialWidgets';
import { TableWidget, ListWidget, NotesWidget, ShortcutsWidget } from './widgets/TableListWidgets';
import {
  ClockWidget,
  CalendarWidget,
  ImageWidget,
  VideoWidget,
  PdfViewerWidget,
  WebsiteEmbedWidget,
} from './widgets/MediaWidgets';
import { QuickActionsWidget, AnnouncementsWidget } from './widgets/UtilityWidgets';

import StatCardWidget from './widgets/StatCardWidget';
import ChartBarWidget from './widgets/ChartBarWidget';
import ChartLineWidget from './widgets/ChartLineWidget';
import ChartPieWidget from './widgets/ChartPieWidget';
import NewTableWidget from './widgets/TableWidget';
import NewClockWidget from './widgets/ClockWidget';
import WeatherWidget from './widgets/WeatherWidget';
import YoutubeAudioWidget from './widgets/YoutubeAudioWidget';
import ProgressWidget from './widgets/ProgressWidget';
import GaugeWidget from './widgets/GaugeWidget';
import TextBlockWidget from './widgets/TextBlockWidget';
import IframeWidget from './widgets/IframeWidget';
import type { DashboardWidgetItem, WidgetType } from './types';

/* ------------------------------------------------------------------ */
/*  Legacy WidgetRenderer (used by DashboardView / index.ts)          */
/* ------------------------------------------------------------------ */

interface LegacyWidgetRendererProps {
  widget: DashboardWidget;
  data?: any;
  isLoading?: boolean;
  onRefresh?: () => void;
  onEdit?: () => void;
  onRemove?: () => void;
  canEdit?: boolean;
}

export function WidgetRenderer({
  widget,
  data,
  isLoading,
  onRefresh,
  onEdit,
  onRemove,
  canEdit,
}: LegacyWidgetRendererProps) {
  const common = {
    widget,
    data,
    isLoading,
    onRefresh,
    onEdit,
    onRemove,
    canEdit,
  };

  switch (widget.widget_type) {
    case 'kpi_card':
    case 'stat_card':
      return <KPICardWidget {...common} />;
    case 'chart':
      return <ChartWidget {...common} />;
    case 'pie_chart':
      return <PieChartWidget {...common} />;
    case 'revenue_chart':
      return <RevenueChartWidget {...common} />;
    case 'fee_breakdown':
      return <FeeBreakdownWidget {...common} />;
    case 'workflow_status':
      return <WorkflowStatusWidget {...common} />;
    case 'task_list':
      return <TaskListWidget {...common} />;
    case 'audit_log':
      return <AuditLogWidget {...common} />;
    case 'system_health':
      return <SystemHealthWidget {...common} />;
    case 'table':
      return <TableWidget {...common} />;
    case 'list':
      return <ListWidget {...common} />;
    case 'notes':
      return <NotesWidget {...common} />;
    case 'shortcuts':
      return <ShortcutsWidget {...common} />;
    case 'quick_actions':
      return <QuickActionsWidget {...common} />;
    case 'announcements':
      return <AnnouncementsWidget {...common} />;
    case 'clock_digital':
      return <ClockWidget {...common} />;
    case 'calendar':
      return <CalendarWidget {...common} />;
    case 'image':
      return <ImageWidget {...common} />;
    case 'video':
      return <VideoWidget {...common} />;
    case 'pdf_viewer':
      return <PdfViewerWidget {...common} />;
    case 'website_embed':
      return <WebsiteEmbedWidget {...common} />;
    default:
      return (
        <div className="relative p-4 bg-white rounded-lg border border-gray-200">
          <div className="text-sm font-medium text-gray-700 mb-2">{widget.name_ar}</div>
          <div className="text-xs text-gray-400">نوع الودجت غير مدعوم: {widget.widget_type}</div>
        </div>
      );
  }
}

/* ------------------------------------------------------------------ */
/*  New WidgetRenderer (used by DashboardGrid)                        */
/* ------------------------------------------------------------------ */

interface NewWidgetRendererProps {
  widget: DashboardWidgetItem;
}

const NEW_RENDERERS: Record<WidgetType, React.FC<{ widget: DashboardWidgetItem }>> = {
  stat_card: StatCardWidget,
  chart_bar: ChartBarWidget,
  chart_line: ChartLineWidget,
  chart_pie: ChartPieWidget,
  table: NewTableWidget,
  clock: NewClockWidget,
  weather: WeatherWidget,
  youtube_audio: YoutubeAudioWidget,
  progress: ProgressWidget,
  gauge: GaugeWidget,
  text_block: TextBlockWidget,
  iframe: IframeWidget,
};

export default function NewWidgetRenderer({ widget }: NewWidgetRendererProps) {
  const Renderer = NEW_RENDERERS[widget.widget_type];
  if (!Renderer) {
    return (
      <div className="flex h-full w-full items-center justify-center text-sm text-gray-500">
        نوع الودجت غير مدعوم
      </div>
    );
  }
  return <Renderer widget={widget} />;
}
