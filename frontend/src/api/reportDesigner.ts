import client from "@/api/client";
import type { 
  ReportSection, 
  ReportObject, 
  DataSource, 
  TableJoin, 
  ReportFilter,
  CalculatedField,
  ChartConfig,
  PivotConfig,
  ReportSchedule,
  ReportTheme,
  BusinessRegister,
  BusinessField,
  RegisterRelationship,
} from "@/types/report";

export interface ReportDesign {
  id?: string;
  name: string;
  name_ar: string;
  description?: string;
  data_source: string;
  sections: ReportSection[];
  joins: TableJoin[];
  filters: ReportFilter[];
  calculatedFields: CalculatedField[];
  charts: ChartConfig[];
  pivotConfig?: PivotConfig;
  theme: string;
  version: number;
  status: "draft" | "published" | "archived";
}

export interface ReportPreviewData {
  data: any[];
  total: number;
  page: number;
  per_page: number;
  aggregations: Record<string, any>;
}

export class ReportDesignerAPI {
  /**
   * Load report design from backend
   */
  static async loadDesign(reportId: string): Promise<ReportDesign> {
    const response = await client.get(`/reports/${reportId}/design`);
    return response.data?.data ?? response.data;
  }

  /**
   * Save report design to backend
   */
  static async saveDesign(reportId: string, design: ReportDesign): Promise<ReportDesign> {
    const response = await client.post(`/reports/${reportId}/design`, design);
    return response.data?.data ?? response.data;
  }

  /**
   * Get preview data for report
   */
  static async getPreviewData(
    reportId: string,
    options: {
      page?: number;
      per_page?: number;
      sort_field?: string;
      sort_direction?: "ASC" | "DESC";
      filters?: ReportFilter[];
    } = {}
  ): Promise<ReportPreviewData> {
    const response = await client.post(`/reports/${reportId}/preview`, options);
    return response.data?.data ?? response.data;
  }

  /**
   * Get available data sources (legacy - database tables)
   */
  static async getDataSources(): Promise<DataSource[]> {
    const response = await client.get("/reports/data-sources");
    return response.data?.data ?? response.data;
  }

  /**
   * Get business registers as report data sources.
   */
  static async getBusinessRegisters(includeInactive = false): Promise<BusinessRegister[]> {
    const response = await client.get("/reports/business-registers", {
      params: { include_inactive: includeInactive },
    });
    return response.data?.data?.registers ?? response.data?.data ?? response.data;
  }

  /**
   * Get business fields for selected registers.
   */
  static async getBusinessFields(registerIds: string[]): Promise<BusinessField[]> {
    const response = await client.post("/reports/business-fields", {
      register_ids: registerIds,
    });
    return response.data?.data?.fields ?? response.data?.data ?? response.data;
  }

  /**
   * Analyze automatic relationships between selected registers.
   */
  static async analyzeRelationships(registerIds: string[]): Promise<RegisterRelationship[]> {
    const response = await client.post("/reports/business-relationships", {
      register_ids: registerIds,
    });
    return response.data?.data?.relationships ?? response.data?.data ?? response.data;
  }

  /**
   * Preview business data for selected registers and fields.
   */
  static async previewBusinessData(
    registerIds: string[],
    fieldIds: string[],
    filters: ReportFilter[] = [],
    limit = 50
  ): Promise<{ data: any[]; total: number }> {
    const response = await client.post("/reports/business-preview", {
      register_ids: registerIds,
      field_ids: fieldIds,
      filters,
      limit,
    });
    return response.data?.data ?? response.data;
  }

  /**
   * Get available fields for a data source
   */
  static async getAvailableFields(
    dataSource: string,
    registerIds?: string[]
  ): Promise<any[]> {
    const params: any = { data_source: dataSource };
    if (registerIds?.length) {
      params.register_ids = registerIds.join(",");
    }
    
    const response = await client.get("/reports/fields/available", { params });
    return response.data?.data?.fields ?? response.data?.data ?? response.data;
  }

  /**
   * Get report templates
   */
  static async getTemplates(): Promise<ReportDesign[]> {
    const response = await client.get("/reports/templates");
    return response.data?.data ?? response.data;
  }

  /**
   * Apply template to current report
   */
  static async applyTemplate(reportId: string, templateId: string): Promise<ReportDesign> {
    const response = await client.post(`/reports/${reportId}/apply-template/${templateId}`);
    return response.data?.data ?? response.data;
  }

  /**
   * Get version history
   */
  static async getVersionHistory(reportId: string): Promise<Array<{
    id: string;
    version: number;
    status: string;
    created_at: string;
    created_by: string;
    change_summary?: string;
  }>> {
    const response = await client.get(`/reports/${reportId}/history`);
    return response.data?.data ?? response.data;
  }

  /**
   * Restore previous version
   */
  static async restoreVersion(reportId: string, versionId: string): Promise<ReportDesign> {
    const response = await client.post(`/reports/${reportId}/restore/${versionId}`);
    return response.data?.data ?? response.data;
  }

  /**
   * Save schedule configuration
   */
  static async saveSchedule(reportId: string, schedule: ReportSchedule): Promise<void> {
    await client.post(`/reports/${reportId}/schedule`, schedule);
  }

  /**
   * Export report
   */
  static async exportReport(
    reportId: string,
    format: "pdf" | "excel" | "csv",
    filters?: ReportFilter[]
  ): Promise<{ download_url: string; filename: string }> {
    const response = await client.post(`/reports/${reportId}/export`, {
      format,
      filters,
    });
    return response.data?.data ?? response.data;
  }

  /**
   * Validate formula
   */
  static async validateFormula(
    formula: string,
    dataSource: string
  ): Promise<{ valid: boolean; error?: string; result_type?: string }> {
    const response = await client.post("/reports/validate-formula", {
      formula,
      data_source: dataSource,
    });
    return response.data?.data ?? response.data;
  }

  /**
   * Test filter
   */
  static async testFilter(
    dataSource: string,
    filters: ReportFilter[]
  ): Promise<{ count: number; sample: any[] }> {
    const response = await client.post("/reports/test-filter", {
      data_source: dataSource,
      filters,
    });
    return response.data?.data ?? response.data;
  }
}
