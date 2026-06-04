import { AgGridReact } from 'ag-grid-react';
import { AllCommunityModule, ModuleRegistry, themeAlpine, type ColDef, type GridOptions } from 'ag-grid-community';
import { EmptyState } from './EmptyState';

ModuleRegistry.registerModules([AllCommunityModule]);

const arabicLocale = {
  pageSize: 'الحجم',
  page: 'صفحة',
  of: 'من',
  to: 'إلى',
  next: 'التالي',
  previous: 'السابق',
  first: 'الأولى',
  last: 'الأخيرة',
  noRowsToShow: 'لا توجد بيانات',
  loadingOoo: 'جاري التحميل...',
  filterOoo: 'بحث...',
  equals: 'يساوي',
  notEqual: 'لا يساوي',
  contains: 'يحتوي',
  notContains: 'لا يحتوي',
  startsWith: 'يبدأ بـ',
  endsWith: 'ينتهي بـ',
  blank: 'فارغ',
  notBlank: 'غير فارغ',
  and: 'و',
  or: 'أو',
  applyFilter: 'تطبيق',
  resetFilter: 'إعادة',
  clearFilter: 'مسح',
};

interface Props<T> {
  columnDefs: ColDef<T>[];
  rowData: T[];
  loading?: boolean;
  onRowClicked?: (row: T) => void;
  pageSize?: number;
  height?: number | string;
  domLayout?: 'normal' | 'autoHeight';
  pinnedBottomRowData?: T[];
}

export function DataTable<T extends object>({
  columnDefs,
  rowData,
  loading,
  onRowClicked,
  pageSize = 25,
  height = 500,
  domLayout = 'normal',
  pinnedBottomRowData,
}: Props<T>) {
  const defaultColDef: ColDef = {
    sortable: true,
    resizable: true,
    filter: false,
    minWidth: 100,
    cellStyle: {
      textAlign: 'right',
      fontFamily: "'Noto Sans Arabic', sans-serif",
      fontSize: '13px',
      direction: 'rtl',
    },
  };

  const gridOptions: GridOptions = {
    enableRtl: true,
    animateRows: true,
    rowSelection: { mode: 'singleRow' },
    suppressCellFocus: true,
    pagination: true,
    paginationPageSize: pageSize,
    paginationPageSizeSelector: [10, 25, 50, 100],
    localeText: arabicLocale,
    overlayLoadingTemplate: '<span style="font-family: Noto Sans Arabic, sans-serif; color: #6b7280;">جاري التحميل...</span>',
    overlayNoRowsTemplate: '<span style="font-family: Noto Sans Arabic, sans-serif; color: #6b7280;">لا توجد بيانات</span>',
    onRowClicked: (e) => onRowClicked?.(e.data as T),
  };

  return (
    <div className="w-full" style={{ height: domLayout === 'autoHeight' ? undefined : height, direction: 'rtl' }}>
      {loading ? (
        <div className="flex h-48 items-center justify-center text-gray-500">جاري التحميل...</div>
      ) : rowData.length === 0 ? (
        <EmptyState />
      ) : (
        <AgGridReact<T>
          theme={themeAlpine}
          rowData={rowData}
          columnDefs={columnDefs}
          defaultColDef={defaultColDef}
          gridOptions={gridOptions}
          pinnedBottomRowData={pinnedBottomRowData}
          domLayout={domLayout}
          enableRtl={true}
        />
      )}
    </div>
  );
}
