import type { Register } from './register';
import type { User } from './auth';

export type ReceiptStatus = 'draft' | 'pending' | 'issued' | 'printed' | 'cancelled';

export interface ReceiptItem {
  id: string;
  receipt_id: string;
  field_id: string;
  field_name_snapshot: string;
  label_ar_snapshot: string;
  amount: string | null;
  text_value: string | null;
}

export interface ReceiptRevision {
  id: string;
  receipt_id: string;
  version: number;
  revised_by: string;
  reviser: User;
  reason: string;
  old_snapshot: Record<string, unknown>;
  new_snapshot: Record<string, unknown>;
  created_at: string;
}

export interface Receipt {
  id: string;
  receipt_number: string;
  register_id: string;
  register: Register;
  created_by: User;
  total_amount: string;
  status: ReceiptStatus;
  version: number;
  notes: string | null;
  qr_payload: string | null;
  printed_at: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  items: ReceiptItem[];
  created_at: string;
  updated_at: string;
}
