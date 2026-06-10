import { forwardRef, useId, useMemo, useRef, useState, useEffect, useLayoutEffect } from 'react';
import * as Select from '@radix-ui/react-select';
import { createPortal } from 'react-dom';

interface Option {
  value: string;
  label: string;
}

/* ------------------------------------------------------------------ */
/*  GovSelect (single)                                                */
/* ------------------------------------------------------------------ */

interface GovSelectProps {
  label?: string;
  error?: string;
  options: Option[];
  placeholder?: string;
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  onValueChange?: (value: string) => void;
  disabled?: boolean;
  className?: string;
  name?: string;
  required?: boolean;
  id?: string;
}

export const GovSelect = forwardRef<HTMLButtonElement, GovSelectProps>(
  (
    {
      label,
      error,
      options,
      placeholder = 'اختر...',
      value,
      defaultValue,
      onChange,
      onValueChange,
      disabled,
      className = '',
      name,
      required,
      id: idProp,
    },
    ref
  ) => {
    const generatedId = useId();
    const id = idProp ?? generatedId;
    const errorId = error ? `${id}-error` : undefined;

    const handleValueChange = (val: string) => {
      onValueChange?.(val);
      onChange?.(val);
    };

    const isRtl = useMemo(
      () => document.documentElement.dir === 'rtl',
      []
    );

    const triggerBase =
      'w-full rounded-md border bg-white px-3 py-2 text-sm text-gray-900 ' +
      'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ' +
      'disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 ' +
      'flex items-center justify-between gap-2 transition-colors';

    const triggerBorder = error
      ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
      : 'border-gray-300';

    return (
      <div className={`w-full ${className}`}>
        {label && (
          <label
            htmlFor={id}
            className="block text-sm font-medium text-gray-700 mb-1"
          >
            {label}
            {required && <span className="text-red-500 mr-1">*</span>}
          </label>
        )}

        <Select.Root
          value={value}
          defaultValue={defaultValue}
          onValueChange={handleValueChange}
          disabled={disabled}
          name={name}
          dir={isRtl ? 'rtl' : 'ltr'}
        >
          <Select.Trigger
            ref={ref}
            id={id}
            aria-invalid={error ? 'true' : 'false'}
            aria-describedby={errorId}
            className={`${triggerBase} ${triggerBorder}`}
          >
            <Select.Value placeholder={placeholder} />
            <Select.Icon className="text-gray-500 shrink-0">
              <svg
                width="12"
                height="12"
                viewBox="0 0 12 12"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
              >
                <path
                  d="M2.5 4.5L6 8L9.5 4.5"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </Select.Icon>
          </Select.Trigger>

          <Select.Portal>
            <Select.Content
              position="popper"
              sideOffset={4}
              align="start"
              className="z-[9999] overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
              <Select.ScrollUpButton className="flex h-6 cursor-default items-center justify-center bg-white text-gray-500 hover:bg-gray-50">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                  <path d="M6 3L2.5 6.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                  <path d="M6 3L9.5 6.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                </svg>
              </Select.ScrollUpButton>

              <Select.Viewport className="p-1">
                {options.map((opt) => (
                  <Select.Item
                    key={opt.value}
                    value={opt.value}
                    className="relative flex cursor-pointer select-none items-center rounded-sm px-3 py-2 text-sm text-gray-900 outline-none transition-colors hover:bg-gray-100 focus:bg-gray-100 data-[state=checked]:bg-blue-50 data-[state=checked]:font-semibold data-[state=checked]:text-blue-700"
                  >
                    <Select.ItemText>{opt.label}</Select.ItemText>
                    <Select.ItemIndicator className="mr-auto ml-2 inline-flex items-center justify-center">
                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path
                          d="M2.5 6.5L5 9L9.5 3.5"
                          stroke="currentColor"
                          strokeWidth="1.5"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                      </svg>
                    </Select.ItemIndicator>
                  </Select.Item>
                ))}
              </Select.Viewport>

              <Select.ScrollDownButton className="flex h-6 cursor-default items-center justify-center bg-white text-gray-500 hover:bg-gray-50">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                  <path d="M2.5 5.5L6 9L9.5 5.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                </svg>
              </Select.ScrollDownButton>
            </Select.Content>
          </Select.Portal>
        </Select.Root>

        {error && (
          <p id={errorId} className="mt-1 text-xs text-red-600">
            {error}
          </p>
        )}
      </div>
    );
  }
);

GovSelect.displayName = 'GovSelect';

/* ------------------------------------------------------------------ */
/*  GovSelectMultiPortal — safe body-portal with cleanup              */
/* ------------------------------------------------------------------ */

interface PortalProps {
  open: boolean;
  panelStyle: React.CSSProperties;
  isRtl: boolean;
  children: React.ReactNode;
}

function GovSelectMultiPortal({ open, children }: PortalProps) {
  const [node, setNode] = useState<HTMLDivElement | null>(null);

  useLayoutEffect(() => {
    if (!open) {
      setNode(null);
      return;
    }
    const el = document.createElement('div');
    document.body.appendChild(el);
    setNode(el);
    return () => {
      if (document.body.contains(el)) {
        document.body.removeChild(el);
      }
    };
  }, [open]);

  if (!node) return null;
  return createPortal(children, node);
}

/* ------------------------------------------------------------------ */
/*  GovSelectMulti                                                    */
/* ------------------------------------------------------------------ */

interface GovSelectMultiProps {
  label?: string;
  error?: string;
  options: Option[];
  placeholder?: string;
  value?: string[];
  onChange?: (values: string[]) => void;
  disabled?: boolean;
  className?: string;
  name?: string;
  required?: boolean;
  id?: string;
}

export const GovSelectMulti = forwardRef<HTMLButtonElement, GovSelectMultiProps>(
  (
    {
      label,
      error,
      options,
      placeholder = 'اختر...',
      value = [],
      onChange,
      disabled,
      className = '',
      name,
      required,
      id: idProp,
    },
    ref
  ) => {
    const generatedId = useId();
    const id = idProp ?? generatedId;
    const errorId = error ? `${id}-error` : undefined;
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement | null>(null);
    const panelRef = useRef<HTMLDivElement | null>(null);
    const portalNodeRef = useRef<HTMLDivElement | null>(null);
    const [panelStyle, setPanelStyle] = useState<React.CSSProperties>({});

    const isRtl = useMemo(
      () => document.documentElement.dir === 'rtl',
      []
    );

    /* --- sync external value --- */
    const [selected, setSelected] = useState<string[]>(value);
    useEffect(() => {
      setSelected(value);
    }, [value]);

    /* --- positioning --- */
    useLayoutEffect(() => {
      if (!open || !triggerRef.current) return;
      const rect = triggerRef.current.getBoundingClientRect();
      setPanelStyle({
        position: 'absolute',
        top: rect.bottom + window.scrollY + 4,
        left: rect.left + window.scrollX,
        width: rect.width,
        minWidth: 200,
      });
    }, [open]);

    /* --- click outside --- */
    useEffect(() => {
      if (!open) return;
      const handleClick = (e: MouseEvent) => {
        const target = e.target as Node;
        if (
          panelRef.current && !panelRef.current.contains(target) &&
          triggerRef.current && !triggerRef.current.contains(target)
        ) {
          setOpen(false);
        }
      };
      document.addEventListener('mousedown', handleClick);
      return () => document.removeEventListener('mousedown', handleClick);
    }, [open]);

    /* --- escape --- */
    useEffect(() => {
      if (!open) return;
      const handleKey = (e: KeyboardEvent) => {
        if (e.key === 'Escape') setOpen(false);
      };
      document.addEventListener('keydown', handleKey);
      return () => document.removeEventListener('keydown', handleKey);
    }, [open]);

    const handleToggle = (val: string) => {
      setSelected((prev) => {
        const next = prev.includes(val)
          ? prev.filter((v) => v !== val)
          : [...prev, val];
        onChange?.(next);
        return next;
      });
    };

    const selectedLabels = useMemo(
      () => options.filter((o) => selected.includes(o.value)),
      [selected, options]
    );

    const triggerBase =
      'w-full rounded-md border bg-white px-3 py-2 text-sm text-gray-900 ' +
      'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ' +
      'disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 ' +
      'flex items-center justify-between gap-2 transition-colors';

    const triggerBorder = error
      ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
      : 'border-gray-300';

    const setButtonRef = (node: HTMLButtonElement | null) => {
      triggerRef.current = node;
      if (typeof ref === 'function') {
        ref(node);
      } else if (ref) {
        ref.current = node;
      }
    };

    return (
      <div className={`w-full ${className}`}>
        {label && (
          <label
            htmlFor={id}
            className="block text-sm font-medium text-gray-700 mb-1"
          >
            {label}
            {required && <span className="text-red-500 mr-1">*</span>}
          </label>
        )}

        <button
          ref={setButtonRef}
          id={id}
          type="button"
          role="listbox"
          aria-multiselectable="true"
          aria-expanded={open}
          aria-invalid={error ? 'true' : 'false'}
          aria-describedby={errorId}
          disabled={disabled}
          name={name}
          onClick={() => setOpen((o) => !o)}
          className={`${triggerBase} ${triggerBorder}`}
        >
          <span className="flex flex-wrap gap-1 overflow-hidden">
            {selectedLabels.length > 0 ? (
              selectedLabels.map((opt) => (
                <span
                  key={opt.value}
                  className="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                >
                  {opt.label}
                </span>
              ))
            ) : (
              <span className="text-gray-400">{placeholder}</span>
            )}
          </span>
          <svg
            width="12"
            height="12"
            viewBox="0 0 12 12"
            fill="none"
            className={`shrink-0 text-gray-500 transition-transform ${open ? 'rotate-180' : ''}`}
          >
            <path
              d="M2.5 4.5L6 8L9.5 4.5"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </button>

        {error && (
          <p id={errorId} className="mt-1 text-xs text-red-600">
            {error}
          </p>
        )}

        <GovSelectMultiPortal open={open} panelStyle={panelStyle} isRtl={isRtl}>
          <div
            ref={panelRef}
            role="listbox"
            aria-multiselectable="true"
            dir={isRtl ? 'rtl' : 'ltr'}
            className="z-[9999] overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg"
            style={panelStyle}
          >
            <div className="max-h-60 overflow-auto p-1">
              {options.map((opt) => {
                const isSelected = selected.includes(opt.value);
                return (
                  <label
                    key={opt.value}
                    className={`flex cursor-pointer items-center gap-2 rounded-sm px-3 py-2 text-sm transition-colors hover:bg-gray-100 ${
                      isSelected
                        ? 'bg-blue-50 font-semibold text-blue-700'
                        : 'text-gray-900'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isSelected}
                      onChange={() => handleToggle(opt.value)}
                      className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>{opt.label}</span>
                  </label>
                );
              })}
            </div>
            <div className="border-t border-gray-100 p-2">
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="w-full rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                تم
              </button>
            </div>
          </div>
        </GovSelectMultiPortal>
      </div>
    );
  }
);

GovSelectMulti.displayName = 'GovSelectMulti';
