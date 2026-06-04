import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { workflowVersionApi } from "@/api/workflows";

export const useEnterpriseSimulation = () =>
  useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      testValues,
      context,
    }: {
      workflowId: string;
      versionId: string;
      testValues: Record<string, string>;
      context?: Record<string, any>;
    }) => workflowVersionApi.simulateEnterprise(workflowId, versionId, testValues, context),
  });

export const useEnterpriseRules = (workflowId: string, versionId: string) =>
  useQuery({
    queryKey: ["enterprise-rules", workflowId, versionId],
    queryFn: () => workflowVersionApi.getValidationRules(workflowId, versionId),
    enabled: !!workflowId && !!versionId,
  });

export const useCreateEnterpriseRule = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      payload: any;
    }) => workflowVersionApi.createValidationRule(workflowId, versionId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["enterprise-rules", vars.workflowId, vars.versionId] }),
  });
};

export const useUpdateEnterpriseRule = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      ruleId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      ruleId: string;
      payload: any;
    }) => workflowVersionApi.updateValidationRule(workflowId, versionId, ruleId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["enterprise-rules", vars.workflowId, vars.versionId] }),
  });
};

export const useDeleteEnterpriseRule = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      ruleId,
    }: {
      workflowId: string;
      versionId: string;
      ruleId: string;
    }) => workflowVersionApi.removeValidationRule(workflowId, versionId, ruleId),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["enterprise-rules", vars.workflowId, vars.versionId] }),
  });
};
