import { create } from 'zustand';

interface UiState {
  sidebarOpen: boolean;
  toggleSidebar: () => void;
  closeSidebar: () => void;
  openSidebar: () => void;
}

export const useUiStore = create<UiState>((set) => ({
  sidebarOpen: true, // Default to open on desktop
  toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
  closeSidebar: () => set({ sidebarOpen: false }),
  openSidebar: () => set({ sidebarOpen: true }),
}));
