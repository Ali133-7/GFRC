import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '@/types/auth';

interface AuthState {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  setAuth: (user: User, token: string) => void;
  logout: () => void;
}

function normalizeUser(user: User | null): User | null {
  if (!user) return null;
  return {
    ...user,
    permissions: user.permissions ?? [],
    roles: user.roles ?? [],
  };
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      isAuthenticated: false,
      setAuth: (user, token) => set({ token, user: normalizeUser(user), isAuthenticated: true }),
      logout: () => set({ token: null, user: null, isAuthenticated: false }),
    }),
    {
      name: 'gfrc-auth',
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        isAuthenticated: state.isAuthenticated,
      }),
      merge: (persistedState, currentState) => {
        const merged = { ...currentState, ...(persistedState as object) };
        merged.user = normalizeUser(merged.user);
        return merged;
      },
    }
  )
);
