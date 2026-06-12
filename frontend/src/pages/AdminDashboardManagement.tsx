import React, { useState, useEffect } from 'react';
import { dashboardApi } from '@/api/dashboard';
import { useUsers } from '@/hooks/useUsers';
import type { Dashboard } from '@/types/dashboard';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { Button } from '@/components/ui/Button';

export default function AdminDashboardManagement() {
  const [dashboards, setDashboards] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [selectedDashboard, setSelectedDashboard] = useState<Dashboard | null>(null);
  const { data: users = [] } = useUsers();

  useEffect(() => {
    loadDashboards();
  }, []);

  const loadDashboards = async () => {
    try {
      setIsLoading(true);
      const result = await dashboardApi.adminList();
      setDashboards(result.dashboards);
    } catch (error) {
      console.error('Failed to load dashboards:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleAssign = async (userId: string, setAsDefault: boolean) => {
    try {
      if (!selectedDashboard) return;
      await dashboardApi.assignToUser(selectedDashboard.id, userId, setAsDefault);
      alert('✅ تم تعيين الداشبورد بنجاح!');
      setShowAssignModal(false);
      loadDashboards();
    } catch (error: any) {
      alert('❌ فشل التعيين: ' + (error.message || ''));
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-6" dir="rtl">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">إدارة الداشبوردات</h1>
            <p className="text-gray-500 mt-1">إنشاء وتخصيص الداشبوردات للموظفين</p>
          </div>
          <Button onClick={() => setShowCreateModal(true)}>
            ➕ إنشاء داشبورد جديد
          </Button>
        </div>

        {/* Dashboards List */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-700">الداشبورد</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-700">النطاق</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-700">مخصص لـ</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-700">الحالة</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-700">تاريخ الإنشاء</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-700">إجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {dashboards.map((dashboard) => (
                <tr key={dashboard.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <div className="font-medium text-gray-900">{dashboard.name_ar}</div>
                    {dashboard.name_en && (
                      <div className="text-xs text-gray-500">{dashboard.name_en}</div>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex px-2 py-1 text-xs rounded-full ${
                      dashboard.scope === 'user' ? 'bg-purple-100 text-purple-800' :
                      dashboard.scope === 'role' ? 'bg-blue-100 text-blue-800' :
                      dashboard.scope === 'department' ? 'bg-green-100 text-green-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {dashboard.scope === 'user' && '👤 شخصي'}
                      {dashboard.scope === 'role' && '🎭 حسب الدور'}
                      {dashboard.scope === 'department' && '🏢 حسب القسم'}
                      {dashboard.scope === 'system' && '🌐 عام'}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    {dashboard.assigned_to ? (
                      <div className="flex items-center gap-2">
                        <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold">
                          {dashboard.assigned_to.name.charAt(0)}
                        </div>
                        <div>
                          <div className="font-medium text-gray-900">{dashboard.assigned_to.name_ar}</div>
                          <div className="text-xs text-gray-500">{dashboard.assigned_to.name}</div>
                        </div>
                      </div>
                    ) : (
                      <span className="text-gray-400">غير معين</span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex px-2 py-1 text-xs rounded-full ${
                      dashboard.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                    }`}>
                      {dashboard.is_active ? '✓ نشط' : '✗ غير نشط'}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {new Date(dashboard.created_at).toLocaleDateString('ar-IQ')}
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => {
                          setSelectedDashboard(dashboard);
                          setShowAssignModal(true);
                        }}
                        className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                      >
                        تعيين لموظف
                      </button>
                      <a
                        href={`/dashboard/builder/${dashboard.id}`}
                        className="text-purple-600 hover:text-purple-800 text-sm font-medium"
                      >
                        تعديل
                      </a>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {dashboards.length === 0 && (
            <div className="text-center py-12 text-gray-500">
              <div className="text-4xl mb-3">📊</div>
              <div>لا توجد داشبوردات بعد</div>
              <div className="text-sm mt-2">ابدأ بإنشاء داشبورد جديد</div>
            </div>
          )}
        </div>
      </div>

      {/* Assign Modal */}
      {showAssignModal && selectedDashboard && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setShowAssignModal(false)}>
          <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">تعيين الداشبورد لموظف</h3>
            <p className="text-sm text-gray-600 mb-4">
              الداشبورد: <span className="font-bold">{selectedDashboard.name_ar}</span>
            </p>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  اختر الموظف
                </label>
                <select
                  className="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                  onChange={(e) => handleAssign(e.target.value, false)}
                  defaultValue=""
                >
                  <option value="" disabled>-- اختر --</option>
                  {users.map((user: any) => (
                    <option key={user.id} value={user.id}>
                      {user.name_ar} ({user.username})
                    </option>
                  ))}
                </select>
              </div>

              <label className="flex items-center gap-2">
                <input type="checkbox" className="rounded" defaultChecked />
                <span className="text-sm text-gray-700">تعيين كداشبورد افتراضي</span>
              </label>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                onClick={() => setShowAssignModal(false)}
                className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
              >
                إلغاء
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Create Modal - Simplified */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setShowCreateModal(false)}>
          <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">إنشاء داشبورد جديد</h3>
            <p className="text-sm text-gray-600 mb-4">
              سيتم توجيهك إلى محرر الداشبورد لإنشاء داشبورد جديد
            </p>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setShowCreateModal(false)}
                className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
              >
                إلغاء
              </button>
              <a
                href="/dashboard/builder"
                className="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700"
              >
                إنشاء
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
