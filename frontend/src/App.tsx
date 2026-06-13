import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { useAuthStore } from '@/stores/authStore';
import { AppLayout } from '@/components/layout/AppLayout';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import LoginPage from '@/pages/LoginPage';
import VerifyReceiptPage from '@/pages/VerifyReceiptPage';
import DashboardPage from '@/pages/DashboardPage';
import DashboardBuilderPage from '@/pages/DashboardBuilderPage';
import AdminDashboardManagement from '@/pages/AdminDashboardManagement';
import ReceiptListPage from '@/pages/receipts/ReceiptListPage';
import ReceiptCreatePage from '@/pages/receipts/ReceiptCreatePage';
import ReceiptDetailPage from '@/pages/receipts/ReceiptDetailPage';
import ReceiptPrintPage from '@/pages/receipts/ReceiptPrintPage';
import ReceiptDesignerPage from '@/pages/receipts/ReceiptDesignerPage';
import ReceiptRevisePage from '@/pages/receipts/ReceiptRevisePage';
import TemplateDesignerPage from '@/pages/receipts/TemplateDesignerPage';
import TransactionTemplateListPage from '@/pages/templates/TransactionTemplateListPage';
import TransactionTemplateFormPage from '@/pages/templates/TransactionTemplateFormPage';
import OfficialFeeLibraryPage from '@/pages/fees/OfficialFeeLibraryPage';
import OfficialFeeFormPage from '@/pages/fees/OfficialFeeFormPage';
import WorkflowListPage from '@/pages/workflows/WorkflowListPage';
import WorkflowFormPage from '@/pages/workflows/WorkflowFormPage';
import WorkflowDesignerPage from '@/pages/workflows/WorkflowDesignerPage';
import WorkflowExecutionPage from '@/pages/workflows/WorkflowExecutionPage';
import RegisterListPage from '@/pages/registers/RegisterListPage';
import RegisterDetailPage from '@/pages/registers/RegisterDetailPage';
import RegisterReceiptsPage from '@/pages/registers/RegisterReceiptsPage';
import UserListPage from '@/pages/users/UserListPage';
import UserFormPage from '@/pages/users/UserFormPage';
import ReportsPage from '@/pages/ReportsPage';
import ReportBuilderPage from '@/pages/reports/ReportBuilderPage';
import AuditLogPage from '@/pages/AuditLogPage';
import SettingsPage from '@/pages/SettingsPage';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
  },
});

function AuthGuard({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, token } = useAuthStore();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted) {
    return (
      <div style={{ height: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <LoadingSpinner />
      </div>
    );
  }
  return isAuthenticated && token ? <>{children}</> : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Routes>
          <Route path="/verify" element={<VerifyReceiptPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/receipts/:id/print" element={<AuthGuard><ReceiptPrintPage /></AuthGuard>} />
          <Route path="/" element={<AuthGuard><AppLayout /></AuthGuard>}>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<DashboardPage />} />
            <Route path="dashboard/builder" element={<DashboardBuilderPage />} />
            <Route path="dashboard/builder/:id" element={<DashboardBuilderPage />} />
            <Route path="admin/dashboards" element={<AdminDashboardManagement />} />
            <Route path="receipts" element={<ReceiptListPage />} />
            <Route path="receipts/create" element={<ReceiptCreatePage />} />
            <Route path="receipts/:id" element={<ReceiptDetailPage />} />
            <Route path="receipts/:id/revise" element={<AuthGuard><ReceiptRevisePage /></AuthGuard>} />
            <Route path="receipts/designer" element={<ReceiptDesignerPage />} />
            <Route path="registers/:registerId/template-designer" element={<TemplateDesignerPage />} />
            <Route path="registers" element={<RegisterListPage />} />
            <Route path="registers/:id" element={<RegisterDetailPage />} />
            <Route path="registers/:id/receipts" element={<RegisterReceiptsPage />} />
            <Route path="users" element={<UserListPage />} />
            <Route path="users/new" element={<UserFormPage />} />
            <Route path="users/:id" element={<UserFormPage />} />
            <Route path="reports" element={<ReportsPage />} />
            <Route path="reports/builder" element={<ReportBuilderPage />} />
            <Route path="audit-logs" element={<AuditLogPage />} />
            <Route path="transaction-templates" element={<TransactionTemplateListPage />} />
            <Route path="transaction-templates/new" element={<TransactionTemplateFormPage />} />
            <Route path="transaction-templates/:id/edit" element={<TransactionTemplateFormPage />} />
            <Route path="official-fees" element={<OfficialFeeLibraryPage />} />
            <Route path="official-fees/new" element={<OfficialFeeFormPage />} />
            <Route path="official-fees/:id/edit" element={<OfficialFeeFormPage />} />
            <Route path="workflows" element={<WorkflowListPage />} />
            <Route path="workflows/new" element={<WorkflowFormPage />} />
            <Route path="workflows/:id" element={<WorkflowDesignerPage />} />
            <Route path="workflows/:id/execute" element={<WorkflowExecutionPage />} />
            <Route path="settings" element={<SettingsPage />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
