import { useState, useRef, useCallback, useEffect } from 'react';
import type { TemplateElement } from '@/types/template';

interface Props {
  elements: TemplateElement[];
  pageWidth: number;
  pageHeight: number;
  backgroundColor: string;
  selectedId: string | null;
  onSelect: (id: string | null) => void;
  onUpdateLocal: (id: string, updates: Partial<TemplateElement>) => void;
  onUpdateServer: (id: string, updates: Partial<TemplateElement>) => void;
  snapToGrid: boolean;
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

export default function CanvasDesignerV2({
  elements,
  pageWidth,
  pageHeight,
  backgroundColor,
  selectedId,
  onSelect,
  onUpdateLocal,
  onUpdateServer,
  snapToGrid,
}: Props) {
  const canvasRef = useRef<HTMLDivElement>(null);
  const [drag, setDrag] = useState<DragState>({ active: false, id: null, startX: 0, startY: 0, elX: 0, elY: 0 });
  const [resize, setResize] = useState<ResizeState>({ active: false, id: null, startX: 0, startY: 0, elW: 0, elH: 0 });
  const [hoveredId, setHoveredId] = useState<string | null>(null);
  const [zoom, setZoom] = useState(1);

  const snap = useCallback((val: number) => {
    if (!snapToGrid) return val;
    return Math.round(val / 10) * 10;
  }, [snapToGrid]);

  const handleMouseDown = useCallback((e: React.MouseEvent, elementId: string) => {
    if ((e.target as HTMLElement).classList.contains('resize-handle')) return;
    e.preventDefault();
    onSelect(elementId);
    const el = elements.find((x) => x.id === elementId);
    if (!el) return;
    setDrag({ active: true, id: elementId, startX: e.clientX, startY: e.clientY, elX: el.x, elY: el.y });
  }, [elements, onSelect]);

  const handleResizeStart = useCallback((e: React.MouseEvent, elementId: string) => {
    e.preventDefault();
    e.stopPropagation();
    const el = elements.find((x) => x.id === elementId);
    if (!el) return;
    setResize({ active: true, id: elementId, startX: e.clientX, startY: e.clientY, elW: el.width, elH: el.height });
  }, [elements]);

  const handleMouseMove = useCallback((e: React.MouseEvent) => {
    if (drag.active && drag.id) {
      let nx = drag.elX + (e.clientX - drag.startX) / zoom;
      let ny = drag.elY + (e.clientY - drag.startY) / zoom;
      nx = Math.max(0, snap(nx));
      ny = Math.max(0, snap(ny));
      onUpdateLocal(drag.id, { x: nx, y: ny });
    }
    if (resize.active && resize.id) {
      let nw = resize.elW + (e.clientX - resize.startX) / zoom;
      let nh = resize.elH + (e.clientY - resize.startY) / zoom;
      nw = Math.max(10, snap(nw));
      nh = Math.max(10, snap(nh));
      onUpdateLocal(resize.id, { width: nw, height: nh });
    }
  }, [drag, resize, zoom, snap, onUpdateLocal]);

  const handleMouseUp = useCallback(() => {
    if (drag.active && drag.id) {
      const el = elements.find((x) => x.id === drag.id);
      if (el) onUpdateServer(drag.id, { x: el.x, y: el.y });
    }
    if (resize.active && resize.id) {
      const el = elements.find((x) => x.id === resize.id);
      if (el) onUpdateServer(resize.id, { width: el.width, height: el.height });
    }
    setDrag((p) => ({ ...p, active: false }));
    setResize((p) => ({ ...p, active: false }));
  }, [drag, resize, elements, onUpdateServer]);

  // Keyboard shortcuts
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Delete' || e.key === 'Backspace') {
        // Let parent handle delete via selectedId
      }
      if (e.key === 'Escape') {
        onSelect(null);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onSelect]);

  const renderContent = (el: TemplateElement) => {
    switch (el.element_type) {
      case 'field':
        return (
          <div className="w-full flex justify-between items-center gap-2">
            <span className="font-bold opacity-80 shrink-0 text-slate-700 text-xs">{el.label}:</span>
            <span className="opacity-45 text-[11px] italic truncate font-mono">[حقل]</span>
          </div>
        );
      case 'total':
        return (
          <div className="w-full flex justify-between items-center gap-2 border-t pt-1 border-dashed border-slate-300">
            <span className="font-bold text-xs text-blue-700">المجموع:</span>
            <span className="font-bold text-xs text-blue-700 font-mono">150,000 د.ع</span>
          </div>
        );
      case 'qr':
        return (
          <div className="w-full h-full flex flex-col items-center justify-center bg-gray-50 border border-gray-100 p-1 rounded">
            <span className="text-xl">🔳</span>
            <span className="text-[9px] text-gray-500 scale-90">QR</span>
          </div>
        );
      case 'signature':
        return (
          <div className="w-full h-full flex flex-col justify-end border-b border-gray-200 pb-1">
            <span className="text-[10px] text-gray-400 text-center font-mono italic">توقيع</span>
            <span className="text-xs text-center font-bold opacity-80">{el.label || 'أمين الصندوق'}</span>
          </div>
        );
      case 'divider':
        return <div className="w-full border-b border-slate-400 my-auto" />;
      case 'image':
        return (
          <div className="w-full h-full flex items-center justify-center bg-gray-50 border rounded">
            <span className="text-xs text-gray-400 font-semibold">🖼️ {el.label || 'صورة'}</span>
          </div>
        );
      case 'spacer':
        return <div className="w-full h-full bg-slate-50/20 border border-dashed border-slate-200/40" />;
      default:
        return <span className="w-full text-center font-bold truncate text-xs">{el.label || 'نص'}</span>;
    }
  };

  return (
    <div className="flex-1 bg-slate-200 overflow-auto flex flex-col">
      {/* Zoom bar */}
      <div className="bg-white border-b border-gray-200 px-3 py-1.5 flex items-center gap-3 no-print">
        <span className="text-xs text-gray-500">التكبير:</span>
        <input
          type="range"
          min={25}
          max={200}
          step={5}
          value={zoom * 100}
          onChange={(e) => setZoom(parseInt(e.target.value) / 100)}
          className="w-32"
        />
        <span className="text-xs font-mono text-gray-600 w-10">{Math.round(zoom * 100)}%</span>
        <div className="flex-1" />
        <span className="text-xs text-gray-400">{pageWidth}×{pageHeight}mm | {elements.length} عنصر</span>
      </div>

      <div className="flex-1 overflow-auto p-6 flex justify-center items-start">
        <div
          className="relative bg-white shadow-xl border border-slate-300 rounded"
          style={{
            width: `${pageWidth}mm`,
            height: `${pageHeight}mm`,
            backgroundColor,
            transform: `scale(${zoom})`,
            transformOrigin: 'top center',
            marginBottom: `${(zoom - 1) * pageHeight * 3.78}px`, // compensate for scale
          }}
          onMouseMove={handleMouseMove}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseUp}
          onClick={(e) => { if (e.target === e.currentTarget) onSelect(null); }}
          ref={canvasRef}
        >
          {/* Grid */}
          <div
            className="absolute inset-0 pointer-events-none"
            style={{
              backgroundImage: 'radial-gradient(circle, #cbd5e1 1px, transparent 1px)',
              backgroundSize: '15px 15px',
            }}
          />

          {/* Elements */}
          {elements.map((el) => {
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
              borderColor: isSelected ? '#3b82f6' : isHovered ? '#60a5fa' : el.style?.border_color || '#cbd5e1',
              borderWidth: isSelected ? '2px' : el.style?.border_width != null ? `${el.style.border_width}px` : '1px',
              borderStyle: isSelected ? 'solid' : isHovered ? 'dashed' : el.style?.border_width ? 'solid' : 'dashed',
              textAlign: el.style?.text_align || 'right',
              opacity: isHidden ? 0.3 : (el.style?.opacity ?? 1),
              display: 'flex',
              alignItems: 'center',
              justifyContent: el.style?.text_align === 'left' ? 'flex-start' : el.style?.text_align === 'center' ? 'center' : 'flex-end',
              paddingTop: `${el.style?.padding?.top ?? 6}px`,
              paddingRight: `${el.style?.padding?.right ?? 6}px`,
              paddingBottom: `${el.style?.padding?.bottom ?? 6}px`,
              paddingLeft: `${el.style?.padding?.left ?? 6}px`,
              boxShadow: isSelected ? '0 4px 12px rgba(59,130,246,0.15)' : 'none',
              zIndex: isSelected ? 50 : 10,
              transition: drag.active || resize.active ? 'none' : 'border-color 0.15s, box-shadow 0.15s',
              cursor: 'move',
            };

            return (
              <div
                key={el.id}
                style={style}
                className={`rounded select-none group ${isSelected ? 'ring-2 ring-blue-500/20' : ''}`}
                onMouseDown={(e) => handleMouseDown(e, el.id)}
                onMouseEnter={() => setHoveredId(el.id)}
                onMouseLeave={() => setHoveredId(null)}
              >
                <div className="w-full h-full overflow-hidden flex items-center">
                  {renderContent(el)}
                </div>

                {isSelected && (
                  <>
                    {/* Corner handles */}
                    <div className="absolute -top-1 -left-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm z-50" />
                    <div className="absolute -top-1 -right-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm z-50" />
                    <div className="absolute -bottom-1 -left-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm z-50" />
                    <div
                      className="resize-handle absolute -bottom-1 -right-1 w-2 h-2 bg-white border-2 border-blue-500 rounded-sm cursor-se-resize z-50"
                      onMouseDown={(e) => handleResizeStart(e, el.id)}
                    />
                    {/* Label */}
                    <div className="absolute -top-5 right-0 bg-blue-500 text-white text-[10px] px-1.5 py-0.5 rounded whitespace-nowrap pointer-events-none">
                      {el.label || el.element_type}
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
