import { lazy, Suspense, useMemo } from 'react';
import type { TemplateProps } from './templates/types';

const templates: Record<string, React.LazyExoticComponent<React.FC<TemplateProps>>> = {
  dynamic: lazy(() => import('./templates/TemplateDesigner')),
  designed: lazy(() => import('./templates/TemplateDesigned')),
  classic: lazy(() => import('./templates/TemplateClassic')),
  modern: lazy(() => import('./templates/TemplateModern')),
  compact: lazy(() => import('./templates/TemplateCompact')),
  premium: lazy(() => import('./templates/TemplatePremium')),
  wide: lazy(() => import('./templates/TemplateWide')),
  narrow: lazy(() => import('./templates/TemplateNarrow')),
  arabic: lazy(() => import('./templates/TemplateArabic')),
  bilingual: lazy(() => import('./templates/TemplateBilingual')),
  largeQr: lazy(() => import('./templates/TemplateLargeQr')),
  noQr: lazy(() => import('./templates/TemplateNoQr')),
};

export const templateNames: Record<string, string> = {
  dynamic: 'مصمم الوصولات (ديناميكي)',
  designed: 'التصميم الحر المخصص',
  classic: 'كلاسيك',
  modern: 'حديث',
  compact: 'مضغوط (حراري)',
  premium: 'فاخر',
  wide: 'عريض',
  narrow: 'ضيق',
  arabic: 'عربي فقط',
  bilingual: 'ثنائي اللغة',
  largeQr: 'QR كبير',
  noQr: 'بدون QR',
};

export const templateColors: Record<string, string> = {
  dynamic: 'bg-gradient-to-br from-indigo-100 to-purple-100 border-indigo-500 shadow-sm border-2 font-bold text-indigo-700',
  designed: 'bg-blue-100 border-indigo-500 shadow-sm border-2 font-bold text-indigo-700',
  classic: 'bg-gray-100 border-gray-400',
  modern: 'bg-blue-50 border-blue-400',
  compact: 'bg-yellow-50 border-yellow-400',
  premium: 'bg-yellow-100 border-yellow-600',
  wide: 'bg-gray-50 border-gray-500',
  narrow: 'bg-green-50 border-green-400',
  arabic: 'bg-green-100 border-green-600',
  bilingual: 'bg-indigo-50 border-indigo-400',
  largeQr: 'bg-purple-50 border-purple-400',
  noQr: 'bg-red-50 border-red-400',
};

export const defaultTemplate = 'classic';

export function ReceiptTemplateRenderer({ templateKey, receipt, settings, ...props }: TemplateProps & { templateKey: string }) {
  const Template = templates[templateKey] || templates[defaultTemplate];

  const filteredReceipt = useMemo(() => {
    if (!settings?.hide_zero_or_empty) return receipt;

    return {
      ...receipt,
      items: (receipt.items || []).filter((item) => {
        const hasAmount = item.amount !== null && item.amount !== undefined && item.amount !== '';
        const isZeroAmount = hasAmount && parseFloat(item.amount || '0') === 0;
        const hasTextValue = item.text_value !== null && item.text_value !== undefined && item.text_value.trim() !== '';

        if (hasAmount) {
          return !isZeroAmount;
        }
        return hasTextValue;
      }),
    };
  }, [receipt, settings?.hide_zero_or_empty]);

  return (
    <Suspense fallback={<div className="p-8 text-center">جاري تحميل القالب...</div>}>
      <Template receipt={filteredReceipt} settings={settings} {...props} />
    </Suspense>
  );
}
