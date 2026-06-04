import React from 'react';
import { useTemplateDesigner } from '@/hooks/useTemplateDesigner';
import type { TemplateElement as TemplateElementType } from '@/types/template';

interface PreviewPaneProps {
  templateId: string | null;
}

export const PreviewPane: React.FC<PreviewPaneProps> = ({ templateId }) => {
  const { template, isLoading, error } = useTemplateDesigner(templateId ?? '');

  if (!templateId) {
    return <div className="p-4 text-gray-500 text-center">لا توجد معاينة متاحة</div>;
  }

  if (isLoading) {
    return <div className="p-4 text-gray-500 text-center">جارٍ تحميل المعاينة...</div>;
  }

  if (error) {
    return <div className="p-4 text-red-500 text-center">خطأ في تحميل المعاينة</div>;
  }

  if (!template) {
    return <div className="p-4 text-gray-500 text-center">لم يتم العثور على القالب</div>;
  }

  // Render element in preview (similar to CanvasDesigner but non-interactive)
  const renderPreviewElement = (element: TemplateElementType) => {
    const previewStyle: React.CSSProperties = {
      position: 'absolute',
      left: `${element.x}px`,
      top: `${element.y}px`,
      width: `${element.width}px`,
      height: `${element.height}px`,
      fontFamily: element.style?.font_family || 'Arial',
      fontSize: element.style?.font_size ? `${element.style.font_size}px` : '13px',
      fontWeight: element.style?.font_weight || 'normal',
      color: element.style?.font_color || '#1e293b',
      backgroundColor: element.style?.background_color || '#ffffff',
      borderColor: element.style?.border_color || '#cbd5e1',
      borderWidth: element.style?.border_width != null ? `${element.style.border_width}px` : '1px',
      borderStyle: element.style?.border_width ? 'solid' : 'dashed',
      textAlign: (element.style?.text_align || 'right') as any,
      opacity: element.style?.opacity ?? 1,
      display: element.style?.display === 'none' ? 'none' : 'flex',
      alignItems: 'center',
      justifyContent: element.style?.text_align === 'left' ? 'flex-start' : element.style?.text_align === 'center' ? 'center' : 'flex-end',
      paddingTop: `${element.style?.padding?.top ?? 6}px`,
      paddingRight: `${element.style?.padding?.right ?? 6}px`,
      paddingBottom: `${element.style?.padding?.bottom ?? 6}px`,
      paddingLeft: `${element.style?.padding?.left ?? 6}px`,
      overflow: 'hidden',
    };

    const renderContent = () => {
      switch (element.element_type) {
        case 'field':
          return (
            <div className="w-full flex justify-between items-center gap-2">
              <span className="font-bold opacity-80 shrink-0 text-slate-700">{element.label}:</span>
              <span className="opacity-45 text-[11px] italic truncate font-mono">[حقل]</span>
            </div>
          );
        case 'total':
          return (
            <div className="w-full flex justify-between items-center gap-2 border-t pt-1 border-dashed border-slate-300">
              <span className="font-bold text-sm text-blue-700">المجموع النهائي:</span>
              <span className="font-bold text-sm text-blue-700 font-mono">150,000 د.ع</span>
            </div>
          );
        case 'qr':
          return (
            <div className="w-full h-full flex flex-col items-center justify-center bg-gray-50 border border-gray-100 p-1 rounded">
              <span className="text-xl">🔳</span>
              <span className="text-[9px] text-gray-500 scale-90">رمز التحقق السريع</span>
            </div>
          );
        case 'signature':
          return (
            <div className="w-full h-full flex flex-col justify-end border-b border-gray-200 pb-1">
              <span className="text-[10px] text-gray-400 text-center font-mono italic">توقيع المخول</span>
              <span className="text-xs text-center font-bold opacity-80">{element.label || 'أمين الصندوق'}</span>
            </div>
          );
        case 'divider':
          return <div className="w-full border-b border-slate-400 my-auto" />;
        case 'image':
          return (
            <div className="w-full h-full flex items-center justify-center bg-gray-50 border border-gray-150 rounded">
              <span className="text-xs text-gray-400 font-semibold flex items-center gap-1">🖼️ شعار المؤسسة</span>
            </div>
          );
        case 'spacer':
          return <div className="w-full h-full bg-slate-50/20 border border-dashed border-slate-200/40" />;
        case 'text':
        default:
          return (
            <span className="w-full text-center font-bold truncate">
              {element.label || 'نص ثابت'}
            </span>
          );
      }
    };

    return (
      <div
        key={element.id}
        className="rounded select-none"
        style={previewStyle}
      >
        <div className="w-full h-full overflow-hidden flex items-center">
          {renderContent()}
        </div>
      </div>
    );
  };

  return (
    <div className="h-full flex flex-col bg-gray-50 overflow-auto">
      <div className="p-4 border-b border-gray-200 bg-white sticky top-0 z-10">
        <h2 className="text-lg font-bold text-gray-800">معاينة القالب</h2>
        <p className="text-sm text-gray-600">{template.name}</p>
      </div>

      {/* Preview Canvas - Real-time visualization */}
      <div className="flex-1 p-4 flex justify-center items-start overflow-auto">
        <div
          className="relative bg-white shadow-lg border border-gray-300 rounded"
          style={{
            width: `${template.page_width}mm`,
            height: `${template.page_height}mm`,
            backgroundColor: template.background_color || '#ffffff',
            backgroundImage: 'radial-gradient(circle, #e5e7eb 0.8px, transparent 0.8px)',
            backgroundSize: '20px 20px',
          }}
        >
          {/* Watermark */}
          {(template as any).watermark_text && (
            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
              <span className="text-6xl font-bold text-gray-300 opacity-20 select-none" style={{ transform: 'rotate(-30deg)' }}>
                {(template as any).watermark_text}
              </span>
            </div>
          )}

          {/* Elements */}
          <div className="w-full h-full relative">
            {template.elements.map((element) => renderPreviewElement(element))}
          </div>
        </div>
      </div>
    </div>
  );
};
export default PreviewPane;
