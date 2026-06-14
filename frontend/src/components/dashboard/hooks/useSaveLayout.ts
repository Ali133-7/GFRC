import { useMutation, useQueryClient } from '@tanstack/react-query';
import { dashboardWidgetsApi } from '@/api/dashboardWidgets';
import type { DashboardWidgetItem } from '../types';

export function useSaveLayout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (widgets: DashboardWidgetItem[]) => dashboardWidgetsApi.saveLayout(widgets),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard', 'layout'] });
    },
  });
}
