import React, { useState, useEffect } from "react";
import { useNavigate, useParams, useLocation } from "react-router-dom";
import { useQuery, useMutation } from "@tanstack/react-query";
import client from "@/api/client";
import { receiptsApi } from "@/api/receipts";
import type { Receipt } from "@/types/receipt";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { PageHeader } from "@/components/layout/PageHeader";
import { formatCurrency } from "@/utils/formatCurrency";

const cardBase = {
  background: "#fff",
  borderRadius: "16px",
  boxShadow: "0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02)",
  border: "1px solid #f1f5f9",
  overflow: "hidden" as const,
};

export default function ReceiptRevisePage() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const location = useLocation();
  const reason = (location.state as any)?.reason ?? "";

  const { data: receipt, isLoading } = useQuery({
    queryKey: ["receipts", id],
    queryFn: async () => {
      const res = await client.get(`/receipts/${id}`);
      return (res.data?.data ?? res.data) as Receipt;
    },
    enabled: !!id,
  });

  const [items, setItems] = useState<Array<{
    field_id: string;
    field_name_snapshot: string;
    label_ar_snapshot: string;
    amount: string;
    text_value: string;
  }>>([]);
  const [initialized, setInitialized] = useState(false);

  useEffect(() => {
    if (receipt && !initialized) {
      setItems(
        receipt.items.map((item) => ({
          field_id: item.field_id,
          field_name_snapshot: item.field_name_snapshot,
          label_ar_snapshot: item.label_ar_snapshot,
          amount: item.amount ?? "",
          text_value: item.text_value ?? "",
        }))
      );
      setNotes(receipt.notes ?? "");
      setInitialized(true);
    }
  }, [receipt, initialized]);

  const [reasonText, setReasonText] = useState(reason || "");
  const [notes, setNotes] = useState(receipt?.notes ?? "");
  const [error, setError] = useState("");

  const reviseMut = useMutation({
    mutationFn: () => {
      if (!receipt) throw new Error("No receipt");
      if (items.length === 0) throw new Error("لا توجد عناصر للتعديل");

      const total = items.reduce((sum, item) => {
        const amt = parseFloat(item.amount) || 0;
        return sum + amt;
      }, 0);

      if (total < 0.001) {
        throw new Error("الإجمالي يجب أن يكون أكبر من 0.001");
      }

      return receiptsApi.revise(receipt.id, {
        total_amount: total,
        notes,
        reason: reasonText,
        items: items.map((item) => ({
          field_id: item.field_id,
          value: item.text_value || undefined,
          amount: parseFloat(item.amount) || 0,
        })),
      });
    },
    onSuccess: (newReceipt) => {
      navigate(`/receipts/${newReceipt.id}`);
    },
    onError: (err: any) => {
      const data = err?.response?.data;
      const msg = data?.message || "فشل تعديل الوصل";
      const errors = data?.errors ? Object.values(data.errors).flat().join("\n") : "";
      setError(errors ? `${msg}\n${errors}` : msg);
    },
  });

  const updateItem = (index: number, field: string, value: string) => {
    setItems((prev) => prev.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
  };

  const total = items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);

  if (isLoading || !initialized) return <LoadingSpinner />;
  if (!receipt) return <div className="p-8 text-center text-gray-500">الوصل غير موجود</div>;
  if (items.length === 0) return <div className="p-8 text-center text-gray-500">لا توجد عناصر في هذا الوصل للتعديل</div>;

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <PageHeader title={`تعديل الوصل: ${receipt.receipt_number}`} />

      {error && (
        <div className="p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
          {error}
        </div>
      )}

      <div style={cardBase}>
        <div className="p-6 border-b border-gray-100">
          <h3 className="font-semibold text-gray-900 mb-2">سبب التعديل <span className="text-red-500">*</span></h3>
          <textarea
            value={reasonText}
            onChange={(e) => setReasonText(e.target.value)}
            rows={2}
            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="أدخل سبب التعديل (10 أحرف على الأقل)"
          />
          {reasonText.length < 10 && reasonText.length > 0 && (
            <p className="text-xs text-red-500 mt-1">{10 - reasonText.length} أحرف متبقية</p>
          )}
        </div>

        <div className="p-6 space-y-4">
          {items.map((item, index) => (
            <div key={`${item.field_id}-${index}`} className="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg">
              <div>
                <label className="block text-xs text-gray-500 mb-1">الحقل</label>
                <span className="text-sm font-medium text-gray-900">{item.label_ar_snapshot}</span>
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1">المبلغ</label>
                <input
                  type="number"
                  step="0.001"
                  value={item.amount}
                  onChange={(e) => updateItem(index, "amount", e.target.value)}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1">القيمة النصية</label>
                <input
                  type="text"
                  value={item.text_value}
                  onChange={(e) => updateItem(index, "text_value", e.target.value)}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="اختياري"
                />
              </div>
            </div>
          ))}
        </div>

        <div className="p-6 border-t border-gray-100 bg-gray-50">
          <div className="flex justify-between items-center">
            <span className="font-semibold text-gray-700">الإجمالي</span>
            <span className="text-xl font-bold text-gray-900">{formatCurrency(total)}</span>
          </div>
        </div>
      </div>

      <div style={cardBase}>
        <div className="p-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">ملاحظات</label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            rows={3}
            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="ملاحظات إضافية (اختياري)"
          />
        </div>
      </div>

      <div className="flex gap-3">
        <button
          onClick={() => navigate(-1)}
          className="flex-1 px-4 py-3 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 font-medium"
        >
          إلغاء
        </button>
        <button
          onClick={() => reviseMut.mutate()}
          disabled={reviseMut.isPending || reasonText.length < 10 || items.length === 0 || total < 0.001}
          className="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium disabled:opacity-50"
        >
          {reviseMut.isPending ? "جاري الحفظ..." : "حفظ التعديلات"}
        </button>
      </div>
    </div>
  );
}
