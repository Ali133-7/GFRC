import { createContext, useContext, useState, useCallback, type ReactNode } from "react";

interface FieldState {
  visible: boolean;
  required: boolean;
  readonly: boolean;
  locked: boolean;
  enabled: boolean;
}

type FieldStateMap = Record<string, FieldState>;

interface FieldStateContextValue {
  states: FieldStateMap;
  updateState: (fieldId: string, patch: Partial<FieldState>) => void;
  updateBatch: (patchMap: Record<string, Partial<FieldState>>) => void;
  getState: (fieldId: string) => FieldState;
}

const defaultState: FieldState = {
  visible: true,
  required: false,
  readonly: false,
  locked: false,
  enabled: true,
};

const FieldStateContext = createContext<FieldStateContextValue | null>(null);

export function useFieldStateContext() {
  const ctx = useContext(FieldStateContext);
  if (!ctx) {
    throw new Error("useFieldStateContext must be used within FieldStateProvider");
  }
  return ctx;
}

interface FieldStateProviderProps {
  children: ReactNode;
  initialStates?: FieldStateMap;
}

export function FieldStateProvider({ children, initialStates = {} }: FieldStateProviderProps) {
  const [states, setStates] = useState<FieldStateMap>(initialStates);

  const updateState = useCallback((fieldId: string, patch: Partial<FieldState>) => {
    setStates((prev) => ({
      ...prev,
      [fieldId]: { ...(prev[fieldId] ?? defaultState), ...patch },
    }));
  }, []);

  const updateBatch = useCallback((patchMap: Record<string, Partial<FieldState>>) => {
    setStates((prev) => {
      const next = { ...prev };
      for (const [fieldId, patch] of Object.entries(patchMap)) {
        next[fieldId] = { ...(prev[fieldId] ?? defaultState), ...patch };
      }
      return next;
    });
  }, []);

  const getState = useCallback(
    (fieldId: string) => states[fieldId] ?? defaultState,
    [states]
  );

  return (
    <FieldStateContext.Provider value={{ states, updateState, updateBatch, getState }}>
      {children}
    </FieldStateContext.Provider>
  );
}
