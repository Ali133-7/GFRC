import { useMutation } from '@tanstack/react-query';
import { systemApi } from '@/api/system';

export function useSystemExport() {
  return useMutation({
    mutationFn: systemApi.export,
  });
}

export function useSystemImport() {
  return useMutation({
    mutationFn: systemApi.import,
  });
}

export function useSystemUploadLogo() {
  return useMutation({
    mutationFn: (file: File) => systemApi.uploadLogo(file),
  });
}
