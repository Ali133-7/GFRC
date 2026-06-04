import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useCreateUser, useUpdateUser, useUpdateUserRoles, useUpdateUserPermissions, useUser } from '@/hooks/useUsers';
import { useRoles } from '@/hooks/useRoles';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { permissionsRegistry, getPermissionSections, getPermissionsBySection } from '@/utils/permissionsRegistry';

export default function UserFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const isNew = !id;
  const { data: user } = useUser(id!);
  const { data: rolesData } = useRoles();

  const create = useCreateUser();
  const update = useUpdateUser();
  const updateRoles = useUpdateUserRoles();
  const updatePermissions = useUpdateUserPermissions();
  const [error, setError] = useState('');
  const [selectedRoles, setSelectedRoles] = useState<string[]>([]);
  const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
  const [permSearch, setPermSearch] = useState('');

  useEffect(() => {
    if (user?.roles) {
      setSelectedRoles(user.roles.map((r) => r.name));
    }
    if (user?.permissions) {
      setSelectedPermissions(user.permissions);
    }
  }, [user?.id]);

  const toggleRole = (roleName: string) => {
    setSelectedRoles((prev) =>
      prev.includes(roleName)
        ? prev.filter((r) => r !== roleName)
        : [...prev, roleName]
    );
  };

  const togglePermission = (permKey: string) => {
    setSelectedPermissions((prev) =>
      prev.includes(permKey)
        ? prev.filter((p) => p !== permKey)
        : [...prev, permKey]
    );
  };

  const filteredPermissions = permSearch
    ? permissionsRegistry.filter((p) =>
        p.label.includes(permSearch) || p.key.includes(permSearch)
      )
    : permissionsRegistry;

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const payload = {
      name: fd.get('name') as string,
      username: fd.get('username') as string,
      email: (fd.get('email') as string) || null,
      password: (fd.get('password') as string) || undefined,
      is_active: fd.get('is_active') === 'on',
    };
    try {
      if (isNew) {
        const newUser = await create.mutateAsync({
          ...payload,
          roles: selectedRoles,
        } as any);
        if (selectedRoles.length > 0 && newUser?.id) {
          await updateRoles.mutateAsync({ id: newUser.id, roles: selectedRoles });
        }
        if (selectedPermissions.length > 0 && newUser?.id) {
          await updatePermissions.mutateAsync({ id: newUser.id, permissions: selectedPermissions });
        }
      } else {
        await update.mutateAsync({ id: id!, payload: payload as any });
        await updateRoles.mutateAsync({ id: id!, roles: selectedRoles });
        await updatePermissions.mutateAsync({ id: id!, permissions: selectedPermissions });
      }
      navigate('/users');
    } catch (e: any) {
      setError(e?.response?.data?.message || 'حدث خطأ');
    }
  };

  return (
    <div>
      <PageHeader title={isNew ? 'مستخدم جديد' : 'تعديل مستخدم'} />
      <form onSubmit={handleSubmit} className="mx-auto max-w-xl rounded-lg bg-white p-6 shadow space-y-4">
        <Input name="name" label="الاسم الكامل" defaultValue={user?.name} required />
        <Input name="username" label="اسم المستخدم" defaultValue={user?.username} required />
        <Input name="email" label="البريد الإلكتروني" type="email" defaultValue={user?.email || ''} />
        <Input name="password" label={isNew ? 'كلمة المرور' : 'كلمة المرور (اتركها فارغة للإبقاء على القديم)'} type="password" required={isNew} />
        <label className="flex items-center gap-2">
          <input type="checkbox" name="is_active" defaultChecked={user?.is_active ?? true} />
          <span className="text-sm">مفعل</span>
        </label>

        {/* Roles section */}
        <div className="rounded-md border p-3 space-y-2">
          <p className="text-sm font-medium">الأدوار</p>
          {rolesData && rolesData.length > 0 ? (
            <div className="grid grid-cols-2 gap-2">
              {rolesData.map((role) => (
                <label key={role.name} className="flex items-center gap-2 rounded p-1 hover:bg-gray-50 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedRoles.includes(role.name)}
                    onChange={() => toggleRole(role.name)}
                  />
                  <span className="text-sm">{role.name}</span>
                </label>
              ))}
            </div>
          ) : (
            <p className="text-xs text-gray-400">لا توجد أدوار متاحة</p>
          )}
        </div>

        {/* Permissions section */}
        <div className="rounded-md border p-3 space-y-3">
          <div className="flex items-center justify-between">
            <p className="text-sm font-medium">الصلاحيات</p>
            <input
              type="text"
              placeholder="بحث..."
              value={permSearch}
              onChange={(e) => setPermSearch(e.target.value)}
              className="text-xs rounded border px-2 py-1 w-40 text-right"
            />
          </div>

          {getPermissionSections().map((section) => {
            const sectionPerms = getPermissionsBySection(section).filter(
              (p) => !permSearch || p.label.includes(permSearch) || p.key.includes(permSearch)
            );
            if (sectionPerms.length === 0) return null;

            return (
              <div key={section}>
                <p className="text-xs font-semibold text-gray-500 mb-1">{section}</p>
                <div className="grid grid-cols-2 gap-1">
                  {sectionPerms.map((perm) => {
                    const isChecked = selectedPermissions.includes(perm.key);
                    return (
                      <label
                        key={perm.key}
                        className={`flex items-center gap-2 rounded px-1 py-1 cursor-pointer ${
                          perm.isDanger ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => togglePermission(perm.key)}
                        />
                        <span className={`text-xs ${perm.isDanger ? 'text-red-700 font-medium' : 'text-sm'}`}>
                          {perm.label}
                        </span>
                        {perm.isDanger && (
                          <span className="text-[10px] bg-red-600 text-white px-1 rounded">خطر</span>
                        )}
                      </label>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>

        {error && <p className="text-red-600 text-sm">{error}</p>}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" type="button" onClick={() => navigate('/users')}>إلغاء</Button>
          <Button type="submit" isLoading={create.isPending || update.isPending || updateRoles.isPending || updatePermissions.isPending}>حفظ</Button>
        </div>
      </form>
    </div>
  );
}
