import client from './client';
import type { Dashboard, DashboardWidget, UserDashboardPreference } from '@/types/dashboard';

export interface DashboardData {
  dashboard: Dashboard;
  is_default?: boolean;
  can_edit?: boolean;
}

export interface WidgetData {
  success: boolean;
  data?: any;
  count?: number;
  error?: string;
}

export const dashboardApi = {
  /**
   * Get user's effective dashboard (with inheritance)
   */
  async getEffectiveDashboard(dashboardId?: number): Promise<DashboardData> {
    const params = dashboardId ? `?dashboard_id=${dashboardId}` : '';
    const res = await client.get(`/dashboards${params}`);
    return res.data?.data ?? res.data;
  },

  /**
   * Get fund statistics
   */
  async getFundStatistics(period: 'today' | 'week' | 'month' | 'year' = 'today') {
    const res = await client.get(`/dashboards/fund-statistics?period=${period}`);
    return res.data?.data ?? res.data;
  },

  /**
   * Get all available dashboards for user
   */
  async getAvailableDashboards(): Promise<Dashboard[]> {
    const res = await client.get('/dashboards/available');
    const payload = res.data?.data ?? res.data;
    return payload.dashboards ?? [];
  },

  /**
   * Set default dashboard
   */
  async setDefaultDashboard(dashboardId: number): Promise<{ message: string }> {
    const res = await client.post('/dashboards/set-default', { dashboard_id: dashboardId });
    return res.data?.data ?? res.data;
  },

  /**
   * Get specific dashboard
   */
  async getDashboard(id: number): Promise<DashboardData> {
    const res = await client.get(`/dashboards/${id}`);
    return res.data?.data ?? res.data;
  },

  /**
   * Create new dashboard
   */
  async createDashboard(data: {
    name_ar: string;
    name_en?: string;
    description?: string;
    scope: 'user' | 'department' | 'role' | 'organization' | 'system';
    visibility: 'private' | 'shared' | 'department' | 'role' | 'organization' | 'public';
    layout_config?: any;
    theme_config?: any;
    is_default?: boolean;
    assigned_to_user_id?: string;
  }): Promise<{ dashboard: Dashboard; message: string }> {
    const res = await client.post('/dashboards', data);
    return res.data?.data ?? res.data;
  },

  /**
   * Update dashboard
   */
  async updateDashboard(id: number, data: Partial<Dashboard>): Promise<{ dashboard: Dashboard; message: string }> {
    const res = await client.put(`/dashboards/${id}`, data);
    return res.data?.data ?? res.data;
  },

  /**
   * Delete dashboard
   */
  async deleteDashboard(id: number): Promise<{ message: string }> {
    const res = await client.delete(`/dashboards/${id}`);
    return res.data?.data ?? res.data;
  },

  /**
   * Get widget data
   */
  async getWidgetData(dashboardId: number, widgetId: number): Promise<WidgetData> {
    const res = await client.get(`/dashboards/${dashboardId}/widgets/${widgetId}/data`);
    return res.data?.data ?? res.data;
  },

  /**
   * Get multiple widgets data (batch)
   */
  async getBatchWidgetData(dashboardId: number, widgetIds: number[]): Promise<{ widgets: Record<number, WidgetData> }> {
    const res = await client.post(`/dashboards/${dashboardId}/widgets/batch`, { widget_ids: widgetIds });
    return res.data?.data ?? res.data;
  },

  /**
   * Get user preferences
   */
  async getPreferences(): Promise<UserDashboardPreference> {
    const res = await client.get('/dashboards/preferences');
    return res.data?.data ?? res.data;
  },

  /**
   * Update user preferences
   */
  async updatePreferences(data: Partial<UserDashboardPreference>): Promise<{ preferences: UserDashboardPreference; message: string }> {
    const res = await client.put('/dashboards/preferences', data);
    return res.data?.data ?? res.data;
  },

  /**
   * Admin: Get all dashboards
   */
  async adminList(): Promise<{ dashboards: any[] }> {
    const res = await client.get('/admin/dashboards');
    return res.data?.data ?? res.data;
  },

  /**
   * Admin: Assign dashboard to user
   */
  async assignToUser(dashboardId: number, userId: string, setAsDefault = false): Promise<{ message: string; dashboard: Dashboard }> {
    const res = await client.post(`/admin/dashboards/${dashboardId}/assign`, {
      user_id: userId,
      set_as_default: setAsDefault,
    });
    return res.data?.data ?? res.data;
  },

  /**
   * Add section to dashboard
   */
  async addSection(dashboardId: number, data: {
    name_ar: string;
    name_en?: string;
    layout_type?: string;
    layout_config?: any;
    is_collapsible?: boolean;
  }): Promise<{ section: any; message: string }> {
    const res = await client.post(`/dashboards/${dashboardId}/sections`, data);
    return res.data?.data ?? res.data;
  },

  /**
   * Update section
   */
  async updateSection(sectionId: number, data: Partial<any>): Promise<{ section: any; message: string }> {
    const res = await client.put(`/sections/${sectionId}`, data);
    return res.data?.data ?? res.data;
  },

  /**
   * Remove section
   */
  async removeSection(sectionId: number): Promise<{ message: string }> {
    const res = await client.delete(`/sections/${sectionId}`);
    return res.data?.data ?? res.data;
  },

  /**
   * Add widget to section
   */
  async addWidget(dashboardId: number, sectionId: number, data: {
    name_ar: string;
    name_en?: string;
    widget_type: string;
    data_source?: string;
    grid_width?: number;
    grid_height?: number;
    data_config?: any;
    display_config?: any;
    filter_by_user?: boolean;
    filter_by_department?: boolean;
    filter_by_role?: boolean;
    refresh_interval?: number;
    is_real_time?: boolean;
  }): Promise<{ widget: any; message: string }> {
    const res = await client.post(`/dashboards/${dashboardId}/sections/${sectionId}/widgets`, data);
    return res.data?.data ?? res.data;
  },

  /**
   * Update widget
   */
  async updateWidget(widgetId: number, data: Partial<any>): Promise<{ widget: any; message: string }> {
    const res = await client.put(`/widgets/${widgetId}`, data);
    return res.data?.data ?? res.data;
  },

  /**
   * Update widget positions (for drag-and-drop)
   */
  async updateWidgetPositions(dashboardId: number, widgets: Array<{
    id: number;
    grid_x: number;
    grid_y: number;
    sort_order: number;
  }>): Promise<{ message: string }> {
    const res = await client.put(`/dashboards/${dashboardId}/widgets/positions`, { widgets });
    return res.data?.data ?? res.data;
  },

  /**
   * Remove widget
   */
  async removeWidget(widgetId: number): Promise<{ message: string }> {
    const res = await client.delete(`/widgets/${widgetId}`);
    return res.data?.data ?? res.data;
  },
};
