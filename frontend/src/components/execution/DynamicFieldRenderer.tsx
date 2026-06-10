import { useFieldStateContext } from "./FieldStateProvider";
import { Input } from "@/components/ui/Input";
import { GovSelect, GovSelectMulti } from "@/components/ui/GovSelect";

interface FieldSchema {
  field_id: string;
  label: string;
  field_type: string;
  options?: Array<{ label: string; value: string }>;
  placeholder?: string;
  default_value?: any;
  validation_rules?: string[];
}

interface DynamicFieldRendererProps {
  field: FieldSchema;
  value: any;
  onChange: (value: any) => void;
}

export function DynamicFieldRenderer({ field, value, onChange }: DynamicFieldRendererProps) {
  const { getState } = useFieldStateContext();
  const state = getState(field.field_id);

  if (!state.visible) {
    return null;
  }

  const commonProps = {
    id: field.field_id,
    name: field.field_id,
    placeholder: field.placeholder,
    disabled: !state.enabled || state.readonly || state.locked,
    required: state.required,
    className: state.readonly || state.locked ? "bg-gray-100 dark:bg-gray-800" : undefined,
  };

  switch (field.field_type) {
    case "select":
    case "dropdown":
      return (
        <GovSelect
          label={field.label}
          id={field.field_id}
          name={field.field_id}
          placeholder={field.placeholder ?? "اختر..."}
          options={field.options ?? []}
          value={value ?? ""}
          onChange={(val) => onChange(val)}
          disabled={!state.enabled || state.readonly || state.locked}
          required={state.required}
          className={state.readonly || state.locked ? "bg-gray-100 dark:bg-gray-800" : undefined}
        />
      );

    case "multiselect":
      return (
        <GovSelectMulti
          label={field.label}
          id={field.field_id}
          name={field.field_id}
          placeholder={field.placeholder ?? "اختر..."}
          options={field.options ?? []}
          value={Array.isArray(value) ? value : value ? [value] : []}
          onChange={(vals) => onChange(vals)}
          disabled={!state.enabled || state.readonly || state.locked}
          required={state.required}
          className={state.readonly || state.locked ? "bg-gray-100 dark:bg-gray-800" : undefined}
        />
      );

    case "number":
      return (
        <div className="space-y-1">
          <label htmlFor={field.field_id} className="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {field.label}
            {state.required && <span className="text-red-500"> *</span>}
          </label>
          <Input
            {...commonProps}
            type="number"
            value={value ?? ""}
            onChange={(e) => onChange(e.target.value)}
          />
        </div>
      );

    case "textarea":
      return (
        <div className="space-y-1">
          <label htmlFor={field.field_id} className="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {field.label}
            {state.required && <span className="text-red-500"> *</span>}
          </label>
          <textarea
            {...commonProps}
            rows={4}
            value={value ?? ""}
            onChange={(e) => onChange(e.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
      );

    case "date":
      return (
        <div className="space-y-1">
          <label htmlFor={field.field_id} className="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {field.label}
            {state.required && <span className="text-red-500"> *</span>}
          </label>
          <Input
            {...commonProps}
            type="date"
            value={value ?? ""}
            onChange={(e) => onChange(e.target.value)}
          />
        </div>
      );

    case "checkbox":
      return (
        <div className="flex items-center gap-2">
          <input
            id={field.field_id}
            type="checkbox"
            checked={!!value}
            disabled={commonProps.disabled}
            onChange={(e) => onChange(e.target.checked)}
            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
          />
          <label htmlFor={field.field_id} className="text-sm font-medium text-gray-700 dark:text-gray-300">
            {field.label}
            {state.required && <span className="text-red-500"> *</span>}
          </label>
        </div>
      );

    case "text":
    default:
      return (
        <div className="space-y-1">
          <label htmlFor={field.field_id} className="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {field.label}
            {state.required && <span className="text-red-500"> *</span>}
          </label>
          <Input
            {...commonProps}
            type="text"
            value={value ?? ""}
            onChange={(e) => onChange(e.target.value)}
          />
        </div>
      );
  }
}
