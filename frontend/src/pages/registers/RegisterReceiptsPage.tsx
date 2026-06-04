import { useMemo, useState, useCallback, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useRegister, useRegisterFields } from '@/hooks/useRegisters';
import { useReceipts } from '@/hooks/useReceipts';
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
import type { RegisterField } from '@/types/register';
import type { ColDef } from 'ag-grid-community';

interface RowData {
  index: number | string;
  receipt: Receipt;
  [key: string]: unknown;
}

function makeFieldKey(name: string): string {
  return name.replace(/\s+/g, '_').replace(/[^\w\u0600-\u06FF]/g, '_');
}

function getItemValue(
  receipt: Receipt,
  fieldName: string,
  isFinancial: boolean
): string | number {
  const item = receipt.items?.find((i) => i.field_name_snapshot === fieldName);
  if (!item) return isFinancial ? 0 : '';
  if (isFinancial) return parseFloat(item.amount ?? '0') || 0;
  return item.text_value ?? '';
}

function isOptionSelected(
  receipt: Receipt,
  fieldName: string,
  optionValue: string
): boolean {
  const item = receipt.items?.find((i) => i.field_name_snapshot === fieldName);
  return item?.text_value === optionValue;
}

export default function RegisterReceiptsPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { can } = usePermissions();
  const [showColumnMenu, setShowColumnMenu] = useState(false);
  const columnMenuRef = useRef<HTMLDivElement>(null);

  const [filters, setFilters] = useState({
    status: '',
    date_from: '',
    date_to: '',
    search: '',
  });

  const { data: register, isLoading: registerLoading } = useRegister(id!);
  const { data: fields, isLoading: fieldsLoading } = useRegisterFields(id!);
  const { data: receiptsData, isLoading: receiptsLoading } = useReceipts({
    register_id: id,
    per_page: 1000,
  });

  const receipts = (receiptsData as unknown as Receipt[]) || [];

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (
        columnMenuRef.current &&
        !columnMenuRef.current.contains(e.target as Node)
      ) {
        setShowColumnMenu(false);
      }
    }
    if (showColumnMenu) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [showColumnMenu]);

  const filteredReceipts = useMemo(() => {
    return receipts.filter((r) => {
      if (filters.status && r.status !== filters.status) return false;
      if (filters.date_from && new Date(r.created_at) < new Date(filters.date_from))
        return false;
      if (filters.date_to && new Date(r.created_at) > new Date(filters.date_to + 'T23:59:59'))
        return false;
      if (
        filters.search &&
        !r.receipt_number.toLowerCase().includes(filters.search.toLowerCase())
      )
        return false;
      return true;
    });
  }, [receipts, filters]);

  const rowData: RowData[] = useMemo(() => {
    return filteredReceipts.map((receipt, idx) => {
      const row: RowData = { index: idx + 1, receipt };
      fields?.forEach((field) => {
        if (
          field.field_type === 'select' &&
          field.options &&
          field.options.length > 0
        ) {
          field.options.forEach((option) => {
            const key = `select_${makeFieldKey(field.name)}_${makeFieldKey(
              option.value
            )}`;
            if (isOptionSelected(receipt, field.name, option.value)) {
              row[key] = parseFloat(receipt.total_amount) || 0;
            } else {
              row[key] = '';
            }
          });
        } else {
          row[`field_${makeFieldKey(field.name)}`] = getItemValue(
            receipt,
            field.name,
            field.is_financial
          );
        }
      });
      return row;
    });
  }, [filteredReceipts, fields]);

  const [hiddenCols, setHiddenCols] = useState<Set<string>>(new Set());

  const toggleColumn = (colId: string) => {
    setHiddenCols((prev) => {
      const next = new Set(prev);
      if (next.has(colId)) next.delete(colId);
      else next.add(colId);
      return next;
    });
  };

  const pinnedBottomRowData: RowData[] = useMemo(() => {
    const totalAmount = filteredReceipts.reduce(
      (sum, r) => sum + (parseFloat(r.total_amount) || 0),
      0
    );
    const row: RowData = {
      index: '',
      receipt: {
        id: '',
        receipt_number: 'الإجمالي',
        register_id: id!,
        register: {} as any,
        created_by: {} as any,
        total_amount: String(totalAmount),
        status: '' as any,
        version: 0,
        notes: null,
        qr_payload: null,
        printed_at: null,
        cancelled_at: null,
        cancel_reason: null,
        items: [],
        created_at: '',
        updated_at: '',
      },
    };
    fields?.forEach((field) => {
      if (
        field.field_type === 'select' &&
        field.options &&
        field.options.length > 0
      ) {
        field.options.forEach((option) => {
          const key = `select_${makeFieldKey(field.name)}_${makeFieldKey(
            option.value
          )}`;
          const total = filteredReceipts.reduce((sum, r) => {
            if (isOptionSelected(r, field.name, option.value)) {
              return sum + (parseFloat(r.total_amount) || 0);
            }
            return sum;
          }, 0);
          row[key] = total || '';
        });
      } else if (field.is_financial) {
        const key = `field_${makeFieldKey(field.name)}`;
        const total = filteredReceipts.reduce(
          (sum, r) =>
            sum + (parseFloat(String(getItemValue(r, field.name, true))) || 0),
          0
        );
        row[key] = total;
      } else {
        row[`field_${makeFieldKey(field.name)}`] = '';
      }
    });
    return [row];
  }, [filteredReceipts, fields, id]);

  const columnDefs = useMemo<ColDef<RowData>[]>(() => {
    const cols: ColDef<RowData>[] = [
      {
        colId: 'index',
        field: 'index',
        headerName: '#',
        width: 60,
        sortable: false,
        cellStyle: { textAlign: 'center' },
      },
      {
        colId: 'receipt_number',
        field: 'receipt.receipt_number',
        headerName: 'رقم الوصل',
        hide: hiddenCols.has('receipt_number'),
        cellRenderer: (p: any) => {
          if (p.value === 'الإجمالي') {
            return <span className="font-bold text-gray-800">{p.value}</span>;
          }
          return (
            <button
              className="text-blue-600 hover:underline font-mono"
              onClick={() => navigate(`/receipts/${p.data.receipt.id}`)}
            >
              {p.value}
            </button>
          );
        },
      },
      {
        colId: 'created_at',
        field: 'receipt.created_at',
        headerName: 'التاريخ',
        hide: hiddenCols.has('created_at'),
        valueFormatter: (p: any) => (p.value ? formatDateTime(p.value) : ''),
        width: 160,
      },
      {
        colId: 'status',
        field: 'receipt.status',
        headerName: 'الحالة',
        hide: hiddenCols.has('status'),
        cellRenderer: (p: any) =>
          p.value ? <ReceiptStatusBadge status={p.value} /> : null,
        width: 120,
      },
      {
        colId: 'total_amount',
        field: 'receipt.total_amount',
        headerName: 'الإجمالي',
        hide: hiddenCols.has('total_amount'),
        valueGetter: (p: any) => parseFloat(p.data.receipt.total_amount) || 0,
        valueFormatter: (p: any) => formatCurrency(p.value),
        cellClass: 'font-bold',
        width: 140,
      },
    ];

    fields?.forEach((field: RegisterField) => {
      if (
        field.field_type === 'select' &&
        field.options &&
        field.options.length > 0
      ) {
        field.options.forEach((option) => {
          const colId = `select_${makeFieldKey(field.name)}_${makeFieldKey(
            option.value
          )}`;
          cols.push({
            colId,
            headerName: option.label_ar,
            field: colId,
            hide: hiddenCols.has(colId),
            valueFormatter: (p: any) => {
              const val = p.value;
              if (val === '' || val == null) return '';
              return formatCurrency(val);
            },
            width: 120,
            cellStyle: { textAlign: 'center' },
          });
        });
      } else {
        const colId = `field_${makeFieldKey(field.name)}`;
        cols.push({
          colId,
          headerName: field.label_ar,
          field: colId,
          hide: hiddenCols.has(colId),
          valueFormatter: (p: any) => {
            if (field.is_financial) return formatCurrency(p.value);
            return p.value ?? '';
          },
          width: 140,
        });
      }
    });

    return cols;
  }, [fields, hiddenCols, navigate]);

  const toggleableColumns = useMemo(() => {
    const list: { colId: string; label: string }[] = [
      { colId: 'receipt_number', label: 'رقم الوصل' },
      { colId: 'created_at', label: 'التاريخ' },
      { colId: 'status', label: 'الحالة' },
      { colId: 'total_amount', label: 'الإجمالي' },
    ];
    fields?.forEach((f) => {
      if (f.field_type === 'select' && f.options && f.options.length > 0) {
        f.options.forEach((o) => {
          list.push({
            colId: `select_${makeFieldKey(f.name)}_${makeFieldKey(o.value)}`,
            label: o.label_ar,
          });
        });
      } else {
        list.push({ colId: `field_${makeFieldKey(f.name)}`, label: f.label_ar });
      }
    });
    return list;
  }, [fields]);

  const exportCSV = useCallback(() => {
    if (rowData.length === 0) return;

    const visibleCols = columnDefs.filter((c) => !c.hide);
    const headers = visibleCols
      .map((c) => c.headerName || c.field || '')
      .join(',');

    const getCellValue = (row: RowData, col: ColDef<RowData>): string => {
      let value: unknown = '';
      if (col.field) {
        const parts = col.field.split('.');
        let obj: any = row;
        for (const part of parts) {
          obj = obj?.[part];
          if (obj === undefined || obj === null) break;
        }
        value = obj ?? '';
      }
      return String(value ?? '');
    };

    const rows = rowData.map((row) => {
      return visibleCols
        .map((col) => {
          const val = getCellValue(row, col);
          return `"${val.replace(/"/g, '""')}"`;
        })
        .join(',');
    });

    const totalsRow = pinnedBottomRowData[0];
    if (totalsRow) {
      const totalsCSV = visibleCols
        .map((col) => {
          const val = getCellValue(totalsRow, col);
          return `"${val.replace(/"/g, '""')}"`;
        })
        .join(',');
      rows.push(totalsCSV);
    }

    const csvContent = '\uFEFF' + [headers, ...rows].join('\n');
    const blob = new Blob([csvContent], {
      type: 'text/csv;charset=utf-8;',
    });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `وصولات_${register?.name_ar || 'سجل'}_${new Date()
      .toISOString()
      .slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }, [rowData, columnDefs, pinnedBottomRowData, register]);

  const isLoading = registerLoading || fieldsLoading || receiptsLoading;

  const totalAmountSum = useMemo(
    () =>
      filteredReceipts.reduce(
        (s, r) => s + (parseFloat(r.total_amount) || 0),
        0
      ),
    [filteredReceipts]
  );

  if (!can('view-registers')) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-gray-500">
        <p className="text-xl font-bold">غير مصرح</p>
        <p className="text-sm">ليس لديك صلاحية الوصول إلى وصولات السجل</p>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={`وصولات السجل: ${register?.name_ar || '...'}`}>
        <Button onClick={() => navigate(`/receipts/create?register_id=${id}`)}>
          وصل جديد
        </Button>
        <Button variant="secondary" onClick={exportCSV}>
          تصدير CSV
        </Button>
        <div className="relative" ref={columnMenuRef}>
          <Button
            variant="ghost"
            onClick={() => setShowColumnMenu((v) => !v)}
          >
            إخفاء الأعمدة ▼
          </Button>
          {showColumnMenu && (
            <div className="absolute left-0 top-full z-50 mt-1 w-56 rounded-lg border bg-white p-2 shadow-lg">
              <p className="mb-2 text-xs font-semibold text-gray-500">
                إظهار / إخفاء
              </p>
              <div className="max-h-60 space-y-1 overflow-y-auto">
                {toggleableColumns.map((col) => (
                  <label
                    key={col.colId}
                    className="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-gray-50"
                  >
                    <input
                      type="checkbox"
                      checked={!hiddenCols.has(col.colId)}
                      onChange={() => toggleColumn(col.colId)}
                    />
                    <span className="text-sm">{col.label}</span>
                  </label>
                ))}
              </div>
              <div className="mt-2 border-t pt-2">
                <button
                  className="w-full text-xs text-blue-600 hover:underline"
                  onClick={() => setHiddenCols(new Set())}
                >
                  إظهار الكل
                </button>
              </div>
            </div>
          )}
        </div>
        <Button variant="ghost" onClick={() => navigate(`/registers/${id}`)}>
          العودة للسجل
        </Button>
      </PageHeader>

      {/* Filters */}
      <div className="rounded-lg bg-white p-4 shadow mb-4">
        <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
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
          <Input
            label="من تاريخ"
            type="date"
            value={filters.date_from}
            onChange={(e) =>
              setFilters({ ...filters, date_from: e.target.value })
            }
          />
          <Input
            label="إلى تاريخ"
            type="date"
            value={filters.date_to}
            onChange={(e) =>
              setFilters({ ...filters, date_to: e.target.value })
            }
          />
          <Input
            label="بحث"
            value={filters.search}
            onChange={(e) =>
              setFilters({ ...filters, search: e.target.value })
            }
            placeholder="رقم الوصل أو اسم الشخص..."
          />
        </div>
      </div>

      {/* Stats cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div className="rounded-lg bg-white p-4 shadow text-center">
          <p className="text-sm text-gray-500">عدد الوصولات</p>
          <p className="text-2xl font-bold text-blue-600">
            {filteredReceipts.length}
          </p>
        </div>
        <div className="rounded-lg bg-white p-4 shadow text-center">
          <p className="text-sm text-gray-500">إجمالي المبالغ</p>
          <p className="text-2xl font-bold text-green-600">
            {formatCurrency(totalAmountSum)}
          </p>
        </div>
        <div className="rounded-lg bg-white p-4 shadow text-center">
          <p className="text-sm text-gray-500">مرحل</p>
          <p className="text-2xl font-bold text-purple-600">
            {filteredReceipts.filter((r) => r.status === 'issued').length}
          </p>
        </div>
        <div className="rounded-lg bg-white p-4 shadow text-center">
          <p className="text-sm text-gray-500">ملغى</p>
          <p className="text-2xl font-bold text-red-600">
            {filteredReceipts.filter((r) => r.status === 'cancelled').length}
          </p>
        </div>
      </div>

      {/* Table */}
      <div className="rounded-lg bg-white p-4 shadow">
        {isLoading ? (
          <div className="flex h-48 items-center justify-center text-gray-500">
            جاري التحميل...
          </div>
        ) : filteredReceipts.length === 0 ? (
          <div className="flex h-48 flex-col items-center justify-center text-gray-500">
            <p className="text-lg font-medium">لا توجد وصولات</p>
            <p className="text-sm">
              لم يتم إصدار أي وصولات لهذا السجل بعد
            </p>
          </div>
        ) : (
          <DataTable
            rowData={rowData}
            columnDefs={columnDefs}
            loading={isLoading}
            pinnedBottomRowData={pinnedBottomRowData}
          />
        )}
      </div>
    </div>
  );
}
