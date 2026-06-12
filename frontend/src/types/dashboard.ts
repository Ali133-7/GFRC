export interface Dashboard {
  id: number;
  name_ar: string;
  name_en?: string;
  description?: string;
  scope: 'system' | 'organization' | 'department' | 'role' | 'user';
  organization_id?: number;
  department_id?: number;
  role_id?: number;
  user_id?: number;
  template_id?: number;
  layout_config?: any;
  theme_config?: any;
  settings?: any;
  visibility: 'private' | 'shared' | 'department' | 'role' | 'organization' | 'public';
  is_default: boolean;
  is_active: boolean;
  version: number;
  status: 'draft' | 'published' | 'archived';
  created_by?: number;
  updated_by?: number;
  published_at?: string;
  created_at: string;
  updated_at: string;
  sections?: DashboardSection[];
}

export interface DashboardSection {
  id: number;
  dashboard_id: number;
  name_ar: string;
  name_en?: string;
  description?: string;
  sort_order: number;
  layout_type: string;
  layout_config?: any;
  background_color?: string;
  border_color?: string;
  padding: number;
  is_collapsible: boolean;
  is_collapsed: boolean;
  is_visible: boolean;
  display_conditions?: any;
  permissions?: any;
  created_by?: number;
  created_at: string;
  updated_at: string;
  widgets?: DashboardWidget[];
}

export interface DashboardWidget {
  id: number;
  section_id: number;
  name_ar: string;
  name_en?: string;
  widget_type: string;
  data_source?: string;
  sort_order: number;
  grid_x: number;
  grid_y: number;
  grid_width: number;
  grid_height: number;
  data_config?: any;
  display_config?: any;
  filter_config?: any;
  filter_by_user: boolean;
  filter_by_department: boolean;
  filter_by_role: boolean;
  filter_by_branch: boolean;
  custom_filters?: any;
  refresh_interval: number;
  is_real_time: boolean;
  is_visible: boolean;
  is_editable: boolean;
  is_removable: boolean;
  visibility_rules?: any;
  required_permissions?: string[];
  allowed_roles?: number[];
  allowed_departments?: number[];
  template_widget_id?: number;
  is_inherited: boolean;
  is_customized: boolean;
  created_by?: number;
  created_at: string;
  updated_at: string;
}

export interface UserDashboard {
  id: number;
  user_id: number;
  dashboard_id: number;
  custom_name?: string;
  is_favorite: boolean;
  sort_order: number;
  is_pinned: boolean;
  layout_overrides?: any;
  widget_positions?: any;
  widget_sizes?: any;
  is_visible: boolean;
  is_hidden_by_user: boolean;
  inherits_from_role: boolean;
  inherits_from_department: boolean;
  allow_inheritance_updates: boolean;
  created_at: string;
  updated_at: string;
}

export interface UserDashboardPreference {
  id?: number;
  user_id: number;
  default_dashboard_id?: number;
  default_view: string;
  theme: 'light' | 'dark' | 'auto';
  color_palette?: string;
  font_size: 'small' | 'medium' | 'large';
  layout_density: 'compact' | 'comfortable' | 'spacious';
  auto_refresh_widgets: boolean;
  default_refresh_interval: number;
  show_widget_borders: boolean;
  show_widget_shadows: boolean;
  show_notifications: boolean;
  show_announcements: boolean;
  show_quick_actions: boolean;
  show_favorites: boolean;
  quick_links?: any[];
  favorite_reports?: number[];
  favorite_workflows?: number[];
  favorite_registers?: number[];
  bookmarks?: any[];
  executive_mode: boolean;
  tv_mode: boolean;
  tv_rotation_interval: number;
  created_at?: string;
  updated_at?: string;
}

export interface UserFavorite {
  id: number;
  user_id: number;
  favorite_type: string;
  favorite_id: string;
  favorite_name_ar: string;
  favorite_name_en?: string;
  category?: string;
  sort_order: number;
  metadata?: any;
  quick_access_config?: any;
  created_at: string;
  updated_at: string;
}

export interface DashboardTemplate {
  id: number;
  name_ar: string;
  name_en?: string;
  description?: string;
  category?: string;
  role_type?: string;
  layout_config?: any;
  default_widgets?: any;
  is_active: boolean;
  is_system: boolean;
  created_by?: number;
  created_at: string;
  updated_at: string;
}

export interface WidgetTypeDefinition {
  type: string;
  name: string;
  name_ar: string;
  icon: string;
  description: string;
  default_width: number;
  default_height: number;
  supported_data_sources: string[];
  config_fields: WidgetConfigField[];
}

export interface WidgetConfigField {
  name: string;
  label: string;
  label_ar: string;
  type: 'text' | 'number' | 'select' | 'boolean' | 'json';
  required: boolean;
  default?: any;
  options?: Array<{ value: string; label: string; label_ar: string }>;
}

// Widget types registry - Enterprise Widget Marketplace
export const WIDGET_TYPES: WidgetTypeDefinition[] = [
  // ========== KPI & STATISTIC WIDGETS ==========
  {
    type: 'kpi_card',
    name: 'KPI Card',
    name_ar: 'بطاقة مؤشر',
    icon: '📊',
    description: 'عرض مؤشر أداء رئيسي واحد',
    default_width: 3,
    default_height: 2,
    supported_data_sources: ['receipts_total', 'receipts_count', 'pending_count', 'custom_query', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'color', label: 'Color', label_ar: 'اللون', type: 'select', required: false, default: 'blue', options: [
        { value: 'blue', label: 'Blue', label_ar: 'أزرق' },
        { value: 'green', label: 'Green', label_ar: 'أخضر' },
        { value: 'amber', label: 'Amber', label_ar: 'عنبري' },
        { value: 'red', label: 'Red', label_ar: 'أحمر' },
        { value: 'purple', label: 'Purple', label_ar: 'أرجواني' },
      ]},
      { name: 'prefix', label: 'Prefix', label_ar: 'بادئة', type: 'text', required: false },
      { name: 'suffix', label: 'Suffix', label_ar: 'لاحقة', type: 'text', required: false },
      { name: 'show_trend', label: 'Show Trend', label_ar: 'عرض الاتجاه', type: 'boolean', required: false, default: true },
    ],
  },
  {
    type: 'stat_card',
    name: 'Statistic Card',
    name_ar: 'بطاقة إحصائية',
    icon: '📈',
    description: 'عرض إحصائية مع اتجاه',
    default_width: 3,
    default_height: 2,
    supported_data_sources: ['receipts_total', 'receipts_count', 'workflow_count', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'comparison_period', label: 'Comparison Period', label_ar: 'فترة المقارنة', type: 'select', required: false, options: [
        { value: 'previous', label: 'Previous Period', label_ar: 'الفترة السابقة' },
        { value: 'same_last_year', label: 'Same Last Year', label_ar: 'نفس الفترة العام الماضي' },
      ]},
    ],
  },
  {
    type: 'gauge',
    name: 'Gauge Chart',
    name_ar: 'مقياس',
    icon: '🎯',
    description: 'عرض نسبة مئوية على مقياس',
    default_width: 4,
    default_height: 4,
    supported_data_sources: ['completion_rate', 'target_achievement', 'custom_query'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'min_value', label: 'Min Value', label_ar: 'الحد الأدنى', type: 'number', required: false, default: 0 },
      { name: 'max_value', label: 'Max Value', label_ar: 'الحد الأقصى', type: 'number', required: false, default: 100 },
      { name: 'target_value', label: 'Target Value', label_ar: 'القيمة المستهدفة', type: 'number', required: false },
    ],
  },

  // ========== FINANCIAL WIDGETS ==========
  {
    type: 'revenue_chart',
    name: 'Revenue Chart',
    name_ar: 'رسم الإيرادات',
    icon: '💰',
    description: 'تحليل الإيرادات خلال الوقت',
    default_width: 8,
    default_height: 6,
    supported_data_sources: ['receipts_total_by_period', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'chart_type', label: 'Chart Type', label_ar: 'نوع الرسم', type: 'select', required: false, default: 'bar', options: [
        { value: 'bar', label: 'Bar', label_ar: 'أعمدة' },
        { value: 'line', label: 'Line', label_ar: 'خطي' },
        { value: 'area', label: 'Area', label_ar: 'مساحة' },
      ]},
      { name: 'group_by', label: 'Group By', label_ar: 'تجميع حسب', type: 'select', required: false, options: [
        { value: 'day', label: 'Day', label_ar: 'يوم' },
        { value: 'week', label: 'Week', label_ar: 'أسبوع' },
        { value: 'month', label: 'Month', label_ar: 'شهر' },
      ]},
    ],
  },
  {
    type: 'fee_breakdown',
    name: 'Fee Breakdown',
    name_ar: 'تفصيل الرسوم',
    icon: '🧾',
    description: 'تحليل الرسوم المفروضة',
    default_width: 6,
    default_height: 6,
    supported_data_sources: ['fees_by_type', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'show_percentages', label: 'Show Percentages', label_ar: 'عرض النسب', type: 'boolean', required: false, default: true },
    ],
  },

  // ========== WORKFLOW WIDGETS ==========
  {
    type: 'workflow_status',
    name: 'Workflow Status',
    name_ar: 'حالة سير العمل',
    icon: '🔄',
    description: 'عرض حالة سير العمل',
    default_width: 6,
    default_height: 4,
    supported_data_sources: ['workflow_executions', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'workflow_id', label: 'Workflow', label_ar: 'سير العمل', type: 'select', required: false },
    ],
  },
  {
    type: 'task_list',
    name: 'Task List',
    name_ar: 'قائمة المهام',
    icon: '✅',
    description: 'قائمة المهام المعلقة',
    default_width: 6,
    default_height: 6,
    supported_data_sources: ['workflow_tasks', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'show_only_mine', label: 'My Tasks Only', label_ar: 'مهامي فقط', type: 'boolean', required: false, default: true },
      { name: 'limit', label: 'Limit', label_ar: 'الحد', type: 'number', required: false, default: 10 },
    ],
  },

  // ========== AUDIT WIDGETS ==========
  {
    type: 'audit_log',
    name: 'Audit Log',
    name_ar: 'سجل التدقيق',
    icon: '🔍',
    description: 'سجل عمليات التدقيق',
    default_width: 12,
    default_height: 6,
    supported_data_sources: ['audit_logs', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'limit', label: 'Limit', label_ar: 'الحد', type: 'number', required: false, default: 20 },
      { name: 'show_user', label: 'Show User', label_ar: 'عرض المستخدم', type: 'boolean', required: false, default: true },
    ],
  },

  // ========== MONITORING WIDGETS ==========
  {
    type: 'system_health',
    name: 'System Health',
    name_ar: 'صحة النظام',
    icon: '💚',
    description: 'مراقبة صحة النظام',
    default_width: 4,
    default_height: 3,
    supported_data_sources: ['system_metrics', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'show_metrics', label: 'Show Metrics', label_ar: 'عرض المقاييس', type: 'boolean', required: false, default: true },
    ],
  },

  // ========== MEDIA WIDGETS ==========
  {
    type: 'image',
    name: 'Image Widget',
    name_ar: 'صورة',
    icon: '🖼️',
    description: 'عرض صورة',
    default_width: 4,
    default_height: 4,
    supported_data_sources: ['static'],
    config_fields: [
      { name: 'image_url', label: 'Image URL', label_ar: 'رابط الصورة', type: 'text', required: true },
      { name: 'alt_text', label: 'Alt Text', label_ar: 'نص بديل', type: 'text', required: false },
      { name: 'fit', label: 'Fit', label_ar: 'ملاءمة', type: 'select', required: false, options: [
        { value: 'contain', label: 'Contain', label_ar: 'احتواء' },
        { value: 'cover', label: 'Cover', label_ar: 'تغطية' },
        { value: 'fill', label: 'Fill', label_ar: 'ملء' },
      ]},
    ],
  },
  {
    type: 'video',
    name: 'Video Widget',
    name_ar: 'فيديو',
    icon: '🎥',
    description: 'عرض فيديو',
    default_width: 8,
    default_height: 6,
    supported_data_sources: ['youtube', 'vimeo', 'local'],
    config_fields: [
      { name: 'video_url', label: 'Video URL', label_ar: 'رابط الفيديو', type: 'text', required: true },
      { name: 'autoplay', label: 'Autoplay', label_ar: 'تشغيل تلقائي', type: 'boolean', required: false, default: false },
    ],
  },
  {
    type: 'pdf_viewer',
    name: 'PDF Viewer',
    name_ar: 'عارض PDF',
    icon: '📄',
    description: 'عرض ملف PDF',
    default_width: 12,
    default_height: 8,
    supported_data_sources: ['local', 'url'],
    config_fields: [
      { name: 'pdf_url', label: 'PDF URL', label_ar: 'رابط PDF', type: 'text', required: true },
      { name: 'show_toolbar', label: 'Show Toolbar', label_ar: 'عرض شريط الأدوات', type: 'boolean', required: false, default: true },
    ],
  },
  {
    type: 'website_embed',
    name: 'Website Embed',
    name_ar: 'تضمين موقع',
    icon: '🌐',
    description: 'تضمين موقع ويب',
    default_width: 12,
    default_height: 8,
    supported_data_sources: ['url'],
    config_fields: [
      { name: 'url', label: 'Website URL', label_ar: 'رابط الموقع', type: 'text', required: true },
      { name: 'height', label: 'Height', label_ar: 'الارتفاع', type: 'number', required: false, default: 600 },
    ],
  },

  // ========== UTILITY WIDGETS ==========
  {
    type: 'clock_digital',
    name: 'Digital Clock',
    name_ar: 'ساعة رقمية',
    icon: '🕐',
    description: 'ساعة رقمية',
    default_width: 3,
    default_height: 2,
    supported_data_sources: ['system'],
    config_fields: [
      { name: 'show_seconds', label: 'Show Seconds', label_ar: 'عرض الثواني', type: 'boolean', required: false, default: true },
      { name: 'show_date', label: 'Show Date', label_ar: 'عرض التاريخ', type: 'boolean', required: false, default: true },
      { name: 'timezone', label: 'Timezone', label_ar: 'المنطقة الزمنية', type: 'select', required: false, options: [
        { value: 'local', label: 'Local', label_ar: 'محلي' },
        { value: 'UTC', label: 'UTC', label_ar: 'UTC' },
      ]},
    ],
  },
  {
    type: 'calendar',
    name: 'Calendar',
    name_ar: 'تقويم',
    icon: '📅',
    description: 'تقويم شهري',
    default_width: 6,
    default_height: 6,
    supported_data_sources: ['events', 'static'],
    config_fields: [
      { name: 'show_events', label: 'Show Events', label_ar: 'عرض الأحداث', type: 'boolean', required: false, default: true },
      { name: 'first_day', label: 'First Day', label_ar: 'أول يوم', type: 'select', required: false, options: [
        { value: 'saturday', label: 'Saturday', label_ar: 'السبت' },
        { value: 'sunday', label: 'Sunday', label_ar: 'الأحد' },
      ]},
    ],
  },
  {
    type: 'notes',
    name: 'Notes',
    name_ar: 'ملاحظات',
    icon: '📝',
    description: 'ملاحظات شخصية',
    default_width: 4,
    default_height: 4,
    supported_data_sources: ['user_notes'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: false },
      { name: 'color', label: 'Color', label_ar: 'اللون', type: 'select', required: false, options: [
        { value: 'yellow', label: 'Yellow', label_ar: 'أصفر' },
        { value: 'blue', label: 'Blue', label_ar: 'أزرق' },
        { value: 'green', label: 'Green', label_ar: 'أخضر' },
        { value: 'pink', label: 'Pink', label_ar: 'وردي' },
      ]},
    ],
  },
  {
    type: 'shortcuts',
    name: 'Shortcuts',
    name_ar: 'اختصارات',
    icon: '⚡',
    description: 'اختصارات سريعة',
    default_width: 4,
    default_height: 3,
    supported_data_sources: ['static'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: false },
      { name: 'links', label: 'Links', label_ar: 'الروابط', type: 'json', required: true },
    ],
  },
  {
    type: 'quick_actions',
    name: 'Quick Actions',
    name_ar: 'إجراءات سريعة',
    icon: '🎯',
    description: 'أزرار إجراءات سريعة',
    default_width: 6,
    default_height: 3,
    supported_data_sources: ['static'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: false },
      { name: 'actions', label: 'Actions', label_ar: 'الإجراءات', type: 'json', required: true },
    ],
  },
  {
    type: 'announcements',
    name: 'Announcements',
    name_ar: 'إعلانات',
    icon: '📢',
    description: 'إعلانات النظام',
    default_width: 12,
    default_height: 4,
    supported_data_sources: ['announcements', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: false },
      { name: 'limit', label: 'Limit', label_ar: 'الحد', type: 'number', required: false, default: 5 },
      { name: 'show_date', label: 'Show Date', label_ar: 'عرض التاريخ', type: 'boolean', required: false, default: true },
    ],
  },

  // ========== DATA WIDGETS ==========
  {
    type: 'table',
    name: 'Data Table',
    name_ar: 'جدول بيانات',
    icon: '📋',
    description: 'عرض بيانات في جدول',
    default_width: 12,
    default_height: 8,
    supported_data_sources: ['receipts', 'workflows', 'registers', 'users', 'custom_query', 'api'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'columns', label: 'Columns', label_ar: 'الأعمدة', type: 'json', required: true },
      { name: 'limit', label: 'Limit', label_ar: 'الحد', type: 'number', required: false, default: 10 },
      { name: 'sortable', label: 'Sortable', label_ar: 'قابل للترتيب', type: 'boolean', required: false, default: true },
      { name: 'searchable', label: 'Searchable', label_ar: 'قابل للبحث', type: 'boolean', required: false, default: true },
    ],
  },
  {
    type: 'list',
    name: 'List',
    name_ar: 'قائمة',
    icon: '📝',
    description: 'عرض قائمة عناصر',
    default_width: 6,
    default_height: 6,
    supported_data_sources: ['receipts', 'workflows', 'workflow_tasks', 'audit_logs', 'custom_query'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'item_template', label: 'Item Template', label_ar: 'قالب العنصر', type: 'json', required: true },
      { name: 'limit', label: 'Limit', label_ar: 'الحد', type: 'number', required: false, default: 10 },
      { name: 'show_avatar', label: 'Show Avatar', label_ar: 'عرض الصورة', type: 'boolean', required: false, default: false },
    ],
  },

  // ========== CHART WIDGETS ==========
  {
    type: 'chart',
    name: 'Chart',
    name_ar: 'رسم بياني',
    icon: '📊',
    description: 'عرض بيانات كرسم بياني',
    default_width: 6,
    default_height: 6,
    supported_data_sources: ['receipts', 'workflows', 'custom_query', 'api'],
    config_fields: [
      { name: 'chart_type', label: 'Chart Type', label_ar: 'نوع الرسم', type: 'select', required: true, default: 'bar', options: [
        { value: 'bar', label: 'Bar', label_ar: 'أعمدة' },
        { value: 'line', label: 'Line', label_ar: 'خطي' },
        { value: 'pie', label: 'Pie', label_ar: 'دائري' },
        { value: 'area', label: 'Area', label_ar: 'مساحة' },
        { value: 'donut', label: 'Donut', label_ar: 'دونات' },
      ]},
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'x_field', label: 'X Field', label_ar: 'حقل X', type: 'text', required: true },
      { name: 'y_field', label: 'Y Field', label_ar: 'حقل Y', type: 'text', required: true },
      { name: 'show_legend', label: 'Show Legend', label_ar: 'عرض الوسيم', type: 'boolean', required: false, default: true },
    ],
  },
  {
    type: 'pie_chart',
    name: 'Pie Chart',
    name_ar: 'رسم دائري',
    icon: '🥧',
    description: 'رسم بياني دائري',
    default_width: 6,
    default_height: 6,
    supported_data_sources: ['receipts_by_register', 'fees_by_type', 'custom_query'],
    config_fields: [
      { name: 'title', label: 'Title', label_ar: 'العنوان', type: 'text', required: true },
      { name: 'label_field', label: 'Label Field', label_ar: 'حقل التسمية', type: 'text', required: true },
      { name: 'value_field', label: 'Value Field', label_ar: 'حقل القيمة', type: 'text', required: true },
      { name: 'show_percentages', label: 'Show Percentages', label_ar: 'عرض النسب', type: 'boolean', required: false, default: true },
    ],
  },
];
