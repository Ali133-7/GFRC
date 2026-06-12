// Enterprise Dynamic Rule Engine V4 - Type Definitions

// ==================== Condition Operators ====================

export type ConditionOperator =
  | 'equals' | 'not_equals'
  | 'greater_than' | 'greater_or_equal'
  | 'less_than' | 'less_or_equal'
  | 'contains' | 'not_contains'
  | 'starts_with' | 'ends_with'
  | 'between'
  | 'in' | 'not_in'
  | 'any_of' | 'all_of'
  | 'is_empty' | 'is_not_empty'
  | 'exists' | 'not_exists'
  | 'regex' | 'matches_pattern'
  | 'database_exists' | 'database_not_exists';

// ==================== Condition Types ====================

export interface SimpleCondition {
  id: string;
  type: 'simple';
  field_id: string;
  operator: ConditionOperator;
  value?: string | number | boolean | string[];
  value_end?: string | number; // for 'between'
  register_id?: string; // for database_exists/not_exists
  register_column?: string; // for database_exists/not_exists
}

export interface ConditionGroup {
  id: string;
  type: 'group';
  logic: 'and' | 'or';
  conditions: (SimpleCondition | ConditionGroup)[];
}

export type ConditionNode = SimpleCondition | ConditionGroup;

// ==================== Action Types ====================

export type ActionType =
  | 'set_value' | 'override_value' | 'calculate' | 'set_fee' | 'apply_discount' | 'multiply_and_add'
  | 'show' | 'hide' | 'enable' | 'disable'
  | 'set_visibility' | 'set_required' | 'set_optional' | 'set_readonly'
  | 'set_editable' | 'set_lock' | 'unlock' | 'set_options' | 'append_options'
  | 'remove_options' | 'set_field_type' | 'clear_value' | 'copy_value'
  | 'route_to_step' | 'route_to_workflow' | 'switch_mode'
  | 'pause_execution' | 'resume_execution' | 'skip_step'
  | 'execute_validation' | 'show_message' | 'show_warning' | 'show_error'
  | 'show_confirmation' | 'generate_reference' | 'audit_log';
  // TODO: Phase 2 — Workflow Administration Actions (not implemented)
  // | 'send_notification' | 'create_task' | 'assign_user' | 'assign_role'
  // | 'create_record' | 'update_record' | 'delete_record';

export interface RuleAction {
  id: string;
  type: ActionType;
  field_id?: string;
  target_field_id?: string; // for copy_value
  source_field_id?: string; // for multiply_and_add, copy_value
  value?: string | number | boolean | object;
  multiplier?: string | number; // for multiply_and_add
  message_ar?: string;
  message_en?: string;
  workflow_id?: string; // for route_to_workflow
  step_id?: string; // for route_to_step, skip_step
  mode?: 'create' | 'update' | 'renewal' | 'review' | 'approval' | 'rejection' | 'reopen';
  preserve_fields?: string[];
  field_mapping?: Record<string, string>;
  fee_code?: string;
  discount_percent?: number;
  notification_type?: string;
  validation_rule_id?: string;
  stop?: boolean; // stop further rule evaluation
}

// ==================== Rule Types ====================

export type RuleCategory =
  | 'validation' | 'routing' | 'calculation' | 'field_control'
  | 'notification' | 'data_mapping' | 'case_based' | 'decision_matrix';

export type ConflictResolution = 'first_match' | 'highest_priority' | 'execute_all' | 'execute_until_stop';

export interface EnterpriseRule {
  id: string;
  workflow_version_id: string;
  name: string;
  description?: string;
  category: RuleCategory;
  priority: number; // 1-10000, higher = evaluated first
  is_active: boolean;
  realtime_enabled?: boolean;
  sort_order: number;

  // Conditions
  conditions: ConditionNode[];

  // Actions (executed when conditions match)
  actions: RuleAction[];

  // Else actions (executed when conditions don't match)
  else_actions?: RuleAction[];

  // Case-based rules
  cases?: RuleCase[];

  // Conflict resolution
  conflict_resolution?: ConflictResolution;

  // Metadata
  tags?: string[];
  created_at?: string;
  updated_at?: string;
}

// ==================== Case-Based Rules ====================

export interface RuleCase {
  id: string;
  label: string;
  conditions: ConditionNode[];
  actions: RuleAction[];
  is_default?: boolean;
}

// ==================== Decision Matrix ====================

export interface DecisionMatrixColumn {
  id: string;
  field_id: string;
  label: string;
}

export interface DecisionMatrixRow {
  id: string;
  conditions: Record<string, string | number | boolean>;
  actions: RuleAction[];
}

export interface DecisionMatrixRule {
  id: string;
  name: string;
  columns: DecisionMatrixColumn[];
  rows: DecisionMatrixRow[];
}

// ==================== Execution Context ====================

export interface ExecutionContext {
  execution_id: string;
  workflow_id: string;
  version_id: string;
  step_id?: string;
  mode: 'create' | 'update' | 'renewal' | 'review' | 'approval' | 'rejection' | 'reopen';
  values: Record<string, any>;
  field_states: Record<string, {
    is_visible: boolean;
    is_required: boolean;
    is_readonly: boolean;
    is_editable: boolean;
    is_locked: boolean;
  }>;
  metadata: Record<string, any>;
}

// ==================== Execution Result ====================

export interface RuleExecutionResult {
  rule_id: string;
  rule_name: string;
  matched: boolean;
  conditions_evaluated: number;
  conditions_matched: number;
  executed_actions: string[];
  skipped_actions: string[];
  routing?: {
    action: string;
    target_workflow_id?: string;
    target_step_id?: string;
    mode?: string;
    preserved_values?: Record<string, any>;
    field_mapping?: Record<string, string>;
  };
  field_effects?: Array<{
    field_id: string;
    action: string;
    value?: any;
  }>;
  messages?: Array<{
    type: 'info' | 'warning' | 'error' | 'confirmation';
    message_ar: string;
    message_en?: string;
  }>;
  stop_evaluation?: boolean;
}

export interface EngineExecutionResult {
  execution_id: string;
  total_rules_evaluated: number;
  matched_rules: number;
  failed_rules: number;
  results: RuleExecutionResult[];
  final_values: Record<string, any>;
  final_field_states: Record<string, any>;
  routing_decisions: Array<{
    rule_id: string;
    action: string;
    data: Record<string, any>;
  }>;
  warnings: string[];
  errors: string[];
  execution_time_ms: number;
}

// ==================== UI Builder Types ====================

export interface ConditionBuilderState {
  node: ConditionNode;
}

export interface ActionBuilderState {
  actions: RuleAction[];
}

export interface RuleBuilderState {
  rule: Partial<EnterpriseRule>;
  isEditing: boolean;
  simulationMode: boolean;
  simulationValues: Record<string, any>;
  simulationResult?: EngineExecutionResult;
}

// ==================== Operator Metadata ====================

export interface OperatorMetadata {
  value: ConditionOperator;
  label: string;
  label_en: string;
  icon: string;
  requires_value: boolean;
  requires_value_end?: boolean;
  value_type?: 'text' | 'number' | 'date' | 'select' | 'array';
  description_ar: string;
}

export const OPERATOR_METADATA: OperatorMetadata[] = [
  { value: 'equals', label: 'يساوي', label_en: 'Equals', icon: '=', requires_value: true, value_type: 'text', description_ar: 'يطابق القيمة تماماً' },
  { value: 'not_equals', label: 'لا يساوي', label_en: 'Not Equals', icon: '≠', requires_value: true, value_type: 'text', description_ar: 'لا يطابق القيمة' },
  { value: 'greater_than', label: 'أكبر من', label_en: 'Greater Than', icon: '>', requires_value: true, value_type: 'number', description_ar: 'القيمة أكبر من' },
  { value: 'greater_or_equal', label: 'أكبر أو يساوي', label_en: 'Greater or Equal', icon: '≥', requires_value: true, value_type: 'number', description_ar: 'القيمة أكبر أو تساوي' },
  { value: 'less_than', label: 'أقل من', label_en: 'Less Than', icon: '<', requires_value: true, value_type: 'number', description_ar: 'القيمة أقل من' },
  { value: 'less_or_equal', label: 'أقل أو يساوي', label_en: 'Less or Equal', icon: '≤', requires_value: true, value_type: 'number', description_ar: 'القيمة أقل أو تساوي' },
  { value: 'contains', label: 'يحتوي على', label_en: 'Contains', icon: '⊃', requires_value: true, value_type: 'text', description_ar: 'يحتوي النص على' },
  { value: 'not_contains', label: 'لا يحتوي على', label_en: 'Not Contains', icon: '⊄', requires_value: true, value_type: 'text', description_ar: 'لا يحتوي النص على' },
  { value: 'starts_with', label: 'يبدأ بـ', label_en: 'Starts With', icon: '⇢', requires_value: true, value_type: 'text', description_ar: 'يبدأ بـ' },
  { value: 'ends_with', label: 'ينتهي بـ', label_en: 'Ends With', icon: '⇠', requires_value: true, value_type: 'text', description_ar: 'ينتهي بـ' },
  { value: 'between', label: 'بين', label_en: 'Between', icon: '↔', requires_value: true, requires_value_end: true, value_type: 'number', description_ar: 'القيمة بين حدين' },
  { value: 'in', label: 'ضمن قائمة', label_en: 'In List', icon: '∈', requires_value: true, value_type: 'array', description_ar: 'القيمة ضمن قائمة' },
  { value: 'not_in', label: 'ليس ضمن قائمة', label_en: 'Not In List', icon: '∉', requires_value: true, value_type: 'array', description_ar: 'القيمة ليست ضمن قائمة' },
  { value: 'any_of', label: 'أي من', label_en: 'Any Of', icon: '∨', requires_value: true, value_type: 'array', description_ar: 'أي من القيم' },
  { value: 'all_of', label: 'جميع', label_en: 'All Of', icon: '∧', requires_value: true, value_type: 'array', description_ar: 'جميع القيم' },
  { value: 'is_empty', label: 'فارغ', label_en: 'Is Empty', icon: '∅', requires_value: false, description_ar: 'الحقل فارغ' },
  { value: 'is_not_empty', label: 'غير فارغ', label_en: 'Is Not Empty', icon: '≠∅', requires_value: false, description_ar: 'الحقل غير فارغ' },
  { value: 'exists', label: 'موجود', label_en: 'Exists', icon: '✓', requires_value: false, description_ar: 'القيمة موجودة' },
  { value: 'not_exists', label: 'غير موجود', label_en: 'Not Exists', icon: '✗', requires_value: false, description_ar: 'القيمة غير موجودة' },
  { value: 'regex', label: 'تعبير نمطي', label_en: 'Regex', icon: '.*', requires_value: true, value_type: 'text', description_ar: 'يطابق تعبير نمطي' },
  { value: 'matches_pattern', label: 'يطابق نمط', label_en: 'Matches Pattern', icon: '📐', requires_value: true, value_type: 'text', description_ar: 'يطابق نمط محدد' },
  { value: 'database_exists', label: 'موجود في السجل', label_en: 'DB Exists', icon: '🗄️', requires_value: false, description_ar: 'سجل موجود في قاعدة البيانات' },
  { value: 'database_not_exists', label: 'غير موجود في السجل', label_en: 'DB Not Exists', icon: '🗄️✗', requires_value: false, description_ar: 'سجل غير موجود في قاعدة البيانات' },
];

// ==================== Action Metadata ====================

export interface ActionMetadata {
  value: ActionType;
  label: string;
  label_en: string;
  icon: string;
  category: string;
  requires_field?: boolean;
  requires_value?: boolean;
  requires_workflow?: boolean;
  requires_step?: boolean;
  description_ar: string;
}

export const ACTION_METADATA: ActionMetadata[] = [
  { value: 'set_value', label: 'تعيين قيمة', label_en: 'Set Value', icon: '✏️', category: 'field', requires_field: true, requires_value: true, description_ar: 'تعيين قيمة لحقل' },
  { value: 'override_value', label: 'تجاوز القيمة', label_en: 'Override Value', icon: '⚡', category: 'field', requires_field: true, requires_value: true, description_ar: 'تجاوز قيمة الحقل' },
  { value: 'calculate', label: 'حساب', label_en: 'Calculate', icon: '🔢', category: 'field', requires_field: true, requires_value: true, description_ar: 'حساب قيمة بصيغة' },
  { value: 'set_fee', label: 'تعيين رسوم', label_en: 'Set Fee', icon: '💰', category: 'financial', requires_value: true, description_ar: 'تعيين رسوم' },
  { value: 'apply_discount', label: 'تطبيق خصم', label_en: 'Apply Discount', icon: '🏷️', category: 'financial', requires_value: true, description_ar: 'تطبيق نسبة خصم' },
  { value: 'multiply_and_add', label: 'ضرب وإضافة', label_en: 'Multiply & Add', icon: '✖️➕', category: 'financial', requires_field: true, requires_value: true, description_ar: 'ضرب قيمة الحقل × رقم ثابت وإضافة الناتج لحقل آخر' },
  { value: 'show', label: 'إظهار', label_en: 'Show', icon: '👁️', category: 'field', requires_field: true, description_ar: 'إظهار حقل' },
  { value: 'hide', label: 'إخفاء', label_en: 'Hide', icon: '🙈', category: 'field', requires_field: true, description_ar: 'إخفاء حقل' },
  { value: 'enable', label: 'تفعيل', label_en: 'Enable', icon: '✓', category: 'field', requires_field: true, description_ar: 'تفعيل حقل' },
  { value: 'disable', label: 'تعطيل', label_en: 'Disable', icon: '✗', category: 'field', requires_field: true, description_ar: 'تعطيل حقل' },
  { value: 'set_visibility', label: 'إظهار/إخفاء', label_en: 'Set Visibility', icon: '👁️', category: 'field', requires_field: true, requires_value: true, description_ar: 'إظهار أو إخفاء حقل' },
  { value: 'set_required', label: 'تعيين مطلوب', label_en: 'Set Required', icon: '⭐', category: 'field', requires_field: true, description_ar: 'تعيين الحقل كإلزامي' },
  { value: 'set_optional', label: 'تعيين اختياري', label_en: 'Set Optional', icon: '○', category: 'field', requires_field: true, description_ar: 'تعيين الحقل كاختياري' },
  { value: 'set_readonly', label: 'للقراءة فقط', label_en: 'Set Readonly', icon: '🔒', category: 'field', requires_field: true, description_ar: 'تعيين الحقل للقراءة فقط' },
  { value: 'set_editable', label: 'قابل للتعديل', label_en: 'Set Editable', icon: '✎', category: 'field', requires_field: true, description_ar: 'تعيين الحقل كقابل للتعديل' },
  { value: 'set_lock', label: 'قفل الحقل', label_en: 'Lock Field', icon: '🔐', category: 'field', requires_field: true, description_ar: 'قفل الحقل' },
  { value: 'unlock', label: 'فتح الحقل', label_en: 'Unlock Field', icon: '🔓', category: 'field', requires_field: true, description_ar: 'فتح الحقل المقفل' },
  { value: 'set_options', label: 'تغيير الخيارات', label_en: 'Set Options', icon: '📋', category: 'field', requires_field: true, requires_value: true, description_ar: 'تغيير خيارات القائمة' },
  { value: 'append_options', label: 'إضافة خيارات', label_en: 'Append Options', icon: '➕', category: 'field', requires_field: true, requires_value: true, description_ar: 'إضافة خيارات جديدة' },
  { value: 'remove_options', label: 'إزالة خيارات', label_en: 'Remove Options', icon: '➖', category: 'field', requires_field: true, requires_value: true, description_ar: 'إزالة خيارات من القائمة' },
  { value: 'set_field_type', label: 'تغيير النوع', label_en: 'Set Field Type', icon: '🔄', category: 'field', requires_field: true, requires_value: true, description_ar: 'تغيير نوع الحقل' },
  { value: 'clear_value', label: 'مسح القيمة', label_en: 'Clear Value', icon: '🗑️', category: 'field', requires_field: true, description_ar: 'مسح قيمة الحقل' },
  { value: 'copy_value', label: 'نسخ القيمة', label_en: 'Copy Value', icon: '📄', category: 'field', requires_field: true, requires_value: false, description_ar: 'نسخ قيمة من حقل لآخر' },
  { value: 'route_to_step', label: 'توجيه لخطوة', label_en: 'Route to Step', icon: '📍', category: 'routing', requires_step: true, description_ar: 'توجيه التنفيذ لخطوة محددة' },
  { value: 'route_to_workflow', label: 'توجيه لسير عمل', label_en: 'Route to Workflow', icon: '🔀', category: 'routing', requires_workflow: true, description_ar: 'توجيه التنفيذ لسير عمل آخر' },
  { value: 'switch_mode', label: 'تبديل الوضع', label_en: 'Switch Mode', icon: '🔁', category: 'routing', requires_value: true, description_ar: 'تبديل وضع التنفيذ' },
  { value: 'pause_execution', label: 'إيقاف مؤقت', label_en: 'Pause Execution', icon: '⏸️', category: 'control', description_ar: 'إيقاف التنفيذ مؤقتاً' },
  { value: 'resume_execution', label: 'استئناف', label_en: 'Resume Execution', icon: '▶️', category: 'control', description_ar: 'استئناف التنفيذ' },
  { value: 'skip_step', label: 'تخطي خطوة', label_en: 'Skip Step', icon: '⏭️', category: 'routing', requires_step: true, description_ar: 'تخطي خطوة' },
  { value: 'generate_reference', label: 'توليد مرجع', label_en: 'Generate Reference', icon: '🔖', category: 'data', description_ar: 'توليد رقم مرجعي للإيصال' },
  { value: 'execute_validation', label: 'تنفيذ تحقق', label_en: 'Execute Validation', icon: '✅', category: 'validation', requires_value: true, description_ar: 'تنفيذ قاعدة تحقق' },
  { value: 'show_message', label: 'عرض رسالة', label_en: 'Show Message', icon: '💬', category: 'ui', requires_value: true, description_ar: 'عرض رسالة للمستخدم' },
  { value: 'show_warning', label: 'عرض تحذير', label_en: 'Show Warning', icon: '⚠️', category: 'ui', requires_value: true, description_ar: 'عرض تحذير' },
  { value: 'show_error', label: 'عرض خطأ', label_en: 'Show Error', icon: '🚫', category: 'ui', requires_value: true, description_ar: 'عرض رسالة خطأ' },
  { value: 'show_confirmation', label: 'طلب تأكيد', label_en: 'Show Confirmation', icon: '❓', category: 'ui', requires_value: true, description_ar: 'طلب تأكيد من المستخدم' },
  // TODO: Phase 2 — Workflow Administration Actions (not implemented)
  // { value: 'create_record', label: 'إنشاء سجل', label_en: 'Create Record', icon: '📁', category: 'data', description_ar: 'إنشاء سجل جديد' },
  // { value: 'update_record', label: 'تحديث سجل', label_en: 'Update Record', icon: '📝', category: 'data', description_ar: 'تحديث سجل موجود' },
  // { value: 'delete_record', label: 'حذف سجل', label_en: 'Delete Record', icon: '🗑️', category: 'data', description_ar: 'حذف سجل' },
  { value: 'audit_log', label: 'تدوين', label_en: 'Audit Log', icon: '📜', category: 'data', requires_value: true, description_ar: 'تسجيل عملية في سجل التدقيق' },
];
