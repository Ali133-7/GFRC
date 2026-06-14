export type WidgetType =
  | 'stat_card'
  | 'chart_bar'
  | 'chart_line'
  | 'chart_pie'
  | 'table'
  | 'clock'
  | 'weather'
  | 'youtube_audio'
  | 'progress'
  | 'gauge'
  | 'text_block'
  | 'iframe';

export type ColorTheme = 'blue' | 'green' | 'amber' | 'red' | 'purple' | 'gray' | 'teal';

export interface WidgetTitle {
  ar?: string;
  en?: string;
}

export interface DashboardWidgetItem {
  id: string;
  widget_type: WidgetType;
  title: WidgetTitle | string;
  icon?: string;
  color_theme?: ColorTheme;
  position_x: number;
  position_y: number;
  width: number;
  height: number;
  data_source: Record<string, any>;
  display_config: Record<string, any>;
  is_visible: boolean;
  sort_order: number;
}

export interface DashboardLayout {
  id: string;
  name: string;
  grid_columns: number;
  widgets: DashboardWidgetItem[];
}

export function getWidgetTitle(widget: { title: WidgetTitle | string }): string {
  if (widget.title && typeof widget.title === 'object') {
    return widget.title.ar ?? widget.title.en ?? '—';
  }
  return widget.title ?? '—';
}

export interface AvailableRegister {
  id: string;
  name: string;
  name_ar?: string;
  name_en?: string;
}

export interface RegisterField {
  id: string;
  name: string;
  label: string;
  label_ar?: string;
  label_en?: string;
  data_type: string;
}
