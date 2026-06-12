export interface Workflow {
  id: string;
  register_id: string;
  register?: { id: string; name_ar: string };
  code: string;
  name_ar: string;
  name_en: string | null;
  description: string | null;
  icon: string | null;
  is_active: boolean;
  current_version: number;
  sort_order: number;
  created_by: string | null;
  creator?: { id: string; name: string };
  versions?: WorkflowVersion[];
  created_at: string;
  updated_at: string;
}

export interface WorkflowVersion {
  id: string;
  workflow_id: string;
  version: number;
  status: 'draft' | 'active' | 'archived';
  published_at: string | null;
  archived_at: string | null;
  published_by: string | null;
  publisher?: { id: string; name: string };
  change_summary: string | null;
  steps?: WorkflowStep[];
  fields?: WorkflowField[];
  rules?: WorkflowRule[];
  validation_rules?: any[];
  created_at: string;
}

export interface WorkflowStep {
  id: string;
  workflow_version_id: string;
  title_ar: string;
  title_en: string | null;
  description: string | null;
  sort_order: number;
  condition_logic: ConditionLogic | null;
  is_visible: boolean;
  fields?: WorkflowField[];
  created_at: string;
}

export interface WorkflowField {
  id: string;
  workflow_version_id: string;
  register_field_id: string | null;
  registerField?: {
    id: string;
    name: string;
    label_ar: string;
    label_en: string | null;
    field_type: string;
    options?: Array<{ label: string; value: string }> | string[] | null;
    validation_rules?: string[] | null;
    default_value?: string | null;
    is_required: boolean;
    is_visible: boolean;
    is_editable: boolean;
    is_locked: boolean;
    is_financial: boolean;
    is_insured: boolean;
    insurance_value?: string | null;
    priority?: number | null;
    sort_order?: number;
  };
  step_id: string | null;
  label: string;
  label_override: string | null;
  custom_name: string | null;
  custom_label: string | null;
  placeholder: string | null;
  default_value: string | null;
  is_required: boolean;
  is_visible: boolean;
  is_readonly: boolean;
  is_editable: boolean;
  is_locked: boolean;
  is_financial: boolean;
  is_insured: boolean;
  insurance_value: string | null;
  priority: number | null;
  is_computed: boolean;
  sort_order: number;
  condition_logic: ConditionLogic | null;
  fee_code: string | null;
  calculation_formula: string | null;
  field_type: string | null;
  options: Array<{ label: string; value: string }> | null;
  validation_rules: string[] | null;
  conditional_validation_rules: any[] | null;
  cross_field_validation_rules: any[] | null;
  computed_formula: string | null;
  computed_dependencies: string[] | null;
  parent_field_id: string | null;
  option_source_type: string | null;
  option_source_config: any | null;
  cascade_config: any | null;
  created_at: string;
}

export interface ConditionLogic {
  operator: 'and' | 'or' | string;
  conditions?: ConditionLogic[];
  field_id?: string;
  value?: string | number | string[];
}

export interface WorkflowRule {
  id: string;
  workflow_version_id: string;
  name: string | null;
  description: string | null;
  rule_type?: 'simple' | 'case_based';
  trigger_field_id?: string | null;
  condition_logic: ConditionLogic;
  actions: RuleAction[];
  cases?: RuleCase[];
  default_actions?: RuleAction[];
  match_mode?: 'exact' | 'contains' | 'pattern' | 'in';
  sort_order: number;
  is_active: boolean;
  realtime_enabled?: boolean;
  created_at: string;
}

export interface RuleCase {
  value: string | string[];
  label?: string;
  actions: RuleAction[];
  priority?: number;
  compound_condition?: ConditionLogic | null;
}

export interface RuleAction {
  action: 'set_value' | 'set_fee' | 'calculate' | 'hide' | 'show' | 'set_required' | 'set_readonly' | 'skip_step' | 'set_visibility' | 'set_lock' | 'set_editable' | 'set_field_type' | 'set_options' | 'apply_discount' | 'override_value';
  target_field_id?: string;
  target_step_id?: string;
  value?: string | number | boolean;
  fee_code?: string;
  formula?: string;
  resolved_amount?: number;
  resolved_value?: string;
  fee_name?: string;
  field_type?: string;
  options?: Array<{ label: string; value: string }>;
}

export interface ValidationRule {
  id: string;
  workflow_version_id: string;
  name: string | null;
  description: string | null;
  validation_type: string;
  category?: string;
  target_register_id?: string;
  trigger_field_id?: string;
  trigger_conditions?: any[];
  target_fields?: any[];
  rule_config?: any;
  response_type?: 'error' | 'warning' | 'confirm';
  error_message_ar?: string;
  confirm_message_ar?: string;
  sort_order: number;
  is_active: boolean;
  realtime_enabled?: boolean;
  priority?: number;
  created_at: string;
}

export interface WorkflowExecution {
  id: string;
  workflow_version_id: string;
  version?: WorkflowVersion;
  register_id: string;
  register?: { id: string; name_ar: string };
  status: 'in_progress' | 'completed' | 'cancelled' | 'abandoned';
  mode: ExecutionMode;
  current_step_index: number;
  values_snapshot: Record<string, string>;
  calculated_items: CalculatedItem[];
  total_amount: number;
  receipt_id: string | null;
  receipt?: { id: string; receipt_number: string };
  branch_state: BranchState | null;
  routing_history: RoutingEvent[];
  preserved_values: Record<string, string> | null;
  state_mapping: Record<string, string> | null;
  started_by: string;
  starter?: { id: string; name: string };
  started_at: string;
  completed_at: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

export interface CalculatedItem {
  field_id: string;
  field_name: string;
  label: string;
  amount: number;
  text_value: string | null;
  fee_code: string | null;
}

export interface ExecutionPreview {
  items: CalculatedItem[];
  total_amount: number;
  matched_rules: Array<{ rule_id: string | null; name: string | null; condition_met: boolean }>;
  actions: RuleAction[];
  values: Record<string, string>;
  modified_values?: Record<string, string>;
  field_states?: Record<string, { is_visible: boolean; is_required: boolean; is_readonly: boolean }>;
}

export interface FeeVersion {
  id: string;
  fee_id: string;
  version: number;
  amount: number;
  effective_from: string;
  effective_to: string | null;
  change_reason: string | null;
  created_by: string | null;
  creator?: { id: string; name: string };
  created_at: string;
}

export interface ReceiptCalculationSnapshot {
  id: string;
  receipt_id: string;
  workflow_version_id: string;
  workflow_definition: {
    workflow_id: string;
    version: number;
    steps: WorkflowStep[];
    fields: WorkflowField[];
    rules: WorkflowRule[];
  };
  rules_applied: CalculatedItem[];
  fees_used: Record<string, { fee_name: string; amount: number; version: number; effective_from: string }>;
  field_values: Record<string, string>;
  calculation_hash: string;
  created_at: string;
}

// --- Branch Execution Types ---

export type ExecutionMode = "create" | "update" | "renewal" | "review";

export interface BranchState {
  active_branch: string;
  redirect_to_workflow_id: string | null;
  redirect_to_step_id: string | null;
  paused: boolean;
  pause_reason: string | null;
  original_execution_id: string | null;
}

export interface RoutingEvent {
  event: string;
  from_workflow_id?: string;
  to_workflow_id?: string;
  from_mode?: string;
  to_mode?: string;
  trigger_field?: string;
  trigger_value?: string;
  rule_id?: string;
  rule_name?: string;
  reason?: string;
  timestamp: string;
  execution_id?: string;
  existing_record_id?: string;
  from_execution_id?: string;
  to_execution_id?: string;
}

export interface BranchDecision {
  effect: "continue" | "block" | "warn" | "confirm" | "redirect" | "mode_switch";
  data: {
    message?: string;
    target_workflow_id?: string;
    target_workflow_name?: string;
    target_version_id?: string;
    target_step_id?: string;
    target_mode?: string;
    from_mode?: string;
    to_mode?: string;
    preserved_values?: Record<string, string>;
    state_mapping?: Record<string, string>;
    existing_record?: { id: string; register_id: string; created_at?: string };
    actions?: string[];
    rule_id?: string;
    rule_name?: string;
    warnings?: Array<{ message: string; rule_name?: string }>;
  };
}

export interface ValidationDecision {
  rule_id: string;
  rule_name: string;
  validation_type: string;
  status: "passed" | "failed" | "found" | "not_found" | "skipped" | "error";
  decision?: string;
  response_type?: string;
  message?: string;
  confirm_message?: string;
  actions?: string[];
  existing_record?: { id: string; register_id: string; created_at?: string };
  route_config?: {
    on_match?: {
      action?: string;
      target_workflow_id?: string;
      target_step_id?: string;
      message_ar?: string;
      actions?: string[];
      target_mode?: string;
    };
    on_not_found?: {
      action?: string;
      message_ar?: string;
    };
  };
  trigger_field?: string;
  trigger_value?: string;
}

export interface FieldValidationResult {
  field_id: string;
  field_value: string;
  has_routing_decision: boolean;
  routing_decision: ValidationDecision | null;
  all_results: ValidationDecision[];
}

export interface BranchStateResponse {
  execution_id: string;
  workflow_id: string;
  workflow_name: string;
  mode: ExecutionMode;
  branch_state: BranchState;
  routing_history: RoutingEvent[];
  preserved_values: Record<string, string>;
  state_mapping: Record<string, string>;
  has_redirect: boolean;
  redirect_target: { workflow_id: string; step_id: string | null } | null;
  is_paused: boolean;
}
