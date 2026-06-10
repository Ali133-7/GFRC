import { useCallback, useState, useRef, useEffect } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import {
  workflowExecutionApi,
  workflowExecutionBranchApi,
} from "@/api/workflows";
import type { WorkflowExecution, WorkflowVersion } from "@/types/workflow";

interface UseWorkflowExecutionOptions {
  version: WorkflowVersion | null;
  executionId?: string;
}

export function useWorkflowExecution({ version, executionId }: UseWorkflowExecutionOptions) {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [localExecutionId, setLocalExecutionId] = useState<string | null>(executionId ?? null);
  const [stepIndex, setStepIndex] = useState(0);
  const [values, setValues] = useState<Record<string, any>>({});
  const [fieldStates, setFieldStates] = useState<Record<string, any>>({});
  const [error, setError] = useState<string | null>(null);
  const [calculatingFor, setCalculatingFor] = useState(0);
  const calculatingTimer = useRef<ReturnType<typeof setInterval> | null>(null);

  const executionQuery = useQuery({
    queryKey: ["workflow-execution", localExecutionId],
    queryFn: () => (localExecutionId ? workflowExecutionApi.get(localExecutionId) : null),
    enabled: !!localExecutionId,
  });

  const currentExecution = executionQuery.data as WorkflowExecution | null;

  const startMutation = useMutation({
    mutationFn: () => workflowExecutionApi.start(version!.id),
    onSuccess: (data: any) => {
      setLocalExecutionId(data?.execution?.id ?? null);
      setStepIndex(0);
      setValues({});
      setError(null);
    },
    onError: (err: any) => {
      setError(err?.message ?? "فشل بدء التنفيذ");
    },
  });

  const submitStepMutation = useMutation({
    mutationFn: (vars: { step_index: number; values: Record<string, any> }) =>
      workflowExecutionApi.submitStep(localExecutionId!, vars.step_index, vars.values),
    onSuccess: (data: any) => {
      setStepIndex(data?.execution?.current_step_index ?? stepIndex + 1);
      setFieldStates(data?.field_states ?? {});
      if (data?.modified_values) {
        setValues((prev) => ({ ...prev, ...data.modified_values }));
      }
    },
    onError: (err: any) => {
      setError(err?.message ?? "فشل حفظ الخطوة");
    },
  });

  const previewMutation = useMutation({
    mutationFn: (vars: { values: Record<string, any> }) =>
      workflowExecutionApi.preview(version!.id, vars.values),
  });

  const completeMutation = useMutation({
    mutationFn: (notes?: string) => workflowExecutionApi.complete(localExecutionId!, notes),
    onSuccess: (data: any) => {
      qc.invalidateQueries({ queryKey: ["workflow-execution", localExecutionId] });
      if (data?.receipt?.id) {
        navigate(`/receipts/${data.receipt.id}`);
      }
    },
    onError: (err: any) => {
      setError(err?.message ?? "فشل إنشاء الوصل");
    },
  });

  const cancelMutation = useMutation({
    mutationFn: (reason: string) => workflowExecutionApi.cancel(localExecutionId!, reason),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["workflow-execution", localExecutionId] });
    },
  });

  const saveDraftMutation = useMutation({
    mutationFn: (vars: { values: Record<string, any> }) =>
      workflowExecutionBranchApi.saveDraft(localExecutionId!, vars.values),
  });

  const handleFieldChange = useCallback((fieldId: string, value: any) => {
    setValues((prev) => {
      const next = { ...prev, [fieldId]: value };
      // Trigger preview fee calculation
      previewMutation.mutate({ values: next });
      return next;
    });
  }, [previewMutation]);

  const handleNext = useCallback(
    (stepValues: Record<string, any>) => {
      if (!localExecutionId) return;
      const merged = { ...values, ...stepValues };
      setValues(merged);
      submitStepMutation.mutate({ step_index: stepIndex, values: merged });
    },
    [localExecutionId, stepIndex, values, submitStepMutation]
  );

  const safeComplete = useCallback(
    async (notes?: string) => {
      setError(null);

      // Refetch current execution state to verify it hasn't been completed already
      const fresh = await qc.fetchQuery({
        queryKey: ["workflow-execution", localExecutionId],
        queryFn: () => workflowExecutionApi.get(localExecutionId!),
        staleTime: 0,
      });

      const freshExecution = fresh as WorkflowExecution | null;
      if (freshExecution?.status === "completed") {
        if (freshExecution?.receipt_id) {
          navigate(`/receipts/${freshExecution.receipt_id}`);
        }
        return;
      }

      if (freshExecution?.status !== "in_progress") {
        setError("العملية ليست نشطة. لا يمكن إكمالها.");
        return;
      }

      try {
        await completeMutation.mutateAsync(notes);
      } catch (err: any) {
        if (err?.response?.status === 409) {
          setError("تم إكمال العملية مسبقاً. تحقق من قائمة الوصولات.");
        } else {
          setError("حدث خطأ في الاتصال. تحقق من حالة العملية قبل إعادة المحاولة.");
        }
      }
    },
    [localExecutionId, completeMutation, navigate, qc]
  );

  // Track calculation duration
  useEffect(() => {
    if (previewMutation.isPending) {
      const start = Date.now();
      calculatingTimer.current = setInterval(() => {
        setCalculatingFor(Date.now() - start);
      }, 100);
    } else {
      if (calculatingTimer.current) {
        clearInterval(calculatingTimer.current);
        calculatingTimer.current = null;
      }
      setCalculatingFor(0);
    }
    return () => {
      if (calculatingTimer.current) {
        clearInterval(calculatingTimer.current);
      }
    };
  }, [previewMutation.isPending]);

  // Auto-save draft every 30 seconds
  useEffect(() => {
    if (!localExecutionId || !values) return;
    const interval = setInterval(() => {
      saveDraftMutation.mutate({ values });
    }, 30000);
    return () => clearInterval(interval);
  }, [localExecutionId, values, saveDraftMutation]);

  return {
    executionId: localExecutionId,
    execution: currentExecution,
    stepIndex,
    values,
    fieldStates,
    error,
    isFetching: previewMutation.isPending,
    calculatingFor,
    isSubmitting: submitStepMutation.isPending,
    isCompleting: completeMutation.isPending,
    start: startMutation.mutate,
    handleFieldChange,
    handleNext,
    safeComplete,
    cancel: cancelMutation.mutate,
    refetchExecution: executionQuery.refetch,
    setError,
  };
}
