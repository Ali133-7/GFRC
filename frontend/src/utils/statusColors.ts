import type { ReceiptStatus } from '@/types/receipt';

// Tailwind class names — kept for backward compatibility with ReceiptStatusBadge & Badge
export const statusColors: Record<ReceiptStatus, { bg: string; text: string; label: string }> = {
  draft:     { bg: 'bg-gray-100',   text: 'text-gray-800',   label: 'مسودة' },
  pending:   { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'معلقة' },
  issued:    { bg: 'bg-green-100',  text: 'text-green-800',  label: 'مرحل' },
  printed:   { bg: 'bg-blue-100',   text: 'text-blue-800',   label: 'مطبوع' },
  cancelled: { bg: 'bg-red-100',    text: 'text-red-800',    label: 'ملغى' },
};

// CSS-variable based config — for inline-style usage in new components
export const statusConfig: Record<ReceiptStatus, { label: string; bg: string; color: string; border: string }> = {
  draft:     { label: 'مسودة',   bg: 'var(--color-background-secondary)', color: 'var(--color-text-secondary)',  border: 'var(--color-border-secondary)' },
  pending:   { label: 'معلق',    bg: 'var(--color-background-warning)',   color: 'var(--color-text-warning)',    border: 'var(--color-border-warning)' },
  issued:    { label: 'مرحّل',   bg: 'var(--color-background-success)',   color: 'var(--color-text-success)',    border: 'var(--color-border-success)' },
  printed:   { label: 'مطبوع',   bg: 'var(--color-background-info)',      color: 'var(--color-text-info)',       border: 'var(--color-border-info)' },
  cancelled: { label: 'ملغى',    bg: 'var(--color-background-danger)',    color: 'var(--color-text-danger)',     border: 'var(--color-border-danger)' },
};

export function getStatusConfig(status: string): typeof statusConfig[ReceiptStatus] {
  return statusConfig[status as ReceiptStatus] ?? statusConfig.draft;
}

export function getStatusLabel(status: string): string {
  return getStatusConfig(status).label;
}
