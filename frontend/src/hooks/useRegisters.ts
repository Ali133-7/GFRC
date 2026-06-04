import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { registersApi } from '@/api/registers';
import type { Register } from '@/types/register';

export function useRegisters() {
  return useQuery({
    queryKey: ['registers'],
    queryFn: () => registersApi.list(),
  });
}

export function useRegister(id: string) {
  return useQuery({
    queryKey: ['register', id],
    queryFn: () => registersApi.get(id),
    enabled: !!id,
  });
}

export function useRegisterFields(id: string) {
  return useQuery({
    queryKey: ['register-fields', id],
    queryFn: () => registersApi.fields(id),
    enabled: !!id,
  });
}

export function useCreateRegister() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: registersApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['registers'] }),
  });
}

export function useUpdateRegister() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<Register> }) =>
      registersApi.update(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['registers'] }),
  });
}
