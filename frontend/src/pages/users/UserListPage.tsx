import { useNavigate } from 'react-router-dom';
import { useUsers, useDeleteUser } from '@/hooks/useUsers';
import { usePermissions } from '@/hooks/usePermissions';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import type { UserListItem } from '@/types/user';
import type { ColDef } from 'ag-grid-community';

export default function UserListPage() {
  const navigate = useNavigate();
  const { data } = useUsers();
  const remove = useDeleteUser();
  const { can } = usePermissions();

  const columnDefs: ColDef<UserListItem>[] = [
    { field: 'name', headerName: 'الاسم', cellRenderer: (p: any) => (
      <button className="text-blue-600 hover:underline" onClick={() => navigate(`/users/${p.data.id}`)}>{p.value}</button>
    )},
    { field: 'username', headerName: 'اسم المستخدم' },
    { field: 'email', headerName: 'البريد' },
    { field: 'roles', headerName: 'الأدوار', valueGetter: (p: any) => (p.data.roles || []).map((r: any) => r.name).join(', ') },
    { field: 'is_active', headerName: 'الحالة', cellRenderer: (p: any) => <span className={p.value ? 'text-green-700' : 'text-red-700'}>{p.value ? 'مفعل' : 'معطل'}</span> },
    can('manage-users') && {
      headerName: '',
      cellRenderer: (p: any) => (
        <Button size="sm" variant="danger" onClick={() => remove.mutateAsync(p.data.id)}>حذف</Button>
      ),
    },
  ].filter(Boolean) as ColDef<UserListItem>[];

  return (
    <div>
      <PageHeader title="المستخدمين">
        {can('manage-users') && <Button onClick={() => navigate('/users/new')}>مستخدم جديد</Button>}
      </PageHeader>
      <DataTable rowData={data || []} columnDefs={columnDefs} />
    </div>
  );
}
