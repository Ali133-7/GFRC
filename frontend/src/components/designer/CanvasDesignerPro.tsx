import { useRef, useState, useCallback } from 'react';
import type { ReceiptTemplate, TemplateElement } from '@/types/template';

interface Props {
  template: ReceiptTemplate;
  selectedId: string | null;
  onSelect: (id: string | null) => void;
  onUpdate: (id: string, updates: Partial<TemplateElement>, saveToServer?: boolean) => void;
  onSyncPosition: (id: string, x: number, y: number) => void;
  onSyncSize: (id: string, w: number, h: number) => void;
  snapToGrid: boolean;
  zoom: number;
}

interface DragState {
  active: boolean;
  id: string | null;
  startX: number;
  startY: number;
  elX: number;
  elY: number;
}

interface ResizeState {
  active: boolean;
  id: string | null;
  startX: number;
  startY: number;
  elW: number;
  elH: number;
}

const snap = (val: number, grid: boolean) => (grid ? Math.round(val / 10) * 10 : val);

const elementTypeIcons: Record<string, string> = {
  field: '📋',
  text: '📝',
  divider: '➖',
  qr: '🔳',
  signature: '✍️',
  total: '💰',
  image: '🖼️',
  spacer: '⬜',
};

export default function CanvasDesignerPro({ template, selectedId, onSelect, onUpdate, onSyncPosition, onSyncSize, snapToGrid, zoom }: Props) {
  const canvasRef = useRef<HTMLDivElement>(null);
  const [drag, setDrag] = useState<DragState>({ active: false, id: null, startX: 0, startY: 0, elX: 0, elY: 0 });
  const [resize, setResize] = useState<ResizeState>({ active: false, id: null, startX: 0, startY: 0, elW: 0, elH: 0 });
  const [hoveredId, setHoveredId] = useState<string | null>(null);
  const [showGuides, setShowGuides] = useState(true);

  const handleMouseDown = useCallback((e: React.MouseEvent, elementId: string) => {
    if ((e.target as HTMLElement).classList.contains('resize-handle')) return;
    e.preventDefault();
    onSelect(elementId);
    const el = template.elements.find((x) => x.id === elementId);
    if (!el) return;
    setDrag({ active: true, id: elementId, startX: e.clientX, startY: e.clientY, elX: el.x, elY: el.y });
  }, [template, onSelect]);

  const handleResizeStart = useCallback((e: React.MouseEvent, elementId: string) => {
    e.preventDefault();
    e.stopPropagation();
    const el = template.elements.find((x) => x.id === elementId);
    if (!el) return;
    setResize({ active: true, id: elementId, startX: e.clientX, startY: e.clientY, elW: el.width, elH: el.height });
  }, [template]);

  const handleMouseMove = useCallback((e: React.MouseEvent) => {
    if (drag.active && drag.id) {
      let nx = drag.elX + (e.clientX - drag.startX) / zoom;
      let ny = drag.elY + (e.clientY - drag.startY) / zoom;
      nx = Math.max(0, snap(nx, snapToGrid));
      ny = Math.max(0, snap(ny, snapToGrid));
      onUpdate(drag.id, { x: nx, y: ny }, false);
    }
    if (resize.active && resize.id) {
      let nw = resize.elW + (e.clientX - resize.startX) / zoom;
      let nh = resize.elH + (e.clientY - resize.startY) / zoom;
      nw = Math.max(10, snap(nw, snapToGrid));
      nh = Math.max(10, snap(nh, snapToGrid));
      onUpdate(resize.id, { width: nw, height: nh }, false);
    }
  }, [drag, resize, zoom, snapToGrid, onUpdate]);

  const handleMouseUp = useCallback(() => {
    if (drag.active && drag.id) {
      const el = template.elements.find((x) => x.id === drag.id);
      if (el) onSyncPosition(drag.id, el.x, el.y);
    }
    if (resize.active && resize.id) {
      const el = template.elements.find((x) => x.id === resize.id);
      if (el) onSyncSize(resize.id, el.width, el.height);
    }
    setDrag({ active: false, id: null, startX: 0, startY: 0, elX: 0, elY: 0 });
    setResize({ active: false, id: null, startX: 0, startY: 0, elW: 0, elH: 0 });
  }, [drag, resize, template, onSyncPosition, onSyncSize]);

  const renderContent = (el: TemplateElement) => {
    switch (el.element_type) {
      case 'field':
        return (
          <div className="w-full flex justify-between items-center gap-2 text-xs">
            <span className="font-semibold opacity-80 text-slate-700 truncate">{el.label}:</span>
            <span className="opacity-40 italic truncate font-mono">[قيمة]</span>
          </div>
        );
      case 'total':
        return (
          <div className="w-full flex justify-between items-center gap-2 border-t border-dashed border-slate-300 pt-1 text-xs">
            <span className="font-bold text-blue-700">المجموع:</span>
            <span className="font-bold text-blue-700 font-mono">150,000 د.ع</span>
          </div>
        );
      case 'qr':
        return (
          <div className="w-full h-full flex flex-col items-center justify-center bg-gray-50 border border-gray-100 rounded text-[9px] text-gray-400">
            <span className="text-lg">🔳</span>
            <span>QR</span>
          </div>
        );
      case 'signature':
        return (
          <div className="w-full h-full flex flex-col justify-end border-b border-gray-300 pb-1">
            <span className="text-[9px] text-gray-400 text-center font-mono italic">توقيع المخول</span>
            <span className="text-xs text-center font-bold opacity-80">{el.label || 'أمين الصندوق'}</span>
          </div>
        );
      case 'divider':
        return <div className="w-full h-px bg-slate-400 my-auto" />;
      case 'image':
        return (
          <div className="w-full h-full flex items-center justify-center bg-gray-50 border border-gray-200 rounded text-xs text-gray-400">
            🖼️ {el.label || 'صورة'}
          </div>
        );
      case 'spacer':
        return <div className="w-full h-full bg-slate-50/30 border border-dashed border-slate-200/50 rounded" />;
      default:
        return <span className="w-full text-center font-bold truncate text-xs">{el.label || 'نص'}</span>;
    }
  };

  // Alignment guides
  const guides: { x: number; y: number; w: number; h: number; type: 'h' | 'v' }[] = [];
  if (showGuides && selectedId && (drag.active || resize.active)) {
    const sel = template.elements.find((e) => e.id === selectedId);
    if (sel) {
      template.elements.forEach((el) => {
        if (el.id === selectedId) return;
        // Horizontal alignment (top, center, bottom)
        if (Math.abs(el.y - sel.y) < 3) guides.push({ x: 0, y: sel.y, w: template.page_width * 3.78, h: 1, type: 'h' });
        if (Math.abs((el.y + el.height / 2) - (sel.y + sel.height / 2)) < 3) guides.push({ x: 0, y: sel.y + sel.height / 2, w: template.page_width * 3.78, h: 1, type: 'h' });
        if (Math.abs((el.y + el.height) - (sel.y + sel.height)) < 3) guides.push({ x: 0, y: sel.y + sel.height, w: template.page_width * 3.78, h: 1, type: 'h' });
        // Vertical alignment (left, center, right)
        if (Math.abs(el.x - sel.x) < 3) guides.push({ x: sel.x, y: 0, w: 1, h: template.page_height * 3.78, type: 'v' });
        if (Math.abs((el.x + el.width / 2) - (sel.x + sel.width / 2)) < 3) guides.push({ x: sel.x + sel.width / 2, y: 0, w: 1, h: template.page_height * 3.78, type: 'v' });
        if (Math.abs((el.x + el.width) - (sel.x + sel.width)) < 3) guides.push({ x: sel.x + sel.width, y: 0, w: 1, h: template.page_height * 3.78, type: 'v' });
      });
    }
  }

  const pagePxWidth = Math.round(template.page_width * 3.78);
  const pagePxHeight = Math.round(template.page_height * 3.78);

  return (
    <div className="flex-1 bg-slate-100 overflow-auto flex flex-col select-none">
      {/* Sub-toolbar */}
      <div className="bg-white border-b border-gray-200 px-3 py-1.5 flex items-center gap-3 no-print">
        <label className="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
          <input type="checkbox" checked={showGuides} onChange={(e) => setShowGuides(e.target.checked)} className="rounded" />
          دليل المحاذاة
        </label>
        <label className="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
          <input type="checkbox" checked={snapToGrid} readOnly className="rounded" />
          شبكة
        </label>
        <div className="flex-1" />
        <span className="text-xs text-gray-400 font-mono">{template.page_width}×{template.page_height}mm</span>
      </div>

      <div className="flex-1 overflow-auto p-6 flex justify-center items-start">
        <div
          ref={canvasRef}
          className="relative bg-white shadow-2xl rounded-sm"
          style={{
            width: pagePxWidth,
            height: pagePxHeight,
            backgroundColor: template.background_color || '#ffffff',
            transform: `scale(${zoom})`,
            transformOrigin: 'top center',
            marginBottom: `${(zoom - 1) * pagePxHeight}px`,
          }}
          onMouseMove={handleMouseMove}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseUp}
          onClick={(e) => { if (e.target === e.currentTarget) onSelect(null); }}
        >
          {/* Grid */}
          <div
            className="absolute inset-0 pointer-events-none opacity-40"
            style={{
              backgroundImage: 'radial-gradient(circle, #94a3b8 0.8px, transparent 0.8px)',
              backgroundSize: '15px 15px',
            }}
          />

          {/* Guides */}
          {guides.map((g, gi) => (
            <div
              key={`${g.type}-${gi}`}
              className="absolute pointer-events-none z-40"
              style={{
                left: g.x,
                top: g.y,
                width: g.type === 'h' ? g.w : 1,
                height: g.type === 'v' ? g.h : 1,
                backgroundColor: '#3b82f6',
                opacity: 0.6,
              }}
            />
          ))}

          {/* Elements */}
          {template.elements
            .slice()
            .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
            .map((el) => {
              const isSelected = selectedId === el.id;
              const isHovered = hoveredId === el.id;
              const isHidden = el.is_visible === false;

              const style: React.CSSProperties = {
                position: 'absolute',
                left: el.x,
                top: el.y,
                width: el.width,
                height: el.height,
                fontFamily: el.style?.font_family || 'Arial',
                fontSize: el.style?.font_size ? `${el.style.font_size}px` : '13px',
                fontWeight: el.style?.font_weight || 'normal',
                color: el.style?.font_color || '#1e293b',
                backgroundColor: el.style?.background_color || 'transparent',
                borderColor: isSelected ? '#3b82f6' : isHovered ? '#60a5fa' : el.style?.border_color || '#e2e8f0',
                borderWidth: isSelected ? '2px' : el.style?.border_width != null ? `${el.style.border_width}px` : '1px',
                borderStyle: isSelected ? 'solid' : isHovered ? 'dashed' : el.style?.border_width ? 'solid' : 'dashed',
                textAlign: el.style?.text_align || 'right',
                opacity: isHidden ? 0.25 : (el.style?.opacity ?? 1),
                display: 'flex',
                alignItems: 'center',
                justifyContent: el.style?.text_align === 'left' ? 'flex-start' : el.style?.text_align === 'center' ? 'center' : 'flex-end',
                paddingTop: `${el.style?.padding?.top ?? 4}px`,
                paddingRight: `${el.style?.padding?.right ?? 8}px`,
                paddingBottom: `${el.style?.padding?.bottom ?? 4}px`,
                paddingLeft: `${el.style?.padding?.left ?? 8}px`,
                boxShadow: isSelected ? '0 0 0 3px rgba(59,130,246,0.15)' : 'none',
                zIndex: isSelected ? 100 : isHovered ? 50 : (el.sort_order || 0) + 10,
                transition: drag.active || resize.active ? 'none' : 'box-shadow 0.15s',
                cursor: 'move',
                borderRadius: '2px',
                overflow: 'hidden',
              };

              return (
                <div
                  key={el.id}
                  style={style}
                  className={`${isSelected ? '' : ''}`}
                  onMouseDown={(e) => handleMouseDown(e, el.id)}
                  onMouseEnter={() => setHoveredId(el.id)}
                  onMouseLeave={() => setHoveredId(null)}
                >
                  <div className="w-full h-full overflow-hidden flex items-center pointer-events-none">
                    {renderContent(el)}
                  </div>

                  {isSelected && (
                    <>
                      {/* Resize handles */}
                      <div className="absolute -top-1 -left-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm z-[110]" />
                      <div className="absolute -top-1 -right-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm z-[110]" />
                      <div className="absolute -bottom-1 -left-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm z-[110]" />
                      <div
                        className="resize-handle absolute -bottom-1 -right-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm cursor-se-resize z-[110]"
                        onMouseDown={(e) => handleResizeStart(e, el.id)}
                      />
                      {/* Label */}
                      <div className="absolute -top-5 right-0 bg-blue-500 text-white text-[10px] px-1.5 py-0.5 rounded whitespace-nowrap pointer-events-none z-[110]">
                        {elementTypeIcons[el.element_type] || ''} {el.label || el.element_type}
                      </div>
                    </>
                  )}
                </div>
              );
            })}
        </div>
      </div>
    </div>
  );
}
