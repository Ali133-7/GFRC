import { useState, useEffect, useCallback } from 'react';
import { workflowExecutionApi } from '@/api/workflows';

export type ExecutionStatus = 'IDLE' | 'EVALUATING' | 'CALCULATING' | 'READY' | 'ERROR';

export interface ExecutionStatusData {
  execution_id: string;
  status: ExecutionStatus;
  error: string | null;
  is_ready: boolean;
  is_executing: boolean;
}

export interface UseExecutionStatusOptions {
  executionId: string | null;
  pollingInterval?: number;
  enabled?: boolean;
}

export interface UseExecutionStatusReturn {
  status: ExecutionStatus;
  error: string | null;
  isReady: boolean;
  isExecuting: boolean;
  isError: boolean;
  isLoading: boolean;
  refresh: () => Promise<void>;
}

/**
 * Hook for polling execution status
 * 
 * Usage:
 * const { status, isReady, isExecuting } = useExecutionStatus({
 *   executionId: execution?.id,
 *   pollingInterval: 1000, // Poll every second
 *   enabled: true
 * });
 */
export function useExecutionStatus(options: UseExecutionStatusOptions): UseExecutionStatusReturn {
  const {
    executionId,
    pollingInterval = 1000,
    enabled = true,
  } = options;

  const [status, setStatus] = useState<ExecutionStatus>('IDLE');
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const refresh = useCallback(async () => {
    if (!executionId || !enabled) {
      return;
    }

    try {
      setIsLoading(true);
      const data = await workflowExecutionApi.getExecutionStatus(executionId);
      
      setStatus(data.status);
      setError(data.error);
    } catch (err: any) {
      setError(err.message ?? 'Failed to get execution status');
      setStatus('ERROR');
    } finally {
      setIsLoading(false);
    }
  }, [executionId, enabled]);

  // Initial fetch
  useEffect(() => {
    refresh();
  }, [refresh]);

  // Polling
  useEffect(() => {
    if (!enabled || !executionId) {
      return;
    }

    const interval = setInterval(() => {
      // Only poll if executing
      if (status === 'EVALUATING' || status === 'CALCULATING') {
        refresh();
      }
    }, pollingInterval);

    return () => clearInterval(interval);
  }, [executionId, enabled, pollingInterval, status, refresh]);

  return {
    status,
    error,
    isReady: status === 'READY' || status === 'IDLE',
    isExecuting: status === 'EVALUATING' || status === 'CALCULATING',
    isError: status === 'ERROR',
    isLoading,
    refresh,
  };
}
