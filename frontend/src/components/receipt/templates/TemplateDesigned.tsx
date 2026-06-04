import { useEffect, useState } from 'react';
import apiClient from '@/services/apiClient';
import { logError } from '@/utils/errorHandler';
import { formatCurrency } from '@/utils/formatCurrency';
import { amountToArabicWords } from '@/utils/amountToArabicWords';
import type { TemplateProps } from './types';

interface DesignedTemplate {
  id: string;
  name: string;
  layout_type: string;
  page_width: number;
  page_height: number;
  background_color: string;
  elements: Array<{
    id: string;
    element_type: 'field' | 'text' | 'divider' | 'qr' | 'signature' | 'total' | 'image' | 'spacer';
    label: string;
    field_id: string | null;
    x: number;
    y: number;
    width: number;
    height: number;
    is_visible: boolean;
    style?: {
      font_family?: string;
      font_size?: number;
      font_weight?: string;
      font_color?: string;
      background_color?: string;
      border_color?: string;
      border_width?: number;
      text_align?: 'left' | 'center' | 'right';
      padding?: {
        top: number;
        right: number;
        bottom: number;
        left: number;
      };
      opacity?: number;
      display?: string;
    };
  }>;
}

const mockDemoTemplate: DesignedTemplate = {
  id: 'demo-template-id',
  name: 'القالب الافتراضي المخصص للمعاينة',
  layout_type: 'portrait',
  page_width: 210,
  page_height: 297,
  background_color: '#ffffff',
  elements: [
    {
      id: 'el-logo',
      element_type: 'image',
      label: 'الشعار',
      field_id: null,
      x: 350,
      y: 20,
      width: 100,
      height: 80,
      is_visible: true,
      style: {
        text_align: 'center',
      }
    },
    {
      id: 'el-title',
      element_type: 'text',
      label: 'جمهورية العراق - نظام الإيصالات المالية',
      field_id: null,
      x: 100,
      y: 110,
      width: 600,
      height: 35,
      is_visible: true,
      style: {
        font_size: 18,
        font_weight: 'bold',
        text_align: 'center',
      }
    },
    {
      id: 'el-divider-1',
      element_type: 'divider',
      label: 'خط فاصل',
      field_id: null,
      x: 40,
      y: 150,
      width: 720,
      height: 10,
      is_visible: true,
    },
    {
      id: 'el-num',
      element_type: 'text',
      label: 'رقم الإيصال: GEN-2026-000001',
      field_id: null,
      x: 480,
      y: 170,
      width: 280,
      height: 30,
      is_visible: true,
      style: {
        font_size: 14,
        font_weight: 'bold',
        text_align: 'right',
      }
    },
    {
      id: 'el-date',
      element_type: 'text',
      label: 'التاريخ: 2026-05-29',
      field_id: null,
      x: 40,
      y: 170,
      width: 250,
      height: 30,
      is_visible: true,
      style: {
        font_size: 12,
        text_align: 'left',
      }
    },
    {
      id: 'el-field-1',
      element_type: 'field',
      label: 'نوع الخدمة',
      field_id: 'field-1',
      x: 40,
      y: 220,
      width: 720,
      height: 40,
      is_visible: true,
      style: {
        font_size: 13,
        text_align: 'right',
        background_color: '#f8fafc',
        border_width: 1,
        border_color: '#e2e8f0',
        padding: { top: 6, right: 10, bottom: 6, left: 10 }
      }
    },
    {
      id: 'el-field-2',
      element_type: 'field',
      label: 'المبلغ الأساسي',
      field_id: 'field-2',
      x: 40,
      y: 275,
      width: 720,
      height: 40,
      is_visible: true,
      style: {
        font_size: 13,
        text_align: 'right',
        background_color: '#ffffff',
        border_width: 1,
        border_color: '#e2e8f0',
        padding: { top: 6, right: 10, bottom: 6, left: 10 }
      }
    },
    {
      id: 'el-field-3',
      element_type: 'field',
      label: 'الضريبة',
      field_id: 'field-3',
      x: 40,
      y: 330,
      width: 720,
      height: 40,
      is_visible: true,
      style: {
        font_size: 13,
        text_align: 'right',
        background_color: '#f8fafc',
        border_width: 1,
        border_color: '#e2e8f0',
        padding: { top: 6, right: 10, bottom: 6, left: 10 }
      }
    },
    {
      id: 'el-field-4',
      element_type: 'field',
      label: 'رسوم إدارية',
      field_id: 'field-4',
      x: 40,
      y: 385,
      width: 720,
      height: 40,
      is_visible: true,
      style: {
        font_size: 13,
        text_align: 'right',
        background_color: '#ffffff',
        border_width: 1,
        border_color: '#e2e8f0',
        padding: { top: 6, right: 10, bottom: 6, left: 10 }
      }
    },
    {
      id: 'el-divider-2',
      element_type: 'divider',
      label: 'خط فاصل',
      field_id: null,
      x: 40,
      y: 445,
      width: 720,
      height: 10,
      is_visible: true,
    },
    {
      id: 'el-total',
      element_type: 'total',
      label: 'المجموع الإجمالي',
      field_id: null,
      x: 400,
      y: 470,
      width: 360,
      height: 60,
      is_visible: true,
      style: {
        font_size: 14,
        font_weight: 'bold',
        text_align: 'right',
      }
    },
    {
      id: 'el-qr',
      element_type: 'qr',
      label: 'رمز التحقق',
      field_id: null,
      x: 40,
      y: 470,
      width: 100,
      height: 100,
      is_visible: true,
    },
    {
      id: 'el-sig',
      element_type: 'signature',
      label: 'أمين الصندوق',
      field_id: null,
      x: 450,
      y: 560,
      width: 250,
      height: 60,
      is_visible: true,
      style: {
        text_align: 'center',
      }
    }
  ]
};

export default function TemplateDesigned({ receipt, settings: _settings, logo, qrSvg }: TemplateProps) {
  const [template, setTemplate] = useState<DesignedTemplate | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchTemplate = async () => {
      if (receipt.register_id === 'demo-reg') {
        setTemplate(mockDemoTemplate);
        setIsLoading(false);
        return;
      }

      try {
        setIsLoading(true);
        const response = await apiClient.get(`/registers/${receipt.register_id}/template`);
        setTemplate(response.data.data);
      } catch (err: any) {
        logError(err, 'تحميل نموذج السجل');
        setError('لا يمكن تحميل تصميم الوصل المخصص لهذا السجل');
      } finally {
        setIsLoading(false);
      }
    };

    if (receipt.register_id) {
      fetchTemplate();
    }
  }, [receipt.register_id]);

  if (isLoading) {
    return <div className="text-center p-8 text-gray-500 italic">جاري تحميل التصميم المخصص...</div>;
  }

  if (error || !template) {
    return (
      <div className="text-center p-4 border border-red-200 bg-red-50 text-red-700 rounded-md">
        {error || 'القالب المخصص غير متوفر'}
      </div>
    );
  }

  return (
    <div
      className="relative mx-auto border shadow-lg overflow-hidden"
      style={{
        width: `${template.page_width}mm`,
        height: `${template.page_height}mm`,
        backgroundColor: template.background_color || '#ffffff',
      }}
      dir="rtl"
    >
      {template.elements
        .filter((el) => el.is_visible !== false)
        .map((element) => {
          // Custom styling from template style properties
          const elStyle = element.style;
          const elementStyle: React.CSSProperties = {
            position: 'absolute',
            left: `${element.x}px`,
            top: `${element.y}px`,
            width: `${element.width}px`,
            height: `${element.height}px`,
            fontFamily: elStyle?.font_family || 'Arial',
            fontSize: elStyle?.font_size ? `${elStyle.font_size}px` : '13px',
            fontWeight: elStyle?.font_weight || 'normal',
            color: elStyle?.font_color || '#1e293b',
            backgroundColor: elStyle?.background_color || 'transparent',
            borderColor: elStyle?.border_color || '#cbd5e1',
            borderWidth: elStyle?.border_width != null ? `${elStyle.border_width}px` : '0px',
            borderStyle: elStyle?.border_width ? 'solid' : 'none',
            textAlign: elStyle?.text_align || 'right',
            opacity: elStyle?.opacity ?? 1,
            display: elStyle?.display === 'none' ? 'none' : 'flex',
            alignItems: 'center',
            justifyContent: elStyle?.text_align === 'left' ? 'flex-start' : elStyle?.text_align === 'center' ? 'center' : 'flex-end',
            paddingTop: `${elStyle?.padding?.top ?? 0}px`,
            paddingRight: `${elStyle?.padding?.right ?? 0}px`,
            paddingBottom: `${elStyle?.padding?.bottom ?? 0}px`,
            paddingLeft: `${elStyle?.padding?.left ?? 0}px`,
            overflow: 'hidden',
          };

          // Render real receipt values based on type
          const renderElementValue = () => {
            switch (element.element_type) {
              case 'field': {
                const item = (receipt.items || []).find((it) => it.field_id === element.field_id);
                if (!item) return null;
                const value = item.amount != null ? formatCurrency(item.amount) : item.text_value;
                return (
                  <div className="w-full flex justify-between items-center gap-2">
                    <span className="font-bold shrink-0">{element.label || item.label_ar_snapshot}:</span>
                    <span className="font-mono">{value}</span>
                  </div>
                );
              }
              case 'total':
                return (
                  <div className="w-full flex flex-col justify-center gap-1">
                    <div className="flex justify-between items-center font-bold text-base border-t border-gray-800 pt-1">
                      <span>المجموع الإجمالي:</span>
                      <span className="font-mono">{formatCurrency(receipt.total_amount)}</span>
                    </div>
                    <div className="text-[10px] text-gray-600 font-semibold text-right">
                      {amountToArabicWords(parseFloat(receipt.total_amount))} دينار عراقي
                    </div>
                  </div>
                );
              case 'qr':
                return qrSvg ? (
                  <img src={qrSvg} alt="QR Code" className="w-full h-full object-contain" />
                ) : (
                  <div className="w-full h-full border border-dashed border-gray-300 flex items-center justify-center text-[10px] text-gray-400">QR Code</div>
                );
              case 'signature':
                return (
                  <div className="w-full h-full flex flex-col justify-end border-b border-gray-300 pb-1">
                    <span className="text-[10px] text-gray-400 text-center font-mono italic">أمين الصندوق</span>
                    <span className="text-xs text-center font-bold opacity-80">{receipt.created_by?.name}</span>
                  </div>
                );
              case 'divider':
                return <div className="w-full border-b border-gray-800 my-auto" />;
              case 'image':
                return logo ? (
                  <img src={logo} alt="Corporate Logo" className="w-full h-full object-contain" />
                ) : (
                  <div className="w-full h-full border border-dashed border-gray-300 flex items-center justify-center text-[10px] text-gray-400">Logo</div>
                );
              case 'spacer':
                return null;
              case 'text':
              default:
                return <span className="w-full font-bold">{element.label}</span>;
            }
          };

          return (
            <div key={element.id} style={elementStyle}>
              {renderElementValue()}
            </div>
          );
        })}
    </div>
  );
}
