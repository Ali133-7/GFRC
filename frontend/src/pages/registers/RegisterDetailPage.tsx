import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useRegister, useRegisterFields } from '@/hooks/useRegisters';
import { usePermissions } from '@/hooks/usePermissions';
import { registersApi } from '@/api/registers';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Modal } from '@/components/ui/Modal';
import { FieldReorderer } from '@/components/registers/FieldReorderer';
import type { RegisterField } from '@/types/register';

const fieldTypes = [
  { value: 'text', label: 'نص' },
  { value: 'number', label: 'رقم' },
  { value: 'decimal', label: 'عشري' },
  { value: 'date', label: 'تاريخ' },
  { value: 'select', label: 'قائمة' },
  { value: 'textarea', label: 'نص طويل' },
  { value: 'hidden', label: 'مخفي' },
  { value: 'calculated', label: 'محسوب' },
];

interface OptionRow {
  value: string;
  label_ar: string;
  label_en: string;
}

function parseOptions(input: unknown): OptionRow[] {
  if (Array.isArray(input)) {
    return input.map((o: any) => ({
      value: String(o.value ?? ''),
      label_ar: String(o.label_ar ?? ''),
      label_en: String(o.label_en ?? ''),
    }));
  }
  if (typeof input === 'string' && input.trim()) {
    try {
      const parsed = JSON.parse(input);
      if (Array.isArray(parsed)) return parseOptions(parsed);
    } catch { /* ignore */ }
  }
  return [];
}

export default function RegisterDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { can } = usePermissions();
  const isNew = id === 'new';
  const { data: register } = useRegister(isNew ? '' : id!);
  const { data: fields, refetch } = useRegisterFields(isNew ? '' : id!);
  const [showModal, setShowModal] = useState(false);
  const [editingField, setEditingField] = useState<Partial<RegisterField>>({});
  const [optionsRows, setOptionsRows] = useState<OptionRow[]>([]);
  const [registerForm, setRegisterForm] = useState({ code: '', name_ar: '', fiscal_year: '' });
  const [isSaving, setIsSaving] = useState(false);
  const [activeTab, setActiveTab] = useState<'fields' | 'reorder'>('fields');

  useEffect(() => {
    if (register) {
      setRegisterForm({
        code: register.code || '',
        name_ar: register.name_ar || '',
        fiscal_year: String(register.fiscal_year || ''),
      });
    }
  }, [register]);

  useEffect(() => {
    setOptionsRows(parseOptions(editingField.options));
  }, [editingField.options]);

  const handleSaveRegister = async () => {
    if (!registerForm.code.trim() || !registerForm.name_ar.trim() || !registerForm.fiscal_year.trim()) {
      alert('الرجاء ملء جميع الحقول المطلوبة');
      return;
    }
    setIsSaving(true);
    try {
      const payload = {
        code: registerForm.code,
        name_ar: registerForm.name_ar,
        fiscal_year: parseInt(registerForm.fiscal_year, 10),
        is_active: true,
      };
      if (isNew) {
        const result = await registersApi.create(payload);
        navigate(`/registers/${result.id}`);
      } else {
        await registersApi.update(id!, payload);
        alert('تم الحفظ بنجاح');
      }
    } catch (e: any) {
      alert(e?.response?.data?.message || 'حدث خطأ أثناء الحفظ');
    } finally {
      setIsSaving(false);
    }
  };

  const handleSaveField = async () => {
    if (isNew) return;
    const payload: Partial<RegisterField> = { ...editingField };
    if (editingField.field_type === 'select') {
      payload.options = optionsRows.filter((r) => r.value.trim() !== '' || r.label_ar.trim() !== '');
    } else {
      payload.options = null;
    }
    try {
      if (editingField.id) {
        await registersApi.updateField(id!, editingField.id, payload);
      } else {
        await registersApi.addField(id!, payload);
      }
      setShowModal(false);
      setEditingField({});
      setOptionsRows([]);
      refetch();
    } catch (e: any) {
      alert(e?.response?.data?.message || 'حدث خطأ أثناء حفظ الحقل');
    }
  };

  const handleDeleteField = async (fieldId: string) => {
    if (!confirm('هل أنت متأكد؟ هذا لن يحذف البيانات التاريخية.')) return;
    await registersApi.removeField(id!, fieldId);
    refetch();
  };

  const addOptionRow = () => {
    setOptionsRows((prev) => [...prev, { value: '', label_ar: '', label_en: '' }]);
  };

  const removeOptionRow = (index: number) => {
    setOptionsRows((prev) => prev.filter((_, i) => i !== index));
  };

  const updateOptionRow = (index: number, key: keyof OptionRow, value: string) => {
    setOptionsRows((prev) => prev.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
  };

  if (!can('view-registers')) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-gray-500">
        <p className="text-xl font-bold">غير مصرح</p>
        <p className="text-sm">ليس لديك صلاحية الوصول إلى السجلات</p>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={isNew ? 'سجل جديد' : register?.name_ar || 'تفاصيل السجل'} />
      <div className="rounded-lg bg-white p-6 shadow mb-6">
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); handleSaveRegister(); }}>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Input 
              label="الكود" 
              value={registerForm.code}
              onChange={(e) => setRegisterForm({ ...registerForm, code: e.target.value })}
              disabled={!isNew}
            />
            <Input 
              label="الاسم العربي" 
              value={registerForm.name_ar}
              onChange={(e) => setRegisterForm({ ...registerForm, name_ar: e.target.value })}
            />
            <Input 
              label="السنة المالية" 
              type="number" 
              value={registerForm.fiscal_year}
              onChange={(e) => setRegisterForm({ ...registerForm, fiscal_year: e.target.value })}
            />
          </div>
          {can('manage-registers') && (
            <div className="flex gap-2">
              <Button type="submit" isLoading={isSaving}>
                {isNew ? '+ إنشاء سجل' : 'حفظ التعديلات'}
              </Button>
              {!isNew && (
                <Button type="button" variant="ghost" onClick={() => navigate('/registers')}>
                  إلغاء
                </Button>
              )}
            </div>
          )}
        </form>
      </div>

      {!isNew && (
        <div className="rounded-lg bg-white p-6 shadow">
          {/* Tabs */}
          <div className="mb-6 border-b">
            <div className="flex gap-4">
              <button
                onClick={() => setActiveTab('fields')}
                className={`py-3 px-1 border-b-2 font-medium transition-colors ${
                  activeTab === 'fields'
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-600 hover:text-gray-900'
                }`}
              >
                📋 إدارة الحقول
              </button>
              <button
                onClick={() => setActiveTab('reorder')}
                className={`py-3 px-1 border-b-2 font-medium transition-colors ${
                  activeTab === 'reorder'
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-600 hover:text-gray-900'
                }`}
              >
                🔄 ترتيب الحقول
              </button>
            </div>
          </div>

          {/* Fields Management Tab */}
          {activeTab === 'fields' && (
            <div>
              <div className="mb-4 flex items-center justify-between">
                <h3 className="font-semibold">الحقول المتاحة</h3>
                <div className="flex gap-2 flex-wrap">
                  <Button size="sm" variant="secondary" onClick={() => navigate(`/registers/${id}/receipts`)}>
                    عرض الوصولات
                  </Button>
                  {can('manage-settings') && (
                    <Button size="sm" onClick={() => navigate(`/registers/${id}/template-designer`)} className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold flex items-center gap-1 shadow-sm">
                      🎨 مصمم الوصل (سحب وإفلات)
                    </Button>
                  )}
                  {can('manage-registers') && (
                    <Button size="sm" variant="secondary" onClick={() => { setEditingField({ field_type: 'text' }); setOptionsRows([]); setShowModal(true); }}>+ إضافة حقل</Button>
                  )}
                </div>
              </div>
              <table className="w-full text-sm">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-3 py-2 text-right">#</th>
                    <th className="px-3 py-2 text-right">الحقل</th>
                    <th className="px-3 py-2 text-right">النوع</th>
                    <th className="px-3 py-2 text-right">مالي</th>
                    <th className="px-3 py-2 text-right">مرئي</th>
                    {can('manage-registers') && <th className="px-3 py-2 text-right">الإجراءات</th>}
                  </tr>
                </thead>
                <tbody>
                  {(fields || []).map((f: RegisterField, idx: number) => (
                    <tr key={f.id} className="border-b hover:bg-gray-50">
                      <td className="px-3 py-2 text-gray-400">{idx + 1}</td>
                      <td className="px-3 py-2 font-medium">{f.label_ar}</td>
                      <td className="px-3 py-2 text-gray-600">{fieldTypes.find(t => t.value === f.field_type)?.label || f.field_type}</td>
                      <td className="px-3 py-2">{f.is_financial ? '✓ نعم' : 'لا'}</td>
                      <td className="px-3 py-2">{f.is_visible ? '✓ نعم' : 'مخفي'}</td>
                      {can('manage-registers') && (
                        <td className="px-3 py-2 flex gap-2">
                          <Button size="sm" variant="ghost" onClick={() => { setEditingField(f); setShowModal(true); }}>تعديل</Button>
                          <Button size="sm" variant="danger" onClick={() => handleDeleteField(f.id)}>حذف</Button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Field Reordering Tab */}
          {activeTab === 'reorder' && (
            <div>
              {fields && fields.length > 0 ? (
                <FieldReorderer
                  registerId={id!}
                  fields={fields}
                  onReorderSuccess={() => {
                    refetch();
                  }}
                />
              ) : (
                <div className="text-center py-12 text-gray-500">
                  <p>لا توجد حقول لهذا السجل. أضف بعض الحقول أولاً.</p>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      <Modal open={showModal} onClose={() => setShowModal(false)} title={editingField.id ? 'تعديل حقل' : 'حقل جديد'}
        footer={<>
          <Button variant="ghost" onClick={() => setShowModal(false)}>إلغاء</Button>
          <Button onClick={handleSaveField}>حفظ</Button>
        </>}
      >
        <div className="space-y-3 max-h-[70vh] overflow-y-auto">
          <Input label="اسم الآلة" value={editingField.name || ''} onChange={(e) => setEditingField({ ...editingField, name: e.target.value })} />
          <Input label="العنوان العربي" value={editingField.label_ar || ''} onChange={(e) => setEditingField({ ...editingField, label_ar: e.target.value })} />
          <Select label="النوع" options={fieldTypes} value={editingField.field_type || 'text'} onChange={(e) => setEditingField({ ...editingField, field_type: e.target.value as any })} />
          <Input label="قيمة افتراضية" value={editingField.default_value || ''} onChange={(e) => setEditingField({ ...editingField, default_value: e.target.value })} />

          {editingField.field_type === 'select' && (
            <div className="rounded-md border p-3 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">خيارات القائمة</span>
                <Button size="sm" variant="secondary" onClick={addOptionRow}>+ إضافة خيار</Button>
              </div>
              {optionsRows.length === 0 && <p className="text-xs text-gray-400">لا توجد خيارات</p>}
              {optionsRows.map((row, idx) => (
                <div key={idx} className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center">
                  <Input label={idx === 0 ? 'القيمة' : undefined} placeholder="القيمة" value={row.value} onChange={(e) => updateOptionRow(idx, 'value', e.target.value)} />
                  <Input label={idx === 0 ? 'العربي' : undefined} placeholder="العربي" value={row.label_ar} onChange={(e) => updateOptionRow(idx, 'label_ar', e.target.value)} />
                  <Input label={idx === 0 ? 'الإنجليزي' : undefined} placeholder="الإنجليزي" value={row.label_en} onChange={(e) => updateOptionRow(idx, 'label_en', e.target.value)} />
                  <Button size="sm" variant="danger" onClick={() => removeOptionRow(idx)}>×</Button>
                </div>
              ))}
            </div>
          )}

          <div className="flex gap-4 pt-2">
            <label className="flex items-center gap-2"><input type="checkbox" checked={editingField.is_required || false} onChange={(e) => setEditingField({ ...editingField, is_required: e.target.checked })} /> مطلوب</label>
            <label className="flex items-center gap-2"><input type="checkbox" checked={editingField.is_visible !== false} onChange={(e) => setEditingField({ ...editingField, is_visible: e.target.checked })} /> مرئي</label>
            <label className="flex items-center gap-2"><input type="checkbox" checked={editingField.is_financial || false} onChange={(e) => setEditingField({ ...editingField, is_financial: e.target.checked })} /> مالي</label>
          </div>
        </div>
      </Modal>
    </div>
  );
}
