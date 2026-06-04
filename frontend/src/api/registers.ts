import client from './client';
import type { Register, RegisterField } from '@/types/register';

export const registersApi = {
  list: () =>
    client.get<Register[]>('/registers').then((r) => r.data),

  create: (payload: Partial<Register>) =>
    client.post<Register>('/registers', payload).then((r) => r.data),

  get: (id: string) =>
    client.get<Register>(`/registers/${id}`).then((r) => r.data),

  update: (id: string, payload: Partial<Register>) =>
    client.put<Register>(`/registers/${id}`, payload).then((r) => r.data),

  fields: (id: string) =>
    client.get<RegisterField[]>(`/registers/${id}/fields`).then((r) => r.data),

  addField: (id: string, payload: Partial<RegisterField>) =>
    client.post<RegisterField>(`/registers/${id}/fields`, payload).then((r) => r.data),

  updateField: (id: string, fieldId: string, payload: Partial<RegisterField>) =>
    client.put<RegisterField>(`/registers/${id}/fields/${fieldId}`, payload).then((r) => r.data),

  removeField: (id: string, fieldId: string) =>
    client.delete<null>(`/registers/${id}/fields/${fieldId}`).then((r) => r.data),

  reorderFields: (id: string, payload: Array<{ id: string; sort_order: number }>) =>
    client.patch<null>(`/registers/${id}/fields/reorder`, { fields: payload }).then((r) => r.data),
};
