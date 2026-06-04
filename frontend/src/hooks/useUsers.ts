import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { usersApi } from '@/api/users';

export function useUpdateUserRoles() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, roles }: { id: string; roles: string[] }) =>
      usersApi.updateRoles(id, roles),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', id] });
    },
  });
}

export function useUpdateUserPermissions() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, permissions }: { id: string; permissions: string[] }) =>
      usersApi.updatePermissions(id, permissions),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', id] });
    },
  });
}

export function useUsers() {
  return useQuery({
    queryKey: ['users'],
    queryFn: usersApi.list,
  });
}

export function useUser(id: string) {
  return useQuery({
    queryKey: ['user', id],
    queryFn: () => usersApi.get(id),
    enabled: !!id,
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: usersApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useUpdateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Parameters<typeof usersApi.update>[1] }) =>
      usersApi.update(id, payload),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', id] });
    },
  });
}

export function useDeleteUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: usersApi.destroy,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}
