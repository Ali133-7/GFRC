import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { receiptsApi } from '@/api/receipts';
import type { CreateReceiptPayload } from '@/api/receipts';

export function useReceipts(params?: Record<string, unknown>) {
  return useQuery({
    queryKey: ['receipts', params],
    queryFn: () => receiptsApi.list(params),
  });
}

export function useReceipt(id: string) {
  return useQuery({
    queryKey: ['receipt', id],
    queryFn: () => receiptsApi.get(id),
    enabled: !!id,
  });
}

export function useCreateReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: receiptsApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['receipts'] }),
  });
}

export function useIssueReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: receiptsApi.issue,
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: ['receipt', id] });
      qc.invalidateQueries({ queryKey: ['receipts'] });
    },
  });
}

export function useCancelReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) => receiptsApi.cancel(id, reason),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['receipt', id] });
      qc.invalidateQueries({ queryKey: ['receipts'] });
    },
  });
}

export function useReviseReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: CreateReceiptPayload & { reason: string } }) =>
      receiptsApi.revise(id, payload),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['receipt', id] });
      qc.invalidateQueries({ queryKey: ['receipts'] });
    },
  });
}

export function useReceiptRevisions(id: string) {
  return useQuery({
    queryKey: ['receipt-revisions', id],
    queryFn: () => receiptsApi.revisions(id),
    enabled: !!id,
  });
}
