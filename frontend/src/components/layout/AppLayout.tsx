import { Outlet, useLocation } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { useAuth } from '@/hooks/useAuth';
import { useUiStore } from '@/stores/uiStore';

export function AppLayout() {
  const { user, logout } = useAuth();
  const { toggleSidebar } = useUiStore();
  const { pathname } = useLocation();
  const isPrintPage = pathname.endsWith('/print');

  return (
    <div className="flex min-h-screen">
      {!isPrintPage && <Sidebar />}
      <div className="flex flex-1 flex-col">
        {!isPrintPage && (
          <header className="flex items-center justify-between border-b bg-white px-4 py-3 md:px-6 print:hidden">
            <button className="md:hidden" onClick={toggleSidebar}>
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <div className="flex items-center gap-4">
              <span className="text-sm text-gray-700">{user?.name}</span>
              <button onClick={() => logout()} className="text-sm text-red-600 hover:underline">
                تسجيل الخروج
              </button>
            </div>
          </header>
        )}
        <main className="flex-1 p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
