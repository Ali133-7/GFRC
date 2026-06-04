import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { backupApi } from '@/api/backups';

export function useBackups() {
  return useQuery({
    queryKey: ['backups'],
    queryFn: backupApi.index,
  });
}

export function useCreateBackup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: backupApi.create,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['backups'] }),
  });
}

export function useRestoreBackup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: backupApi.restore,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['backups'] }),
  });
}

export function useDeleteBackup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: backupApi.destroy,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['backups'] }),
  });
}
