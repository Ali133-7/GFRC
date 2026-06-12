// Utility Widgets for Dashboard
import React from 'react';
import type { DashboardWidget } from '@/types/dashboard';

interface WidgetProps {
  widget: DashboardWidget;
  data?: any;
  isLoading?: boolean;
  onRefresh?: () => void;
  onEdit?: () => void;
  onRemove?: () => void;
  canEdit?: boolean;
}

/**
 * Notes Widget
 */
export function NotesWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const [notes, setNotes] = React.useState('');
  
  return (
    <div className="relative p-4 bg-yellow-50 rounded-lg border border-yellow-200 h-full">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/50 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      <textarea
        value={notes}
        onChange={(e) => setNotes(e.target.value)}
        className="w-full h-full bg-transparent border-none resize-none focus:ring-0 text-sm"
        placeholder="اكتب ملاحظاتك هنا..."
      />
    </div>
  );
}

/**
 * Shortcuts Widget
 */
export function ShortcutsWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'اختصارات';
  const links = displayConfig.links || [];

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      <div className="text-sm font-medium text-gray-700 mb-3">{title}</div>
      <div className="grid grid-cols-2 gap-2">
        {links.map((link: any, idx: number) => (
          <a
            key={idx}
            href={link.url}
            className="p-2 bg-gray-50 rounded hover:bg-gray-100 text-center text-sm transition-colors"
          >
            <div className="text-lg mb-1">{link.icon || '📌'}</div>
            <div>{link.label_ar || link.label}</div>
          </a>
        ))}
        {links.length === 0 && (
          <div className="col-span-2 text-center text-gray-400 py-4">لا توجد اختصارات</div>
        )}
      </div>
    </div>
  );
}

/**
 * Quick Actions Widget
 */
export function QuickActionsWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'إجراءات سريعة';
  const actions = displayConfig.actions || [];

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      <div className="text-sm font-medium text-gray-700 mb-3">{title}</div>
      <div className="grid grid-cols-2 gap-2">
        {actions.map((action: any, idx: number) => (
          <button
            key={idx}
            onClick={() => window.location.href = action.url}
            className="p-3 bg-blue-50 hover:bg-blue-100 rounded text-center text-sm transition-colors"
          >
            <div className="text-lg mb-1">{action.icon || '🎯'}</div>
            <div className="font-medium text-blue-900">{action.label_ar || action.label}</div>
          </button>
        ))}
        {actions.length === 0 && (
          <div className="col-span-2 text-center text-gray-400 py-4">لا توجد إجراءات</div>
        )}
      </div>
    </div>
  );
}

/**
 * Announcements Widget
 */
export function AnnouncementsWidget({ widget, data, isLoading, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const title = displayConfig.title || 'إعلانات';
  const limit = displayConfig.limit || 5;
  const announcements = data?.data || [];

  return (
    <div className="relative p-4 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-gray-100 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      <div className="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
        <span>📢</span>
        {title}
      </div>
      <div className="space-y-2">
        {announcements.slice(0, limit).map((announcement: any, idx: number) => (
          <div key={idx} className="p-3 bg-blue-50 rounded border border-blue-100">
            <div className="text-sm font-medium text-blue-900">{announcement.title}</div>
            <div className="text-xs text-blue-700 mt-1">{announcement.content}</div>
            {displayConfig.show_date && announcement.date && (
              <div className="text-xs text-blue-500 mt-2">{new Date(announcement.date).toLocaleDateString('ar-IQ')}</div>
            )}
          </div>
        ))}
        {announcements.length === 0 && (
          <div className="text-center text-gray-400 py-4">لا توجد إعلانات</div>
        )}
      </div>
    </div>
  );
}
