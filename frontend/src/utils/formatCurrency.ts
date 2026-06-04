export function formatCurrency(amount: number | string | null | undefined): string {
  if (amount === null || amount === undefined || amount === "") return "0.000";
  const num = typeof amount === "string" ? parseFloat(amount) : amount;
  if (isNaN(num)) return "0.000";
  return new Intl.NumberFormat("ar-IQ", {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  }).format(num);
}

export function formatCurrencyCompact(amount: number | string | null | undefined): string {
  if (amount === null || amount === undefined || amount === "") return "0.000";
  const num = typeof amount === "string" ? parseFloat(amount) : amount;
  if (isNaN(num)) return "0.000";
  if (num >= 1_000_000) return `${(num / 1_000_000).toFixed(3)} م`;
  if (num >= 1_000) return `${(num / 1_000).toFixed(3)} ألف`;
  return formatCurrency(num);
}

export function parseAmount(value: string): number {
  return parseFloat(value.replace(/[^0-9.]/g, "")) || 0;
}

export default formatCurrency;
