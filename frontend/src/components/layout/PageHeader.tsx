import { useState } from "react";
import type { ReactNode } from "react";
import HelpDrawer from "@/components/help/HelpDrawer";

interface BackButton {
  label: string;
  onClick: () => void;
}

interface ActionButton {
  label: string;
  onClick: () => void;
  variant?: "primary" | "secondary" | "danger";
}

interface Props {
  title: string;
  subtitle?: string;
  children?: ReactNode;
  back?: BackButton;
  action?: ActionButton;
  helpPageKey?: string;
  helpContext?: Record<string, any>;
}

const actionColors = {
  primary: {
    bg: "var(--color-background-info)",
    color: "var(--color-text-info)",
    border: "var(--color-border-info)",
  },
  secondary: {
    bg: "var(--color-background-secondary)",
    color: "var(--color-text-primary)",
    border: "var(--color-border-secondary)",
  },
  danger: {
    bg: "var(--color-background-danger)",
    color: "var(--color-text-danger)",
    border: "var(--color-border-danger)",
  },
};

export function PageHeader({ title, subtitle, children, back, action, helpPageKey, helpContext }: Props) {
  const [helpOpen, setHelpOpen] = useState(false);

  return (
    <div
      style={{
        marginBottom: "20px",
        direction: "rtl",
        fontFamily: "'Noto Sans Arabic', sans-serif",
      }}
    >
      {back && (
        <button
          onClick={back.onClick}
          style={{
            background: "none",
            border: "none",
            cursor: "pointer",
            fontSize: "12px",
            color: "var(--color-text-info)",
            padding: "0",
            marginBottom: "8px",
            fontFamily: "inherit",
            display: "flex",
            alignItems: "center",
            gap: "4px",
          }}
        >
          {back.label}
        </button>
      )}

      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "flex-start",
          gap: "12px",
        }}
      >
        <div>
          <h1
            style={{
              fontSize: "20px",
              fontWeight: 500,
              color: "var(--color-text-primary)",
              margin: 0,
              lineHeight: 1.3,
            }}
          >
            {title}
          </h1>
          {subtitle && (
            <p
              style={{
                fontSize: "13px",
                color: "var(--color-text-secondary)",
                marginTop: "3px",
              }}
            >
              {subtitle}
            </p>
          )}
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexShrink: 0 }}>
          {helpPageKey && (
            <button
              onClick={() => setHelpOpen(true)}
              style={{
                width: "32px",
                height: "32px",
                borderRadius: "50%",
                border: "0.5px solid var(--color-border-secondary)",
                background: "var(--color-background-secondary)",
                color: "var(--color-text-secondary)",
                cursor: "pointer",
                fontSize: "14px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                fontFamily: "inherit",
              }}
              title="المساعدة"
            >
              ?
            </button>
          )}
          {action && (
            <button
              onClick={action.onClick}
              style={{
                padding: "8px 16px",
                fontSize: "13px",
                fontWeight: 500,
                border: `0.5px solid ${actionColors[action.variant ?? "primary"].border}`,
                background: actionColors[action.variant ?? "primary"].bg,
                color: actionColors[action.variant ?? "primary"].color,
                borderRadius: "var(--border-radius-md)",
                cursor: "pointer",
                fontFamily: "inherit",
                whiteSpace: "nowrap",
              }}
            >
              {action.label}
            </button>
          )}
          {children}
        </div>
      </div>

      {helpPageKey && (
        <HelpDrawer
          pageKey={helpPageKey}
          isOpen={helpOpen}
          onClose={() => setHelpOpen(false)}
          contextData={helpContext}
        />
      )}
    </div>
  );
}

export default PageHeader;
