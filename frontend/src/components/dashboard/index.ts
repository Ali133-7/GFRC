/**
 * Dashboard Components
 * 
 * Personal Workspace & Dashboard System
 * 
 * Features:
 * - Dashboard inheritance (User → Role → Department → System)
 * - Dynamic widget filtering by user/role/department
 * - Permission-based visibility
 * - User preferences and customization
 * - Real-time widget data refresh
 */

export { DashboardView } from './DashboardView';
export { WidgetRenderer } from './WidgetRenderer';
export { DashboardSelector } from './DashboardSelector';
export { UserPreferencesModal } from './UserPreferencesModal';

export { KPICardWidget } from './widgets/KPICardWidget';
export { ChartWidget } from './widgets/ChartWidget';
export { TableWidget, ListWidget, NotesWidget, ShortcutsWidget } from './widgets/TableListWidgets';
