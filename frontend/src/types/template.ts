export interface TemplateStyle {
  id: string;
  element_id: string;
  font_family: string;
  font_size: number;
  font_weight: string;
  font_color: string;
  background_color?: string;
  border_color?: string;
  border_width: number;
  text_align: 'left' | 'center' | 'right';
  padding: {
    top: number;
    right: number;
    bottom: number;
    left: number;
  };
  opacity: number;
  display: 'block' | 'inline' | 'none';
  line_height: number;
  letter_spacing?: string;
}

export interface TemplateElement {
  id: string;
  template_id: string;
  field_id?: string;
  element_type: 'field' | 'text' | 'divider' | 'qr' | 'signature' | 'total' | 'image' | 'spacer';
  label?: string;
  sort_order: number;
  x: number;
  y: number;
  width: number;
  height: number;
  is_visible: boolean;
  metadata?: Record<string, any>;
  style?: TemplateStyle;
}

export interface ReceiptTemplate {
  id: string;
  register_id: string;
  name: string;
  description?: string;
  is_active: boolean;
  is_default: boolean;
  layout_type: 'portrait' | 'landscape' | 'custom';
  page_width: number; // mm
  page_height: number; // mm
  background_color: string;
  metadata?: Record<string, any>;
  elements: TemplateElement[];
  created_by: string;
  updated_by?: string;
  created_at: string;
  updated_at: string;
}

export interface TemplatePreset {
  id: string;
  name: string;
  description: string;
  layout_type: 'portrait' | 'landscape';
  page_width: number;
  page_height: number;
  elements: Omit<TemplateElement, 'id' | 'template_id'>[];
}

export interface TemplateExport {
  template: ReceiptTemplate;
  exportedAt: string;
  version: string;
}
