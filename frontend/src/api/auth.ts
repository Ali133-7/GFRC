import client from './client';
import type { LoginResponse, User } from '@/types/auth';

export const authApi = {
  login: (username: string, password: string) =>
    client.post<LoginResponse>('/auth/login', { username, password }).then((r) => r.data),

  logout: () =>
    client.post<null>('/auth/logout').then((r) => r.data),

  logoutAll: () =>
    client.post<null>('/auth/logout-all').then((r) => r.data),

  me: () =>
    client.get<User>('/auth/me').then((r) => r.data),
};
