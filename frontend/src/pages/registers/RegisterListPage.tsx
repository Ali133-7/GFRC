import { useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useRegisters, useUpdateRegister } from '@/hooks/useRegisters';
import { usePermissions } from '@/hooks/usePermissions';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';

import type { Register } from '@/types/register';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';

function StatusCell(params: ICellRendererParams<Register>) {
  const active = params.value;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
        active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
      }`}
    >
      {active ? 'مفعل' : 'معطل'}
    </span>
  );
}

function ActionsCell({
  params,
  onToggle,
  isProcessing,
  navigate,
}: {
  params: ICellRendererParams<Register>;
  onToggle: (row: Register) => void;
  isProcessing: boolean;
  navigate: ReturnType<typeof useNavigate>;
}) {
  const row = params.data!;
  const processing = isProcessing;
  return (
    <div className="flex items-center gap-1.5 h-full">
      <button
        className="rounded-md px-2.5 py-1 text-xs font-medium bg-blue-600 text-white hover:bg-blue-700 transition-colors whitespace-nowrap"
        onClick={() => navigate(`/registers/${row.id}/template-designer`)}
        title="تصميم القالب"
      >
        🎨 تصميم
      </button>
      <button
        className={`rounded-md px-2.5 py-1 text-xs font-medium transition-colors whitespace-nowrap ${
          row.is_active
            ? 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200'
            : 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200'
        } ${processing ? 'opacity-50 cursor-not-allowed' : ''}`}
        disabled={processing}
        onClick={() => !processing && onToggle(row)}
      >
        {processing ? 'جاري...' : row.is_active ? 'إلغاء' : 'تفعيل'}
      </button>
    </div>
  );
}

export default function RegisterListPage() {
  const navigate = useNavigate();
  const { data, isLoading } = useRegisters();
  const update = useUpdateRegister();
  const { can } = usePermissions();

  const handleToggle = async (row: Register) => {
    try {
      await update.mutateAsync({
        id: row.id,
        payload: {
          code: row.code,
          name_ar: row.name_ar,
          fiscal_year: row.fiscal_year,
          is_active: !row.is_active,
        },
      });
    } catch (e: any) {
      alert(e?.response?.data?.message || 'حدث خطأ أثناء التحديث');
    }
  };

  const exportCSV = useCallback(() => {
    const rows = data || [];
    if (rows.length === 0) return;

    const headers = ['الكود', 'الاسم', 'السنة المالية', 'التسلسل', 'الحالة'];
    const csvRows = rows.map((r) => [
      r.code,
      r.name_ar,
      String(r.fiscal_year),
      String(r.current_sequence),
      r.is_active ? 'مفعل' : 'معطل',
    ]);

    const csvContent =
      '\uFEFF' +
      [headers.join(','), ...csvRows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `السجلات_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }, [data]);

  const columnDefs: ColDef<Register>[] = [
    {
      field: 'code',
      headerName: 'الكود',
      minWidth: 100,
      maxWidth: 140,
      flex: 0,
    },
    {
      field: 'name_ar',
      headerName: 'الاسم',
      minWidth: 200,
      flex: 2,
      cellRenderer: (params: ICellRendererParams<Register>) => (
        <button
          className="text-blue-600 hover:underline font-medium"
          onClick={() => navigate(`/registers/${params.data!.id}`)}
        >
          {params.value}
        </button>
      ),
    },
    {
      field: 'fiscal_year',
      headerName: 'السنة المالية',
      minWidth: 120,
      maxWidth: 140,
      flex: 0,
    },
    {
      field: 'current_sequence',
      headerName: 'التسلسل',
      minWidth: 100,
      maxWidth: 120,
      flex: 0,
    },
    {
      headerName: 'الحالة',
      field: 'is_active',
      minWidth: 100,
      maxWidth: 110,
      flex: 0,
      cellRenderer: StatusCell,
    },
  ];

  if (can('manage-registers')) {
    columnDefs.push({
      headerName: 'الإجراءات',
      minWidth: 200,
      maxWidth: 240,
      flex: 0,
      sortable: false,
      cellRenderer: (params: ICellRendererParams<Register>) => (
        <ActionsCell
          params={params}
          onToggle={handleToggle}
          isProcessing={update.isPending}
          navigate={navigate}
        />
      ),
    });
  }

  if (!can('view-registers')) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-gray-500">
        <p className="text-xl font-bold">غير مصرح</p>
        <p className="text-sm">ليس لديك صلاحية الوصول إلى السجلات</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <PageHeader title="السجلات">
        <Button variant="secondary" onClick={exportCSV}>
          تصدير CSV
        </Button>
        {can('manage-registers') && (
          <Button onClick={() => navigate('/registers/new')}>+ سجل جديد</Button>
        )}
      </PageHeader>
      <DataTable rowData={data || []} columnDefs={columnDefs} loading={isLoading} />
    </div>
  );
}
