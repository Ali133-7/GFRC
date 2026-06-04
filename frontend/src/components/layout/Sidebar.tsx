import { Link, useLocation } from "react-router-dom";
import { usePermissions } from "@/hooks/usePermissions";
import { useUiStore } from "@/stores/uiStore";

const allNav = [
  { label: "الرئيسية",         path: "/dashboard",              permission: "view-receipt"      },
  { label: "الوصولات",         path: "/receipts",               permission: "view-receipt"      },
  { label: "مصمم الوصولات",    path: "/receipts/designer",      permission: "manage-settings"   },
  { label: "السجلات",          path: "/registers",              permission: "view-registers"    },
  { label: "المستخدمين",       path: "/users",                  permission: "view-users"        },
  { label: "التقارير",         path: "/reports",                permission: "view-reports"      },
  { label: "محرك سير العمل",   path: "/workflows",              permission: "manage-settings"   },
  { label: "قوالب المعاملات",  path: "/transaction-templates",  permission: "manage-settings"   },
  { label: "مكتبة الرسوم",     path: "/official-fees",          permission: "manage-settings"   },
  { label: "سجل التدقيق",      path: "/audit-logs",             permission: "view-audit-logs"   },
  { label: "الإعدادات",        path: "/settings",               permission: "manage-settings"   },
];

export function Sidebar() {
  const { pathname } = useLocation();
  const { can }      = usePermissions();
  const { sidebarOpen, closeSidebar } = useUiStore();

  const nav = allNav.filter((item) => can(item.permission));

  const isActive = (path: string) =>
    path === "/dashboard"
      ? pathname === "/dashboard" || pathname === "/"
      : pathname === path || pathname.startsWith(path + "/");

  return (
    <aside
      className={`
        ${sidebarOpen
          ? "fixed inset-y-0 right-0 z-40 w-64 bg-white shadow-lg block"
          : "fixed inset-y-0 right-0 z-40 w-64 bg-white shadow-lg hidden"
        }
        md:relative md:block md:w-64 md:bg-white md:shadow-none print:hidden
      `}
    >
      <div
        style={{
          display: "flex",
          flexDirection: "column",
          height: "100%",
          borderLeft: "0.5px solid var(--color-border-tertiary)",
          direction: "rtl",
        }}
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
              نظام المقبوضات الحكومي
            </div>
          </div>
        </div>

        {/* Nav */}
        <nav
          style={{
            flex: 1,
            padding: "12px 10px",
            overflowY: "auto",
          }}
        >
          {nav.map((item) => {
            const active = isActive(item.path);
            return (
              <Link
                key={item.path}
                to={item.path}
                onClick={closeSidebar}
                style={{
                  display: "block",
                  padding: "9px 12px",
                  borderRadius: "var(--border-radius-md)",
                  fontSize: "13px",
                  fontWeight: active ? 500 : 400,
                  color: active ? "var(--color-text-info)" : "var(--color-text-secondary)",
                  background: active ? "var(--color-background-info)" : "transparent",
                  textDecoration: "none",
                  marginBottom: "2px",
                  transition: "background .15s, color .15s",
                  fontFamily: "'Noto Sans Arabic', sans-serif",
                  borderRight: active
                    ? "2px solid var(--color-border-info)"
                    : "2px solid transparent",
                }}
                onMouseEnter={(e) => {
                  if (!active)
                    (e.currentTarget as HTMLElement).style.background =
                      "var(--color-background-secondary)";
                }}
                onMouseLeave={(e) => {
                  if (!active)
                    (e.currentTarget as HTMLElement).style.background = "transparent";
                }}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>
      </div>
    </aside>
  );
}

export default Sidebar;
