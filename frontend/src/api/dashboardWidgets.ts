import client from '@/api/client';
import type { DashboardWidgetItem, AvailableRegister, RegisterField, DashboardLayout } from '@/components/dashboard/types';

function normalizeArrayResponse<T>(...candidates: unknown[]): T[] {
  for (const candidate of candidates) {
    if (Array.isArray(candidate)) {
      return candidate as T[];
    }

    if (candidate && typeof candidate === 'object') {
      const nestedData = (candidate as { data?: unknown }).data;
      if (Array.isArray(nestedData)) {
        return nestedData as T[];
      }
    }
  }

  return [];
}

export const dashboardWidgetsApi = {
  async getLayout(): Promise<DashboardLayout> {
    const response = await client.get('/dashboard/layout');
    const payload = response.data?.data ?? response.data ?? {};
    return {
      id: payload.id || '',
      name: payload.name || '',
      grid_columns: payload.grid_columns ?? 12,
      widgets: normalizeArrayResponse<DashboardWidgetItem>(
        payload.widgets,
        payload.data?.widgets,
        payload.data,
        response.data
      ),
    };
  },

  async saveLayout(widgets: DashboardWidgetItem[]): Promise<void> {
    await client.post('/dashboard/layout', { widgets });
  },

  async getWidgetData(widget: DashboardWidgetItem): Promise<Record<string, any>> {
    const response = await client.post('/dashboard/widgets/data', { widget });
    return response.data?.data ?? response.data ?? {};
  },

  async getAvailableRegisters(): Promise<AvailableRegister[]> {
    const response = await client.get('/dashboard/registers');
    return normalizeArrayResponse<AvailableRegister>(
      response.data?.registers,
      response.data?.data?.registers,
      response.data?.data,
      response.data
    );
  },

  async getRegisterFields(registerId: string): Promise<RegisterField[]> {
    const response = await client.get(`/dashboard/registers/${registerId}/fields`);
    return normalizeArrayResponse<RegisterField>(
      response.data?.fields,
      response.data?.data?.fields,
      response.data?.data,
      response.data
    );
  },
};
