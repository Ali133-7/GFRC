import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { settingsApi } from '@/api/settings';

export function useSettings() {
  return useQuery({
    queryKey: ['settings'],
    queryFn: settingsApi.list,
  });
}

export function useBulkUpdateSettings() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: settingsApi.bulkUpdate,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['settings'] }),
  });
}
