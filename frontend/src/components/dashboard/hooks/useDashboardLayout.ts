import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { dashboardWidgetsApi } from '@/api/dashboardWidgets';
import type { DashboardLayout, DashboardWidgetItem } from '../types';

export function useDashboardLayout() {
  const { data, isLoading, error, isError } = useQuery<DashboardLayout>({
    queryKey: ['dashboard', 'layout'],
    queryFn: dashboardWidgetsApi.getLayout,
  });

  const [widgets, setWidgets] = useState<DashboardWidgetItem[]>([]);

  useEffect(() => {
    if (data?.widgets) {
      setWidgets(data.widgets);
    }
  }, [data]);

  return { widgets, setWidgets, isLoading, error, isError };
}
