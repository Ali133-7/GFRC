import { useState, useEffect } from 'react';
import { useFieldsEditor } from '@/hooks/useTemplateDesigner';
import { Button } from '@/components/ui/Button';
import apiClient from '@/services/apiClient';

interface FieldEditorProps {
  templateId: string;
  registerId?: string;
  onClose: () => void;
  onElementAdded?: (element: any) => void;
}

const elementTypes = [
  { value: 'field', label: '📋 حقل من السجل', icon: '📋' },
  { value: 'text', label: '📝 نص ثابت', icon: '📝' },
  { value: 'divider', label: '➖ فاصل أفقي', icon: '➖' },
  { value: 'qr', label: '🔳 رمز الاستجابة السريعة', icon: '🔳' },
  { value: 'signature', label: '✍️ توقيع', icon: '✍️' },
  { value: 'total', label: '💰 المجموع', icon: '💰' },
  { value: 'image', label: '🖼️ صورة', icon: '🖼️' },
  { value: 'spacer', label: '⬜ مسافة فارغة', icon: '⬜' },
];

export default function FieldEditor({ templateId, registerId, onClose, onElementAdded }: FieldEditorProps) {
  const { addElement, registerFields, isLoading } = useFieldsEditor(templateId);
  const [selectedType, setSelectedType] = useState<string>('field');
  const [selectedField, setSelectedField] = useState<string>('');
  const [label, setLabel] = useState<string>('');
  const [registers, setRegisters] = useState<any[]>([]);
  const [selectedRegister, setSelectedRegister] = useState<string>(registerId || '');
  const [fieldsForRegister, setFieldsForRegister] = useState<any[]>([]);

  useEffect(() => {
    const loadRegisters = async () => {
      try {
        const response = await apiClient.get('/registers?limit=50');
        setRegisters(response.data.data || []);
      } catch (e) {
        console.error('فشل تحميل السجلات');
      }
    };
    loadRegisters();
  }, []);

  useEffect(() => {
    const loadFieldsForRegister = async () => {
      if (!selectedRegister) return;
      try {
        const response = await apiClient.get(`/registers/${selectedRegister}/fields`);
        const fields = response.data.data || [];
        const normalized = fields.map((f: any) => ({
          id: f.id,
          label: f.label || f.label_ar || f.name,
          name: f.name,
          field_type: f.field_type || 'text'
        }));
        setFieldsForRegister(normalized);
      } catch (e) {
        setFieldsForRegister([]);
      }
    };
    loadFieldsForRegister();
  }, [selectedRegister]);

  const handleAddElement = async () => {
    if (!selectedType) return;

    const elementData: any = {
      element_type: selectedType,
      x: 10,
      y: 10,
      width: 100,
      height: 30,
      is_visible: true,
    };

    if (selectedType === 'field' && selectedField) {
      elementData.field_id = selectedField;
    }

    if (label) {
      elementData.label = label;
    }

    try {
      const result = await addElement(elementData);
      if (result) {
        onElementAdded?.(result);
        onClose();
      } else {
        alert('❌ فشل إضافة العنصر');
      }
    } catch (e) {
      alert('❌ خطأ: ' + (e instanceof Error ? e.message : 'خطأ غير معروف'));
    }
  };

  // Get preview text/icon for field
  const getFieldPreview = (field: any) => {
    const typeIcon: Record<string, string> = {
      'text': '📝',
      'number': '🔢',
      'date': '📅',
      'select': '📌',
      'textarea': '📄',
      'email': '📧',
      'phone': '📞',
      'currency': '💵',
    };
    return typeIcon[field.field_type] || '🔹';
  };

  return (
    <div className="bg-white rounded-lg shadow-md border border-gray-200 p-5 space-y-5">
      <div className="flex justify-between items-center mb-4">
        <h3 className="font-bold text-lg text-gray-800">إضافة عنصر جديد</h3>
        <button
          onClick={onClose}
          className="text-gray-500 hover:text-gray-700 text-2xl font-light"
        >
          ✕
        </button>
      </div>

      {/* Element Type Selection */}
      <div className="space-y-3">
        <label className="block text-sm font-semibold text-gray-700">نوع العنصر</label>
        <div className="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto">
          {elementTypes.map((type) => (
            <button
              key={type.value}
              onClick={() => {
                setSelectedType(type.value);
                setSelectedField('');
                setLabel('');
              }}
              className={`px-3 py-2.5 rounded text-sm font-medium text-right transition border-2 ${
                selectedType === type.value
                  ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-sm'
                  : 'border-gray-200 bg-white hover:border-blue-300 hover:bg-gray-50'
              }`}
            >
              <span className="ml-1">{type.icon}</span>
              <span>{type.label}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Field Selection (if field type) */}
      {selectedType === 'field' && (
        <div className="space-y-3 bg-gradient-to-b from-blue-50 to-blue-25 p-4 rounded-lg border border-blue-200 shadow-sm">
          {/* Register Selector */}
          <div className="space-y-2">
            <label className="block text-sm font-semibold text-gray-700">اختر السجل (اختياري)</label>
            <select
              value={selectedRegister}
              onChange={(e) => setSelectedRegister(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">السجل الافتراضي</option>
              {registers.map((reg) => (
                <option key={reg.id} value={reg.id}>{reg.name}</option>
              ))}
            </select>
          </div>

          <label className="block text-sm font-semibold text-gray-700">اختر الحقل من السجل</label>
          
          {(selectedRegister ? fieldsForRegister : registerFields).length > 0 ? (
            <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
              {(selectedRegister ? fieldsForRegister : registerFields).map((field) => (
                <button
                  key={field.id}
                  onClick={() => {
                    setSelectedField(field.id);
                    setLabel(field.label || field.name);
                  }}
                  className={`w-full px-4 py-3 rounded-lg text-sm text-right transition border-2 group ${
                    selectedField === field.id
                      ? 'border-blue-500 bg-white shadow-md'
                      : 'border-blue-300 bg-white hover:bg-blue-100/50 hover:border-blue-400'
                  }`}
                >
                  <div className="flex justify-between items-center">
                    <div className="flex items-center gap-2">
                      <span className="text-lg">{getFieldPreview(field)}</span>
                      <span className="text-xs text-gray-500 font-normal">({field.field_type})</span>
                    </div>
                    <span className="font-semibold text-gray-800 group-hover:text-blue-600">{field.label || field.name}</span>
                  </div>
                </button>
              ))}
            </div>
          ) : (
            <div className="text-center py-6">
              <p className="text-sm text-gray-500">❌ لا توجد حقول متاحة</p>
              <p className="text-xs text-gray-400 mt-1">تأكد من إضافة حقول للسجل أولاً</p>
            </div>
          )}
        </div>
      )}

      {/* Custom Label */}
      {selectedType !== 'field' && (
        <div className="space-y-2">
          <label className="block text-sm font-semibold text-gray-700">العنوان (اختياري)</label>
          <input
            type="text"
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            placeholder="مثال: 'عنوان الوصل'"
            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
      )}

      {/* Preview */}
      <div className="bg-gray-100 p-4 rounded-lg border border-gray-300">
        <p className="text-xs font-semibold text-gray-600 mb-2.5">📸 معاينة لحظية:</p>
        <div className="w-full min-h-16 border-2 border-dashed border-gray-400 rounded-md flex items-center justify-center bg-white text-gray-600 text-center p-3 hover:bg-gray-50 transition">
          <span className="text-sm break-words">
            {selectedType === 'field' 
              ? (selectedField ? `${registerFields.find(f => f.id === selectedField)?.label}` : '⏳ اختر حقلاً') 
              : (label || elementTypes.find((t) => t.value === selectedType)?.label)}
          </span>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex gap-2 pt-2">
        <Button
          onClick={handleAddElement}
          disabled={isLoading || !selectedType || (selectedType === 'field' && !selectedField)}
          className="flex-1"
          variant="primary"
        >
          {isLoading ? '⏳ جاري...' : '➕ إضافة العنصر'}
        </Button>
        <Button
          onClick={onClose}
          variant="secondary"
          className="flex-1"
        >
          إلغاء
        </Button>
      </div>
    </div>
  );
}
