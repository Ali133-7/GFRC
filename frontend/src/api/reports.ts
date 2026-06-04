import client from './client';

export const reportsApi = {
  daily: (params: { date: string; register_id?: string; user_id?: string }) =>
    client.get<unknown>('/reports/daily', { params }).then((r) => r.data),

  monthly: (params: { year: number; month: number; register_id?: string }) =>
    client.get<unknown>('/reports/monthly', { params }).then((r) => r.data),

  userActivity: (params: { date_from: string; date_to: string; user_id?: string }) =>
    client.get<unknown>('/reports/user-activity', { params }).then((r) => r.data),

  registerSummary: (params: { date_from: string; date_to: string; register_id?: string }) =>
    client.get<unknown>('/reports/register-summary', { params }).then((r) => r.data),

  custom: (params: Record<string, unknown>) =>
    client.post<unknown>('/reports/custom', params).then((r) => r.data),

  exportCsv: (params: Record<string, unknown>) =>
    client.get('/reports/export-csv', { params, responseType: 'blob' }).then((r) => r.data),
};
