import { Link, useLocation } from "react-router-dom";
import { usePermissions } from "@/hooks/usePermissions";

const allNav = [
  { label: "الرئيسية",         path: "/dashboard",              permission: "view-receipt"      },
  { label: "الإيصالات",         path: "/receipts",               permission: "view-receipt"      },
  { label: "مصمم الإيصالات",    path: "/receipts/designer",      permission: "manage-settings"   },
  { label: "السجلات",          path: "/registers",              permission: "view-registers"    },
  { label: "المستخدمين",        path: "/users",                  permission: "view-users"        },
  { label: "إدارة الداشبورد",   path: "/admin/dashboards",       permission: "manage-dashboards" },
  { label: "التقارير",         path: "/reports",                permission: "view-reports"      },
  { label: "أتمتة سير العمل",   path: "/workflows",              permission: "manage-settings"   },
  { label: "قوالب المعاملات",  path: "/transaction-templates",  permission: "manage-settings"   },
  { label: "رسوم رسمية",     path: "/official-fees",          permission: "manage-settings"   },
  { label: " سجل التدقيق",      path: "/audit-logs",             permission: "view-audit-logs"   },
  { label: "الإعدادات",        path: "/settings",               permission: "manage-settings"   },
];

export function Sidebar() {
  const { pathname } = useLocation();
  const { can }      = usePermissions();

  const nav = allNav.filter((item) => can(item.permission));

  const isActive = (path: string) =>
    path === "/dashboard"
      ? pathname === "/dashboard" || pathname === "/"
      : pathname === path || pathname.startsWith(path + "/");

  return (
    <aside
      className="
        w-64 bg-white border-l border-gray-200 
        flex flex-col 
        hidden md:flex
        print:hidden
      "
    >
      {/* Logo */}
      <div
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          padding: "18px 16px",
          borderBottom: "0.5px solid var(--color-border-tertiary)",
        }}
      >
        <div>
          <div
            style={{
              fontSize: "18px",
              fontWeight: 500,
              color: "var(--color-text-info)",
              textAlign: "center",
              fontFamily: "'Noto Sans Arabic', sans-serif",
            }}
          >
            GFRC
          </div>
          <div
            style={{
              fontSize: "10px",
              color: "var(--color-text-tertiary)",
              textAlign: "center",
              marginTop: "2px",
              fontFamily: "'Noto Sans Arabic', sans-serif",
            }}
          >
            نظام الإيصالات المالية
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav style={{ flex: 1, overflowY: "auto", padding: "12px 0" }}>
        {nav.map((item) => {
          const active = isActive(item.path);
          return (
            <Link
              key={item.path}
              to={item.path}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "10px",
                padding: "10px 16px",
                marginBottom: "4px",
                marginInline: "8px",
                borderRadius: "6px",
                fontSize: "13px",
                fontWeight: active ? 500 : 400,
                color: active
                  ? "var(--color-text-info)"
                  : "var(--color-text-secondary)",
                background: active
                  ? "var(--color-background-info-subtle)"
                  : "transparent",
                border: active
                  ? "0.5px solid var(--color-border-info)"
                  : "0.5px solid transparent",
                textDecoration: "none",
                transition: "all .15s",
              }}
              onMouseEnter={(e) => {
                if (!active) {
                  e.currentTarget.style.background =
                    "var(--color-background-secondary)";
                }
              }}
              onMouseLeave={(e) => {
                if (!active) {
                  e.currentTarget.style.background = "transparent";
                }
              }}
            >
              {item.label}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
