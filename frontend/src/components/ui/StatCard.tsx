interface Props {
  title: string;
  value: string | number;
  color?: "success" | "info" | "warning" | "danger" | "default" | string;
  subtitle?: string;
}

const colorMap: Record<string, { bg: string; text: string; border: string }> = {
  success: {
    bg: "var(--color-background-success)",
    text: "var(--color-text-success)",
    border: "var(--color-border-success)",
  },
  info: {
    bg: "var(--color-background-info)",
    text: "var(--color-text-info)",
    border: "var(--color-border-info)",
  },
  warning: {
    bg: "var(--color-background-warning)",
    text: "var(--color-text-warning)",
    border: "var(--color-border-warning)",
  },
  danger: {
    bg: "var(--color-background-danger)",
    text: "var(--color-text-danger)",
    border: "var(--color-border-danger)",
  },
  default: {
    bg: "var(--color-background-secondary)",
    text: "var(--color-text-primary)",
    border: "var(--color-border-tertiary)",
  },
  // backward compat for old Tailwind class names
  "bg-emerald-50": {
    bg: "var(--color-background-success)",
    text: "var(--color-text-success)",
    border: "var(--color-border-success)",
  },
  "bg-sky-50": {
    bg: "var(--color-background-info)",
    text: "var(--color-text-info)",
    border: "var(--color-border-info)",
  },
  "bg-amber-50": {
    bg: "var(--color-background-warning)",
    text: "var(--color-text-warning)",
    border: "var(--color-border-warning)",
  },
  "bg-red-50": {
    bg: "var(--color-background-danger)",
    text: "var(--color-text-danger)",
    border: "var(--color-border-danger)",
  },
  "bg-white": {
    bg: "var(--color-background-primary)",
    text: "var(--color-text-primary)",
    border: "var(--color-border-tertiary)",
  },
};

export function StatCard({ title, value, color = "default", subtitle }: Props) {
  const scheme = colorMap[color] ?? colorMap["default"];

  return (
    <div
      style={{
        background: scheme.bg,
        border: `0.5px solid ${scheme.border}`,
        borderRadius: "var(--border-radius-md)",
        padding: "14px 16px",
        direction: "rtl",
        fontFamily: "'Noto Sans Arabic', sans-serif",
      }}
    >
      <p
        style={{
          fontSize: "12px",
          color: "var(--color-text-secondary)",
          marginBottom: "6px",
          fontWeight: 400,
        }}
      >
        {title}
      </p>
      <p
        style={{
          fontSize: "22px",
          fontWeight: 500,
          color: scheme.text,
          fontFamily: "var(--font-mono)",
          lineHeight: 1.2,
        }}
      >
        {value}
      </p>
      {subtitle && (
        <p
          style={{
            fontSize: "11px",
            color: "var(--color-text-tertiary)",
            marginTop: "4px",
          }}
        >
          {subtitle}
        </p>
      )}
    </div>
  );
}

export default StatCard;
