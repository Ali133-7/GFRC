import { useEffect, useState } from 'react';
import type { DashboardWidgetItem } from '../types';

interface ClockWidgetProps {
  widget: DashboardWidgetItem;
}

export default function ClockWidget({ widget }: ClockWidgetProps) {
  const [now, setNow] = useState(new Date());
  const ds = widget.data_source || {};
  const calendar = ds.calendar === 'hijri' ? 'hijri' : 'gregorian';
  const timeFormat = ds.format === '12h' ? '12h' : '24h';
  const showDate = ds.show_date !== false;

  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const timeFormatter = new Intl.DateTimeFormat('ar-IQ', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: timeFormat === '12h',
  });

  const gregorianFormatter = new Intl.DateTimeFormat('ar-IQ', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  const hijriFormatter = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <div className="flex h-full w-full flex-col items-center justify-center p-4 text-center">
      <div className="text-3xl font-bold text-gray-900">{timeFormatter.format(now)}</div>
      {showDate && (
        <div className="mt-2 text-sm text-gray-600">
          {calendar === 'hijri' ? hijriFormatter.format(now) : gregorianFormatter.format(now)}
        </div>
      )}
    </div>
  );
}
