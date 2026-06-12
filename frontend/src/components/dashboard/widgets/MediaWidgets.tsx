// Media & Advanced Widgets
import React, { useEffect, useState } from 'react';
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
 * Clock Widget
 */
export function ClockWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const [time, setTime] = useState(new Date());
  const displayConfig = widget.display_config || {};
  const showSeconds = displayConfig.show_seconds ?? true;
  const showDate = displayConfig.show_date ?? true;

  useEffect(() => {
    const timer = setInterval(() => setTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  return (
    <div className="relative p-4 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg text-white h-full flex flex-col items-center justify-center">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/20 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      <div className="text-4xl font-bold mb-2">
        {time.toLocaleTimeString('en-US', { 
          hour12: true,
          hour: '2-digit',
          minute: '2-digit',
          second: showSeconds ? '2-digit' : undefined
        })}
      </div>
      {showDate && (
        <div className="text-sm opacity-90">
          {time.toLocaleDateString('ar-IQ', { 
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          })}
        </div>
      )}
    </div>
  );
}

/**
 * Calendar Widget
 */
export function CalendarWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const [currentDate, setCurrentDate] = useState(new Date());
  const displayConfig = widget.display_config || {};
  const showEvents = displayConfig.show_events ?? true;

  const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();
  const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();

  const monthNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

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
      
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <button onClick={() => setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1))} className="p-1 hover:bg-gray-100 rounded">◀</button>
        <div className="font-bold text-gray-900">
          {monthNames[currentDate.getMonth()]} {currentDate.getFullYear()}
        </div>
        <button onClick={() => setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1))} className="p-1 hover:bg-gray-100 rounded">▶</button>
      </div>

      {/* Days grid */}
      <div className="grid grid-cols-7 gap-1 text-center text-sm">
        {['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'].map(day => (
          <div key={day} className="text-xs text-gray-500 py-1">{day}</div>
        ))}
        
        {Array.from({ length: firstDayOfMonth }).map((_, i) => (
          <div key={`empty-${i}`} className="py-2"></div>
        ))}
        
        {Array.from({ length: daysInMonth }).map((_, i) => {
          const day = i + 1;
          const isToday = day === new Date().getDate() && 
                         currentDate.getMonth() === new Date().getMonth() &&
                         currentDate.getFullYear() === new Date().getFullYear();
          
          return (
            <div
              key={day}
              className={`py-2 rounded ${
                isToday ? 'bg-blue-600 text-white font-bold' : 'hover:bg-gray-100'
              }`}
            >
              {day}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/**
 * Image Widget
 */
export function ImageWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const imageUrl = displayConfig.image_url;
  const altText = displayConfig.alt_text || '';
  const fit = displayConfig.fit || 'contain';

  return (
    <div className="relative p-2 bg-white rounded-lg border border-gray-200 h-full overflow-hidden">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity z-10">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/90 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      {imageUrl ? (
        <img
          src={imageUrl}
          alt={altText}
          className="w-full h-full object-cover rounded"
          style={{ objectFit: fit as any }}
        />
      ) : (
        <div className="h-full flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-4xl mb-2">🖼️</div>
            <div className="text-sm">لا توجد صورة</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Video Widget
 */
export function VideoWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const videoUrl = displayConfig.video_url;
  const autoplay = displayConfig.autoplay || false;

  return (
    <div className="relative p-2 bg-white rounded-lg border border-gray-200">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity z-10">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/90 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      {videoUrl ? (
        <div className="aspect-video">
          <iframe
            src={videoUrl}
            title="Video"
            className="w-full h-full rounded"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          />
        </div>
      ) : (
        <div className="h-48 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-4xl mb-2">🎥</div>
            <div className="text-sm">لا يوجد فيديو</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * PDF Viewer Widget
 */
export function PdfViewerWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const pdfUrl = displayConfig.pdf_url;
  const showToolbar = displayConfig.show_toolbar ?? true;

  return (
    <div className="relative bg-white rounded-lg border border-gray-200 h-full overflow-hidden">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity z-10">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/90 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      {pdfUrl ? (
        <iframe
          src={pdfUrl}
          className="w-full h-full"
          title="PDF Viewer"
        />
      ) : (
        <div className="h-full flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-4xl mb-2">📄</div>
            <div className="text-sm">لا يوجد ملف PDF</div>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Website Embed Widget
 */
export function WebsiteEmbedWidget({ widget, canEdit, onEdit, onRemove }: WidgetProps) {
  const displayConfig = widget.display_config || {};
  const url = displayConfig.url;
  const height = displayConfig.height || 600;

  return (
    <div className="relative bg-white rounded-lg border border-gray-200 overflow-hidden">
      {(canEdit || onEdit || onRemove) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 hover:opacity-100 transition-opacity z-10">
          {onEdit && canEdit && (
            <button onClick={onEdit} className="p-1 hover:bg-white/90 rounded">✏️</button>
          )}
          {onRemove && canEdit && (
            <button onClick={onRemove} className="p-1 hover:bg-red-100 rounded text-red-600">🗑️</button>
          )}
        </div>
      )}
      {url ? (
        <iframe
          src={url}
          className="w-full"
          style={{ height: `${height}px` }}
          title="Embedded Website"
          sandbox="allow-scripts allow-same-origin"
        />
      ) : (
        <div className="h-48 flex items-center justify-center text-gray-400">
          <div className="text-center">
            <div className="text-4xl mb-2">🌐</div>
            <div className="text-sm">لا يوجد موقع مضمن</div>
          </div>
        </div>
      )}
    </div>
  );
}
