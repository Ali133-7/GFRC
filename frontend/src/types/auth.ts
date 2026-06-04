export interface Role {
  id: string;
  name: string;
}

export interface User {
  id: string;
  name: string;
  username: string;
  email: string | null;
  is_active: boolean;
  roles: Role[];
  permissions: string[];
  last_login_at: string | null;
}

export interface PartialUser {
  id: string;
  name?: string;
  username?: string;
  email?: string | null;
  is_active?: boolean;
  roles?: Role[];
  permissions?: string[];
  last_login_at?: string | null;
}

export interface LoginResponse {
  user: User;
  token: string;
}
