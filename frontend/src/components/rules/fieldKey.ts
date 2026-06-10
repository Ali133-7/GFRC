import type { WorkflowField } from "@/types/workflow";

/**
 * The SINGLE source of truth for how a field is identified inside rules, conditions,
 * actions and execution values.
 *
 * The execution engine keys all values by `register_field_id` (or `custom_<id>` for
 * custom workflow fields) — NOT by the WorkflowField primary key. Rule builders that
 * stored `f.id` produced conditions whose field_id never matched the value keys, so the
 * engine read `null` and silently skipped the rule. Every builder must use this helper.
 */
export function fieldKey(f: WorkflowField): string {
  return f.register_field_id ?? `custom_${f.id}`;
}

/** Find a field by its execution key (register_field_id ?? custom_<id>). */
export function findFieldByKey(fields: WorkflowField[], key: string | null | undefined): WorkflowField | undefined {
  if (!key) return undefined;
  return fields.find((f) => fieldKey(f) === key);
}

/** Human label for a field, preferring workflow overrides then the register label. */
export function fieldDisplayLabel(f: WorkflowField): string {
  return (
    (f as any).custom_label ??
    (f as any).label ??
    f.registerField?.label_ar ??
    f.registerField?.name ??
    fieldKey(f)
  );
}

/** Effective field type (workflow override, else inherited from RegisterField). */
export function fieldType(f: WorkflowField): string {
  return f.field_type ?? f.registerField?.field_type ?? "text";
}

const SELECT_TYPES = ["select", "multi_select", "radio", "checkbox"];

/** Whether a field renders as a choice control (so condition/value inputs become dropdowns). */
export function isChoiceField(f: WorkflowField): boolean {
  return SELECT_TYPES.includes(fieldType(f));
}

/**
 * Normalized choice options for a field, from the workflow override or inherited from
 * RegisterField. Handles both {label,value}[] and string[] shapes, and checkbox.
 */
export function getFieldOptions(f: WorkflowField): Array<{ label: string; value: string }> {
  if (fieldType(f) === "checkbox") {
    return [
      { label: "نعم", value: "1" },
      { label: "لا", value: "0" },
    ];
  }
  const raw: any = (f as any).options ?? f.registerField?.options ?? null;
  if (!raw || !Array.isArray(raw)) return [];
  return raw.map((o: any) =>
    typeof o === "string"
      ? { label: o, value: o }
      : { label: String(o.label_ar ?? o.label ?? o.value ?? ""), value: String(o.value ?? "") }
  );
}
