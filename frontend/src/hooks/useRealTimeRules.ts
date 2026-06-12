import { useCallback, useState, useEffect, useRef } from 'react';
import { workflowExecutionApi } from '@/api/workflows';

export interface RealTimeExecutionResult {
  success: boolean;
  validation_results?: any[];
  workflow_results?: any[];
  financial_results?: {
    financial_values: Record<string, number>;
    subtotal: number;
    discounts: number;
    fees: number;
    taxes: number;
    insurance: number;
    total: number;
  };
  affected_rule_count: number;
  error?: string;
}

export interface UseRealTimeRulesOptions {
  executionId: string | null;
  enabled?: boolean;
  debounceMs?: number;
  onExecute?: (result: RealTimeExecutionResult) => void;
  onError?: (error: Error) => void;
}

export interface UseRealTimeRulesReturn {
  isExecuting: boolean;
  isReady: boolean;
  hasError: boolean;
  error: string | null;
  execute: (fieldId: string, value: any, values: Record<string, any>) => Promise<void>;
  cancel: () => void;
  reset: () => void;
}

/**
 * Hook for real-time rule execution
 * 
 * FIXED: Prevents infinite loops and request storms by:
 * 1. Using refs for callbacks to prevent re-subscription
 * 2. Proper cleanup of timeouts and abort controllers
 * 3. Stable dependencies in useCallback
 * 4. Ignoring AbortError (expected when cancelling)
 */
export function useRealTimeRules(options: UseRealTimeRulesOptions): UseRealTimeRulesReturn {
  const {
    executionId,
    enabled = true,
    debounceMs = 500, // Increased default debounce to 500ms
    onExecute,
    onError,
  } = options;

  const [isExecuting, setIsExecuting] = useState(false);
  const [isReady, setIsReady] = useState(true);
  const [hasError, setHasError] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // FIXED: Use refs for timeouts and abort controllers
  const timeoutIdRef = useRef<NodeJS.Timeout | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);
  
  // FIXED: Use refs for callbacks to prevent re-subscription
  const onExecuteRef = useRef(onExecute);
  const onErrorRef = useRef(onError);
  
  // Update refs when callbacks change (without triggering re-subscription)
  useEffect(() => {
    onExecuteRef.current = onExecute;
  }, [onExecute]);
  
  useEffect(() => {
    onErrorRef.current = onError;
  }, [onError]);

  // FIXED: Cleanup on unmount
  useEffect(() => {
    return () => {
      if (timeoutIdRef.current) {
        clearTimeout(timeoutIdRef.current);
        timeoutIdRef.current = null;
      }
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
        abortControllerRef.current = null;
      }
    };
  }, []);

  // FIXED: Actual API execution function
  const executeRequest = useCallback(async (
    fieldId: string,
    value: any,
    values: Record<string, any>
  ) => {
    console.log('[useRealTimeRules] executeRequest called:', { fieldId, value, valuesCount: Object.keys(values).length });
    
    if (!enabled || !executionId) {
      console.warn('[useRealTimeRules] Skipping - not enabled or no executionId');
      return;
    }

    // FIXED: Cancel previous request if still pending
    if (abortControllerRef.current) {
      console.log('[useRealTimeRules] Cancelling previous request');
      abortControllerRef.current.abort();
    }
    abortControllerRef.current = new AbortController();

    try {
      setIsExecuting(true);
      setIsReady(false);
      setHasError(false);
      setError(null);

      console.log('[useRealTimeRules] Making API call...');
      
      // FIXED: Pass abort signal to API call
      const result = await workflowExecutionApi.executeRealTime(
        executionId, 
        fieldId, 
        value, 
        values,
        { signal: abortControllerRef.current.signal }
      );

      console.log('[useRealTimeRules] API response:', result);

      if (result.success) {
        setIsReady(true);
        // FIXED: Use ref instead of direct callback
        onExecuteRef.current?.(result);
      } else {
        setHasError(true);
        setError(result.error ?? 'Unknown error');
        // FIXED: Use ref instead of direct callback
        onErrorRef.current?.(new Error(result.error ?? 'Unknown error'));
      }
    } catch (error: any) {
      console.log('[useRealTimeRules] API error:', error);
      
      // FIXED: Ignore AbortError and Axios Cancel - these are normal behavior when cancelling requests
      if (error?.name === 'AbortError' || error?.message === 'canceled') {
        console.log('[useRealTimeRules] Request was cancelled, ignoring');
        return;
      }
      
      setHasError(true);
      setError(error.message ?? 'Execution failed');
      // FIXED: Use ref instead of direct callback
      onErrorRef.current?.(error);
    } finally {
      setIsExecuting(false);
      abortControllerRef.current = null;
    }
  }, [executionId, enabled]); // FIXED: Removed onExecute, onError from deps

  // FIXED: Debounced execute function
  const execute = useCallback(async (
    fieldId: string,
    value: any,
    values: Record<string, any>
  ) => {
    console.log('[useRealTimeRules] execute called:', { fieldId, value, enabled, executionId });
    
    if (!enabled || !executionId) {
      console.warn('[useRealTimeRules] Skipping execute - not enabled or no executionId');
      return;
    }

    // FIXED: Clear pending timeout
    if (timeoutIdRef.current) {
      clearTimeout(timeoutIdRef.current);
      timeoutIdRef.current = null;
    }

    console.log('[useRealTimeRules] Setting timeout for', debounceMs, 'ms');
    
    // FIXED: Debounce execution to prevent request storms
    timeoutIdRef.current = setTimeout(() => {
      console.log('[useRealTimeRules] Timeout elapsed, calling executeRequest');
      executeRequest(fieldId, value, values);
    }, debounceMs);
  }, [executionId, enabled, debounceMs, executeRequest]); // FIXED: Stable deps

  const cancel = useCallback(() => {
    // FIXED: Clear timeout
    if (timeoutIdRef.current) {
      clearTimeout(timeoutIdRef.current);
      timeoutIdRef.current = null;
    }
    
    // FIXED: Abort pending request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      abortControllerRef.current = null;
    }
    
    setIsExecuting(false);
  }, []);

  const reset = useCallback(() => {
    cancel();
    setIsReady(true);
    setHasError(false);
    setError(null);
  }, [cancel]);

  return {
    isExecuting,
    isReady,
    hasError,
    error,
    execute,
    cancel,
    reset,
  };
}
