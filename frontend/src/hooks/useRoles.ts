import { useQuery } from '@tanstack/react-query';
import client from '@/api/client';
import type { Role } from '@/types/auth';

export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: () => client.get<Role[]>('/roles').then((r) => r.data),
  });
}
