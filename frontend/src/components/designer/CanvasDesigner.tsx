import React, { useState, useRef, useCallback } from 'react';
import { useTemplateDesigner } from '@/hooks/useTemplateDesigner';
import StylePanel from './StylePanel';
import FieldEditor from './FieldEditor';
import DesignerToolbar from './DesignerToolbar';
import type { TemplateElement as TemplateElementType } from '@/types/template';
import { logError } from '@/utils/errorHandler';
import apiClient from '@/services/apiClient';

interface CanvasDesignerProps {
  templateId: string;
  onSave?: (templateData: any) => void;
}

interface DragState {
  isDragging: boolean;
  elementId: string | null;
  startX: number;
  startY: number;
  startElementX: number;
  startElementY: number;
}

interface ResizeState {
  isResizing: boolean;
  elementId: string | null;
  startX: number;
  startY: number;
  startWidth: number;
  startHeight: number;
}

export default function CanvasDesigner({ templateId, onSave }: CanvasDesignerProps) {
  const canvasRef = useRef<HTMLDivElement>(null);
  const { 
    template, 
    updateElementLocal, 
    saveElementToServer, 
    selectedElement, 
    setSelectedElement, 
    deleteElement,
    clearElements 
  } = useTemplateDesigner(templateId);

  const [snapToGrid, setSnapToGrid] = useState(true);
  const [hoveredElement, setHoveredElement] = useState<string | null>(null);

  const [dragState, setDragState] = useState<DragState>({
    isDragging: false,
    elementId: null,
    startX: 0,
    startY: 0,
    startElementX: 0,
    startElementY: 0,
  });

  const [resizeState, setResizeState] = useState<ResizeState>({
    isResizing: false,
    elementId: null,
    startX: 0,
    startY: 0,
    startWidth: 0,
    startHeight: 0,
  });

  const [showFieldEditor, setShowFieldEditor] = useState(false);

  // Handle mouse down on element (drag start)
  const handleMouseDown = useCallback((e: React.MouseEvent, elementId: string) => {
    // If clicking a resize handle, ignore drag start
    if ((e.target as HTMLElement).classList.contains('resize-handle')) {
      return;
    }

    e.preventDefault();
    setSelectedElement(elementId);

    const element = template?.elements.find((el) => el.id === elementId);
    if (!element || !canvasRef.current) return;

    setDragState({
      isDragging: true,
      elementId,
      startX: e.clientX,
      startY: e.clientY,
      startElementX: element.x,
      startElementY: element.y,
    });
  }, [template, setSelectedElement]);

  // Handle mouse move (60fps local drag/resize update)
  const handleMouseMove = useCallback((e: React.MouseEvent) => {
    if (dragState.isDragging && dragState.elementId) {
      const deltaX = e.clientX - dragState.startX;
      const deltaY = e.clientY - dragState.startY;

      let newX = dragState.startElementX + deltaX;
      let newY = dragState.startElementY + deltaY;

      // Apply grid snapping (10px increments)
      if (snapToGrid) {
        newX = Math.round(newX / 10) * 10;
        newY = Math.round(newY / 10) * 10;
      }

      newX = Math.max(0, newX);
      newY = Math.max(0, newY);

      updateElementLocal(dragState.elementId, { x: newX, y: newY });
    }

    if (resizeState.isResizing && resizeState.elementId) {
      const deltaX = e.clientX - resizeState.startX;
      const deltaY = e.clientY - resizeState.startY;

      let newWidth = resizeState.startWidth + deltaX;
      let newHeight = resizeState.startHeight + deltaY;

      // Apply grid snapping (10px increments)
      if (snapToGrid) {
        newWidth = Math.round(newWidth / 10) * 10;
        newHeight = Math.round(newHeight / 10) * 10;
      }

      newWidth = Math.max(10, newWidth);
      newHeight = Math.max(10, newHeight);

      updateElementLocal(resizeState.elementId, { width: newWidth, height: newHeight });
    }
  }, [dragState, resizeState, updateElementLocal, snapToGrid]);

  // Handle mouse up (single API save on drag/resize completion)
  const handleMouseUp = useCallback(() => {
    if (dragState.isDragging && dragState.elementId) {
      const element = template?.elements.find((el) => el.id === dragState.elementId);
      if (element) {
        saveElementToServer(dragState.elementId, { x: element.x, y: element.y });
      }
    }

    if (resizeState.isResizing && resizeState.elementId) {
      const element = template?.elements.find((el) => el.id === resizeState.elementId);
      if (element) {
        saveElementToServer(resizeState.elementId, { width: element.width, height: element.height });
      }
    }

    setDragState((prev) => ({ ...prev, isDragging: false }));
    setResizeState((prev) => ({ ...prev, isResizing: false }));
  }, [dragState, resizeState, template, saveElementToServer]);

  // Handle resize start
  const handleResizeStart = useCallback((e: React.MouseEvent, elementId: string) => {
    e.preventDefault();
    e.stopPropagation();

    const element = template?.elements.find((el) => el.id === elementId);
    if (!element) return;

    setResizeState({
      isResizing: true,
      elementId,
      startX: e.clientX,
      startY: e.clientY,
      startWidth: element.width,
      startHeight: element.height,
    });
  }, [template]);

  // Render element on canvas
  const renderCanvasElement = (element: TemplateElementType) => {
    const isSelected = selectedElement === element.id;
    const isHovered = hoveredElement === element.id;

    // Custom CSS style mimicking Photoshop/Figma active boundaries
    const customStyle: React.CSSProperties = {
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
      borderColor: isSelected 
        ? '#3b82f6' 
        : isHovered 
          ? '#60a5fa' 
          : element.style?.border_color || '#cbd5e1',
      borderWidth: isSelected 
        ? '2px' 
        : element.style?.border_width != null 
          ? `${element.style.border_width}px` 
          : '1px',
      borderStyle: isSelected 
        ? 'solid' 
        : isHovered 
          ? 'dashed' 
          : element.style?.border_width 
            ? 'solid' 
            : 'dashed',
      textAlign: element.style?.text_align || 'right',
      opacity: element.style?.opacity ?? 1,
      display: element.style?.display === 'none' ? 'none' : 'flex',
      alignItems: 'center',
      justifyContent: element.style?.text_align === 'left' ? 'flex-start' : element.style?.text_align === 'center' ? 'center' : 'flex-end',
      paddingTop: `${element.style?.padding?.top ?? 6}px`,
      paddingRight: `${element.style?.padding?.right ?? 6}px`,
      paddingBottom: `${element.style?.padding?.bottom ?? 6}px`,
      paddingLeft: `${element.style?.padding?.left ?? 6}px`,
      boxShadow: isSelected ? '0 10px 15px -3px rgba(59, 130, 246, 0.1), 0 4px 6px -2px rgba(59, 130, 246, 0.05)' : 'none',
      zIndex: isSelected ? 50 : 10,
      transition: dragState.isDragging || resizeState.isResizing ? 'none' : 'border-color 0.15s, border-style 0.15s, box-shadow 0.15s',
    };

    // Render realistic placeholder content based on element type
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
        className={`rounded select-none group transition-all relative ${isSelected ? 'ring-2 ring-blue-500/20' : ''}`}
        style={customStyle}
        onMouseDown={(e) => handleMouseDown(e, element.id)}
        onMouseEnter={() => setHoveredElement(element.id)}
        onMouseLeave={() => setHoveredElement(null)}
      >
        <div className="w-full h-full overflow-hidden flex items-center">
          {renderContent()}
        </div>

        {/* Photoshop Bounding Box corner handles */}
        {isSelected && (
          <>
            <div className="absolute -top-1 -left-1 w-2.5 h-2.5 bg-white border-2 border-blue-500 rounded-sm cursor-nwse-resize z-50 shadow-sm" />
            <div className="absolute -top-1 -right-1 w-2.5 h-2.5 bg-white border-2 border-blue-500 rounded-sm cursor-nesw-resize z-50 shadow-sm" />
            <div className="absolute -bottom-1 -left-1 w-2.5 h-2.5 bg-white border-2 border-blue-500 rounded-sm cursor-nesw-resize z-50 shadow-sm" />
            <div
              className="resize-handle absolute -bottom-1 -right-1 w-2.5 h-2.5 bg-white border-2 border-blue-500 rounded-sm cursor-se-resize z-50 shadow-sm"
              onMouseDown={(e) => handleResizeStart(e, element.id)}
              title="اسحب لتغيير الحجم"
            />
          </>
        )}

        {/* Quick Action Floating Overlay */}
        {isSelected && (
          <div 
            className="absolute -top-11 left-0 flex items-center bg-slate-900 text-white px-2 py-1 rounded shadow-lg gap-2 text-xs no-select z-50 transition-all hover:bg-black border border-slate-700/80 scale-95"
            onMouseDown={(e) => e.stopPropagation()} // Prevent dragging active element when clicking overlays
          >
            <button
              onClick={async () => {
                if (confirm('هل أنت متأكد من رغبتك في حذف هذا العنصر؟')) {
                  try {
                    await deleteElement(element.id);
                    // No need to reload page, UI updates via local state
                  } catch (e) {
                    logError(e, 'حذف العنصر');
                    alert('فشل حذف العنصر');
                  }
                }
              }}
              className="hover:text-red-400 p-1 font-bold flex items-center gap-0.5"
              title="حذف سريع"
            >
              🗑️
            </button>
            <span className="text-slate-700 font-light">|</span>
            <button
              onClick={async () => {
                try {
                  const cloneData = {
                    element_type: element.element_type,
                    label: element.label + ' (نسخة)',
                    field_id: element.field_id,
                    x: element.x + 20,
                    y: element.y + 20,
                    width: element.width,
                    height: element.height,
                    is_visible: element.is_visible,
                    style: element.style,
                  };
                  await apiClient.post(`/templates/${templateId}/elements`, cloneData);
                  window.location.reload();
                } catch (e) {
                  logError(e, 'تكرار العنصر');
                }
              }}
              className="hover:text-blue-400 p-1 font-bold flex items-center gap-0.5"
              title="تكرار العنصر"
            >
              📑
            </button>
            <span className="text-slate-700 font-light">|</span>
            <button
              onClick={async () => {
                try {
                  const newSort = (element.sort_order ?? 0) + 1;
                  updateElementLocal(element.id, { sort_order: newSort });
                  await saveElementToServer(element.id, { sort_order: newSort });
                } catch (e) {
                  logError(e, 'إرسال العنصر للأمام');
                }
              }}
              className="hover:text-amber-400 p-1 font-bold"
              title="إرسال للأمام"
            >
              🔼
            </button>
            <button
              onClick={async () => {
                try {
                  const newSort = Math.max(0, (element.sort_order ?? 0) - 1);
                  updateElementLocal(element.id, { sort_order: newSort });
                  await saveElementToServer(element.id, { sort_order: newSort });
                } catch (e) {
                  logError(e, 'إرسال العنصر للخلف');
                }
              }}
              className="hover:text-amber-400 p-1 font-bold"
              title="إرسال للخلف"
            >
              🔽
            </button>
          </div>
        )}

        {/* Small label for name */}
        {isSelected && (
          <div className="absolute top-0 right-0 -top-6 bg-blue-500 text-white text-[10px] px-1.5 py-0.5 rounded whitespace-nowrap shadow-sm pointer-events-none scale-90">
            {element.label || element.element_type}
          </div>
        )}
      </div>
    );
  };

  if (!template) {
    return <div className="flex items-center justify-center h-96">جاري التحميل...</div>;
  }

  return (
    <div className="h-full flex flex-col gap-4 p-4 bg-gray-50">
      <DesignerToolbar
        templateId={templateId}
        onAddField={() => setShowFieldEditor(true)}
        onSave={onSave}
        snapToGrid={snapToGrid}
        onToggleSnapToGrid={() => setSnapToGrid(!snapToGrid)}
        onClear={async () => {
          if (confirm('هل أنت متأكد من رغبتك في حذف جميع عناصر التصميم والبدء من الصفر؟ لا يمكن التراجع عن هذا الإجراء.')) {
            await clearElements();
          }
        }}
      />

      <div className="flex-1 flex gap-4 overflow-hidden">
        {/* Canvas Area with dotted radial grid background */}
        <div className="flex-1 bg-slate-200 rounded-lg shadow-inner overflow-auto border border-gray-300 p-6 flex justify-center items-start">
          <div
            ref={canvasRef}
            className="relative bg-white shadow-xl border border-slate-300 rounded"
            style={{
              width: `${template.page_width}mm`,
              height: `${template.page_height}mm`,
              backgroundColor: template.background_color || '#ffffff',
              backgroundImage: 'radial-gradient(circle, #cbd5e1 1.2px, transparent 1.2px)',
              backgroundSize: '15px 15px',
            }}
            onMouseMove={handleMouseMove}
            onMouseUp={handleMouseUp}
            onMouseLeave={handleMouseUp}
          >
            <div className="w-full h-full relative">
              {template.elements.map((element) => renderCanvasElement(element))}
            </div>
          </div>
        </div>

        <div className="w-80 space-y-4 overflow-y-auto shrink-0">
          {selectedElement && (
            <StylePanel
              elementId={selectedElement}
              templateId={templateId}
              currentStyle={template.elements.find((el) => el.id === selectedElement)?.style}
            />
          )}

          {showFieldEditor && (
            <FieldEditor
              templateId={templateId}
              registerId={template.register_id}
              onClose={() => setShowFieldEditor(false)}
            />
          )}

          {!selectedElement && !showFieldEditor && (
            <div className="bg-white rounded-lg p-4 border border-gray-200 text-center text-gray-500 shadow-sm">
              <p className="text-sm">اختر عنصراً من ساحة العمل لتحريكه أو تنسيقه 🎨</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
