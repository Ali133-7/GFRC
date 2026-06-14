import {
  BarChart3,
  LineChart,
  PieChart,
  Table,
  Clock,
  CloudSun,
  Headphones,
  Activity,
  Gauge,
  Type,
  Globe,
  LayoutDashboard,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { DashboardWidgetItem, WidgetType } from './types';

export interface LibraryItem {
  type: WidgetType;
  title: string;
  icon: LucideIcon;
  defaultW: number;
  defaultH: number;
}

export const WIDGET_LIBRARY: LibraryItem[] = [
  { type: 'stat_card', title: 'بطاقة إحصائية', icon: LayoutDashboard, defaultW: 3, defaultH: 2 },
  { type: 'chart_bar', title: 'رسم أعمدة', icon: BarChart3, defaultW: 6, defaultH: 4 },
  { type: 'chart_line', title: 'رسم خطي', icon: LineChart, defaultW: 6, defaultH: 4 },
  { type: 'chart_pie', title: 'رسم دائري', icon: PieChart, defaultW: 4, defaultH: 4 },
  { type: 'table', title: 'جدول بيانات', icon: Table, defaultW: 12, defaultH: 6 },
  { type: 'clock', title: 'ساعة', icon: Clock, defaultW: 3, defaultH: 2 },
  { type: 'weather', title: 'الطقس', icon: CloudSun, defaultW: 3, defaultH: 3 },
  { type: 'youtube_audio', title: 'مشغل صوت YouTube', icon: Headphones, defaultW: 4, defaultH: 3 },
  { type: 'progress', title: 'شريط التقدم', icon: Activity, defaultW: 4, defaultH: 3 },
  { type: 'gauge', title: 'مقياس', icon: Gauge, defaultW: 4, defaultH: 4 },
  { type: 'text_block', title: 'نص ثابت', icon: Type, defaultW: 4, defaultH: 3 },
  { type: 'iframe', title: 'تضمين موقع', icon: Globe, defaultW: 12, defaultH: 8 },
];

function defaultDataSource(type: WidgetType): Record<string, any> {
  switch (type) {
    case 'stat_card':
      return { aggregation: 'count', field: '', filters: {} };
    case 'chart_bar':
    case 'chart_line':
    case 'chart_pie':
      return { aggregation: 'count', field: '', group_by: 'period', group_field: '', period: 'month', filters: {} };
    case 'table':
      return { fields: [], filters: {}, sort_by: '', sort_order: 'asc', per_page: 10 };
    case 'progress':
    case 'gauge':
      return { field: '', target: 100, filters: {} };
    case 'clock':
      return { timezone: 'Asia/Baghdad', format: '24h', show_date: true, calendar: 'gregorian' };
    case 'weather':
      return { provider: 'open_meteo', location: { lat: 33.3152, lon: 44.3661, name: 'بغداد' } };
    case 'youtube_audio':
      return { provider: 'youtube', video_id: '', loop: false, autoplay: false };
    case 'text_block':
      return { content: '' };
    case 'iframe':
      return { url: '' };
    default:
      return {};
  }
}

export function getDefaultWidget(type: WidgetType, overrides: Partial<DashboardWidgetItem> = {}): DashboardWidgetItem {
  const lib = WIDGET_LIBRARY.find((item) => item.type === type) || WIDGET_LIBRARY[0];
  return {
    id: `widget_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
    widget_type: lib.type,
    title: { ar: lib.title, en: lib.title },
    color_theme: 'blue',
    position_x: 0,
    position_y: 0,
    width: lib.defaultW,
    height: lib.defaultH,
    data_source: defaultDataSource(lib.type),
    display_config: { color: 'blue' },
    is_visible: true,
    sort_order: 0,
    ...overrides,
  };
}

export const COLOR_OPTIONS = [
  { value: 'blue', label: 'أزرق', className: 'bg-blue-600' },
  { value: 'green', label: 'أخضر', className: 'bg-green-600' },
  { value: 'amber', label: 'عنبري', className: 'bg-amber-600' },
  { value: 'red', label: 'أحمر', className: 'bg-red-600' },
  { value: 'purple', label: 'أرجواني', className: 'bg-purple-600' },
  { value: 'gray', label: 'رمادي', className: 'bg-gray-600' },
  { value: 'teal', label: 'فيروزي', className: 'bg-teal-600' },
];

export function getColorClass(color?: string): string {
  switch (color) {
    case 'green':
      return 'bg-green-600';
    case 'amber':
      return 'bg-amber-600';
    case 'red':
      return 'bg-red-600';
    case 'purple':
      return 'bg-purple-600';
    case 'gray':
      return 'bg-gray-600';
    case 'teal':
      return 'bg-teal-600';
    case 'blue':
    default:
      return 'bg-blue-600';
  }
}

export function getTextColorClass(color?: string): string {
  switch (color) {
    case 'green':
      return 'text-green-600';
    case 'amber':
      return 'text-amber-600';
    case 'red':
      return 'text-red-600';
    case 'purple':
      return 'text-purple-600';
    case 'gray':
      return 'text-gray-600';
    case 'teal':
      return 'text-teal-600';
    case 'blue':
    default:
      return 'text-blue-600';
  }
}
