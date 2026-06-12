import React, { useState } from 'react';
import type { UserDashboardPreference } from '@/types/dashboard';

interface UserPreferencesModalProps {
  preferences: UserDashboardPreference;
  onUpdate: (preferences: Partial<UserDashboardPreference>) => void;
  onClose: () => void;
}

/**
 * User Preferences Modal
 * Allows users to customize their dashboard experience
 */
export function UserPreferencesModal({
  preferences,
  onUpdate,
  onClose,
}: UserPreferencesModalProps) {
  const [formData, setFormData] = useState<Partial<UserDashboardPreference>>({
    theme: preferences.theme || 'light',
    font_size: preferences.font_size || 'medium',
    layout_density: preferences.layout_density || 'comfortable',
    auto_refresh_widgets: preferences.auto_refresh_widgets ?? true,
    default_refresh_interval: preferences.default_refresh_interval || 60,
    executive_mode: preferences.executive_mode ?? false,
    tv_mode: preferences.tv_mode ?? false,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onUpdate(formData);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={onClose}>
      <div
        className="bg-white rounded-lg w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-bold text-gray-900">تفضيلات الداشبورد</h2>
          <button
            onClick={onClose}
            className="p-1 hover:bg-gray-100 rounded"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Theme */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              السمة
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { value: 'light', label: 'فاتح' },
                { value: 'dark', label: 'داكن' },
                { value: 'auto', label: 'تلقائي' },
              ].map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setFormData({ ...formData, theme: option.value as any })}
                  className={`p-3 text-sm rounded-lg border transition-colors ${
                    formData.theme === option.value
                      ? 'bg-blue-50 border-blue-200 text-blue-900'
                      : 'bg-white border-gray-200 text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </div>

          {/* Font Size */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              حجم الخط
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { value: 'small', label: 'صغير' },
                { value: 'medium', label: 'متوسط' },
                { value: 'large', label: 'كبير' },
              ].map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setFormData({ ...formData, font_size: option.value as any })}
                  className={`p-3 text-sm rounded-lg border transition-colors ${
                    formData.font_size === option.value
                      ? 'bg-blue-50 border-blue-200 text-blue-900'
                      : 'bg-white border-gray-200 text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </div>

          {/* Layout Density */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              كثافة التخطيط
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { value: 'compact', label: 'مضغوط' },
                { value: 'comfortable', label: 'مريح' },
                { value: 'spacious', label: 'واسع' },
              ].map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setFormData({ ...formData, layout_density: option.value as any })}
                  className={`p-3 text-sm rounded-lg border transition-colors ${
                    formData.layout_density === option.value
                      ? 'bg-blue-50 border-blue-200 text-blue-900'
                      : 'bg-white border-gray-200 text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </div>

          {/* Auto Refresh */}
          <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <div className="font-medium text-gray-900">التحديث التلقائي للـ widgets</div>
              <div className="text-sm text-gray-500">تحديث البيانات تلقائياً</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={formData.auto_refresh_widgets}
                onChange={(e) => setFormData({ ...formData, auto_refresh_widgets: e.target.checked })}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>

          {/* Refresh Interval */}
          {formData.auto_refresh_widgets && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                فاصل التحديث (ثواني)
              </label>
              <input
                type="number"
                min="0"
                max="3600"
                value={formData.default_refresh_interval || 60}
                onChange={(e) => setFormData({ ...formData, default_refresh_interval: parseInt(e.target.value) })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              />
            </div>
          )}

          {/* Executive Mode */}
          <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <div className="font-medium text-gray-900">الوضع التنفيذي</div>
              <div className="text-sm text-gray-500">عرض مبسط للمدراء</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={formData.executive_mode}
                onChange={(e) => setFormData({ ...formData, executive_mode: e.target.checked })}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>

          {/* TV Mode */}
          <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <div className="font-medium text-gray-900">وضع التلفزيون</div>
              <div className="text-sm text-gray-500">لعرض الشاشات الكبيرة</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={formData.tv_mode}
                onChange={(e) => setFormData({ ...formData, tv_mode: e.target.checked })}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-2 pt-4 border-t">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
            >
              إلغاء
            </button>
            <button
              type="submit"
              className="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700"
            >
              حفظ التغييرات
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
