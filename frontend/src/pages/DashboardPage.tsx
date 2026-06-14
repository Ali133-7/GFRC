import DashboardGrid from '@/components/dashboard/DashboardGrid';
import LegacyDashboardPage from './LegacyDashboardPage';

const USE_NEW_DASHBOARD = true; // toggle for rollback

export default function DashboardPage() {
  return USE_NEW_DASHBOARD ? <DashboardGrid /> : <LegacyDashboardPage />;
}
