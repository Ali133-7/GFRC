import client from './client';
import type { UserListItem, UserActivitySummary } from '@/types/user';

export const usersApi = {
  list: (params?: Record<string, unknown>) =>
    client.get<UserListItem[]>('/users', { params }).then((r) => r.data),

  create: (payload: Partial<UserListItem> & { password: string; roles?: string[] }) =>
    client.post<UserListItem>('/users', payload).then((r) => r.data),

  get: (id: string) =>
    client.get<UserListItem>(`/users/${id}`).then((r) => r.data),

  update: (id: string, payload: Partial<UserListItem>) =>
    client.put<UserListItem>(`/users/${id}`, payload).then((r) => r.data),

  destroy: (id: string) =>
    client.delete<null>(`/users/${id}`).then((r) => r.data),

  updateRoles: (id: string, roles: string[]) =>
    client.put<UserListItem>(`/users/${id}/roles`, { roles }).then((r) => r.data),

  updatePermissions: (id: string, permissions: string[]) =>
    client.put<UserListItem>(`/users/${id}/permissions`, { permissions }).then((r) => r.data),

  activitySummary: (id: string) =>
    client.get<UserActivitySummary>(`/users/${id}/activity-summary`).then((r) => r.data),
};
