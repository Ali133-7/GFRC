export interface OfficialFeeCategory {
  id: string;
  name_ar: string;
  name_en: string | null;
  code: string;
  description: string | null;
  is_active: boolean;
}

export interface OfficialFee {
  id: string;
  category_id: string;
  category?: OfficialFeeCategory;
  name_ar: string;
  name_en: string | null;
  amount: string;
  effective_from: string | null;
  effective_to: string | null;
  is_active: boolean;
}

export interface TemplateRule {
  id?: string;
  template_id?: string;
  name?: string | null;
  trigger_field_id: string;
  trigger_operator: 'equals' | 'not_equals' | 'contains' | 'gt' | 'lt';
  trigger_value: string;
  target_field_id: string;
  action: 'set_value' | 'set_amount' | 'hide' | 'show';
  action_value: string | null;
  sort_order: number;
  is_active: boolean;
  triggerField?: { id: string; name: string; label_ar: string };
  targetField?: { id: string; name: string; label_ar: string };
}

export interface TransactionTemplateField {
  id?: string;
  template_id?: string;
  register_field_id: string;
  registerField?: import("./register").RegisterField;
  label_override: string | null;
  placeholder: string | null;
  default_value: string | null;
  is_required: boolean;
  is_visible: boolean;
  is_readonly: boolean;
  sort_order: number;
  options?: string[] | null;
}

export interface TransactionTemplateSection {
  id: string;
  title: string;
  field_ids: string[];
  condition?: {
    field_id: string;
    operator: 'equals' | 'not_equals' | 'contains' | 'gt' | 'lt';
    value: string;
  } | null;
}

export interface TransactionTemplate {
  id: string;
  register_id: string;
  register?: { id: string; name_ar: string; code: string };
  name_ar: string;
  name_en: string | null;
  description: string | null;
  sections?: TransactionTemplateSection[];
  icon: string | null;
  is_active: boolean;
  is_default: boolean;
  sort_order: number;
  usage_count: number;
  fields?: TransactionTemplateField[];
  rules?: TemplateRule[];
}

export interface GuidedReceiptBuild {
  template: { id: string; name_ar: string; register_id: string };
  items: { field_id: string; field_name_snapshot: string; label_ar_snapshot: string; amount: string | null; text_value: string | null }[];
  total_amount: string;
  values: Record<string, string>;
}
