import { useEffect } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import { authApi } from '@/api/auth';
import type { AxiosError } from 'axios';

export function useAuth() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user, token, setAuth, logout } = useAuthStore();

  const meQuery = useQuery({
    queryKey: ['me'],
    queryFn: authApi.me,
    enabled: !!token,
    staleTime: 1000 * 60 * 5,
    retry: false,
  });

  useEffect(() => {
    if (meQuery.data && token) {
      setAuth(meQuery.data, token);
    }
  }, [meQuery.data, token]);

  const loginMutation = useMutation({
    mutationFn: async ({ username, password }: { username: string; password: string }) => {
      const data = await authApi.login(username, password);
      // Store token immediately so subsequent requests include it
      setAuth(data.user, data.token);
      queryClient.invalidateQueries({ queryKey: ['me'] });
      return data.user;
    },
    onSuccess: () => {
      navigate('/dashboard', { replace: true });
    },
  });

  const logoutMutation = useMutation({
    mutationFn: authApi.logout,
    onSuccess: () => {
      logout();
      queryClient.clear();
      navigate('/login', { replace: true });
    },
  });

  return {
    user: meQuery.data ?? user ?? null,
    isAuthenticated: !!token,
    isLoading: meQuery.isLoading,
    login: loginMutation.mutateAsync,
    logout: logoutMutation.mutateAsync,
    loginError: loginMutation.error as AxiosError | null,
    isLoggingIn: loginMutation.isPending,
  };
}
