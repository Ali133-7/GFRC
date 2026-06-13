export interface ReportSection {
  id: string;
  type: "report_header" | "page_header" | "group_header" | "details" | "group_footer" | "page_footer" | "report_footer";
  name: string;
  height: number;
  objects: ReportObject[];
  groupBy?: string;
}

export interface ReportObject {
  id: string;
  type: "field" | "text" | "image" | "chart" | "table" | "barcode" | "qr" | "formula" | "separator";
  field?: ReportField;
  content?: string;
  x: number;
  y: number;
  width: number;
  height: number;
  properties: {
    fontSize?: number;
    fontFamily?: string;
    color?: string;
    backgroundColor?: string;
    border?: string;
    align?: "left" | "center" | "right";
    valign?: "top" | "middle" | "bottom";
    visible?: boolean;
    conditionalFormatting?: ConditionalFormat[];
  };
}

export interface ReportField {
  id?: string;
  name: string;
  label?: string;
  label_ar?: string;
  type: "string" | "number" | "currency" | "date" | "datetime" | "boolean";
  table?: string;
  tableAlias?: string;
  registerId?: string;
  registerName?: string;
  category?: string;
  description?: string;
  sourceType?: "register_field" | "system" | "calculated";
  isSearchable?: boolean;
  isFilterable?: boolean;
  isAggregatable?: boolean;
  nullable?: boolean;
}

export interface BusinessRegister {
  id: string;
  type: "register";
  code: string;
  name: string;
  name_en?: string;
  description?: string;
  is_active: boolean;
  record_count: number;
  table_alias: string;
}

export interface BusinessField {
  id: string;
  register_id: string;
  name: string;
  label: string;
  label_en?: string;
  description?: string;
  data_type: ReportField["type"];
  source_type: "register_field";
  category: string;
  register_name?: string;
  is_searchable: boolean;
  is_filterable: boolean;
  is_aggregatable: boolean;
  is_required: boolean;
  is_visible: boolean;
  is_financial: boolean;
  sort_order: number;
}

export interface RegisterRelationship {
  id: string;
  left_register_id: string;
  left_register_name: string;
  right_register_id: string;
  right_register_name: string;
  relationship_key: string;
  join_type: "INNER" | "LEFT" | "RIGHT" | "FULL";
  confidence: "high" | "medium" | "low";
  auto_generated: boolean;
  left_table_alias: string;
  right_table_alias: string;
}

export interface FieldFavorite {
  fieldId: string;
  registeredAt: string;
}

export interface RecentlyUsedField {
  fieldId: string;
  usedAt: string;
}

export interface ConditionalFormat {
  id: string;
  condition: string;
  properties: {
    color?: string;
    backgroundColor?: string;
    fontWeight?: "normal" | "bold";
    fontStyle?: "normal" | "italic";
    visible?: boolean;
  };
}

export interface DataSource {
  id: string;
  name: string;
  type: "table" | "view" | "query";
  fields: ReportField[];
  joins?: TableJoin[];
}

export interface TableJoin {
  id: string;
  table: string;
  leftField: string;
  operator: "=" | ">" | "<" | ">=" | "<=" | "<>";
  rightField: string;
  type: "INNER" | "LEFT" | "RIGHT" | "FULL";
}

export interface ReportFilter {
  id: string;
  field: string;
  operator: "=" | "!=" | ">" | "<" | ">=" | "<=" | "LIKE" | "IN" | "BETWEEN";
  value: any;
  valueType: "string" | "number" | "date" | "boolean" | "array";
  logic?: "AND" | "OR";
  group?: string;
}

export interface CalculatedField {
  id: string;
  name: string;
  label: string;
  formula: string;
  type: "string" | "number" | "currency" | "date" | "boolean";
}

export interface ChartConfig {
  id: string;
  type: "bar" | "column" | "line" | "area" | "pie" | "donut" | "scatter" | "radar" | "treemap" | "funnel";
  title: string;
  xAxis?: string;
  yAxis?: string;
  series: ChartSeries[];
  colors?: string[];
  theme?: string;
}

export interface ChartSeries {
  name: string;
  field: string;
  aggregation?: "SUM" | "COUNT" | "AVG" | "MIN" | "MAX";
}

export interface PivotConfig {
  rows: string[];
  columns: string[];
  values: PivotValue[];
  filters: ReportFilter[];
}

export interface PivotValue {
  field: string;
  aggregation: "SUM" | "COUNT" | "AVG" | "MIN" | "MAX";
  label: string;
}

export interface ReportTheme {
  id: string;
  name: string;
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    text: string;
  };
  fonts: {
    heading: string;
    body: string;
  };
}

export interface ReportSchedule {
  enabled: boolean;
  frequency: "daily" | "weekly" | "monthly" | "custom";
  cron?: string;
  recipients: string[];
  format: "pdf" | "excel" | "csv";
  delivery: "email" | "notification" | "dashboard";
}
