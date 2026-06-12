import React from 'react';
import type { Dashboard } from '@/types/dashboard';

interface DashboardSelectorProps {
  dashboards: Dashboard[];
  selectedDashboardId?: number;
  onSelect: (dashboardId: number) => void;
  onClose: () => void;
}

/**
 * Dashboard Selector Modal
 * Allows users to switch between available dashboards
 */
export function DashboardSelector({
  dashboards,
  selectedDashboardId,
  onSelect,
  onClose,
}: DashboardSelectorProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={onClose}>
      <div
        className="bg-white rounded-lg w-full max-w-md p-6"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-gray-900">اختر داشبورد</h2>
          <button
            onClick={onClose}
            className="p-1 hover:bg-gray-100 rounded"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="space-y-2">
          {dashboards.map((dashboard) => (
            <button
              key={dashboard.id}
              onClick={() => onSelect(dashboard.id)}
              className={`w-full p-4 text-right rounded-lg border transition-colors ${
                selectedDashboardId === dashboard.id
                  ? 'bg-blue-50 border-blue-200 text-blue-900'
                  : 'bg-white border-gray-200 text-gray-900 hover:bg-gray-50'
              }`}
            >
              <div className="flex items-center justify-between">
                <div>
                  <div className="font-medium">{dashboard.name_ar}</div>
                  {dashboard.description && (
                    <div className="text-sm text-gray-500 mt-1">
                      {dashboard.description}
                    </div>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  {/* Scope Badge */}
                  <span className={`px-2 py-1 text-xs rounded ${
                    dashboard.scope === 'user' ? 'bg-purple-100 text-purple-800' :
                    dashboard.scope === 'role' ? 'bg-blue-100 text-blue-800' :
                    dashboard.scope === 'department' ? 'bg-green-100 text-green-800' :
                    dashboard.scope === 'organization' ? 'bg-amber-100 text-amber-800' :
                    'bg-gray-100 text-gray-800'
                  }`}>
                    {dashboard.scope === 'user' && 'شخصي'}
                    {dashboard.scope === 'role' && 'حسب الدور'}
                    {dashboard.scope === 'department' && 'القسم'}
                    {dashboard.scope === 'organization' && 'المؤسسة'}
                    {dashboard.scope === 'system' && 'عام'}
                  </span>
                  
                  {/* Selected Indicator */}
                  {selectedDashboardId === dashboard.id && (
                    <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                    </svg>
                  )}
                </div>
              </div>
            </button>
          ))}
        </div>

        {dashboards.length === 0 && (
          <div className="text-center text-gray-500 py-8">
            لا توجد داشبوردات متاحة
          </div>
        )}

        <div className="mt-6 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          >
            إغلاق
          </button>
        </div>
      </div>
    </div>
  );
}
