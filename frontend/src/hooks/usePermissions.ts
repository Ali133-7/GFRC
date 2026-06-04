import { useAuthStore } from '@/stores/authStore';

export function usePermissions() {
  const { user } = useAuthStore();

  const can = (permission: string): boolean => {
    const perms = user?.permissions ?? [];
    return perms.includes(permission) || perms.includes('*');
  };

  const hasRole = (role: string): boolean => {
    return !!user?.roles?.some((r: { name: string }) => r.name === role);
  };

  const canAny = (...permissions: string[]): boolean => {
    return permissions.some((p) => can(p));
  };

  return { can, hasRole, canAny };
}
