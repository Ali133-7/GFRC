import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, DragOverlay } from '@dnd-kit/core';
import { sortableKeyboardCoordinates, arrayMove } from '@dnd-kit/sortable';
import { dashboardApi } from '@/api/dashboard';
import type { Dashboard, DashboardSection, DashboardWidget } from '@/types/dashboard';
import { WIDGET_TYPES } from '@/types/dashboard';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { Button } from '@/components/ui/Button';

// Widget categories with graphical icons
const WIDGET_CATEGORIES = [
  {
    id: 'kpi',
    name: 'مؤشرات الأداء',
    icon: '📊',
    color: 'bg-blue-50 border-blue-200',
    widgets: ['kpi_card', 'stat_card', 'gauge'],
  },
  {
    id: 'financial',
    name: 'المالية',
    icon: '💰',
    color: 'bg-green-50 border-green-200',
    widgets: ['revenue_chart', 'fee_breakdown'],
  },
  {
    id: 'workflow',
    name: 'سير العمل',
    icon: '🔄',
    color: 'bg-purple-50 border-purple-200',
    widgets: ['workflow_status', 'task_list'],
  },
  {
    id: 'data',
    name: 'البيانات',
    icon: '📋',
    color: 'bg-amber-50 border-amber-200',
    widgets: ['table', 'list'],
  },
  {
    id: 'charts',
    name: 'الرسوم البيانية',
    icon: '📈',
    color: 'bg-rose-50 border-rose-200',
    widgets: ['chart', 'pie_chart'],
  },
  {
    id: 'media',
    name: 'الوسائط',
    icon: '🖼️',
    color: 'bg-indigo-50 border-indigo-200',
    widgets: ['image', 'video', 'pdf_viewer', 'website_embed'],
  },
  {
    id: 'utility',
    name: 'أدوات مساعدة',
    icon: '⚡',
    color: 'bg-gray-50 border-gray-200',
    widgets: ['clock_digital', 'calendar', 'notes', 'shortcuts', 'quick_actions', 'announcements'],
  },
];

// Data sources for widgets
const DATA_SOURCES = [
  { value: 'receipts_total', label: 'إجمالي الوصولات', icon: '📄' },
  { value: 'receipts_count', label: 'عدد الوصولات', icon: '🔢' },
  { value: 'pending_receipts', label: 'الوصول المعلقة', icon: '⏳' },
  { value: 'receipts_by_register', label: 'حسب السجل', icon: '📁' },
  { value: 'fees_by_type', label: 'حسب نوع الرسم', icon: '💵' },
  { value: 'workflow_tasks', label: 'مهام سير العمل', icon: '✅' },
  { value: 'audit_logs', label: 'سجل التدقيق', icon: '🔍' },
  { value: 'system_metrics', label: 'مقاييس النظام', icon: '💻' },
  { value: 'custom_query', label: 'استعلام مخصص', icon: '🔧' },
  { value: 'api', label: 'API خارجي', icon: '🌐' },
];

// Register data sources (will be fetched)
const getRegisterSources = (registers: any[]) =>
  registers.map((r: any) => ({
    value: `register_${r.id}`,
    label: r.name_ar || r.name,
    icon: '📁',
  }));

interface WidgetConfigModal {
  isOpen: boolean;
  sectionId: number | null;
  widgetType: string | null;
}

interface WidgetSettingsModal {
  isOpen: boolean;
  widget: DashboardWidget | null;
  sectionId: number | null;
}

export default function DashboardBuilderPage() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const [isLoading, setIsLoading] = useState(false);
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [dashboardName, setDashboardName] = useState<string>('داشبورد جديد');
  const [sections, setSections] = useState<DashboardSection[]>([
    {
      id: 1,
      dashboard_id: 0,
      name_ar: 'الإحصائيات العامة',
      layout_type: 'grid',
      sort_order: 0,
      is_visible: true,
      is_collapsible: false,
      is_collapsed: false,
      padding: 16,
      widgets: [
        {
          id: 1,
          section_id: 1,
          name_ar: 'إجمالي الإيرادات اليومية',
          widget_type: 'stat_card',
          data_source: 'registers',
          sort_order: 0,
          grid_x: 0,
          grid_y: 0,
          grid_width: 1,
          grid_height: 1,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 60,
          is_real_time: true,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            metric: 'total_revenue',
            period: 'today',
            comparison: 'yesterday',
          },
          display_config: {
            title: 'إجمالي الإيرادات اليوم',
            color: 'blue',
            icon: '💰',
            show_trend: true,
            prefix: '',
            suffix: 'د.ع',
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 2,
          section_id: 1,
          name_ar: 'عدد المعاملات',
          widget_type: 'stat_card',
          data_source: 'registers',
          sort_order: 1,
          grid_x: 1,
          grid_y: 0,
          grid_width: 1,
          grid_height: 1,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 60,
          is_real_time: true,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            metric: 'transaction_count',
            period: 'today',
            comparison: 'yesterday',
          },
          display_config: {
            title: 'عدد المعاملات اليوم',
            color: 'green',
            icon: '📊',
            show_trend: true,
            prefix: '',
            suffix: 'معاملة',
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 3,
          section_id: 1,
          name_ar: 'المعاملات المعلقة',
          widget_type: 'stat_card',
          data_source: 'registers',
          sort_order: 2,
          grid_x: 2,
          grid_y: 0,
          grid_width: 1,
          grid_height: 1,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 60,
          is_real_time: true,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            metric: 'pending_count',
            period: 'today',
          },
          display_config: {
            title: 'المعاملات المعلقة',
            color: 'amber',
            icon: '⏳',
            show_trend: false,
            prefix: '',
            suffix: 'معاملة',
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 4,
          section_id: 1,
          name_ar: 'نسبة الإنجاز',
          widget_type: 'stat_card',
          data_source: 'registers',
          sort_order: 3,
          grid_x: 3,
          grid_y: 0,
          grid_width: 1,
          grid_height: 1,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 60,
          is_real_time: true,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            metric: 'completion_rate',
            period: 'today',
          },
          display_config: {
            title: 'نسبة الإنجاز',
            color: 'purple',
            icon: '✅',
            show_trend: true,
            prefix: '',
            suffix: '%',
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 2,
      dashboard_id: 0,
      name_ar: 'النشاط اليومي',
      layout_type: 'horizontal',
      sort_order: 1,
      is_visible: true,
      is_collapsible: true,
      is_collapsed: false,
      padding: 16,
      widgets: [
        {
          id: 5,
          section_id: 2,
          name_ar: 'آخر المعاملات',
          widget_type: 'table',
          data_source: 'registers',
          sort_order: 0,
          grid_x: 0,
          grid_y: 0,
          grid_width: 2,
          grid_height: 2,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 30,
          is_real_time: true,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            register_id: 'all',
            limit: 10,
            sort_by: 'created_at',
            sort_order: 'desc',
          },
          display_config: {
            title: 'آخر 10 معاملات',
            color: 'slate',
            show_header: true,
            columns: ['transaction_number', 'amount', 'status', 'created_at'],
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 6,
          section_id: 2,
          name_ar: 'الرسم البياني اليومي',
          widget_type: 'chart',
          data_source: 'registers',
          sort_order: 1,
          grid_x: 2,
          grid_y: 0,
          grid_width: 2,
          grid_height: 2,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 60,
          is_real_time: false,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            chart_type: 'line',
            metric: 'revenue',
            period: '7days',
            group_by: 'day',
          },
          display_config: {
            title: 'الإيرادات خلال 7 أيام',
            color: 'blue',
            show_legend: true,
            show_grid: true,
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 3,
      dashboard_id: 0,
      name_ar: 'الأداء الشهري',
      layout_type: 'grid',
      sort_order: 2,
      is_visible: true,
      is_collapsible: true,
      is_collapsed: true,
      padding: 16,
      widgets: [
        {
          id: 7,
          section_id: 3,
          name_ar: 'إجمالي الإيرادات الشهرية',
          widget_type: 'stat_card',
          data_source: 'registers',
          sort_order: 0,
          grid_x: 0,
          grid_y: 0,
          grid_width: 1,
          grid_height: 1,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 300,
          is_real_time: false,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            metric: 'total_revenue',
            period: 'month',
            comparison: 'last_month',
          },
          display_config: {
            title: 'إيرادات هذا الشهر',
            color: 'emerald',
            icon: '📈',
            show_trend: true,
            prefix: '',
            suffix: 'د.ع',
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 8,
          section_id: 3,
          name_ar: 'مقارنة الأشهر',
          widget_type: 'chart',
          data_source: 'registers',
          sort_order: 1,
          grid_x: 1,
          grid_y: 0,
          grid_width: 2,
          grid_height: 1,
          is_visible: true,
          is_editable: true,
          is_removable: true,
          is_inherited: false,
          is_customized: false,
          refresh_interval: 300,
          is_real_time: false,
          filter_by_user: false,
          filter_by_department: false,
          filter_by_role: false,
          filter_by_branch: false,
          data_config: {
            chart_type: 'bar',
            metric: 'revenue',
            period: '6months',
            group_by: 'month',
          },
          display_config: {
            title: 'مقارنة الإيرادات - 6 أشهر',
            color: 'indigo',
            show_legend: true,
            show_grid: true,
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
  ]);
  const [selectedWidget, setSelectedWidget] = useState<string | null>(null);
  const [showPreview, setShowPreview] = useState(false);
  const [scope, setScope] = useState<'user' | 'role' | 'department' | 'system'>('user');
  const [visibility, setVisibility] = useState<'private' | 'shared' | 'role' | 'department' | 'public'>('private');
  const [registers, setRegisters] = useState<any[]>([]);
  const [widgetConfig, setWidgetConfig] = useState<WidgetConfigModal>({ isOpen: false, sectionId: null, widgetType: null });
  const [widgetSettings, setWidgetSettings] = useState<WidgetSettingsModal>({ isOpen: false, widget: null, sectionId: null });
  const [previewData, setPreviewData] = useState<any>(null);
  const [activeWidget, setActiveWidget] = useState<DashboardWidget | null>(null);

  // Auto-update preview when sections change
  useEffect(() => {
    if (showPreview) {
      setPreviewData({
        sections: sections,
        dashboard_name: dashboardName,
        scope: scope,
        visibility: visibility,
        widgets_count: sections.reduce((acc, section) => acc + (section.widgets?.length || 0), 0),
        sections_count: sections.length,
      });
    }
  }, [sections, dashboardName, scope, visibility, showPreview]);

  // Drag and drop sensors
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  // Load registers
  useEffect(() => {
    const loadRegisters = async () => {
      try {
        // Get token from gfrc-auth storage
        const authRaw = localStorage.getItem('gfrc-auth');
        let token = null;
        if (authRaw) {
          try {
            const parsed = JSON.parse(authRaw);
            token = parsed?.state?.token;
          } catch (e) {
            console.error('Failed to parse auth token:', e);
          }
        }
        
        const response = await fetch('/api/v1/registers', {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (response.ok) {
          const data = await response.json();
          setRegisters(data.data || []);
        } else if (response.status === 401) {
          console.warn('Unauthorized - please login');
          window.location.href = '/login';
        }
      } catch (error) {
        console.error('Failed to load registers:', error);
      }
    };
    loadRegisters();
  }, []);

  // Load existing dashboard
  useEffect(() => {
    if (id) {
      loadDashboard(id);
    }
  }, [id]);

  const loadDashboard = async (dashboardId: string) => {
    try {
      setIsLoading(true);
      const result = await dashboardApi.getDashboard(parseInt(dashboardId));
      setDashboard(result.dashboard);
      setDashboardName(result.dashboard.name_ar || 'داشبورد جديد');
      setSections(result.dashboard.sections || []);
      setScope(result.dashboard.scope as any || 'user');
      setVisibility(result.dashboard.visibility as any || 'private');
    } catch (error) {
      console.error('Failed to load dashboard:', error);
      setDashboardName('داشبورد جديد');
      setSections([]);
    } finally {
      setIsLoading(false);
    }
  };

  const resetToDefault = () => {
    if (window.confirm('هل أنت متأكد من إعادة تعيين الداشبورد للتصميم الافتراضي؟ سيتم فقدان جميع التغييرات.')) {
      setSections([
        {
          id: 1,
          dashboard_id: 0,
          name_ar: 'الإحصائيات العامة',
          layout_type: 'grid',
          sort_order: 0,
          is_visible: true,
          is_collapsible: false,
          is_collapsed: false,
          padding: 16,
          widgets: [
            {
              id: 1,
              section_id: 1,
              name_ar: 'إجمالي الإيرادات اليومية',
              widget_type: 'stat_card',
              data_source: 'registers',
              sort_order: 0,
              grid_x: 0,
              grid_y: 0,
              grid_width: 1,
              grid_height: 1,
              is_visible: true,
              is_editable: true,
              is_removable: true,
              is_inherited: false,
              is_customized: false,
              refresh_interval: 60,
              is_real_time: true,
              filter_by_user: false,
              filter_by_department: false,
              filter_by_role: false,
              filter_by_branch: false,
              data_config: { metric: 'total_revenue', period: 'today', comparison: 'yesterday' },
              display_config: { title: 'إجمالي الإيرادات اليوم', color: 'blue', icon: '💰', show_trend: true, suffix: 'د.ع' },
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString(),
            },
            {
              id: 2,
              section_id: 1,
              name_ar: 'عدد المعاملات',
              widget_type: 'stat_card',
              data_source: 'registers',
              sort_order: 1,
              grid_x: 1,
              grid_y: 0,
              grid_width: 1,
              grid_height: 1,
              is_visible: true,
              is_editable: true,
              is_removable: true,
              is_inherited: false,
              is_customized: false,
              refresh_interval: 60,
              is_real_time: true,
              filter_by_user: false,
              filter_by_department: false,
              filter_by_role: false,
              filter_by_branch: false,
              data_config: { metric: 'transaction_count', period: 'today', comparison: 'yesterday' },
              display_config: { title: 'عدد المعاملات اليوم', color: 'green', icon: '📊', show_trend: true, suffix: 'معاملة' },
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString(),
            },
            {
              id: 3,
              section_id: 1,
              name_ar: 'المعاملات المعلقة',
              widget_type: 'stat_card',
              data_source: 'registers',
              sort_order: 2,
              grid_x: 2,
              grid_y: 0,
              grid_width: 1,
              grid_height: 1,
              is_visible: true,
              is_editable: true,
              is_removable: true,
              is_inherited: false,
              is_customized: false,
              refresh_interval: 60,
              is_real_time: true,
              filter_by_user: false,
              filter_by_department: false,
              filter_by_role: false,
              filter_by_branch: false,
              data_config: { metric: 'pending_count', period: 'today' },
              display_config: { title: 'المعاملات المعلقة', color: 'amber', icon: '⏳', suffix: 'معاملة' },
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString(),
            },
            {
              id: 4,
              section_id: 1,
              name_ar: 'نسبة الإنجاز',
              widget_type: 'stat_card',
              data_source: 'registers',
              sort_order: 3,
              grid_x: 3,
              grid_y: 0,
              grid_width: 1,
              grid_height: 1,
              is_visible: true,
              is_editable: true,
              is_removable: true,
              is_inherited: false,
              is_customized: false,
              refresh_interval: 60,
              is_real_time: true,
              filter_by_user: false,
              filter_by_department: false,
              filter_by_role: false,
              filter_by_branch: false,
              data_config: { metric: 'completion_rate', period: 'today' },
              display_config: { title: 'نسبة الإنجاز', color: 'purple', icon: '✅', show_trend: true, suffix: '%' },
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString(),
            },
          ],
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ]);
      setDashboardName('الداشبورد الرئيسية');
    }
  };

  const addSection = () => {
    const newSection: DashboardSection = {
      id: Date.now(),
      dashboard_id: id ? parseInt(id) : 0,
      name_ar: `قسم ${sections.length + 1}`,
      layout_type: 'grid',
      sort_order: sections.length,
      is_visible: true,
      is_collapsible: false,
      is_collapsed: false,
      padding: 16,
      widgets: [],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    setSections([...sections, newSection]);
  };

  const removeSection = (sectionId: number) => {
    if (window.confirm('هل أنت متأكد من حذف هذا القسم؟')) {
      setSections(sections.filter(s => s.id !== sectionId));
    }
  };

  const openWidgetConfig = (sectionId: number, widgetType: string) => {
    setWidgetConfig({ isOpen: true, sectionId, widgetType });
  };

  const closeWidgetConfig = () => {
    setWidgetConfig({ isOpen: false, sectionId: null, widgetType: null });
  };

  const addWidgetToSection = (sectionId: number, config: any) => {
    const widgetDef = WIDGET_TYPES.find(w => w.type === widgetConfig.widgetType);
    if (!widgetDef || !widgetConfig.sectionId) return;

    // Build intelligent data_config based on widget type and user selections
    const dataConfig: any = {
      register_id: config.register_id,
      field_id: config.field_id,
      metric_type: config.metric_type,
      calculation: config.calculation,
      period: config.period,
      comparison_enabled: config.comparison_enabled,
      comparison_period: config.comparison_period,
    };

    // Add field metadata if available
    if (config.field_metadata) {
      dataConfig.field_type = config.field_metadata.field_type;
      dataConfig.field_name = config.field_metadata.name;
      dataConfig.field_label = config.field_metadata.label;
    }

    // Add register metadata if available
    if (config.register_metadata) {
      dataConfig.register_name = config.register_metadata.name_ar || config.register_metadata.name;
      dataConfig.register_code = config.register_metadata.code;
      dataConfig.is_financial = config.register_metadata.is_financial || false;
    }

    const newWidget: DashboardWidget = {
      id: Date.now(),
      section_id: widgetConfig.sectionId,
      name_ar: config.title || widgetDef.name_ar,
      widget_type: widgetConfig.widgetType!,
      data_source: config.data_source || undefined,
      sort_order: sections.find(s => s.id === sectionId)?.widgets?.length || 0,
      grid_x: 0,
      grid_y: 0,
      grid_width: widgetDef.default_width,
      grid_height: widgetDef.default_height,
      is_visible: true,
      is_editable: true,
      is_removable: true,
      is_inherited: false,
      is_customized: false,
      refresh_interval: 30,
      is_real_time: false,
      filter_by_user: false,
      filter_by_department: false,
      filter_by_role: false,
      filter_by_branch: false,
      data_config: dataConfig,
      display_config: {
        title: config.title || widgetDef.name_ar,
        color: config.color || 'blue',
        show_trend: config.comparison_enabled,
        icon: widgetDef.icon,
        suffix: config.calculation === 'sum' || config.calculation === 'avg' ? 'د.ع' : config.calculation === 'count' ? '' : '%',
      },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    setSections(sections.map(section => {
      if (section.id === sectionId) {
        return {
          ...section,
          widgets: [...(section.widgets || []), newWidget],
        };
      }
      return section;
    }));

    closeWidgetConfig();
  };

  const openWidgetSettings = (sectionId: number, widget: DashboardWidget) => {
    setWidgetSettings({ isOpen: true, widget, sectionId });
  };

  const closeWidgetSettings = () => {
    setWidgetSettings({ isOpen: false, widget: null, sectionId: null });
  };

  const updateWidget = (sectionId: number, widgetId: number, updates: Partial<DashboardWidget>) => {
    setSections(sections.map(section => {
      if (section.id === sectionId) {
        return {
          ...section,
          widgets: section.widgets?.map((w: DashboardWidget) =>
            w.id === widgetId ? { ...w, ...updates } : w
          ),
        };
      }
      return section;
    }));
  };

  const removeWidget = (sectionId: number, widgetId: number) => {
    if (window.confirm('هل أنت متأكد من إزالة هذا widget؟')) {
      setSections(sections.map(section => {
        if (section.id === sectionId) {
          return {
            ...section,
            widgets: section.widgets?.filter((w: DashboardWidget) => w.id !== widgetId),
          };
        }
        return section;
      }));
    }
  };

  const loadPreviewData = async () => {
    try {
      setIsLoading(true);
      // Preview the actual dashboard with current sections and widgets
      setPreviewData({
        sections: sections,
        dashboard_name: dashboardName,
        scope: scope,
        visibility: visibility,
        widgets_count: sections.reduce((acc, section) => acc + (section.widgets?.length || 0), 0),
        sections_count: sections.length,
      });
    } catch (error) {
      console.error('Failed to load preview data:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleDragStart = (event: any) => {
    const { active } = event;
    const widget = sections
      .flatMap(s => s.widgets || [])
      .find(w => w.id === active.id);
    setActiveWidget(widget || null);
  };

  const handleDragEnd = (event: any) => {
    const { active, over } = event;
    setActiveWidget(null);

    if (over && active.id !== over.id) {
      // Handle widget reordering
      setSections(sections.map(section => {
        const widgetIds = (section.widgets || []).map(w => w.id);
        const oldIndex = widgetIds.indexOf(active.id);
        const newIndex = widgetIds.indexOf(over.id);

        if (oldIndex !== -1 && newIndex !== -1) {
          const newWidgets = arrayMove(section.widgets || [], oldIndex, newIndex);
          return { ...section, widgets: newWidgets };
        }
        return section;
      }));
    }
  };

  const saveDashboard = async () => {
    try {
      if (!dashboardName || dashboardName.trim() === '') {
        alert('⚠️ الرجاء إدخال اسم الداشبورد');
        return;
      }

      if (dashboardName.trim().length > 255) {
        alert('⚠️ اسم الداشبورد طويل جداً (الحد الأقصى 255 حرف)');
        return;
      }

      setIsLoading(true);
      
      const dashboardData = {
        name_ar: dashboardName.trim(),
        scope,
        visibility,
        is_active: true,
        layout_config: {},
        theme_config: {},
      };

      if (id && dashboard) {
        await dashboardApi.updateDashboard(dashboard.id, dashboardData);
        alert('✅ تم تحديث الداشبورد بنجاح!');
      } else {
        const result = await dashboardApi.createDashboard(dashboardData);
        alert('✅ تم إنشاء الداشبورد بنجاح!');
        navigate(`/dashboard/builder/${result.dashboard.id}`);
        return;
      }

      navigate('/dashboard');
    } catch (error: any) {
      console.error('Failed to save dashboard:', error);
      const errorMessage = error?.response?.data?.message || error?.message || 'فشل حفظ الداشبورد';
      alert('❌ ' + errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading && !dashboard && id) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50" dir="rtl">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 px-6 py-4 sticky top-0 z-50 shadow-sm">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate('/dashboard')}
              className="p-2 hover:bg-gray-100 rounded transition-colors"
              title="عودة"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <div className="flex items-center gap-2">
              <input
                type="text"
                value={dashboardName}
                onChange={(e) => setDashboardName(e.target.value)}
                className="text-xl font-bold text-gray-900 border border-gray-300 rounded px-3 py-1 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="اسم الداشبورد"
              />
              {id && (
                <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                  جارٍ التعديل
                </span>
              )}
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={resetToDefault}
              className="text-orange-600 border-orange-300 hover:bg-orange-50"
            >
              🔄 افتراضي
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setShowPreview(!showPreview);
                if (!showPreview) {
                  loadPreviewData();
                }
              }}
            >
              {showPreview ? '✏️ تعديل' : '👁️ معاينة'}
            </Button>
            <Button
              onClick={saveDashboard}
              disabled={isLoading}
              className="bg-blue-600 hover:bg-blue-700"
            >
              {isLoading ? '⏳ جاري الحفظ...' : '💾 حفظ الداشبورد'}
            </Button>
          </div>
        </div>

        {/* Settings Bar */}
        <div className="mt-4 pt-4 border-t border-gray-200 flex items-center gap-4 flex-wrap">
          <div className="flex items-center gap-2">
            <label className="text-sm text-gray-600 font-medium">النطاق:</label>
            <select
              value={scope}
              onChange={(e) => setScope(e.target.value as any)}
              className="text-sm border border-gray-300 rounded px-3 py-1.5 focus:ring-2 focus:ring-blue-500 bg-white"
            >
              <option value="user">👤 شخصي</option>
              <option value="role">🎭 حسب الدور</option>
              <option value="department">🏢 حسب القسم</option>
              <option value="system">🌐 عام</option>
            </select>
          </div>

          <div className="flex items-center gap-2">
            <label className="text-sm text-gray-600 font-medium">الرؤية:</label>
            <select
              value={visibility}
              onChange={(e) => setVisibility(e.target.value as any)}
              className="text-sm border border-gray-300 rounded px-3 py-1.5 focus:ring-2 focus:ring-blue-500 bg-white"
            >
              <option value="private">🔒 خاص</option>
              <option value="shared">👥 مشارك</option>
              <option value="role">🎭 للدور</option>
              <option value="department">🏢 للقسم</option>
              <option value="public">🌍 عام</option>
            </select>
          </div>

          <div className="flex-1" />

          <div className="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded">
            📊 {sections.length} قسم | {sections.reduce((acc, s) => acc + (s.widgets?.length || 0), 0)} widget
          </div>
        </div>
      </header>

      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        <div className="flex" style={{ height: 'calc(100vh - 140px)' }}>
          {/* Main Canvas */}
          <main className="flex-1 p-6 overflow-auto">
            <div className="max-w-6xl mx-auto">
              {sections.length === 0 ? (
                <div className="text-center py-20">
                  <div className="text-6xl mb-4">🎨</div>
                  <h3 className="text-xl font-bold text-gray-700 mb-2">ابدأ بإنشاء داشبوردك</h3>
                  <p className="text-gray-500 mb-6">اضف أقسام و widgets لتخصيص الداشبورد الخاص بك</p>
                  <button
                    onClick={addSection}
                    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                  >
                    ➕ إضافة أول قسم
                  </button>
                </div>
              ) : (
                <>
                  {/* Sections */}
                  {sections.map((section, sectionIdx) => (
                    <div
                      key={section.id}
                      className="mb-6 p-5 bg-white rounded-lg border-2 border-dashed border-gray-300 hover:border-blue-400 transition-colors shadow-sm"
                    >
                      {/* Section Header */}
                      <div className="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                        <input
                          type="text"
                          value={section.name_ar}
                          onChange={(e) => {
                            const newSections = [...sections];
                            newSections[sectionIdx].name_ar = e.target.value;
                            setSections(newSections);
                          }}
                          className="text-lg font-semibold text-gray-900 border-none focus:ring-0 bg-transparent w-full"
                          placeholder="اسم القسم"
                        />
                        <button
                          onClick={() => removeSection(section.id)}
                          className="p-2 text-red-600 hover:bg-red-50 rounded transition-colors mr-2"
                          title="حذف القسم"
                        >
                          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>

                      {/* Widgets Grid */}
                      <div className="grid grid-cols-12 gap-4 min-h-[150px]">
                        {section.widgets?.map((widget: DashboardWidget, widgetIdx: number) => (
                          <div
                            key={widget.id}
                            className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-4 border border-gray-200 relative group hover:shadow-lg hover:border-blue-300 transition-all cursor-move"
                            style={{
                              gridColumn: `span ${widget.grid_width}`,
                              minHeight: `${widget.grid_height * 60}px`,
                            }}
                            onClick={() => openWidgetSettings(section.id, widget)}
                          >
                            {/* Widget Controls */}
                            <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  openWidgetSettings(section.id, widget);
                                }}
                                className="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors"
                                title="إعدادات"
                              >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                              </button>
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  removeWidget(section.id, widget.id);
                                }}
                                className="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors"
                                title="إزالة"
                              >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                              </button>
                            </div>

                            {/* Widget Content */}
                            <div className="text-center h-full flex flex-col items-center justify-center">
                              <div className="text-4xl mb-3">
                                {WIDGET_TYPES.find(w => w.type === widget.widget_type)?.icon}
                              </div>
                              <div className="font-semibold text-gray-800 mb-1">{widget.name_ar}</div>
                              {widget.data_source && (
                                <div className="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded mb-2">
                                  📊 {DATA_SOURCES.find(ds => ds.value === widget.data_source)?.label || widget.data_source}
                                </div>
                              )}
                              <div className="text-xs text-gray-500 bg-gray-200 inline-block px-2 py-0.5 rounded">
                                {widget.grid_width} × {widget.grid_height}
                              </div>
                            </div>

                            {/* Size Controls */}
                            <div className="absolute bottom-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity" onClick={(e) => e.stopPropagation()}>
                              <select
                                value={widget.grid_width}
                                onChange={(e) => updateWidget(section.id, widget.id, { grid_width: parseInt(e.target.value) })}
                                className="text-xs border border-gray-300 rounded px-1.5 py-1 bg-white shadow-sm"
                                title="عرض widget"
                              >
                                <option value={3}>3 أعمدة</option>
                                <option value={4}>4 أعمدة</option>
                                <option value={6}>6 أعمدة</option>
                                <option value={8}>8 أعمدة</option>
                                <option value={12}>12 عمود</option>
                              </select>
                            </div>
                          </div>
                        ))}

                        {/* Add Widget Button */}
                        <div className="col-span-12">
                          <div
                            onClick={() => openWidgetConfig(section.id, 'placeholder')}
                            className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 hover:bg-blue-50 cursor-pointer transition-all"
                          >
                            <div className="text-4xl text-gray-300 mb-2">➕</div>
                            <div className="text-gray-500 font-medium">اضغط لإضافة widget</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}

                  {/* Add Section Button */}
                  <button
                    onClick={addSection}
                    className="w-full py-6 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition-all font-medium"
                  >
                    ➕ إضافة قسم جديد
                  </button>
                </>
              )}
            </div>
          </main>

          {/* Widget Palette Sidebar */}
          <aside className="w-80 bg-white border-r border-gray-200 overflow-auto shadow-lg">
            <div className="p-4 h-full flex flex-col">
              <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span>🎨</span> widgets المتاحة
              </h3>

              <div className="space-y-4 flex-1 overflow-auto">
                {WIDGET_CATEGORIES.map((category) => (
                  <div key={category.id} className="border border-gray-200 rounded-lg overflow-hidden">
                    <div className={`p-3 ${category.color} border-b`}>
                      <div className="flex items-center gap-2">
                        <span className="text-xl">{category.icon}</span>
                        <span className="font-semibold text-gray-800">{category.name}</span>
                      </div>
                    </div>
                    <div className="p-2 space-y-1">
                      {category.widgets.map((widgetType) => {
                        const widgetDef = WIDGET_TYPES.find(w => w.type === widgetType);
                        if (!widgetDef) return null;
                        
                        return (
                          <button
                            key={widgetType}
                            onClick={() => {
                              if (sections.length > 0) {
                                openWidgetConfig(sections[sections.length - 1].id, widgetType);
                              } else {
                                alert('⚠️ الرجاء إضافة قسم أولاً');
                              }
                            }}
                            className="w-full p-2 text-right hover:bg-gray-50 rounded transition-colors flex items-center gap-2"
                          >
                            <span className="text-lg">{widgetDef.icon}</span>
                            <div className="flex-1">
                              <div className="text-sm font-medium text-gray-800">{widgetDef.name_ar}</div>
                              <div className="text-xs text-gray-500">{widgetDef.description}</div>
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                ))}
              </div>

              {/* Quick Tips */}
              <div className="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                <h5 className="text-xs font-semibold text-blue-800 mb-2">💡 نصائح سريعة:</h5>
                <ul className="text-xs text-blue-700 space-y-1">
                  <li>• اسحب وأفلت widgets لتغيير الترتيب</li>
                  <li>• اضغط على widget لتعديل الإعدادات</li>
                  <li>• استخدم معاينة قبل الحفظ</li>
                  <li>• اختر مصدر البيانات المناسب</li>
                </ul>
              </div>
            </div>
          </aside>
        </div>
      </DndContext>

      {/* Widget Configuration Modal */}
      {widgetConfig.isOpen && (
        <WidgetConfigForm
          widgetType={widgetConfig.widgetType!}
          sectionId={widgetConfig.sectionId!}
          registers={registers}
          onSubmit={addWidgetToSection}
          onClose={closeWidgetConfig}
        />
      )}

      {/* Widget Settings Modal */}
      {widgetSettings.isOpen && widgetSettings.widget && (
        <WidgetSettingsForm
          widget={widgetSettings.widget}
          registers={registers}
          onSubmit={(updates) => {
            if (widgetSettings.sectionId && widgetSettings.widget) {
              updateWidget(widgetSettings.sectionId, widgetSettings.widget.id, updates);
            }
            closeWidgetSettings();
          }}
          onClose={closeWidgetSettings}
        />
      )}

      {/* Preview Modal - Show actual dashboard layout */}
      {showPreview && previewData && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setShowPreview(false)}>
          <div className="bg-white rounded-lg p-6 max-w-6xl w-full mx-4 max-h-[90vh] overflow-auto" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-gray-800">معاينة: {previewData.dashboard_name}</h3>
                <p className="text-sm text-gray-500 mt-1">
                  📊 {previewData.sections_count} أقسام • 🎯 {previewData.widgets_count} ودجات
                </p>
              </div>
              <button
                onClick={() => setShowPreview(false)}
                className="px-6 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
              >
                إغلاق
              </button>
            </div>
            
            {/* Preview sections and widgets */}
            <div className="space-y-6">
              {previewData.sections?.map((section: any, idx: number) => (
                <div key={section.id} className="border-2 border-gray-200 rounded-xl p-5 bg-gradient-to-br from-gray-50 to-white shadow-sm">
                  <div className="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                    <div className="flex items-center gap-3">
                      <span className="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm">
                        {idx + 1}
                      </span>
                      <h4 className="font-bold text-gray-800 text-lg">
                        {section.name_ar || `قسم ${idx + 1}`}
                      </h4>
                    </div>
                    <div className="flex items-center gap-4 text-sm text-gray-500">
                      <span>📐 التخطيط: {section.layout_type === 'grid' ? 'شبكي' : section.layout_type === 'horizontal' ? 'أفقي' : 'عمودي'}</span>
                      <span className="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-medium">
                        {section.widgets?.length || 0} ودجات
                      </span>
                    </div>
                  </div>
                  
                  {section.widgets && section.widgets.length > 0 ? (
                    <div className={`grid gap-4 ${
                      section.layout_type === 'grid' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' : 
                      section.layout_type === 'horizontal' ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-1'
                    }`}>
                      {section.widgets.map((widget: any, wIdx: number) => {
                        const widgetIcon = widget.widget_type === 'stat_card' ? '📊' : widget.widget_type === 'chart' ? '📈' : widget.widget_type === 'table' ? '📋' : '🎯';
                        const widgetColor = widget.display_config?.color === 'blue' ? 'bg-blue-50 border-blue-200 text-blue-700' :
                          widget.display_config?.color === 'green' ? 'bg-green-50 border-green-200 text-green-700' :
                          widget.display_config?.color === 'amber' ? 'bg-amber-50 border-amber-200 text-amber-700' :
                          widget.display_config?.color === 'purple' ? 'bg-purple-50 border-purple-200 text-purple-700' :
                          widget.display_config?.color === 'emerald' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' :
                          widget.display_config?.color === 'indigo' ? 'bg-indigo-50 border-indigo-200 text-indigo-700' :
                          'bg-slate-50 border-slate-200 text-slate-700';
                        
                        return (
                          <div
                            key={widget.id}
                            className={`p-4 rounded-xl border-2 ${widgetColor} transition-all hover:shadow-md`}
                          >
                            <div className="flex items-center gap-3 mb-3">
                              <span className="text-2xl">{widgetIcon}</span>
                              <div className="flex-1">
                                <div className="font-bold text-sm">{widget.display_config?.title || widget.name_ar || 'ودجت'}</div>
                                <div className="text-xs opacity-70">{widget.widget_type === 'stat_card' ? 'بطاقة إحصائية' : widget.widget_type === 'chart' ? 'رسم بياني' : 'جدول بيانات'}</div>
                              </div>
                            </div>
                            
                            {widget.display_config?.icon && (
                              <div className="text-3xl text-right opacity-50 mb-2">{widget.display_config.icon}</div>
                            )}
                            
                            <div className="text-xs space-y-1 opacity-80">
                              {widget.data_config?.metric && (
                                <div className="flex items-center gap-2">
                                  <span>📐 المقياس:</span>
                                  <span className="font-medium">{widget.data_config.metric}</span>
                                </div>
                              )}
                              {widget.data_config?.period && (
                                <div className="flex items-center gap-2">
                                  <span>⏰ الفترة:</span>
                                  <span className="font-medium">{widget.data_config.period === 'today' ? 'اليوم' : widget.data_config.period === '7days' ? '7 أيام' : widget.data_config.period === 'month' ? 'شهر' : widget.data_config.period === '6months' ? '6 أشهر' : widget.data_config.period}</span>
                                </div>
                              )}
                              {widget.data_config?.chart_type && (
                                <div className="flex items-center gap-2">
                                  <span>📊 نوع الرسم:</span>
                                  <span className="font-medium">{widget.data_config.chart_type === 'line' ? 'خطي' : widget.data_config.chart_type === 'bar' ? 'أعمدة' : widget.data_config.chart_type}</span>
                                </div>
                              )}
                              {widget.display_config?.suffix && (
                                <div className="flex items-center gap-2">
                                  <span>🏷️ اللاحقة:</span>
                                  <span className="font-medium">{widget.display_config.suffix}</span>
                                </div>
                              )}
                            </div>
                            
                            {widget.display_config?.show_trend && (
                              <div className="mt-3 pt-3 border-t border-current opacity-60">
                                <div className="text-xs flex items-center gap-1">
                                  <span>📈 يظهر الاتجاه</span>
                                </div>
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                    <div className="text-center py-8 px-4 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                      <div className="text-4xl mb-2">📭</div>
                      <div className="text-sm text-gray-500 font-medium">لا توجد ودجات في هذا القسم</div>
                      <div className="text-xs text-gray-400 mt-1">اضغط على + لإضافة ودجات جديدة</div>
                    </div>
                  )}
                </div>
              ))}
            </div>

            {(!previewData.sections || previewData.sections.length === 0) && (
              <div className="text-center py-16">
                <div className="text-6xl mb-4">🎨</div>
                <h4 className="text-xl font-bold text-gray-700 mb-2">الداشبورد فارغة</h4>
                <p className="text-gray-500 mb-6">ابدأ بإضافة أقسام وودجات لبناء داشبوردك</p>
                <button
                  onClick={() => setShowPreview(false)}
                  className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
                >
                  العودة للتحرير
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

// Advanced Widget Configuration Form with Intelligent Data Source Selection
function WidgetConfigForm({ widgetType, sectionId, registers, onSubmit, onClose }: {
  widgetType: string;
  sectionId: number;
  registers: any[];
  onSubmit: (sectionId: number, config: any) => void;
  onClose: () => void;
}) {
  const [selectedRegisterId, setSelectedRegisterId] = useState<string>('');
  const [selectedFieldId, setSelectedFieldId] = useState<string>('');
  const [selectedMetric, setSelectedMetric] = useState<string>('');
  const [config, setConfig] = useState({
    title: '',
    data_source_type: 'register', // 'register' | 'system' | 'custom'
    register_id: '',
    field_id: '',
    metric_type: '',
    calculation: 'sum', // sum, count, avg, min, max
    period: 'today',
    color: 'blue',
    comparison_enabled: false,
    comparison_period: 'yesterday',
  });

  const widgetDef = WIDGET_TYPES.find(w => w.type === widgetType);
  
  // Get fields for selected register
  const selectedRegister = registers.find(r => r.id === selectedRegisterId);
  const registerFields = selectedRegister?.fields || [];

  // Smart metric suggestions based on field type
  const getAvailableMetrics = (fieldType?: string) => {
    const metrics: Array<{ value: string; label: string; icon: string }> = [];
    
    if (!fieldType) return metrics;
    
    // Numeric fields support all calculations
    if (['number', 'decimal', 'currency'].includes(fieldType)) {
      metrics.push(
        { value: 'sum', label: 'المجموع', icon: '∑' },
        { value: 'avg', label: 'المتوسط', icon: '📊' },
        { value: 'count', label: 'العدد', icon: '#' },
        { value: 'min', label: 'الأقل', icon: '📉' },
        { value: 'max', label: 'الأعلى', icon: '📈' },
      );
    } else {
      // Text/date fields only support count
      metrics.push({ value: 'count', label: 'العدد', icon: '#' });
    }
    
    return metrics;
  };

  const availableMetrics = getAvailableMetrics(selectedRegister?.fields?.find((f: any) => f.id === selectedFieldId)?.field_type);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    // Build intelligent config based on selections
    const intelligentConfig = {
      title: config.title || widgetDef?.name_ar,
      data_source: config.data_source_type === 'register' ? `register_${selectedRegisterId}` : config.data_source_type,
      register_id: selectedRegisterId,
      field_id: selectedFieldId,
      metric_type: selectedMetric,
      calculation: config.calculation,
      period: config.period,
      color: config.color,
      comparison_enabled: config.comparison_enabled,
      comparison_period: config.comparison_period,
      // Advanced metadata
      field_metadata: selectedRegister?.fields?.find((f: any) => f.id === selectedFieldId),
      register_metadata: selectedRegister,
      is_financial: selectedRegister?.is_financial || false,
      requires_approval: selectedRegister?.requires_approval || false,
    };
    
    onSubmit(sectionId, intelligentConfig);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={onClose}>
      <div className="bg-white rounded-lg p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-6 pb-4 border-b">
          <div>
            <h3 className="text-xl font-bold text-gray-800">⚙️ إعدادات {widgetDef?.name_ar}</h3>
            <p className="text-sm text-gray-500 mt-1">اختر مصدر البيانات والحقول بذكاء</p>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-2xl">×</button>
        </div>
        
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Info */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">📝 العنوان المخصص</label>
              <input
                type="text"
                value={config.title}
                onChange={(e) => setConfig({ ...config, title: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder={widgetDef?.name_ar || 'عنوان الودجت'}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">🎨 اللون</label>
              <select
                value={config.color}
                onChange={(e) => setConfig({ ...config, color: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
              >
                <option value="blue">🔵 أزرق</option>
                <option value="green">🟢 أخضر</option>
                <option value="amber">🟡 عنبري</option>
                <option value="red">🔴 أحمر</option>
                <option value="purple">🟣 أرجواني</option>
                <option value="emerald">💚 زمردي</option>
                <option value="indigo">🔵 نيلي</option>
                <option value="rose">🌹 وردي</option>
              </select>
            </div>
          </div>

          {/* Data Source Type */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">📊 نوع مصدر البيانات</label>
            <div className="grid grid-cols-3 gap-3">
              <button
                type="button"
                onClick={() => setConfig({ ...config, data_source_type: 'register' })}
                className={`p-4 rounded-lg border-2 transition-all ${
                  config.data_source_type === 'register'
                    ? 'border-blue-500 bg-blue-50 text-blue-700'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <div className="text-2xl mb-1">📁</div>
                <div className="font-medium text-sm">سجل مالي</div>
              </button>
              <button
                type="button"
                onClick={() => setConfig({ ...config, data_source_type: 'system' })}
                className={`p-4 rounded-lg border-2 transition-all ${
                  config.data_source_type === 'system'
                    ? 'border-blue-500 bg-blue-50 text-blue-700'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <div className="text-2xl mb-1">⚙️</div>
                <div className="font-medium text-sm">بيانات النظام</div>
              </button>
              <button
                type="button"
                onClick={() => setConfig({ ...config, data_source_type: 'custom' })}
                className={`p-4 rounded-lg border-2 transition-all ${
                  config.data_source_type === 'custom'
                    ? 'border-blue-500 bg-blue-50 text-blue-700'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <div className="text-2xl mb-1">🔧</div>
                <div className="font-medium text-sm">مخصص</div>
              </button>
            </div>
          </div>

          {/* Register Selection */}
          {config.data_source_type === 'register' && (
            <div className="space-y-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
              <div className="flex items-center gap-2 mb-3">
                <span className="text-lg font-bold text-gray-700">🏦 اختيار السجل المالي</span>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">1️⃣ اختر السجل</label>
                <select
                  value={selectedRegisterId}
                  onChange={(e) => {
                    setSelectedRegisterId(e.target.value);
                    setSelectedFieldId('');
                    setSelectedMetric('');
                    setConfig({ ...config, register_id: e.target.value });
                  }}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">-- اختر السجل --</option>
                  {registers.map(r => (
                    <option key={r.id} value={r.id}>
                      📁 {r.name_ar || r.name} {r.code ? `(${r.code})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              {selectedRegisterId && (
                <>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      2️⃣ اختر الحقل <span className="text-xs text-gray-500">(من {selectedRegister?.fields?.length || 0} حقول متاحة)</span>
                    </label>
                    <select
                      value={selectedFieldId}
                      onChange={(e) => {
                        setSelectedFieldId(e.target.value);
                        setSelectedMetric('');
                        setConfig({ ...config, field_id: e.target.value });
                      }}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="">-- اختر الحقل --</option>
                      {registerFields.map((f: any) => (
                        <option key={f.id} value={f.id}>
                          {f.field_type === 'number' || f.field_type === 'decimal' ? '💰' : '📝'} 
                          {f.label || f.name} 
                          {f.field_type ? ` - ${f.field_type}` : ''}
                        </option>
                      ))}
                    </select>
                  </div>

                  {selectedFieldId && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        3️⃣ اختر العملية الحسابية
                      </label>
                      <div className="grid grid-cols-5 gap-2">
                        {availableMetrics.map(metric => (
                          <button
                            key={metric.value}
                            type="button"
                            onClick={() => {
                              setSelectedMetric(metric.value);
                              setConfig({ ...config, metric_type: metric.value, calculation: metric.value });
                            }}
                            className={`p-3 rounded-lg border-2 transition-all text-center ${
                              selectedMetric === metric.value
                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                : 'border-gray-200 hover:border-gray-300'
                            }`}
                          >
                            <div className="text-xl mb-1">{metric.icon}</div>
                            <div className="text-xs font-medium">{metric.label}</div>
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          )}

          {/* Time Period */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">⏰ الفترة الزمنية</label>
            <div className="grid grid-cols-6 gap-2">
              {[
                { value: 'today', label: 'اليوم', icon: '🌅' },
                { value: 'yesterday', label: 'أمس', icon: '🌇' },
                { value: 'week', label: 'الأسبوع', icon: '📅' },
                { value: 'month', label: 'الشهر', icon: '🗓️' },
                { value: 'year', label: 'السنة', icon: '🎉' },
                { value: 'all', label: 'الكل', icon: '📊' },
              ].map(period => (
                <button
                  key={period.value}
                  type="button"
                  onClick={() => setConfig({ ...config, period: period.value })}
                  className={`p-3 rounded-lg border-2 transition-all text-center ${
                    config.period === period.value
                      ? 'border-blue-500 bg-blue-50 text-blue-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  <div className="text-xl mb-1">{period.icon}</div>
                  <div className="text-xs font-medium">{period.label}</div>
                </button>
              ))}
            </div>
          </div>

          {/* Comparison */}
          <div className="p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div className="flex items-center justify-between mb-3">
              <label className="text-sm font-medium text-gray-700">📈 تفعيل المقارنة</label>
              <input
                type="checkbox"
                checked={config.comparison_enabled}
                onChange={(e) => setConfig({ ...config, comparison_enabled: e.target.checked })}
                className="w-5 h-5 text-blue-600 rounded focus:ring-blue-500"
              />
            </div>
            
            {config.comparison_enabled && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">قارن مع:</label>
                <select
                  value={config.comparison_period}
                  onChange={(e) => setConfig({ ...config, comparison_period: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                >
                  <option value="yesterday">الأمس</option>
                  <option value="last_week">الأسبوع الماضي</option>
                  <option value="last_month">الشهر الماضي</option>
                  <option value="last_year">السنة الماضية</option>
                </select>
              </div>
            )}
          </div>

          {/* Summary */}
          {selectedRegisterId && selectedFieldId && selectedMetric && (
            <div className="p-4 bg-green-50 rounded-lg border border-green-200">
              <div className="flex items-center gap-2 mb-2">
                <span className="text-lg">✅</span>
                <span className="font-bold text-green-800">ملخص التكوين</span>
              </div>
              <div className="text-sm text-green-700 space-y-1">
                <div>📁 السجل: <span className="font-medium">{selectedRegister?.name_ar}</span></div>
                <div>📝 الحقل: <span className="font-medium">{registerFields.find((f: any) => f.id === selectedFieldId)?.label}</span></div>
                <div>🔢 العملية: <span className="font-medium">{availableMetrics.find(m => m.value === selectedMetric)?.label}</span></div>
                <div>⏰ الفترة: <span className="font-medium">{config.period}</span></div>
                {config.comparison_enabled && (
                  <div>📈 المقارنة: <span className="font-medium">مفعلة ({config.comparison_period})</span></div>
                )}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
            >
              إلغاء
            </button>
            <button
              type="submit"
              disabled={!selectedRegisterId || !selectedFieldId || !selectedMetric}
              className="px-6 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors font-medium disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
              ✨ إضافة الودجت
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// Widget Settings Form Component
function WidgetSettingsForm({ widget, registers, onSubmit, onClose }: {
  widget: DashboardWidget;
  registers: any[];
  onSubmit: (updates: Partial<DashboardWidget>) => void;
  onClose: () => void;
}) {
  const [settings, setSettings] = useState({
    title: widget.display_config?.title || widget.name_ar,
    color: widget.display_config?.color || 'blue',
    grid_width: widget.grid_width,
    grid_height: widget.grid_height,
    data_source: widget.data_source || '',
    period: widget.data_config?.period || 'today',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit({
      name_ar: settings.title,
      grid_width: settings.grid_width,
      grid_height: settings.grid_height,
      data_source: settings.data_source || undefined,
      display_config: {
        title: settings.title,
        color: settings.color,
      },
      data_config: {
        period: settings.period,
      },
    });
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={onClose}>
      <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4" onClick={(e) => e.stopPropagation()}>
        <h3 className="text-lg font-bold mb-4">⚙️ إعدادات widget</h3>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
            <input
              type="text"
              value={settings.title}
              onChange={(e) => setSettings({ ...settings, title: e.target.value })}
              className="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">مصدر البيانات</label>
            <select
              value={settings.data_source}
              onChange={(e) => setSettings({ ...settings, data_source: e.target.value })}
              className="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
            >
              <option value="">-- اختر --</option>
              {DATA_SOURCES.map(ds => (
                <option key={ds.value} value={ds.value}>{ds.icon} {ds.label}</option>
              ))}
              {registers.map(r => (
                <option key={r.id} value={`register_${r.id}`}>📁 {r.name_ar || r.name}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">العرض</label>
              <select
                value={settings.grid_width}
                onChange={(e) => setSettings({ ...settings, grid_width: parseInt(e.target.value) })}
                className="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
              >
                <option value={3}>3</option>
                <option value={4}>4</option>
                <option value={6}>6</option>
                <option value={8}>8</option>
                <option value={12}>12</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">الارتفاع</label>
              <select
                value={settings.grid_height}
                onChange={(e) => setSettings({ ...settings, grid_height: parseInt(e.target.value) })}
                className="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
              >
                <option value={2}>2</option>
                <option value={3}>3</option>
                <option value={4}>4</option>
                <option value={6}>6</option>
                <option value={8}>8</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">اللون</label>
            <select
              value={settings.color}
              onChange={(e) => setSettings({ ...settings, color: e.target.value })}
              className="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
            >
              <option value="blue">أزرق</option>
              <option value="green">أخضر</option>
              <option value="amber">عنبري</option>
              <option value="red">أحمر</option>
              <option value="purple">أرجواني</option>
            </select>
          </div>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
            >
              إلغاء
            </button>
            <button
              type="submit"
              className="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700"
            >
              حفظ
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
