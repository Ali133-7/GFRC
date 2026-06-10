import { useMemo } from "react";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";

interface FeeItem {
  label: string;
  amount: string | number;
  discount?: string | number;
  net_amount?: string | number;
}

interface RealTimeFeePanelProps {
  items: FeeItem[];
  totalAmount: string | number;
  isFetching: boolean;
  calculatingFor: number;
}

export function RealTimeFeePanel({
  items,
  totalAmount,
  isFetching,
  calculatingFor,
}: RealTimeFeePanelProps) {
  const formattedTotal = useMemo(() => {
    const num = Number(totalAmount);
    return Number.isFinite(num) ? num.toLocaleString("ar-IQ", { minimumFractionDigits: 3 }) : "0.000";
  }, [totalAmount]);

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
          الرسوم المحسوبة
        </h3>
        {isFetching && (
          <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <LoadingSpinner />
            جارٍ الحساب...
          </span>
        )}
      </div>

      {calculatingFor > 3000 && (
        <div className="mb-2 rounded bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
          الحساب يستغرق وقتاً أطول من المعتاد
        </div>
      )}

      <div className="space-y-2">
        {items.length === 0 && !isFetching && (
          <p className="text-xs text-gray-500 dark:text-gray-400">لا توجد رسوم محسوبة</p>
        )}

        {items.map((item, idx) => (
          <div
            key={idx}
            className="flex items-center justify-between text-sm"
          >
            <span className="text-gray-700 dark:text-gray-300">{item.label}</span>
            <div className="text-left">
              {item.discount && Number(item.discount) > 0 && (
                <span className="mr-2 text-xs text-red-500 line-through dark:text-red-400">
                  {Number(item.amount).toLocaleString("ar-IQ", { minimumFractionDigits: 3 })}
                </span>
              )}
              <span className="font-medium text-gray-900 dark:text-gray-100">
                {Number(item.net_amount ?? item.amount).toLocaleString("ar-IQ", {
                  minimumFractionDigits: 3,
                })}{" "}
                د.ع
              </span>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700">
        <div className="flex items-center justify-between">
          <span className="text-sm font-bold text-gray-900 dark:text-gray-100">الإجمالي</span>
          <span className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
            {formattedTotal} د.ع
          </span>
        </div>
      </div>
    </div>
  );
}
