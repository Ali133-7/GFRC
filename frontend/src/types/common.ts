export interface ApiResponse<T = unknown> {
  success: boolean;
  data: T;
  message: string;
  meta?: {
    pagination?: {
      page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface ApiError {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
  error_code?: string;
}
