import React, { useState, useEffect, useRef } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { usePermissions } from "@/hooks/usePermissions";
import { useAuth } from "@/hooks/useAuth";
import { useSystemUploadLogo } from "@/hooks/useSystem";
import { formatDateTime } from "@/utils/formatDate";
import { storeLogo, fileToBase64 } from "@/utils/localStorageLogo";

interface Setting { id: string; key: string; value: string; group: string; type: string; label_ar: string; }
interface Backup  { filename: string; size: string; created_at: string; }

type TabKey = "general" | "print" | "security" | "backup" | "dashboard";

const SETTING_KEYS: Record<TabKey, string[]> = {
  general:  ["DEPT_NAME_AR","DEPT_NAME_EN","DEFAULT_FISCAL_YEAR","CURRENCY_CODE","RECEIPT_NUMBER_FORMAT"],
  print:    ["PRINT_FOOTER_TEXT","HIDE_ZERO_OR_EMPTY"],
  security: ["MAX_LOGIN_ATTEMPTS","LOGIN_LOCKOUT_MINUTES","ENABLE_AUDIT_LOG"],
  backup:   [],
  dashboard: [],
};

const SETTING_LABELS: Record<string, string> = {
  DEPT_NAME_AR: "اسم الدائرة (عربي)", DEPT_NAME_EN: "اسم الدائرة (إنجليزي)",
  DEFAULT_FISCAL_YEAR: "السنة المالية الافتراضية", CURRENCY_CODE: "رمز العملة",
  RECEIPT_NUMBER_FORMAT: "تنسيق رقم الوصل",
  PRINT_FOOTER_TEXT: "نص تذييل الطباعة", HIDE_ZERO_OR_EMPTY: "إخفاء الأرصدة الصفرية والحقول الفارغة",
  MAX_LOGIN_ATTEMPTS: "حد محاولات الدخول",
  LOGIN_LOCKOUT_MINUTES: "مدة الحظر (دقيقة)", ENABLE_AUDIT_LOG: "تفعيل سجل التدقيق",
};

export default function SettingsPage() {
  const [tab, setTab] = useState<TabKey>("general");
  const [localValues, setLocalValues] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState("");
  const [creatingBackup, setCreatingBackup] = useState(false);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [logoUploading, setLogoUploading] = useState(false);
  const [showResetModal, setShowResetModal] = useState(false);
  const [resetConfirmation, setResetConfirmation] = useState("");
  const [resetting, setResetting] = useState(false);
  const { can } = usePermissions();
  const { user } = useAuth();
  const qc = useQueryClient();
  const uploadLogoMutation = useSystemUploadLogo();
  const logoInputRef = useRef<HTMLInputElement>(null);

  const canReset = can("system.reset") && user?.roles?.some((r: { name: string }) => r.name === "super_admin");

  // Dashboard Management
  const { data: dashboards, isLoading: loadingDashboards, refetch: refetchDashboards } = useQuery({
    queryKey: ["dashboards-all"],
    queryFn: async () => {
      const r = await client.get("/dashboards");
      return r.data?.data ?? r.data ?? [];
    },
    enabled: tab === "dashboard",
  });

  const { data: users, isLoading: loadingUsers } = useQuery({
    queryKey: ["users-list"],
    queryFn: async () => {
      const r = await client.get("/users");
      return r.data?.data ?? r.data ?? [];
    },
    enabled: tab === "dashboard",
  });

  const handleAssignDashboard = async (userId: string, dashboardId: string) => {
    try {
      await client.post(`/users/${userId}/dashboard`, { dashboard_id: dashboardId });
      setSaveMsg("تم تعيين الداشبورد بنجاح ✓");
      setTimeout(() => setSaveMsg(""), 3000);
      refetchDashboards();
    } catch {
      setSaveMsg("فشل تعيين الداشبورد");
    }
  };

  const handleDeleteDashboard = async (dashboardId: string, dashboardName: string) => {
    if (!confirm(`هل أنت متأكد من حذف الداشبورد "${dashboardName}" نهائياً؟ لا يمكن التراجع عن هذا الإجراء.`)) return;
    try {
      await client.delete(`/dashboards/${dashboardId}`);
      setSaveMsg("تم حذف الداشبورد بنجاح ✓");
      setTimeout(() => setSaveMsg(""), 3000);
      refetchDashboards();
    } catch (err: any) {
      const msg = err?.response?.data?.message || "فشل حذف الداشبورد";
      alert(msg);
    }
  };

  const { data: settings, isLoading: loadingSettings } = useQuery({
    queryKey: ["settings"],
    queryFn: async () => {
      const r = await client.get("/settings");
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : d?.data ?? []) as Setting[];
    },
  });

  useEffect(() => {
    if (settings) {
      const vals: Record<string, string> = {};
      let serverLogoUrl: string | null = null;
      settings.forEach((s) => {
        if (s.key === "system_logo") {
          serverLogoUrl = s.value;
        } else if (s.key === "ENABLE_AUDIT_LOG" || s.key === "HIDE_ZERO_OR_EMPTY") {
          vals[s.key] = s.value === "1" || s.value === "true" ? "1" : "0";
        } else {
          vals[s.key] = s.value;
        }
      });
      setLocalValues(vals);
      if (serverLogoUrl) {
        setLogoPreview(serverLogoUrl);
      }
    }
  }, [settings]);

  const { data: backups, isLoading: loadingBackups } = useQuery({
    queryKey: ["backups"],
    queryFn: async () => {
      const r = await client.get("/backups");
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : d?.data ?? []) as Backup[];
    },
    enabled: tab === "backup",
  });

  const handleSave = async () => {
    setSaving(true);
    setSaveMsg("");
    try {
      const keys = SETTING_KEYS[tab];
      const bulk = keys.map((key) => ({ key, value: localValues[key] ?? "" }));
      await client.post("/settings/bulk", { settings: bulk });
      qc.invalidateQueries({ queryKey: ["settings"] });
      qc.invalidateQueries({ queryKey: ["settings-public"] });
      setSaveMsg("تم الحفظ بنجاح ✓");
      setTimeout(() => setSaveMsg(""), 3000);
    } catch {
      setSaveMsg("فشل الحفظ");
    } finally {
      setSaving(false);
    }
  };

  const handleLogoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setLogoUploading(true);
    try {
      await uploadLogoMutation.mutateAsync(file);
      const base64 = await fileToBase64(file);
      storeLogo(base64);
      setLogoPreview(base64);
      qc.invalidateQueries({ queryKey: ["settings"] });
      qc.invalidateQueries({ queryKey: ["settings-public"] });
      setSaveMsg("تم رفع الشعار بنجاح ✓");
      setTimeout(() => setSaveMsg(""), 3000);
    } catch {
      setSaveMsg("فشل رفع الشعار");
    } finally {
      setLogoUploading(false);
    }
  };

  const handleRemoveLogo = () => {
    setLogoPreview(null);
    localStorage.removeItem("gfrc-logo");
    setSaveMsg("تم إزالة الشعار");
    setTimeout(() => setSaveMsg(""), 3000);
  };

  const handleCreateBackup = async () => {
    setCreatingBackup(true);
    try {
      await client.post("/backups");
      qc.invalidateQueries({ queryKey: ["backups"] });
    } catch { alert("فشل إنشاء النسخة الاحتياطية"); }
    finally { setCreatingBackup(false); }
  };

  const handleDownloadBackup = async (filename: string) => {
    try {
      const r = await client.get(`/backups/${encodeURIComponent(filename)}`, { responseType: "blob" });
      const url = URL.createObjectURL(r.data);
      const a = document.createElement("a"); a.href = url; a.download = filename; a.click(); URL.revokeObjectURL(url);
    } catch { alert("تعذّر التحميل"); }
  };

  const handleDeleteBackup = async (filename: string) => {
    if (!confirm(`حذف النسخة ${filename}؟`)) return;
    try {
      await client.delete(`/backups/${encodeURIComponent(filename)}`);
      qc.invalidateQueries({ queryKey: ["backups"] });
    } catch { alert("فشل الحذف"); }
  };

  const handleResetSystem = async () => {
    if (resetConfirmation !== "DELETE") return;
    setResetting(true);
    try {
      await client.post("/system/reset", { confirmation: "DELETE" });
      setShowResetModal(false);
      setResetConfirmation("");
      qc.invalidateQueries({ queryKey: ["settings"] });
      qc.invalidateQueries({ queryKey: ["me"] });
      alert("تم إعادة تعيين النظام بنجاح");
      window.location.reload();
    } catch (err: any) {
      const msg = err?.response?.data?.message || "فشل إعادة تعيين النظام";
      alert(msg);
    } finally {
      setResetting(false);
    }
  };

  const tabStyle = (t: TabKey): React.CSSProperties => ({
    padding: "8px 16px", fontSize: "13px", fontWeight: tab === t ? 500 : 400,
    border: "none", background: "none", cursor: "pointer", fontFamily: "inherit",
    color: tab === t ? "var(--color-text-info)" : "var(--color-text-secondary)",
    borderBottom: tab === t ? "2px solid var(--color-border-info)" : "2px solid transparent",
  });

  const inputStyle: React.CSSProperties = { width: "100%", padding: "8px 12px", fontSize: "13px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", fontFamily: "inherit", direction: "rtl" };

  const currentKeys = SETTING_KEYS[tab];
  const canManage = can("manage-settings");

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader title="الإعدادات" />

      <div style={{ display: "flex", borderBottom: "0.5px solid var(--color-border-tertiary)", marginBottom: "20px", flexWrap: "wrap", gap: "8px" }}>
        <button style={tabStyle("general")}  onClick={() => setTab("general")}>عام</button>
        <button style={tabStyle("print")}    onClick={() => setTab("print")}>طباعة</button>
        <button style={tabStyle("security")} onClick={() => setTab("security")}>أمان</button>
        <button style={tabStyle("backup")}   onClick={() => setTab("backup")}>نسخ احتياطي</button>
        <button style={tabStyle("dashboard")} onClick={() => setTab("dashboard")}>📊 الداشبورد</button>
      </div>

      {tab !== "backup" && (
        <div style={{ background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-lg)", padding: "20px" }}>
          {loadingSettings ? <LoadingSpinner /> : (
            <>
              {tab === "print" && (
                <div style={{ marginBottom: "20px", padding: "16px", background: "var(--color-background-secondary)", borderRadius: "8px" }}>
                  <label style={{ display: "block", fontSize: "12px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "10px" }}>
                    شعار النظام
                  </label>
                  <div style={{ display: "flex", alignItems: "center", gap: "16px" }}>
                    {logoPreview ? (
                      <img src={logoPreview} alt="الشعار" style={{ height: "64px", width: "auto", objectFit: "contain", border: "1px solid var(--color-border-secondary)", borderRadius: "6px", padding: "4px", background: "#fff" }} />
                    ) : (
                      <div style={{ height: "64px", width: "120px", display: "flex", alignItems: "center", justifyContent: "center", border: "1px dashed var(--color-border-tertiary)", borderRadius: "6px", color: "var(--color-text-tertiary)", fontSize: "12px" }}>
                        لا يوجد شعار
                      </div>
                    )}
                    <div style={{ display: "flex", gap: "8px" }}>
                      <input
                        ref={logoInputRef}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        onChange={handleLogoUpload}
                        style={{ display: "none" }}
                      />
                      <button
                        onClick={() => logoInputRef.current?.click()}
                        disabled={logoUploading || !canManage}
                        style={{ padding: "7px 14px", fontSize: "12px", fontWeight: 500, border: "0.5px solid var(--color-border-info)", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "6px", cursor: logoUploading || !canManage ? "not-allowed" : "pointer", opacity: logoUploading || !canManage ? 0.7 : 1, fontFamily: "inherit" }}
                      >
                        {logoUploading ? "جاري الرفع..." : "تغيير الشعار"}
                      </button>
                      {logoPreview && (
                        <button
                          onClick={handleRemoveLogo}
                          disabled={!canManage}
                          style={{ padding: "7px 14px", fontSize: "12px", fontWeight: 500, border: "0.5px solid var(--color-border-danger)", background: "none", color: "var(--color-text-danger)", borderRadius: "6px", cursor: !canManage ? "not-allowed" : "pointer", fontFamily: "inherit" }}
                        >
                          إزالة
                        </button>
                      )}
                    </div>
                  </div>
                  <p style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "8px", marginBottom: 0 }}>
                    الصيغ المدعومة: PNG, JPG, WEBP | الحد الأقصى: 2MB
                  </p>
                </div>
              )}

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "16px", marginBottom: "20px" }}>
                {currentKeys.map((key) => (
                  <div key={key}>
                    <label style={{ display: "block", fontSize: "12px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "6px" }}>
                      {SETTING_LABELS[key] ?? key}
                    </label>
                    {key === "ENABLE_AUDIT_LOG" || key === "HIDE_ZERO_OR_EMPTY" ? (
                      <select value={localValues[key] ?? "1"} onChange={e => setLocalValues(v => ({ ...v, [key]: e.target.value }))} disabled={!canManage} style={inputStyle}>
                        <option value="1">مفعّل</option>
                        <option value="0">معطّل</option>
                      </select>
                    ) : (
                      <input type="text" value={localValues[key] ?? ""} onChange={e => setLocalValues(v => ({ ...v, [key]: e.target.value }))} disabled={!canManage} style={inputStyle} />
                    )}
                  </div>
                ))}
              </div>
              {canManage && (
                <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
                  <button onClick={handleSave} disabled={saving} style={{ padding: "9px 20px", fontSize: "13px", fontWeight: 500, border: "0.5px solid var(--color-border-success)", background: "var(--color-background-success)", color: "var(--color-text-success)", borderRadius: "6px", cursor: saving ? "not-allowed" : "pointer", opacity: saving ? 0.7 : 1, fontFamily: "inherit" }}>
                    {saving ? "جاري الحفظ..." : "حفظ الإعدادات"}
                  </button>
                  {saveMsg && <span style={{ fontSize: "12px", color: saveMsg.includes("نجاح") ? "var(--color-text-success)" : "var(--color-text-danger)" }}>{saveMsg}</span>}
                </div>
              )}
            </>
          )}
        </div>
      )}

      {tab === "backup" && (
        <div style={{ background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-lg)", overflow: "hidden" }}>
          <div style={{ padding: "14px 16px", borderBottom: "0.5px solid var(--color-border-tertiary)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <span style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)" }}>النسخ الاحتياطية</span>
            {canManage && (
              <button onClick={handleCreateBackup} disabled={creatingBackup} style={{ padding: "7px 14px", fontSize: "12px", fontWeight: 500, border: "0.5px solid var(--color-border-info)", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "6px", cursor: creatingBackup ? "not-allowed" : "pointer", opacity: creatingBackup ? 0.7 : 1, fontFamily: "inherit" }}>
                {creatingBackup ? "جاري الإنشاء..." : "+ إنشاء نسخة احتياطية"}
              </button>
            )}
          </div>

          {loadingBackups ? (
            <div style={{ padding: "32px", textAlign: "center" }}><LoadingSpinner /></div>
          ) : (backups ?? []).length === 0 ? (
            <div style={{ padding: "32px", textAlign: "center", color: "var(--color-text-tertiary)", fontSize: "13px" }}>لا توجد نسخ احتياطية</div>
          ) : (
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
              <thead><tr style={{ background: "var(--color-background-secondary)" }}>
                {["اسم الملف","الحجم","تاريخ الإنشاء","إجراءات"].map(h => (
                  <th key={h} style={{ padding: "9px 12px", textAlign: "right", fontWeight: 500, fontSize: "12px", color: "var(--color-text-secondary)", borderBottom: "0.5px solid var(--color-border-tertiary)" }}>{h}</th>
                ))}
              </tr></thead>
              <tbody>
                {(backups ?? []).map((b) => (
                  <tr key={b.filename} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                    <td style={{ padding: "9px 12px", fontFamily: "var(--font-mono)", fontSize: "12px" }}>{b.filename}</td>
                    <td style={{ padding: "9px 12px", color: "var(--color-text-secondary)", fontSize: "12px" }}>{b.size}</td>
                    <td style={{ padding: "9px 12px", color: "var(--color-text-tertiary)", fontSize: "12px" }}>{formatDateTime(b.created_at)}</td>
                    <td style={{ padding: "9px 12px" }}>
                      <div style={{ display: "flex", gap: "8px" }}>
                        <button onClick={() => handleDownloadBackup(b.filename)} style={{ fontSize: "11px", padding: "3px 10px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", borderRadius: "4px", cursor: "pointer", fontFamily: "inherit" }}>⬇ تحميل</button>
                        {canManage && (
                          <button onClick={() => handleDeleteBackup(b.filename)} style={{ fontSize: "11px", padding: "3px 10px", border: "0.5px solid var(--color-border-danger)", background: "none", color: "var(--color-text-danger)", borderRadius: "4px", cursor: "pointer", fontFamily: "inherit" }}>✕ حذف</button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {tab === "dashboard" && (
        <div style={{ background: "var(--color-background-primary)", border: "0.5px solid var(--color-border-tertiary)", borderRadius: "var(--border-radius-lg)", padding: "20px" }}>
          {loadingDashboards || loadingUsers ? <LoadingSpinner /> : (
            <>
              {/* Dashboards List */}
              <div style={{ marginBottom: "32px" }}>
                <h3 style={{ fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "16px" }}>
                  📊 الداشبورد المتاحة ({dashboards?.length || 0})
                </h3>
                
                {(dashboards && dashboards.length > 0) ? (
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(350px, 1fr))", gap: "16px" }}>
                    {dashboards.map((dashboard: any) => (
                      <div
                        key={dashboard.id}
                        style={{
                          padding: "16px",
                          background: "var(--color-background-secondary)",
                          border: "1px solid var(--color-border-tertiary)",
                          borderRadius: "8px",
                          transition: "box-shadow 0.2s",
                        }}
                        onMouseEnter={(e) => {
                          (e.currentTarget as HTMLElement).style.boxShadow = "0 2px 8px rgba(0,0,0,0.08)";
                        }}
                        onMouseLeave={(e) => {
                          (e.currentTarget as HTMLElement).style.boxShadow = "none";
                        }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "12px" }}>
                          <div>
                            <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "4px" }}>
                              {dashboard.name_ar || dashboard.name || 'داشبورد بدون اسم'}
                            </div>
                            <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
                              {dashboard.visibility === 'public' ? '🌍 عامة' :
                               dashboard.visibility === 'shared' ? '👥 مشتركة' :
                               dashboard.visibility === 'role' ? '👔 حسب الصلاحية' :
                               dashboard.visibility === 'department' ? '🏢 حسب القسم' : '🔒 خاصة'}
                            </div>
                          </div>
                          <div style={{ display: "flex", gap: "6px" }}>
                            <span style={{
                              fontSize: "11px",
                              padding: "2px 8px",
                              borderRadius: "10px",
                              background: dashboard.scope === 'system' ? "var(--color-background-info)" : "var(--color-background-primary)",
                              color: dashboard.scope === 'system' ? "var(--color-text-info)" : "var(--color-text-secondary)",
                              border: "0.5px solid var(--color-border-tertiary)",
                            }}>
                              {dashboard.scope === 'system' ? '⚙️ نظام' : dashboard.scope === 'user' ? '👤 مستخدم' : '👥 مجموعة'}
                            </span>
                          </div>
                        </div>
                        
                        <div style={{ fontSize: "12px", color: "var(--color-text-secondary)", marginBottom: "12px" }}>
                          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "4px" }}>
                            <span>📑 الأقسام:</span>
                            <span style={{ fontWeight: 500 }}>{dashboard.sections_count || 0}</span>
                          </div>
                          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "4px" }}>
                            <span>🎯 الودجات:</span>
                            <span style={{ fontWeight: 500 }}>{dashboard.widgets_count || 0}</span>
                          </div>
                          {dashboard.created_by_name && (
                            <div style={{ display: "flex", justifyContent: "space-between" }}>
                              <span>👤 أنشأها:</span>
                              <span style={{ fontWeight: 500 }}>{dashboard.created_by_name}</span>
                            </div>
                          )}
                        </div>
                        
                        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                          <button
                            onClick={() => window.location.href = `/dashboard/builder/${dashboard.id}`}
                            style={{
                              flex: 1,
                              padding: "6px 12px",
                              fontSize: "12px",
                              fontWeight: 500,
                              border: "0.5px solid var(--color-border-info)",
                              background: "var(--color-background-info)",
                              color: "var(--color-text-info)",
                              borderRadius: "6px",
                              cursor: "pointer",
                              fontFamily: "inherit",
                            }}
                          >
                            ✏️ تعديل
                          </button>
                          <button
                            onClick={() => handleDeleteDashboard(dashboard.id, dashboard.name_ar || dashboard.name)}
                            style={{
                              padding: "6px 12px",
                              fontSize: "12px",
                              fontWeight: 500,
                              border: "0.5px solid var(--color-border-danger)",
                              background: "none",
                              color: "var(--color-text-danger)",
                              borderRadius: "6px",
                              cursor: "pointer",
                              fontFamily: "inherit",
                            }}
                          >
                            🗑️ حذف
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div style={{ textAlign: "center", padding: "48px", color: "var(--color-text-tertiary)" }}>
                    <div style={{ fontSize: "48px", marginBottom: "16px" }}>📊</div>
                    <div style={{ fontSize: "14px", fontWeight: 500 }}>لا توجد داشبورد</div>
                    <div style={{ fontSize: "12px", marginTop: "8px" }}>ابدأ بإنشاء داشبورد جديدة من صفحة الداشبورد</div>
                  </div>
                )}
              </div>

              {/* User Dashboard Assignments */}
              <div>
                <h3 style={{ fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "16px" }}>
                  👥 تعيين الداشبورد للمستخدمين
                </h3>
                
                {(users && users.length > 0) ? (
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(400px, 1fr))", gap: "12px" }}>
                    {users.map((user: any) => (
                      <div
                        key={user.id}
                        style={{
                          padding: "14px",
                          background: "var(--color-background-secondary)",
                          border: "1px solid var(--color-border-tertiary)",
                          borderRadius: "8px",
                        }}
                      >
                        <div style={{ display: "flex", alignItems: "center", gap: "12px", marginBottom: "12px" }}>
                          <div style={{
                            width: "40px",
                            height: "40px",
                            borderRadius: "50%",
                            background: "var(--color-background-info)",
                            color: "var(--color-text-info)",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                            fontSize: "16px",
                            fontWeight: 600,
                            flexShrink: 0,
                          }}>
                            {user.name?.charAt(0) || user.username?.charAt(0) || '👤'}
                          </div>
                          <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                              {user.name || 'مستخدم بدون اسم'}
                            </div>
                            <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
                              {user.username}
                            </div>
                          </div>
                        </div>
                        
                        <div style={{ marginBottom: "8px" }}>
                          <label style={{ fontSize: "11px", fontWeight: 500, color: "var(--color-text-secondary)", display: "block", marginBottom: "6px" }}>
                            الداشبورد المخصصة:
                          </label>
                          <select
                            value={user.dashboard_id || ''}
                            onChange={(e) => handleAssignDashboard(user.id, e.target.value)}
                            style={{
                              width: "100%",
                              padding: "6px 10px",
                              fontSize: "12px",
                              border: "0.5px solid var(--color-border-secondary)",
                              borderRadius: "6px",
                              background: "var(--color-background-primary)",
                              color: "var(--color-text-primary)",
                              fontFamily: "inherit",
                            }}
                          >
                            <option value="">-- داشبورد افتراضية --</option>
                            {dashboards?.filter((d: any) => d.visibility === 'public' || d.visibility === 'shared').map((d: any) => (
                              <option key={d.id} value={d.id}>
                                {d.name_ar || d.name || 'داشبورد'}
                              </option>
                            ))}
                          </select>
                        </div>
                        
                        {user.dashboard_id && (
                          <div style={{ fontSize: "11px", color: "var(--color-text-success)", display: "flex", alignItems: "center", gap: "4px" }}>
                            <span>✅</span>
                            <span>تم تعيين داشبورد مخصصة</span>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
                    لا يوجد مستخدمين
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      )}

      {canReset && (
        <div style={{ marginTop: "24px", background: "var(--color-background-primary)", border: "1px solid var(--color-border-danger)", borderRadius: "var(--border-radius-lg)", overflow: "hidden" }}>
          <div style={{ padding: "14px 16px", borderBottom: "1px solid var(--color-border-danger)", background: "rgba(220, 38, 38, 0.05)" }}>
            <span style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-danger)" }}>⚠️ منطقة الخطر</span>
          </div>
          <div style={{ padding: "20px" }}>
            <p style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: "16px", lineHeight: 1.6 }}>
              إعادة تعيين النظام ستحذف جميع البيانات (الإيصالات، السجلات، الحقول، الإعدادات) مع الحفاظ على حساب الأدمن.
              <br />
              <strong style={{ color: "var(--color-text-danger)" }}>هذا الإجراء لا يمكن التراجع عنه.</strong>
            </p>
            <button
              onClick={() => setShowResetModal(true)}
              style={{ padding: "9px 20px", fontSize: "13px", fontWeight: 500, border: "1px solid var(--color-border-danger)", background: "rgba(220, 38, 38, 0.1)", color: "var(--color-text-danger)", borderRadius: "6px", cursor: "pointer", fontFamily: "inherit" }}
            >
              حذف كافة البيانات
            </button>
          </div>
        </div>
      )}

      {showResetModal && (
        <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", display: "flex", alignItems: "center", justifyContent: "center", zIndex: 9999 }}>
          <div style={{ background: "var(--color-background-primary)", border: "1px solid var(--color-border-danger)", borderRadius: "12px", padding: "24px", maxWidth: "480px", width: "90%", boxShadow: "0 20px 60px rgba(0,0,0,0.3)" }}>
            <h3 style={{ fontSize: "16px", fontWeight: 600, color: "var(--color-text-danger)", marginBottom: "12px" }}>⚠️ تأكيد حذف كافة البيانات</h3>
            <p style={{ fontSize: "13px", color: "var(--color-text-secondary)", marginBottom: "16px", lineHeight: 1.6 }}>
              هذا الإجراء سيحذف جميع البيانات بشكل نهائي ولا يمكن التراجع عنه.
              <br />
              سيتم الحفاظ على حساب الأدمن فقط.
            </p>
            <div style={{ marginBottom: "16px" }}>
              <label style={{ display: "block", fontSize: "12px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "6px" }}>
                اكتب <code style={{ background: "var(--color-background-secondary)", padding: "2px 6px", borderRadius: "4px", color: "var(--color-text-danger)" }}>DELETE</code> للتأكيد
              </label>
              <input
                type="text"
                value={resetConfirmation}
                onChange={(e) => setResetConfirmation(e.target.value)}
                style={{ width: "100%", padding: "8px 12px", fontSize: "13px", border: "1px solid var(--color-border-danger)", borderRadius: "6px", background: "var(--color-background-primary)", color: "var(--color-text-primary)", fontFamily: "inherit" }}
                placeholder="DELETE"
              />
            </div>
            <div style={{ display: "flex", gap: "12px", justifyContent: "flex-end" }}>
              <button
                onClick={() => { setShowResetModal(false); setResetConfirmation(""); }}
                disabled={resetting}
                style={{ padding: "9px 20px", fontSize: "13px", fontWeight: 500, border: "0.5px solid var(--color-border-secondary)", background: "var(--color-background-secondary)", color: "var(--color-text-secondary)", borderRadius: "6px", cursor: resetting ? "not-allowed" : "pointer", fontFamily: "inherit" }}
              >
                إلغاء
              </button>
              <button
                onClick={handleResetSystem}
                disabled={resetConfirmation !== "DELETE" || resetting}
                style={{ padding: "9px 20px", fontSize: "13px", fontWeight: 500, border: "1px solid var(--color-border-danger)", background: "var(--color-background-danger)", color: "#fff", borderRadius: "6px", cursor: resetConfirmation !== "DELETE" || resetting ? "not-allowed" : "pointer", fontFamily: "inherit", opacity: resetConfirmation !== "DELETE" || resetting ? 0.7 : 1 }}
              >
                {resetting ? "جاري الحذف..." : "حذف كافة البيانات"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
