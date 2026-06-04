import type { Role } from './auth';

export interface UserListItem {
  id: string;
  name: string;
  username: string;
  email: string | null;
  is_active: boolean;
  roles: Role[];
  permissions: string[];
  last_login_at: string | null;
}

export interface UserActivitySummary {
  user: UserListItem;
  summary: {
    total_receipts: number;
    total_issued: number;
    total_cancelled: number;
    total_amount: number;
    last_login_at: string | null;
  };
  recent_activity: Array<{
    event: string;
    description: string;
    created_at: string;
  }>;
}
