import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useReceipts } from '@/hooks/useReceipts';
import { useRegisters } from '@/hooks/useRegisters';
import { usePermissions } from '@/hooks/usePermissions';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { DataTable } from '@/components/ui/DataTable';
import { ReceiptStatusBadge } from '@/components/receipt/ReceiptStatusBadge';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import type { Receipt } from '@/types/receipt';
import type { ColDef } from 'ag-grid-community';

export default function ReceiptListPage() {
  const navigate = useNavigate();
  const { can } = usePermissions();
  const { data: registersData } = useRegisters();

  const [filters, setFilters] = useState({
    register_id: '',
    status: '',
    date_from: '',
    date_to: '',
    search: '',
  });

  const { data, isLoading } = useReceipts(filters);
  const receipts = (data as unknown as Receipt[]) || [];

  const columnDefs: ColDef<Receipt>[] = [
    { field: 'receipt_number', headerName: 'رقم الوصل', cellRenderer: (p: any) => (
      <button className="text-blue-600 hover:underline font-mono" onClick={() => navigate(`/receipts/${p.data.id}`)}>{p.value}</button>
    )},
    { field: 'register.name_ar', headerName: 'السجل' },
    { field: 'total_amount', headerName: 'المبلغ', valueFormatter: (p: any) => formatCurrency(p.value) },
    { field: 'status', headerName: 'الحالة', cellRenderer: (p: any) => <ReceiptStatusBadge status={p.value} /> },
    { field: 'created_by.name', headerName: 'أمين الصندوق' },
    { field: 'created_at', headerName: 'التاريخ', valueFormatter: (p: any) => formatDateTime(p.value) },
  ];

  return (
    <div>
      <PageHeader title="قائمة الوصولات">
        {can('create-receipt') && <Button onClick={() => navigate('/receipts/create')}>وصل جديد</Button>}
      </PageHeader>

      <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
        <Select
          label="السجل"
          options={[{ value: '', label: 'الكل' }, ...(registersData || []).map((r) => ({ value: r.id, label: r.name_ar }))]}
          value={filters.register_id}
          onChange={(e) => setFilters({ ...filters, register_id: e.target.value })}
        />
        <Select
          label="الحالة"
          options={[
            { value: '', label: 'الكل' },
            { value: 'draft', label: 'مسودة' },
            { value: 'pending', label: 'معلقة' },
            { value: 'issued', label: 'مرحل' },
            { value: 'printed', label: 'مطبوع' },
            { value: 'cancelled', label: 'ملغى' },
          ]}
          value={filters.status}
          onChange={(e) => setFilters({ ...filters, status: e.target.value })}
        />
        <Input label="من" type="date" value={filters.date_from} onChange={(e) => setFilters({ ...filters, date_from: e.target.value })} />
        <Input label="إلى" type="date" value={filters.date_to} onChange={(e) => setFilters({ ...filters, date_to: e.target.value })} />
        <Input label="بحث" value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value })} />
      </div>

      <DataTable rowData={receipts} columnDefs={columnDefs} loading={isLoading} />
    </div>
  );
}
