import { useQuery } from '@tanstack/react-query';
import { dashboardWidgetsApi } from '@/api/dashboardWidgets';
import type { DashboardWidgetItem } from '../types';

export function useWidgetData(widget: DashboardWidgetItem) {
  return useQuery({
    queryKey: ['dashboard', 'widgetData', widget.id, widget.widget_type, widget.data_source, widget.display_config],
    queryFn: () => dashboardWidgetsApi.getWidgetData(widget),
    enabled: !!widget.id,
  });
}
