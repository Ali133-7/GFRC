export type FieldType = 'text' | 'number' | 'decimal' | 'date' | 'select' | 'textarea' | 'hidden' | 'calculated';

export interface RegisterFieldOption {
  value: string;
  label_ar: string;
  label_en?: string;
}

export interface RegisterField {
  id: string;
  register_id: string;
  name: string;
  label_ar: string;
  label_en: string | null;
  field_type: FieldType;
  is_required: boolean;
  is_visible: boolean;
  is_editable: boolean;
  is_locked: boolean;
  is_financial: boolean;
  is_insured: boolean;
  insurance_value: string | null;
  priority: number | null;
  sort_order: number;
  validation_rules: string[] | string | null;
  default_value: string | null;
  options: RegisterFieldOption[] | null;
}

export interface Register {
  id: string;
  code: string;
  name_ar: string;
  name_en: string | null;
  description: string | null;
  fiscal_year: number;
  is_active: boolean;
  current_sequence: number;
  fields?: RegisterField[];
}
