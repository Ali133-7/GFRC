import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  workflowApi,
  workflowVersionApi,
  workflowExecutionApi,
  workflowExecutionBranchApi,
} from "@/api/workflows";
import type { WorkflowListParams } from "@/api/workflows";
import { helpApi } from "@/api/help";

// Workflows
export const useWorkflows = (params?: WorkflowListParams) =>
  useQuery({
    queryKey: ["workflows", params],
    queryFn: () => workflowApi.list(params),
  });

export const useWorkflow = (id: string) =>
  useQuery({
    queryKey: ["workflows", id],
    queryFn: () => workflowApi.get(id),
    enabled: !!id,
  });

export const useCreateWorkflow = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: workflowApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["workflows"] }),
  });
};

export const useUpdateWorkflow = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Parameters<typeof workflowApi.update>[1] }) =>
      workflowApi.update(id, payload),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ["workflows", vars.id] });
      qc.invalidateQueries({ queryKey: ["workflows"] });
    },
  });
};

export const useDeleteWorkflow = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: workflowApi.remove,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["workflows"] }),
  });
};

// Versions
export const useWorkflowVersions = (workflowId: string) =>
  useQuery({
    queryKey: ["workflows", workflowId, "versions"],
    queryFn: () => workflowVersionApi.list(workflowId),
    enabled: !!workflowId,
  });

export const useWorkflowVersion = (workflowId: string, versionId: string) =>
  useQuery({
    queryKey: ["workflows", workflowId, "versions", versionId],
    queryFn: () => workflowVersionApi.get(workflowId, versionId),
    enabled: !!workflowId && !!versionId,
  });

export const useCreateVersion = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ workflowId, change_summary }: { workflowId: string; change_summary?: string }) =>
      workflowVersionApi.create(workflowId, change_summary),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions"] }),
  });
};

export const usePublishVersion = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ workflowId, versionId }: { workflowId: string; versionId: string }) =>
      workflowVersionApi.publish(workflowId, versionId),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] });
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions"] });
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId] });
    },
  });
};

export const useCloneVersion = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      change_summary,
    }: {
      workflowId: string;
      versionId: string;
      change_summary?: string;
    }) => workflowVersionApi.clone(workflowId, versionId, change_summary),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions"] }),
  });
};

// Steps
export const useCreateStep = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      payload: Parameters<typeof workflowVersionApi.createStep>[2];
    }) => workflowVersionApi.createStep(workflowId, versionId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useUpdateStep = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      stepId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      stepId: string;
      payload: Parameters<typeof workflowVersionApi.updateStep>[3];
    }) => workflowVersionApi.updateStep(workflowId, versionId, stepId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useDeleteStep = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      stepId,
    }: {
      workflowId: string;
      versionId: string;
      stepId: string;
    }) => workflowVersionApi.removeStep(workflowId, versionId, stepId),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

// Fields
export const useCreateWorkflowField = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      payload: Parameters<typeof workflowVersionApi.createField>[2];
    }) => workflowVersionApi.createField(workflowId, versionId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useUpdateWorkflowField = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      fieldId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      fieldId: string;
      payload: Parameters<typeof workflowVersionApi.updateField>[3];
    }) => workflowVersionApi.updateField(workflowId, versionId, fieldId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useDeleteWorkflowField = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      fieldId,
    }: {
      workflowId: string;
      versionId: string;
      fieldId: string;
    }) => workflowVersionApi.removeField(workflowId, versionId, fieldId),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useReorderWorkflowFields = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      fields,
    }: {
      workflowId: string;
      versionId: string;
      fields: Array<{ workflow_field_id: string; sort_order: number }>;
    }) => workflowVersionApi.reorderFields(workflowId, versionId, fields),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

// Rules
export const useCreateWorkflowRule = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      payload,
    }: {
      workflowId: string;
      versionId: string;
      payload: Parameters<typeof workflowVersionApi.createRule>[2];
    }) => workflowVersionApi.createRule(workflowId, versionId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useUpdateWorkflowRule = () => {
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
      payload: Parameters<typeof workflowVersionApi.updateRule>[3];
    }) => workflowVersionApi.updateRule(workflowId, versionId, ruleId, payload),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

export const useDeleteWorkflowRule = () => {
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
    }) => workflowVersionApi.removeRule(workflowId, versionId, ruleId),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ["workflows", vars.workflowId, "versions", vars.versionId] }),
  });
};

// Executions
export const useStartExecution = () =>
  useMutation({
    mutationFn: (workflow_version_id: string) => workflowExecutionApi.start(workflow_version_id),
  });

export const useSubmitStep = () =>
  useMutation({
    mutationFn: ({
      id,
      step_index,
      values,
    }: {
      id: string;
      step_index: number;
      values: Record<string, string>;
    }) => workflowExecutionApi.submitStep(id, step_index, values),
  });

export const usePreviewExecution = () =>
  useMutation({
    mutationFn: ({
      workflow_version_id,
      values,
    }: {
      workflow_version_id: string;
      values: Record<string, string>;
    }) => workflowExecutionApi.preview(workflow_version_id, values),
  });

export const useCompleteExecution = () =>
  useMutation({
    mutationFn: ({ id, notes }: { id: string; notes?: string }) =>
      workflowExecutionApi.complete(id, notes),
  });

// Help Center
export const useHelpArticles = (pageKey?: string) =>
  useQuery({
    queryKey: ["help", pageKey],
    queryFn: () => (pageKey ? helpApi.getByPageKey(pageKey) : helpApi.list()),
    enabled: !!pageKey,
  });

export const useAllHelpArticles = (search?: string) =>
  useQuery({
    queryKey: ["help", "all", search],
    queryFn: () => helpApi.list(search ? { search } : undefined),
  });

export const useSeedHelp = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: helpApi.seed,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["help"] }),
  });
};

// Field Validation (real-time)
export const useFieldValidation = () => {
  return useMutation({
    mutationFn: ({
      workflowId,
      versionId,
      fieldId,
      fieldValue,
      contextValues,
    }: {
      workflowId: string;
      versionId: string;
      fieldId: string;
      fieldValue: string;
      contextValues?: Record<string, string>;
    }) => workflowVersionApi.validateField(workflowId, versionId, fieldId, fieldValue, contextValues),
  });
};

// Branch Control
export const useBranchState = (executionId: string) =>
  useQuery({
    queryKey: ["branch-state", executionId],
    queryFn: () => workflowExecutionBranchApi.getBranchState(executionId),
    enabled: !!executionId,
  });

export const useSwitchMode = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ executionId, mode, reason }: { executionId: string; mode: string; reason?: string }) =>
      workflowExecutionBranchApi.switchMode(executionId, mode, reason),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["branch-state", vars.executionId] }),
  });
};

export const usePauseExecution = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ executionId, reason }: { executionId: string; reason?: string }) =>
      workflowExecutionBranchApi.pause(executionId, reason),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["branch-state", vars.executionId] }),
  });
};

export const useResumeExecution = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (executionId: string) => workflowExecutionBranchApi.resume(executionId),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["branch-state", vars] }),
  });
};

export const useRedirectExecution = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      executionId,
      targetWorkflowId,
      targetStepId,
      stateMapping,
      reason,
    }: {
      executionId: string;
      targetWorkflowId: string;
      targetStepId?: string;
      stateMapping?: Record<string, string>;
      reason?: string;
    }) => workflowExecutionBranchApi.redirect(executionId, targetWorkflowId, targetStepId, stateMapping, reason),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["branch-state", vars.executionId] }),
  });
};

export const useSaveDraft = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ executionId, values }: { executionId: string; values: Record<string, string> }) =>
      workflowExecutionBranchApi.saveDraft(executionId, values),
    onSuccess: (_, vars) => qc.invalidateQueries({ queryKey: ["branch-state", vars.executionId] }),
  });
};
