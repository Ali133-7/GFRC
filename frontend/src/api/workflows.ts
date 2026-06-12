import client from "@/api/client";
import type {
  Workflow,
  WorkflowExecution,
  ExecutionPreview,
  WorkflowVersion,
  WorkflowStep,
  WorkflowField,
  WorkflowRule,
  ConditionLogic,
  RuleAction,
} from "@/types/workflow";

export interface WorkflowListParams {
  register_id?: string;
  search?: string;
  is_active?: boolean;
  paginate?: boolean;
  per_page?: number;
}

export const workflowApi = {
  list: (params?: WorkflowListParams) =>
    client.get("/workflows", { params }).then((r) => r.data ?? []),

  get: (id: string) => client.get(`/workflows/${id}`).then((r) => (r.data as Workflow) ?? null),

  create: (payload: Partial<Workflow>) =>
    client.post("/workflows", payload).then((r) => (r.data as Workflow) ?? null),

  update: (id: string, payload: Partial<Workflow>) =>
    client.put(`/workflows/${id}`, payload).then((r) => (r.data as Workflow) ?? null),

  remove: (id: string) => client.delete(`/workflows/${id}`).then((r) => r.data ?? null),
};

export const workflowVersionApi = {
  list: (workflowId: string) =>
    client.get(`/workflows/${workflowId}/versions`).then((r) => (r.data as WorkflowVersion[]) ?? []),

  get: (workflowId: string, versionId: string) =>
    client
      .get(`/workflows/${workflowId}/versions/${versionId}`)
      .then((r) => (r.data as any)?.data?.version ?? (r.data as any)?.version ?? null),

  create: (workflowId: string, change_summary?: string) =>
    client
      .post(`/workflows/${workflowId}/versions`, { change_summary })
      .then((r) => (r.data as WorkflowVersion) ?? null),

  update: (workflowId: string, versionId: string, payload: Partial<WorkflowVersion>) =>
    client
      .put(`/workflows/${workflowId}/versions/${versionId}`, payload)
      .then((r) => (r.data as WorkflowVersion) ?? null),

  publish: (workflowId: string, versionId: string) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/publish`)
      .then((r) => (r.data as WorkflowVersion) ?? null),

  archive: (workflowId: string, versionId: string) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/archive`)
      .then((r) => (r.data as WorkflowVersion) ?? null),

  clone: (workflowId: string, versionId: string, change_summary?: string) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/clone`, { change_summary })
      .then((r) => (r.data as WorkflowVersion) ?? null),

  // Steps
  createStep: (workflowId: string, versionId: string, payload: Partial<WorkflowStep>) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/steps`, payload)
      .then((r) => (r.data as WorkflowStep) ?? null),

  updateStep: (workflowId: string, versionId: string, stepId: string, payload: Partial<WorkflowStep>) =>
    client
      .put(`/workflows/${workflowId}/versions/${versionId}/steps/${stepId}`, payload)
      .then((r) => (r.data as WorkflowStep) ?? null),

  removeStep: (workflowId: string, versionId: string, stepId: string) =>
    client.delete(`/workflows/${workflowId}/versions/${versionId}/steps/${stepId}`).then((r) => r.data),

  reorderSteps: (workflowId: string, versionId: string, steps: Array<{ id: string; sort_order: number }>) =>
    client
      .patch(`/workflows/${workflowId}/versions/${versionId}/steps/reorder`, { steps })
      .then((r) => (r.data as WorkflowStep[]) ?? []),

  // Fields
  createField: (workflowId: string, versionId: string, payload: Partial<WorkflowField>) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/fields`, payload)
      .then((r) => (r.data as WorkflowField) ?? null),

  updateField: (workflowId: string, versionId: string, fieldId: string, payload: Partial<WorkflowField>) =>
    client
      .put(`/workflows/${workflowId}/versions/${versionId}/fields/${fieldId}`, payload)
      .then((r) => (r.data as WorkflowField) ?? null),

  removeField: (workflowId: string, versionId: string, fieldId: string) =>
    client.delete(`/workflows/${workflowId}/versions/${versionId}/fields/${fieldId}`).then((r) => r.data),

  reorderFields: (workflowId: string, versionId: string, fields: Array<{ workflow_field_id: string; sort_order: number }>) =>
    client
      .patch(`/workflows/${workflowId}/versions/${versionId}/fields/reorder`, { fields })
      .then((r) => (r.data as WorkflowField[]) ?? []),

  // Rules
  createRule: (workflowId: string, versionId: string, payload: Partial<WorkflowRule>) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/rules`, payload)
      .then((r) => (r.data as WorkflowRule) ?? null),

  updateRule: (workflowId: string, versionId: string, ruleId: string, payload: Partial<WorkflowRule>) =>
    client
      .put(`/workflows/${workflowId}/versions/${versionId}/rules/${ruleId}`, payload)
      .then((r) => (r.data as WorkflowRule) ?? null),

  removeRule: (workflowId: string, versionId: string, ruleId: string) =>
    client.delete(`/workflows/${workflowId}/versions/${versionId}/rules/${ruleId}`).then((r) => r.data),

  simulateRule: (workflowId: string, versionId: string, ruleId: string, testValues: Record<string, string>) =>
    client
      .post(`/workflows/${workflowId}/versions/${versionId}/rules/${ruleId}/simulate`, { test_values: testValues })
      .then((r) => r.data),

  // Validation Rules
  getValidationRules: (workflowId: string, versionId: string) =>
    client.get(`/workflows/${workflowId}/versions/${versionId}/validations`).then((r) => r.data ?? []),

  createValidationRule: (workflowId: string, versionId: string, payload: any) =>
    client.post(`/workflows/${workflowId}/versions/${versionId}/validations`, payload).then((r) => r.data ?? null),

  updateValidationRule: (workflowId: string, versionId: string, ruleId: string, payload: any) =>
    client.put(`/workflows/${workflowId}/versions/${versionId}/validations/${ruleId}`, payload).then((r) => r.data ?? null),

  removeValidationRule: (workflowId: string, versionId: string, ruleId: string) =>
    client.delete(`/workflows/${workflowId}/versions/${versionId}/validations/${ruleId}`).then((r) => r.data),

  reorderValidationRules: (workflowId: string, versionId: string, rules: Array<{ id: string; sort_order: number }>) =>
    client.patch(`/workflows/${workflowId}/versions/${versionId}/validations/reorder`, { rules }).then((r) => r.data ?? []),

  simulateValidation: (workflowId: string, versionId: string, testValues: Record<string, string>) =>
    client.post(`/workflows/${workflowId}/versions/${versionId}/validations/simulate`, { test_values: testValues }).then((r) => r.data),

  validateField: (workflowId: string, versionId: string, fieldId: string, fieldValue: string, contextValues?: Record<string, string>) =>
    client.post(`/workflows/${workflowId}/versions/${versionId}/validate-field`, {
      field_id: fieldId,
      field_value: fieldValue,
      context_values: contextValues,
    }).then((r) => r.data),

  // Enterprise Rules
  simulateEnterprise: (workflowId: string, versionId: string, testValues: Record<string, string>, context?: Record<string, any>) =>
    client.post(`/workflows/${workflowId}/versions/${versionId}/enterprise/simulate`, { test_values: testValues, context }).then((r) => r.data),
};

export const workflowExecutionBranchApi = {
  getBranchState: (executionId: string) =>
    client.get(`/workflow-executions/${executionId}/branch-state`).then((r) => r.data),

  switchMode: (executionId: string, mode: string, reason?: string) =>
    client.post(`/workflow-executions/${executionId}/switch-mode`, { mode, reason }).then((r) => r.data),

  pause: (executionId: string, reason?: string) =>
    client.post(`/workflow-executions/${executionId}/pause`, { reason }).then((r) => r.data),

  resume: (executionId: string) =>
    client.post(`/workflow-executions/${executionId}/resume`).then((r) => r.data),

  redirect: (executionId: string, targetWorkflowId: string, targetStepId?: string, stateMapping?: Record<string, string>, reason?: string) =>
    client.post(`/workflow-executions/${executionId}/redirect`, {
      target_workflow_id: targetWorkflowId,
      target_step_id: targetStepId,
      state_mapping: stateMapping,
      reason,
    }).then((r) => r.data),

  saveDraft: (executionId: string, values: Record<string, string>) =>
    client.post(`/workflow-executions/${executionId}/save-draft`, { values }).then((r) => r.data),
};

export const workflowExecutionApi = {
  list: (params?: { workflow_id?: string; status?: string; paginate?: boolean; per_page?: number }) =>
    client.get("/workflow-executions", { params }).then((r) => r.data ?? []),

  get: (id: string) =>
    client.get(`/workflow-executions/${id}`).then((r) => (r.data as WorkflowExecution) ?? null),

  start: (workflow_version_id: string) =>
    client.post("/workflow-executions", { workflow_version_id }).then((r) => r.data ?? null),

  submitStep: (id: string, step_index: number, values: Record<string, string>) =>
    client
      .put(`/workflow-executions/${id}/step`, { step_index, values })
      .then((r) => r.data ?? null),

  preview: (workflow_version_id: string, values: Record<string, string>) =>
    client
      .post("/workflow-executions/preview", { workflow_version_id, values })
      .then((r) => (r.data as ExecutionPreview) ?? null),

  complete: (id: string, notes?: string) =>
    client
      .post(`/workflow-executions/${id}/complete`, { notes })
      .then((r) => r.data ?? null),

  cancel: (id: string, reason: string) =>
    client.post(`/workflow-executions/${id}/cancel`, { reason }).then((r) => r.data ?? null),

  // Real-time rule execution
  executeRealTime: (
    id: string, 
    fieldId: string, 
    value: any, 
    values: Record<string, any>,
    options?: { signal?: AbortSignal }
  ) =>
    client
      .post(`/workflow-executions/${id}/execute-realtime`, { field_id: fieldId, value, values }, {
        signal: options?.signal
      })
      .then((r) => r.data ?? null),

  getExecutionStatus: (id: string) =>
    client
      .get(`/workflow-executions/${id}/execution-status`)
      .then((r) => r.data ?? null),
};
