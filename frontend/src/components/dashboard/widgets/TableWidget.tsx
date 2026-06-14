import { useWidgetData } from '../hooks/useWidgetData';
import type { DashboardWidgetItem } from '../types';

interface TableWidgetProps {
  widget: DashboardWidgetItem;
}

export default function TableWidget({ widget }: TableWidgetProps) {
  const { data, isLoading } = useWidgetData(widget);
  const rows = Array.isArray(data?.rows) ? data.rows : Array.isArray(data?.data?.rows) ? data.data.rows : [];
  const fields = Array.isArray(data?.fields)
    ? data.fields
    : Array.isArray(data?.data?.fields)
    ? data.data.fields
    : null;

  const columns =
    fields && fields.length > 0
      ? fields.map((field: any) => ({
          key: typeof field === 'string' ? field : field.key || field.field || field.name,
          label: typeof field === 'string' ? field : field.label || field.key || field.field || field.name,
        }))
      : rows.length > 0
      ? Object.keys(rows[0]).map((key) => ({ key, label: key }))
      : [];

  return (
    <div className="h-full w-full overflow-auto p-2">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((col: { key: string; label: string }) => (
              <th
                key={col.key}
                className="px-3 py-2 text-right font-medium text-gray-700"
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200 bg-white">
          {isLoading ? (
            <tr>
              <td colSpan={columns.length || 1} className="px-3 py-4 text-center text-gray-500">
                جاري التحميل...
              </td>
            </tr>
          ) : rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length || 1} className="px-3 py-4 text-center text-gray-500">
                لا توجد بيانات
              </td>
            </tr>
          ) : (
            rows.map((row: Record<string, any>, rowIndex: number) => (
              <tr key={rowIndex}>
                {columns.map((col: { key: string; label: string }) => (
                  <td key={col.key} className="px-3 py-2 text-gray-900">
                    {String(row[col.key] ?? '')}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
