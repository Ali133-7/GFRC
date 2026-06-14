import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { X } from 'lucide-react';
import { GovSelect } from '@/components/ui/GovSelect';
import { dashboardWidgetsApi } from '@/api/dashboardWidgets';
import type { DashboardWidgetItem, WidgetType } from './types';
import { getWidgetTitle } from './types';
import { COLOR_OPTIONS } from './widgetDefaults';

interface WidgetEditorModalProps {
  widget: DashboardWidgetItem | null;
  open: boolean;
  onClose: () => void;
  onSave: (widget: DashboardWidgetItem) => void;
}

const DATA_WIDGET_TYPES: WidgetType[] = [
  'stat_card',
  'chart_bar',
  'chart_line',
  'chart_pie',
  'table',
  'progress',
  'gauge',
];

const AGGREGATION_OPTIONS = [
  { value: 'count', label: 'العدد' },
  { value: 'sum', label: 'المجموع' },
  { value: 'avg', label: 'المتوسط' },
  { value: 'min', label: 'الأدنى' },
  { value: 'max', label: 'الأعلى' },
];

const FORMAT_OPTIONS = [
  { value: 'number', label: 'رقم' },
  { value: 'currency', label: 'عملة' },
];

const GROUP_BY_OPTIONS = [
  { value: 'period', label: 'فترة' },
  { value: 'field', label: 'حقل' },
];

const PERIOD_OPTIONS = [
  { value: 'day', label: 'يوم' },
  { value: 'week', label: 'أسبوع' },
  { value: 'month', label: 'شهر' },
  { value: 'year', label: 'سنة' },
];

export default function WidgetEditorModal({
  widget,
  open,
  onClose,
  onSave,
}: WidgetEditorModalProps) {
  const [title, setTitle] = useState('');
  const [color, setColor] = useState('blue');
  const [registerId, setRegisterId] = useState('');
  const [fieldId, setFieldId] = useState('');
  const [aggregation, setAggregation] = useState('count');
  const [groupBy, setGroupBy] = useState('period');
  const [groupField, setGroupField] = useState('');
  const [period, setPeriod] = useState('month');
  const [content, setContent] = useState('');
  const [url, setUrl] = useState('');
  const [videoId, setVideoId] = useState('');
  const [lat, setLat] = useState('33.3152');
  const [lon, setLon] = useState('44.3661');
  const [target, setTarget] = useState('100');
  const [format, setFormat] = useState('number');
  const [calendar, setCalendar] = useState('gregorian');
  const [timeFormat, setTimeFormat] = useState('24h');
  const [showDate, setShowDate] = useState(true);
  const [fields, setFields] = useState('');
  const [perPage, setPerPage] = useState('10');
  const [sortBy, setSortBy] = useState('');
  const [sortOrder, setSortOrder] = useState('asc');

  const type: WidgetType | undefined = widget?.widget_type;
  const isDataWidget = type ? DATA_WIDGET_TYPES.includes(type) : false;

  useEffect(() => {
    if (!widget) return;
    const titleValue = getWidgetTitle(widget);
    setTitle(titleValue);
    setColor(widget.color_theme || (widget.display_config?.color as string) || 'blue');

    const ds = widget.data_source || {};
    const dc = widget.display_config || {};

    setRegisterId(String(ds.register_id || ''));
    setFieldId(String(ds.field || ds.field_id || ''));
    setAggregation(String(ds.aggregation || ''));
    setGroupBy(String(ds.group_by || ''));
    setGroupField(String(ds.group_field || ''));
    setPeriod(String(ds.period || ''));
    setContent(String(ds.content || ''));
    setUrl(String(ds.url || dc.url || ''));
    setVideoId(String(ds.video_id || ''));
    setLat(String(ds.location?.lat ?? '33.3152'));
    setLon(String(ds.location?.lon ?? '44.3661'));
    setTarget(String(ds.target ?? '100'));
    setFormat(String(dc.format || ds.format || 'number'));
    setCalendar(String(ds.calendar || 'gregorian'));
    setTimeFormat(String(ds.timeFormat || ds.format || '24h'));
    setShowDate(ds.show_date !== false);
    setFields(Array.isArray(ds.fields) ? ds.fields.join(',') : '');
    setPerPage(String(ds.per_page ?? '10'));
    setSortBy(String(ds.sort_by || ''));
    setSortOrder(String(ds.sort_order || 'asc'));
  }, [widget]);

  const { data: registers = [] } = useQuery({
    queryKey: ['dashboard', 'registers'],
    queryFn: dashboardWidgetsApi.getAvailableRegisters,
    enabled: open && isDataWidget,
  });

  const { data: registerFields = [] } = useQuery({
    queryKey: ['dashboard', 'registers', registerId, 'fields'],
    queryFn: () => dashboardWidgetsApi.getRegisterFields(registerId),
    enabled: open && isDataWidget && !!registerId,
  });

  const registerOptions = useMemo(
    () => registers.map((r) => ({ value: r.id, label: r.name_ar || r.name || r.name_en || r.id })),
    [registers]
  );

  const fieldOptions = useMemo(
    () => registerFields.map((f) => ({ value: f.name, label: `${f.label_ar || f.label || f.name} (${f.name})` })),
    [registerFields]
  );

  const colorOptions = useMemo(
    () => COLOR_OPTIONS.map((c) => ({ value: c.value, label: c.label })),
    []
  );

  const handleSave = () => {
    if (!widget) return;

    const nextDataSource: Record<string, any> = {};
    const nextDisplayConfig: Record<string, any> = { color };

    if (isDataWidget) {
      nextDataSource.register_id = registerId;
      nextDataSource.field = fieldId;
      nextDataSource.aggregation = aggregation;
      nextDataSource.filters = widget.data_source?.filters || {};
    }

    if (type === 'stat_card') {
      nextDisplayConfig.format = format;
    }

    if (type === 'chart_bar' || type === 'chart_line' || type === 'chart_pie') {
      nextDataSource.group_by = groupBy;
      nextDataSource.group_field = groupField;
      nextDataSource.period = period;
    }

    if (type === 'table') {
      nextDataSource.fields = fields
        .split(',')
        .map((c) => c.trim())
        .filter(Boolean);
      nextDataSource.per_page = Number(perPage) || 10;
      nextDataSource.sort_by = sortBy;
      nextDataSource.sort_order = sortOrder;
    }

    if (type === 'progress' || type === 'gauge') {
      nextDataSource.target = Number(target) || 100;
    }

    if (type === 'text_block') {
      nextDataSource.content = content;
    }

    if (type === 'iframe') {
      nextDataSource.url = url;
    }

    if (type === 'youtube_audio') {
      nextDataSource.video_id = videoId || url;
      nextDataSource.loop = false;
      nextDataSource.autoplay = false;
    }

    if (type === 'weather') {
      nextDataSource.provider = 'open_meteo';
      nextDataSource.location = {
        lat: Number(lat) || 33.3152,
        lon: Number(lon) || 44.3661,
        name: widget.data_source?.location?.name || '',
      };
    }

    if (type === 'clock') {
      nextDataSource.calendar = calendar;
      nextDataSource.format = timeFormat;
      nextDataSource.show_date = showDate;
      nextDataSource.timezone = widget.data_source?.timezone || 'Asia/Baghdad';
    }

    const nextTitle = typeof widget.title === 'object' ? { ...widget.title, ar: title.trim() || widget.title.ar } : title.trim() || widget.title;

    onSave({
      ...widget,
      title: nextTitle,
      color_theme: color as any,
      data_source: nextDataSource,
      display_config: nextDisplayConfig,
    });
    onClose();
  };

  if (!open || !widget) return null;

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
      <div className="max-h-[90vh] w-full max-w-lg overflow-auto rounded-lg bg-white p-6 shadow-xl" dir="rtl">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-bold text-gray-900">تعديل الودجت</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded p-1 text-gray-500 hover:bg-gray-100"
          >
            <X size={20} />
          </button>
        </div>

        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">العنوان</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <GovSelect
            label="اللون"
            value={color}
            onValueChange={setColor}
            options={colorOptions}
          />

          {isDataWidget && (
            <>
              <GovSelect
                label="السجل"
                value={registerId}
                onValueChange={(value) => {
                  setRegisterId(value);
                  setFieldId('');
                }}
                options={registerOptions}
                placeholder="اختر السجل..."
              />

              <GovSelect
                label="الحقل"
                value={fieldId}
                onValueChange={setFieldId}
                options={fieldOptions}
                placeholder="اختر الحقل..."
                disabled={!registerId}
              />

              <GovSelect
                label="التجميع"
                value={aggregation}
                onValueChange={setAggregation}
                options={AGGREGATION_OPTIONS}
              />
            </>
          )}

          {type === 'stat_card' && (
            <GovSelect
              label="التنسيق"
              value={format}
              onValueChange={setFormat}
              options={FORMAT_OPTIONS}
            />
          )}

          {(type === 'chart_bar' || type === 'chart_line' || type === 'chart_pie') && (
            <>
              <GovSelect
                label="التجميع حسب"
                value={groupBy}
                onValueChange={setGroupBy}
                options={GROUP_BY_OPTIONS}
              />
              {groupBy === 'field' && (
                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">حقل التجميع</label>
                  <input
                    type="text"
                    value={groupField}
                    onChange={(e) => setGroupField(e.target.value)}
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </div>
              )}
              {groupBy === 'period' && (
                <GovSelect
                  label="الفترة"
                  value={period}
                  onValueChange={setPeriod}
                  options={PERIOD_OPTIONS}
                />
              )}
            </>
          )}

          {type === 'table' && (
            <>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">الأعمدة (مفصولة بفاصلة)</label>
                <input
                  type="text"
                  value={fields}
                  onChange={(e) => setFields(e.target.value)}
                  placeholder="مثال: name, amount, date"
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">عدد الصفوف في الصفحة</label>
                <input
                  type="number"
                  value={perPage}
                  onChange={(e) => setPerPage(e.target.value)}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </>
          )}

          {(type === 'progress' || type === 'gauge') && (
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">الهدف</label>
              <input
                type="number"
                value={target}
                onChange={(e) => setTarget(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          )}

          {type === 'text_block' && (
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">المحتوى</label>
              <textarea
                value={content}
                onChange={(e) => setContent(e.target.value)}
                rows={5}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          )}

          {type === 'iframe' && (
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">عنوان URL</label>
              <input
                type="text"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          )}

          {type === 'youtube_audio' && (
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">معرف الفيديو</label>
              <input
                type="text"
                value={videoId}
                onChange={(e) => setVideoId(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          )}

          {type === 'weather' && (
            <>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">خط العرض</label>
                <input
                  type="number"
                  value={lat}
                  onChange={(e) => setLat(e.target.value)}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">خط الطول</label>
                <input
                  type="number"
                  value={lon}
                  onChange={(e) => setLon(e.target.value)}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </>
          )}

          {type === 'clock' && (
            <div className="space-y-2">
              <GovSelect
                label="التقويم"
                value={calendar}
                onValueChange={setCalendar}
                options={[
                  { value: 'gregorian', label: 'ميلادي' },
                  { value: 'hijri', label: 'هجري' },
                ]}
              />
              <GovSelect
                label="تنسيق الوقت"
                value={timeFormat}
                onValueChange={setTimeFormat}
                options={[
                  { value: '24h', label: '24 ساعة' },
                  { value: '12h', label: '12 ساعة' },
                ]}
              />
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={showDate}
                  onChange={(e) => setShowDate(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300 text-blue-600"
                />
                <span className="text-sm text-gray-700">عرض التاريخ</span>
              </label>
            </div>
          )}
        </div>

        <div className="mt-6 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            إلغاء
          </button>
          <button
            type="button"
            onClick={handleSave}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
          >
            حفظ
          </button>
        </div>
      </div>
    </div>
  );
}
