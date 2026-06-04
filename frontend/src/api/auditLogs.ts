import client from './client';

export const auditLogsApi = {
  list: (params?: Record<string, unknown>) =>
    client.get<unknown[]>('/audit-logs', { params }).then((r) => r.data),
};
