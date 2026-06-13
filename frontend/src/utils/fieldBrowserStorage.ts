import type { FieldFavorite, RecentlyUsedField } from "@/types/report";

const FAVORITES_KEY = "gfrc_report_field_favorites";
const RECENT_KEY = "gfrc_report_recent_fields";
const MAX_RECENT = 20;

function read<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

function write<T>(key: string, value: T): void {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // ignore
  }
}

export function getFavoriteFieldIds(): string[] {
  const favorites = read<FieldFavorite[]>(FAVORITES_KEY, []);
  return favorites.map((f) => f.fieldId);
}

export function isFieldFavorite(fieldId: string): boolean {
  return getFavoriteFieldIds().includes(fieldId);
}

export function toggleFieldFavorite(fieldId: string): boolean {
  const favorites = read<FieldFavorite[]>(FAVORITES_KEY, []);
  const index = favorites.findIndex((f) => f.fieldId === fieldId);
  let isFavorite: boolean;

  if (index >= 0) {
    favorites.splice(index, 1);
    isFavorite = false;
  } else {
    favorites.unshift({ fieldId, registeredAt: new Date().toISOString() });
    isFavorite = true;
  }

  write(FAVORITES_KEY, favorites);
  return isFavorite;
}

export function recordFieldUsage(fieldId: string): void {
  const recent = read<RecentlyUsedField[]>(RECENT_KEY, []);
  const filtered = recent.filter((r) => r.fieldId !== fieldId);
  filtered.unshift({ fieldId, usedAt: new Date().toISOString() });
  write(RECENT_KEY, filtered.slice(0, MAX_RECENT));
}

export function getRecentFieldIds(): string[] {
  const recent = read<RecentlyUsedField[]>(RECENT_KEY, []);
  return recent.map((r) => r.fieldId);
}
