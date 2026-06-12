import React, { useEffect, useCallback, useRef } from 'react';
import { useRealTimeRules, RealTimeExecutionResult } from '@/hooks/useRealTimeRules';
import { formatNumber } from '@/utils/formatNumber';

interface RealTimeRuleExecutorProps {
  executionId: string;
  values: Record<string, any>;
  onValuesUpdate?: (updatedValues: Record<string, any>) => void;
  onFinancialUpdate?: (financials: {
    subtotal: number;
    discounts: number;
    fees: number;
    taxes: number;
    insurance: number;
    total: number;
  }) => void;
  children: React.ReactNode;
}

/**
 * RealTimeRuleExecutor Component
 * 
 * Wraps form inputs and automatically triggers real-time rule execution
 * when field values change.
 * 
 * FIXED: Prevents infinite request loops by using refs instead of state
 * for tracking previous values and callbacks.
 */
export function RealTimeRuleExecutor({
  executionId,
  values,
  onValuesUpdate,
  onFinancialUpdate,
  children,
}: RealTimeRuleExecutorProps) {
  // FIXED: Use refs instead of state to prevent re-renders causing loops
  const previousValuesRef = useRef<Record<string, any>>({});
  const changedFieldIdRef = useRef<string | null>(null);
  
  // FIXED: Use refs for callbacks to prevent stale closures and re-subscription
  const onValuesUpdateRef = useRef(onValuesUpdate);
  const onFinancialUpdateRef = useRef(onFinancialUpdate);
  
  // Update refs when callbacks change (without triggering re-execution)
  useEffect(() => {
    onValuesUpdateRef.current = onValuesUpdate;
  }, [onValuesUpdate]);
  
  useEffect(() => {
    onFinancialUpdateRef.current = onFinancialUpdate;
  }, [onFinancialUpdate]);

  // FIXED: Stable callback using refs - no dependencies to prevent re-creation
  const handleExecute = useCallback((result: RealTimeExecutionResult) => {
    console.log('[RealTimeRuleExecutor] handleExecute called with:', result);
    
    // FIXED: Guard - don't process if result is not successful or has no financial data
    if (!result.success || !result.financial_results) {
      console.warn('[RealTimeRuleExecutor] Skipping - no success or no financial_results');
      return;
    }
    
    console.log('[RealTimeRuleExecutor] Financial results:', result.financial_results);
    
    // FIXED: Guard - don't update if financial_values is empty and subtotal is 0
    const subtotalNum = typeof result.financial_results.subtotal === 'string' 
      ? parseFloat(result.financial_results.subtotal) 
      : result.financial_results.subtotal;
    
    const hasFinancialValues = 
      (result.financial_results.financial_values && Object.keys(result.financial_results.financial_values).length > 0) ||
      (subtotalNum ?? 0) > 0;
    
    console.log('[RealTimeRuleExecutor] hasFinancialValues:', hasFinancialValues);
    
    if (!hasFinancialValues) {
      console.warn('[RealTimeRuleExecutor] Skipping - no financial values');
      return;
    }
    
    // Update financial values
    onFinancialUpdateRef.current?.(result.financial_results);
    
    // Update field values from rule effects
    if (result.financial_results.financial_values && Object.keys(result.financial_results.financial_values).length > 0) {
      console.log('[RealTimeRuleExecutor] Updating values:', result.financial_results.financial_values);
      // FIXED: Use previousValuesRef.current instead of values to avoid stale closure
      // Format numbers to remove trailing zeros
      const formattedValues: Record<string, any> = {};
      for (const [key, value] of Object.entries(result.financial_results.financial_values)) {
        formattedValues[key] = formatNumber(value);
      }
      
      const updatedValues = { 
        ...previousValuesRef.current, 
        ...formattedValues 
      };
      onValuesUpdateRef.current?.(updatedValues);
    }
  }, []); // FIXED: Empty deps - uses refs, stable callback

  const handleError = useCallback((error: Error) => {
    // FIXED: Don't log AbortError or Axios Cancel - these are expected when cancelling requests
    if (error.name === 'AbortError' || error.message === 'canceled') {
      return;
    }
    console.error('Real-time rule execution error:', error);
  }, []);

  const { execute } = useRealTimeRules({
    executionId,
    enabled: true,
    debounceMs: 500, // Increased debounce to 500ms for better UX
    onExecute: handleExecute,
    onError: handleError,
  });

  // FIXED: Detect value changes and trigger execution without causing loops
  useEffect(() => {
    console.log('[RealTimeRuleExecutor] useEffect triggered');
    console.log('[RealTimeRuleExecutor] Current values:', values);
    console.log('[RealTimeRuleExecutor] Previous values:', previousValuesRef.current);
    
    // Find ALL changed fields (not just the first one)
    const changedFields = Object.keys(values).filter(
      (key) => values[key] !== previousValuesRef.current[key]
    );

    console.log('[RealTimeRuleExecutor] Changed fields:', changedFields);

    if (changedFields.length === 0) {
      console.log('[RealTimeRuleExecutor] No changes detected, skipping execution');
      return;
    }

    // FIXED: Update ref BEFORE execute to prevent re-triggering
    // IMPORTANT: Must clone to avoid reference issues
    previousValuesRef.current = JSON.parse(JSON.stringify(values));

    // Trigger execution for EACH changed field
    for (const fieldId of changedFields) {
      console.log('[RealTimeRuleExecutor] Triggering execution for field:', fieldId, 'with value:', values[fieldId]);
      execute(fieldId, values[fieldId], values);
    }
    
    console.log('[RealTimeRuleExecutor] All executions triggered');
    
    // FIXED: Removed setPreviousValues(values) which caused re-render loop
  }, [values, execute]); // FIXED: Removed previousValues from deps

  return <>{children}</>;
}
