import React from 'react';
import { useExecutionStatus, ExecutionStatus } from '@/hooks/useExecutionStatus';

interface ExecutionStatusIndicatorProps {
  executionId: string;
  pollingInterval?: number;
  showDetails?: boolean;
}

/**
 * ExecutionStatusIndicator Component
 * 
 * Displays the current execution status with visual indicators.
 * 
 * States:
 * - IDLE: No execution in progress (green)
 * - EVALUATING: Rules being evaluated (yellow)
 * - CALCULATING: Financial calculations in progress (orange)
 * - READY: Execution complete, can proceed (green)
 * - ERROR: Execution failed (red)
 * 
 * Usage:
 * <ExecutionStatusIndicator executionId={execution?.id} />
 */
export function ExecutionStatusIndicator({
  executionId,
  pollingInterval = 1000,
  showDetails = false,
}: ExecutionStatusIndicatorProps) {
  const { status, error, isReady, isExecuting, isError, isLoading } = useExecutionStatus({
    executionId,
    pollingInterval,
    enabled: true,
  });

  const getStatusConfig = (status: ExecutionStatus) => {
    switch (status) {
      case 'IDLE':
        return {
          color: 'var(--color-text-success)',
          bg: 'var(--color-background-success)',
          icon: '✓',
          label: 'جاهز',
          pulse: false,
        };
      case 'EVALUATING':
        return {
          color: 'var(--color-text-warning)',
          bg: 'var(--color-background-warning)',
          icon: '⚡',
          label: 'جاري تقييم القواعد',
          pulse: true,
        };
      case 'CALCULATING':
        return {
          color: 'var(--color-text-info)',
          bg: 'var(--color-background-info)',
          icon: '🔢',
          label: 'جاري الحساب',
          pulse: true,
        };
      case 'READY':
        return {
          color: 'var(--color-text-success)',
          bg: 'var(--color-background-success)',
          icon: '✓',
          label: 'جاهز للمتابعة',
          pulse: false,
        };
      case 'ERROR':
        return {
          color: 'var(--color-text-danger)',
          bg: 'var(--color-background-danger)',
          icon: '✗',
          label: 'خطأ',
          pulse: false,
        };
      default:
        return {
          color: 'var(--color-text-secondary)',
          bg: 'var(--color-background-secondary)',
          icon: '○',
          label: status,
          pulse: false,
        };
    }
  };

  const config = getStatusConfig(status);

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '8px 12px',
        background: config.bg,
        borderRadius: '6px',
        fontSize: '13px',
        fontWeight: 500,
        color: config.color,
      }}
    >
      <span
        style={{
          animation: config.pulse ? 'pulse 1.5s ease-in-out infinite' : 'none',
        }}
      >
        {config.icon}
      </span>
      <span>{config.label}</span>
      {showDetails && error && (
        <span style={{ fontSize: '11px', opacity: 0.8 }}>{error}</span>
      )}
      {isLoading && (
        <span
          style={{
            width: '12px',
            height: '12px',
            border: '2px solid currentColor',
            borderTopColor: 'transparent',
            borderRadius: '50%',
            animation: 'spin 0.8s linear infinite',
          }}
        />
      )}
    </div>
  );
}

interface NextButtonProps {
  executionId: string;
  onClick: () => void;
  disabled?: boolean;
  children: React.ReactNode;
}

/**
 * NextButton Component
 * 
 * A button that is automatically disabled during rule execution.
 * 
 * Usage:
 * <NextButton executionId={execution?.id} onClick={handleNext}>
 *   التالي
 * </NextButton>
 */
export function NextButton({
  executionId,
  onClick,
  disabled = false,
  children,
}: NextButtonProps) {
  const { isReady, isExecuting } = useExecutionStatus({
    executionId,
    pollingInterval: 500,
    enabled: true,
  });

  const isDisabled = disabled || !isReady || isExecuting;

  return (
    <button
      onClick={onClick}
      disabled={isDisabled}
      style={{
        padding: '10px 20px',
        fontSize: '14px',
        fontWeight: 500,
        background: isDisabled
          ? 'var(--color-background-secondary)'
          : 'var(--color-background-success)',
        color: isDisabled
          ? 'var(--color-text-secondary)'
          : 'var(--color-text-success)',
        border: '0.5px solid var(--color-border-secondary)',
        borderRadius: 'var(--border-radius-md)',
        cursor: isDisabled ? 'not-allowed' : 'pointer',
        fontFamily: 'inherit',
        opacity: isDisabled ? 0.6 : 1,
        position: 'relative',
      }}
    >
      {children}
      {isExecuting && (
        <span
          style={{
            position: 'absolute',
            right: '10px',
            top: '50%',
            transform: 'translateY(-50%)',
            width: '16px',
            height: '16px',
            border: '2px solid currentColor',
            borderTopColor: 'transparent',
            borderRadius: '50%',
            animation: 'spin 0.8s linear infinite',
          }}
        />
      )}
    </button>
  );
}
