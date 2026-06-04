import client from './client';

export interface Backup {
  filename: string;
  size: string;
  date: string;
  encrypted: boolean;
  verified: boolean;
}

export interface BackupCreateResponse {
  filename: string;
  size: string;
  hash: string;
  crc: string;
}

export const backupApi = {
  index: () => client.get<Backup[]>('/backups').then((r) => r.data),

  create: () =>
    client.post<BackupCreateResponse>('/backups').then((r) => r.data),

  restore: (filename: string) =>
    client
      .post<null>(`/backups/${encodeURIComponent(filename)}/restore`)
      .then((r) => r.data),

  destroy: (filename: string) =>
    client
      .delete<null>(`/backups/${encodeURIComponent(filename)}`)
      .then((r) => r.data),
};
