import client from './client';
import type { Receipt, ReceiptRevision } from '@/types/receipt';

export interface CreateReceiptPayload {
  register_id: string;
  total_amount: number;
  notes?: string;
  items: Array<{ field_id: string; value?: string; amount?: number }>;
}

export interface ReviseReceiptPayload {
  total_amount: number;
  notes?: string;
  reason: string;
  items: Array<{ field_id: string; value?: string; amount?: number }>;
}

export const receiptsApi = {
  list: (params?: Record<string, unknown>) =>
    client.get<Receipt[]>('/receipts', { params }).then((r) => r.data),

  create: (payload: CreateReceiptPayload) =>
    client.post<Receipt>('/receipts', payload).then((r) => r.data),

  get: (id: string) =>
    client.get<Receipt>(`/receipts/${id}`).then((r) => r.data),

  update: (id: string, payload: Partial<CreateReceiptPayload>) =>
    client.put<Receipt>(`/receipts/${id}`, payload).then((r) => r.data),

  issue: (id: string) =>
    client.post<Receipt>(`/receipts/${id}/issue`).then((r) => r.data),

  cancel: (id: string, reason: string) =>
    client.post<Receipt>(`/receipts/${id}/cancel`, { reason }).then((r) => r.data),

  revise: (id: string, payload: ReviseReceiptPayload) =>
    client.post<Receipt>(`/receipts/${id}/revise`, payload).then((r) => r.data),

  print: (id: string) =>
    client.get(`/receipts/${id}/print`, { responseType: 'blob' }),

  qr: (id: string) =>
    client.get<string>(`/receipts/${id}/qr`, { responseType: 'text' }).then((r) => r.data),

  revisions: (id: string) =>
    client.get<ReceiptRevision[]>(`/receipts/${id}/revisions`).then((r) => r.data),
};
