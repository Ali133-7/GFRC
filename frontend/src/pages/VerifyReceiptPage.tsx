import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { receiptsApi } from '@/api/receipts';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDateTime } from '@/utils/formatDate';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { Receipt } from '@/types/receipt';

export default function VerifyReceiptPage() {
  const [searchParams] = useSearchParams();
  const id = searchParams.get('id');
  const hash = searchParams.get('hash');
  const [receipt, setReceipt] = useState<Receipt | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id) { setError('رقم الوصل مفقود'); setLoading(false); return; }
    receiptsApi.get(id)
      .then((data) => {
        setReceipt(data);
        setLoading(false);
      })
      .catch(() => {
        setError('لم يتم العثور على الوصل');
        setLoading(false);
      });
  }, [id]);

  const expectedHash = receipt?.qr_payload ? JSON.parse(receipt.qr_payload).hash : '';
  const isValid = hash && expectedHash && hash === expectedHash;

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50" dir="rtl">
        <div className="text-center">
          <div className="animate-spin h-10 w-10 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-4" />
          <p className="text-gray-600">جاري التحقق من الوصل...</p>
        </div>
      </div>
    );
  }

  if (error || !receipt) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50" dir="rtl">
        <div className="bg-white rounded-lg shadow-lg p-8 text-center max-w-md">
          <div className="text-6xl mb-4">❌</div>
          <h1 className="text-2xl font-bold text-red-600 mb-2">وصل غير صالح</h1>
          <p className="text-gray-600">{error || 'لم يتم العثور على الوصل'}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8 px-4" dir="rtl">
      <div className="max-w-2xl mx-auto">
        {/* Status Banner */}
        <div className={`rounded-lg p-4 mb-6 text-center ${isValid ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
          <div className="text-4xl mb-2">{isValid ? '✅' : '⚠️'}</div>
          <h1 className="text-xl font-bold">{isValid ? 'وصل أصلي ومفعّل' : 'وصل موجود لكن التحقق غير مكتمل'}</h1>
        </div>

        {/* Receipt Card */}
        <div className="bg-white rounded-lg shadow-lg p-6">
          <div className="text-center mb-6 border-b pb-4">
            <h2 className="text-2xl font-bold">{receipt.receipt_number}</h2>
            <p className="text-gray-500 mt-1">رقم الوصل</p>
          </div>

          <div className="grid grid-cols-2 gap-4 text-sm mb-6">
            <div className="bg-gray-50 rounded p-3">
              <p className="text-gray-500">السجل</p>
              <p className="font-medium">{receipt.register?.name_ar}</p>
            </div>
            <div className="bg-gray-50 rounded p-3">
              <p className="text-gray-500">التاريخ</p>
              <p className="font-medium">{formatDateTime(receipt.created_at)}</p>
            </div>
            <div className="bg-gray-50 rounded p-3">
              <p className="text-gray-500">أمين الصندوق</p>
              <p className="font-medium">{receipt.created_by?.name}</p>
            </div>
            <div className="bg-gray-50 rounded p-3">
              <p className="text-gray-500">الحالة</p>
              <p className={`font-medium ${receipt.status === 'cancelled' ? 'text-red-600' : 'text-green-600'}`}>
                {receipt.status === 'issued' ? 'مصدر' : receipt.status === 'cancelled' ? 'ملغى' : receipt.status}
              </p>
            </div>
          </div>

          <table className="w-full text-sm mb-6">
            <thead className="bg-gray-100">
              <tr><th className="px-3 py-2 text-right">البيان</th><th className="px-3 py-2 text-left">القيمة</th></tr>
            </thead>
            <tbody>
              {(receipt.items || []).map((item) => (
                <tr key={item.id} className="border-b">
                  <td className="px-3 py-2">{item.label_ar_snapshot}</td>
                  <td className="px-3 py-2 text-left font-mono">{item.amount != null ? formatCurrency(item.amount) : item.text_value}</td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="text-center bg-blue-50 rounded-lg p-4">
            <p className="text-sm text-gray-600">المجموع الكلي</p>
            <p className="text-3xl font-bold text-blue-900">{formatCurrency(receipt.total_amount)}</p>
            <p className="text-gray-600">{amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي</p>
          </div>

          {receipt.status === 'cancelled' && receipt.cancel_reason && (
            <div className="mt-4 bg-red-50 rounded p-3 text-sm text-red-700">
              <span className="font-bold">سبب الإلغاء:</span> {receipt.cancel_reason}
            </div>
          )}
        </div>

        <div className="text-center mt-6 text-sm text-gray-500">
          <p>تم التحقق من هذا الوصل إلكترونياً عبر نظام GFRC</p>
          <p className="mt-1 font-mono">رمز التحقق: {expectedHash?.substring(0, 8)}</p>
        </div>
      </div>
    </div>
  );
}
