import { statusColors } from '@/utils/statusColors';
import type { ReceiptStatus } from '@/types/receipt';

interface Props {
  status: ReceiptStatus;
}

export function Badge({ status }: Props) {
  const config = statusColors[status];
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${config.bg} ${config.text}`}>
      {config.label}
    </span>
  );
}
