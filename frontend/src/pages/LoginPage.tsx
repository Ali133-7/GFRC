import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import { useAuth } from '@/hooks/useAuth';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';

export default function LoginPage() {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuthStore();
  const { login, isLoggingIn, loginError } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/dashboard', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!username || !password) return;
    try {
      await login({ username, password });
    } catch {
      // Error is surfaced via loginError
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100" dir="rtl">
      <div className="w-full max-w-md rounded-lg bg-white p-8 shadow-lg">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-gray-900">نظام الإيصالات المالية</h1>
          <p className="mt-2 text-sm text-gray-500">تسجيل الدخول</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="اسم المستخدم"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            autoFocus
          />
          <Input
            label="كلمة المرور"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          {loginError && (
            <p className="text-sm text-red-600">
              {(loginError as any)?.arabicMessage || 'فشل تسجيل الدخول'}
            </p>
          )}
          <Button type="submit" className="w-full" isLoading={isLoggingIn}>
            {isLoggingIn ? <LoadingSpinner /> : 'تسجيل الدخول'}
          </Button>
        </form>
      </div>
    </div>
  );
}
