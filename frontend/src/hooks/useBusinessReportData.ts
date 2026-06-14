import { useQuery, useMutation } from "@tanstack/react-query";
import { ReportDesignerAPI } from "@/api/reportDesigner";
import type { BusinessRegister, BusinessField, RegisterRelationship, ReportFilter } from "@/types/report";

const STALE_TIME = 5 * 60 * 1000;
const EMPTY_ARRAY: never[] = [];

export function useBusinessRegisters(includeInactive = false) {
  return useQuery<BusinessRegister[]>({
    queryKey: ["business-registers", includeInactive],
    queryFn: () => ReportDesignerAPI.getBusinessRegisters(includeInactive),
    select: (data) => (Array.isArray(data) ? data : EMPTY_ARRAY),
    staleTime: STALE_TIME,
  });
}

export function useBusinessFields(registerIds: string[]) {
  return useQuery<BusinessField[]>({
    queryKey: ["business-fields", registerIds],
    queryFn: () => ReportDesignerAPI.getBusinessFields(registerIds),
    select: (data) => (Array.isArray(data) ? data : EMPTY_ARRAY),
    enabled: registerIds.length > 0,
    staleTime: STALE_TIME,
  });
}

export function useRegisterRelationships(registerIds: string[]) {
  return useQuery<RegisterRelationship[]>({
    queryKey: ["business-relationships", registerIds],
    queryFn: () => ReportDesignerAPI.analyzeRelationships(registerIds),
    select: (data) => (Array.isArray(data) ? data : EMPTY_ARRAY),
    enabled: registerIds.length >= 2,
    staleTime: STALE_TIME,
  });
}

export function useBusinessPreview() {
  return useMutation<
    { data: any[]; total: number },
    Error,
    { registerIds: string[]; fieldIds: string[]; filters?: ReportFilter[]; limit?: number }
  >({
    mutationFn: ({ registerIds, fieldIds, filters, limit }) =>
      ReportDesignerAPI.previewBusinessData(registerIds, fieldIds, filters, limit),
  });
}
