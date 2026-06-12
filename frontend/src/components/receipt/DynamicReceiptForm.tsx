import React, { useEffect, useMemo } from "react";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { RegisterField } from "@/types/register";
import { formatCurrency } from "@/utils/formatCurrency";
import { formatNumber } from "@/utils/formatNumber";
import { GovSelect } from "@/components/ui/GovSelect";

interface DynamicReceiptFormProps {
  fields: RegisterField[];
  onSubmit: (values: Record<string, string | number | null>, total: number) => void;
  onTotalChange?: (total: number) => void;
  disabled?: boolean;
  defaultValues?: Record<string, string | number | null>;
}

function buildSchema(fields: RegisterField[]) {
  const shape: Record<string, z.ZodTypeAny> = {};
  fields.forEach((f) => {
    if (!f.is_visible) return;
    if (f.field_type === "decimal" || f.field_type === "number") {
      let rule = z.coerce.number({ invalid_type_error: "يجب أن يكون رقماً" }).min(0, "لا يمكن أن يكون سالباً");
      if (!f.is_required) {
        shape[f.name] = z.union([rule, z.literal("")]).optional();
      } else {
        shape[f.name] = rule;
      }
    } else if (f.field_type === "date") {
      shape[f.name] = f.is_required ? z.string().min(1, "هذا الحقل مطلوب") : z.string().optional();
    } else {
      shape[f.name] = f.is_required ? z.string().min(1, "هذا الحقل مطلوب") : z.string().optional();
    }
  });
  return z.object(shape);
}

export default function DynamicReceiptForm({
  fields,
  onSubmit,
  onTotalChange,
  disabled = false,
  defaultValues = {},
}: DynamicReceiptFormProps) {
  const visibleFields = useMemo(() => fields.filter((f) => f.is_visible && f.field_type !== "hidden"), [fields]);

  const schema = useMemo(() => buildSchema(fields), [fields]);

  const { control, handleSubmit, reset, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues,
  });

  useEffect(() => {
    reset(defaultValues);
  }, [fields, reset]);

  const watched = useWatch({ control });

  const financialTotal = useMemo(() => {
    return fields
      .filter((f) => f.is_financial)
      .reduce((sum, f) => {
        const val = watched[f.name];
        const num = parseFloat(String(val ?? "0"));
        return sum + (isNaN(num) ? 0 : num);
      }, 0);
  }, [watched, fields]);

  useEffect(() => {
    onTotalChange?.(financialTotal);
  }, [financialTotal, onTotalChange]);

  const onFormSubmit = (values: Record<string, unknown>) => {
    onSubmit(values as Record<string, string | number | null>, financialTotal);
  };

  const financialFields = fields.filter((f) => f.is_financial);

  return (
    <form onSubmit={handleSubmit(onFormSubmit)} noValidate dir="rtl">
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "16px", marginBottom: "24px" }}>
        {visibleFields.map((field) => (
          <div
            key={field.id}
            style={{ gridColumn: field.field_type === "textarea" || field.field_type === "calculated" ? "1 / -1" : undefined }}
          >
            <label style={{ display: "block", fontSize: "13px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "6px" }}>
              {field.label_ar}
              {field.is_required && <span style={{ color: "var(--color-text-danger)", marginRight: "4px" }}>*</span>}
              {field.is_financial && <span style={{ fontSize: "10px", color: "var(--color-text-success)", marginRight: "4px", background: "var(--color-background-success)", padding: "1px 5px", borderRadius: "3px" }}>مالي</span>}
            </label>

            <Controller
              name={field.name}
              control={control}
              defaultValue={field.default_value ?? ""}
              render={({ field: f }) => {
                switch (field.field_type) {
                  case "decimal":
                  case "number":
                    return (
                      <input
                        {...f}
                        type="number"
                        step={field.field_type === "decimal" ? "0.001" : "1"}
                        min="0"
                        disabled={disabled}
                        style={inputStyle(!!errors[field.name])}
                        placeholder="0.000"
                      />
                    );
                  case "date":
                    return (
                      <input
                        {...f}
                        type="date"
                        disabled={disabled}
                        style={inputStyle(!!errors[field.name])}
                      />
                    );
                  case "select":
                    return (
                      <GovSelect
                        name={f.name}
                        value={f.value}
                        onChange={f.onChange}
                        options={(field.options ?? []).map((opt) => ({
                          value: typeof opt === 'string' ? opt : opt.value,
                          label: typeof opt === 'string' ? opt : opt.label_ar,
                        }))}
                        placeholder="— اختر —"
                        disabled={disabled}
                      />
                    );
                  case "textarea":
                    return (
                      <textarea
                        {...f}
                        disabled={disabled}
                        rows={3}
                        style={{ ...inputStyle(!!errors[field.name]), resize: "vertical" }}
                      />
                    );
                  case "calculated":
                    return (
                      <input
                        value={formatNumber(financialTotal)}
                        readOnly
                        style={{ ...inputStyle(false), background: "var(--color-background-secondary)", color: "var(--color-text-tertiary)" }}
                      />
                    );
                  default:
                    return (
                      <input
                        {...f}
                        type="text"
                        disabled={disabled}
                        style={inputStyle(!!errors[field.name])}
                      />
                    );
                }
              }}
            />

            {errors[field.name] && (
              <p style={{ fontSize: "11px", color: "var(--color-text-danger)", marginTop: "4px" }}>
                {String(errors[field.name]?.message ?? "خطأ في الحقل")}
              </p>
            )}
          </div>
        ))}
      </div>

      {financialFields.length > 0 && (
        <div style={{ border: "0.5px solid var(--color-border-secondary)", borderRadius: "8px", padding: "16px", marginBottom: "24px", background: "var(--color-background-secondary)", direction: "rtl" }}>
          <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "12px" }}>ملخص المبالغ المالية</div>
          {financialFields.map((f) => {
            const val = parseFloat(String(watched[f.name] ?? "0"));
            return (
              <div key={f.id} style={{ display: "flex", justifyContent: "space-between", fontSize: "13px", padding: "4px 0", borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                <span style={{ color: "var(--color-text-secondary)" }}>{f.label_ar}</span>
                <span style={{ fontFamily: "monospace", fontWeight: 500 }}>{formatCurrency(isNaN(val) ? 0 : val)}</span>
              </div>
            );
          })}
          <div style={{ display: "flex", justifyContent: "space-between", fontSize: "15px", fontWeight: 700, marginTop: "10px", paddingTop: "8px", borderTop: "1.5px solid var(--color-border-primary)", color: "var(--color-text-primary)" }}>
            <span>المجموع الكلي</span>
            <span style={{ fontFamily: "monospace" }}>{formatCurrency(financialTotal)}</span>
          </div>
        </div>
      )}

      {!disabled && (
        <button
          type="submit"
          style={{ background: "var(--color-background-info)", color: "var(--color-text-info)", border: "0.5px solid var(--color-border-info)", borderRadius: "8px", padding: "10px 24px", fontSize: "14px", fontWeight: 500, cursor: "pointer", fontFamily: "inherit" }}
        >
          حفظ
        </button>
      )}
    </form>
  );
}

function inputStyle(hasError: boolean): React.CSSProperties {
  return {
    width: "100%",
    padding: "8px 12px",
    fontSize: "13px",
    border: `0.5px solid ${hasError ? "var(--color-border-danger)" : "var(--color-border-secondary)"}`,
    borderRadius: "6px",
    outline: "none",
    fontFamily: "'Noto Sans Arabic', sans-serif",
    direction: "rtl",
    boxSizing: "border-box",
  };
}
