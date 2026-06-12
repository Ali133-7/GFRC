import React, { useState, useEffect } from 'react';
import { dashboardApi } from '@/api/dashboard';
import type { Dashboard } from '@/types/dashboard';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { formatCurrency, formatNumber } from '@/utils/formatNumber';

interface FundStats {
  total_receipts: number;
  total_amount: number;
  pending_receipts: number;
  period: string;
}

export default function DashboardPage() {
  const [currentDashboard, setCurrentDashboard] = useState<Dashboard | null>(null);
  const [fundStats, setFundStats] = useState<FundStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [period, setPeriod] = useState<'today' | 'week' | 'month' | 'year'>('today');

  // Load dashboard and statistics
  useEffect(() => {
    loadDashboardData();
    loadFundStatistics();
  }, [period]);

  const loadDashboardData = async () => {
    try {
      const dashboardResult = await dashboardApi.getEffectiveDashboard();
      setCurrentDashboard(dashboardResult.dashboard);
    } catch (err: any) {
      console.error('[DashboardPage] Error loading dashboard:', err);
    }
  };

  const loadFundStatistics = async () => {
    try {
      const result = await dashboardApi.getFundStatistics(period);
      setFundStats(result.statistics);
    } catch (err: any) {
      console.error('[DashboardPage] Error loading statistics:', err);
    } finally {
      setIsLoading(false);
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
    <div className="min-h-screen bg-gray-50" dir="rtl">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 px-6 py-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              {currentDashboard?.name_ar || 'لوحة تحكم الصندوق'}
            </h1>
            {currentDashboard?.description && (
              <p className="text-sm text-gray-500 mt-1">
                {currentDashboard.description}
              </p>
            )}
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={() => window.location.href = '/dashboard/builder'}
              className="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 transition-colors"
            >
              ✏️ تعديل الداشبورد
            </button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="p-6">
        <div className="max-w-7xl mx-auto">
          {/* Period Selector */}
          <div className="mb-6 flex items-center justify-between">
            <h2 className="text-lg font-bold text-gray-900">إحصائيات الصندوق</h2>
            <div className="flex items-center gap-2">
              {[
                { value: 'today', label: 'اليوم' },
                { value: 'week', label: 'الأسبوع' },
                { value: 'month', label: 'الشهر' },
                { value: 'year', label: 'السنة' },
              ].map((p) => (
                <button
                  key={p.value}
                  onClick={() => setPeriod(p.value as any)}
                  className={`px-4 py-2 text-sm rounded transition-colors ${
                    period === p.value
                      ? 'bg-blue-600 text-white'
                      : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300'
                  }`}
                >
                  {p.label}
                </button>
              ))}
            </div>
          </div>

          {/* Stats Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {/* Total Receipts */}
            <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-medium text-gray-600">إجمالي الوصولات</h3>
                <span className="text-3xl">📋</span>
              </div>
              <div className="text-3xl font-bold text-gray-900 mb-2">
                {fundStats ? formatNumber(fundStats.total_receipts) : '-'}
              </div>
              <div className="text-sm text-gray-500">
                وصل صادر
              </div>
            </div>

            {/* Total Amount */}
            <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-medium text-gray-600">إجمالي المقبوضات</h3>
                <span className="text-3xl">💰</span>
              </div>
              <div className="text-3xl font-bold text-green-600 mb-2">
                {fundStats ? formatCurrency(fundStats.total_amount) : '-'}
              </div>
              <div className="text-sm text-gray-500">
                دينار عراقي
              </div>
            </div>

            {/* Pending Receipts */}
            <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-medium text-gray-600">الوصولات المعلقة</h3>
                <span className="text-3xl">⏳</span>
              </div>
              <div className="text-3xl font-bold text-amber-600 mb-2">
                {fundStats ? formatNumber(fundStats.pending_receipts) : '-'}
              </div>
              <div className="text-sm text-gray-500">
                تحتاج مراجعة
              </div>
            </div>

            {/* Average per Receipt */}
            <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-medium text-gray-600">متوسط الوصل</h3>
                <span className="text-3xl">📊</span>
              </div>
              <div className="text-3xl font-bold text-blue-600 mb-2">
                {fundStats && fundStats.total_receipts > 0
                  ? formatCurrency(fundStats.total_amount / fundStats.total_receipts)
                  : '-'}
              </div>
              <div className="text-sm text-gray-500">
                لكل وصل
              </div>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200 mb-8">
            <h2 className="text-lg font-bold text-gray-900 mb-4">إجراءات سريعة</h2>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <a
                href="/receipts/create"
                className="p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-center"
              >
                <div className="text-3xl mb-2">➕</div>
                <div className="font-medium text-blue-900">وصل جديد</div>
              </a>

              <a
                href="/receipts"
                className="p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors text-center"
              >
                <div className="text-3xl mb-2">📋</div>
                <div className="font-medium text-green-900">الوصولات</div>
              </a>

              <a
                href="/registers"
                className="p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors text-center"
              >
                <div className="text-3xl mb-2">🗂️</div>
                <div className="font-medium text-purple-900">السجلات</div>
              </a>

              <a
                href="/reports"
                className="p-4 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors text-center"
              >
                <div className="text-3xl mb-2">📈</div>
                <div className="font-medium text-amber-900">التقارير</div>
              </a>
            </div>
          </div>

          {/* Dashboard Sections */}
          {currentDashboard?.sections && currentDashboard.sections.length > 0 ? (
            currentDashboard.sections.map((section) => (
              <div key={section.id} className="mb-8">
                <h3 className="text-lg font-bold text-gray-900 mb-4">{section.name_ar}</h3>
                <div className="grid grid-cols-12 gap-4">
                  {section.widgets?.map((widget) => (
                    <div
                      key={widget.id}
                      className="bg-white rounded-lg p-4 shadow-sm border border-gray-200"
                      style={{
                        gridColumn: `span ${widget.grid_width || 6}`,
                      }}
                    >
                      <div className="text-center text-gray-500">
                        <div className="text-2xl mb-2">
                          {widget.widget_type === 'kpi_card' && '📊'}
                          {widget.widget_type === 'chart' && '📈'}
                          {widget.widget_type === 'table' && '📋'}
                          {widget.widget_type === 'list' && '📝'}
                        </div>
                        <div className="font-medium">{widget.name_ar}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))
          ) : (
            <div className="bg-white rounded-lg p-12 text-center shadow-sm border border-gray-200">
              <div className="text-6xl mb-4">🎨</div>
              <h3 className="text-xl font-bold text-gray-700 mb-2">داشبوردك الشخصي</h3>
              <p className="text-gray-500 mb-6">
                قم بتخصيص الداشبورد الخاص بك لعرض الإحصائيات التي تهمك
              </p>
              <a
                href="/dashboard/builder"
                className="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                تخصيص الداشبورد
              </a>
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
