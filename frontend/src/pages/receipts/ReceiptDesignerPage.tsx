import { useState, useMemo } from 'react';
import { useReceipt, useReceipts } from '@/hooks/useReceipts';
import { usePermissions } from '@/hooks/usePermissions';
import { useAuthStore } from '@/stores/authStore';
import ReceiptDesignerV2 from '@/components/receipt/ReceiptDesignerV2';
import { demoReceipt } from '@/components/receipt/demoReceipt';
import { PageHeader } from '@/components/layout/PageHeader';
import type { Receipt } from '@/types/receipt';

export default function ReceiptDesignerPage() {
  const { can } = usePermissions();
  const { user } = useAuthStore();
  const [selectedId, setSelectedId] = useState<string>(demoReceipt.id);
  const { data: receiptData } = useReceipt(selectedId === demoReceipt.id ? '' : selectedId);
  const { data: receiptsList } = useReceipts({ per_page: 50 });

  if (!user) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-gray-500">
        <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-4"></div>
        <p className="text-xl font-bold">جاري تحميل البيانات...</p>
      </div>
    );
  }

  if (!can('manage-settings')) {
    return (
      <div className="flex h-96 flex-col items-center justify-center text-gray-500">
        <span className="text-6xl mb-4">🔒</span>
        <p className="text-xl font-bold">غير مصرح</p>
        <p className="text-sm">ليس لديك صلاحية الوصول إلى مصمم الوصولات</p>
      </div>
    );
  }

  const receipt = useMemo<Receipt>(() => {
    if (selectedId === demoReceipt.id) return demoReceipt;
    return receiptData || demoReceipt;
  }, [selectedId, receiptData]);

  const allReceipts = useMemo(() => {
    const list = (receiptsList as Receipt[] | undefined) || [];
    return list;
  }, [receiptsList]);

  return (
    <div className="h-full">
      <PageHeader title="مصمم الوصولات" />
      <ReceiptDesignerV2
        receipt={receipt}
        allReceipts={allReceipts}
        onSelectReceipt={setSelectedId}
      />
    </div>
  );
}
