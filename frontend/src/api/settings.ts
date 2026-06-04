import client from './client';

export interface Setting {
  id: string;
  key: string;
  value: string;
  type: string;
  group: string;
  label_ar: string;
  description: string | null;
  is_public: boolean;
}

export const settingsApi = {
  list: () =>
    client.get<Setting[]>('/settings').then((r) => r.data),

  public: () =>
    client.get<Record<string, unknown>>('/settings/public').then((r) => r.data),

  bulkUpdate: (settings: Array<{ key: string; value: string }>) =>
    client.post<null>('/settings/bulk', { settings }).then((r) => r.data),
};
