export interface PermissionDefinition {
  key: string;
  label: string;
  section: string;
  isDanger?: boolean;
}

export const permissionsRegistry: PermissionDefinition[] = [
  { key: 'system.reset', label: 'حذف كافة البيانات', section: 'النظام', isDanger: true },

  { key: 'manage-users', label: 'إدارة المستخدمين', section: 'المستخدمين' },
  { key: 'view-users', label: 'عرض المستخدمين', section: 'المستخدمين' },

  { key: 'workflows.*', label: 'إدارة سير العمل', section: 'سير العمل' },
  { key: 'workflows.view', label: 'عرض سير العمل', section: 'سير العمل' },
  { key: 'workflows.manage', label: 'إدارة سير العمل', section: 'سير العمل' },

  { key: 'manage-registers', label: 'إدارة السجلات', section: 'المالية' },
  { key: 'view-registers', label: 'عرض السجلات', section: 'المالية' },
  { key: 'manage-settings', label: 'إدارة الإعدادات', section: 'المالية' },

  { key: 'create-receipt', label: 'إنشاء إيصال', section: 'الإيصالات' },
  { key: 'view-receipt', label: 'عرض الإيصالات', section: 'الإيصالات' },
  { key: 'issue-receipt', label: 'إصدار إيصال', section: 'الإيصالات' },
  { key: 'cancel-receipt', label: 'إلغاء إيصال', section: 'الإيصالات' },
  { key: 'revise-receipt', label: 'مراجعة إيصال', section: 'الإيصالات' },
  { key: 'print-receipt', label: 'طباعة إيصال', section: 'الإيصالات' },
  { key: 'view-all-receipts', label: 'عرض جميع الإيصالات', section: 'الإيصالات' },

  { key: 'view-reports', label: 'عرض التقارير', section: 'التقارير' },
  { key: 'export-reports', label: 'تصدير التقارير', section: 'التقارير' },

  { key: 'view-audit-logs', label: 'عرض سجل التدقيق', section: 'التدقيق' },
];

export function getPermissionSections(): string[] {
  const sections = new Set<string>();
  permissionsRegistry.forEach((p) => sections.add(p.section));
  return Array.from(sections);
}

export function getPermissionsBySection(section: string): PermissionDefinition[] {
  return permissionsRegistry.filter((p) => p.section === section);
}
