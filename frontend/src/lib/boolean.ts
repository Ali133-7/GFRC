/**
 * Unified boolean coercion utility.
 *
 * Backend stores boolean-like values as strings:
 *   "1", "0", "true", "false", ""
 *
 * This function provides a single source of truth for
 * converting any of these representations to a real boolean.
 */
export function toBoolean(value: unknown): boolean {
  if (typeof value === "boolean") return value;
  if (typeof value === "number") return value !== 0;
  if (typeof value !== "string") return false;

  const normalized = value.trim().toLowerCase();
  return normalized === "1" || normalized === "true" || normalized === "yes";
}

/**
 * Convert a boolean (or any value) to the canonical string
 * representation used by the backend ("1" / "0").
 */
export function fromBoolean(value: unknown): string {
  return toBoolean(value) ? "1" : "0";
}
