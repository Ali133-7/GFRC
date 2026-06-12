/**
 * Format number to remove trailing zeros after decimal point
 * 
 * Examples:
 * - 10000.000 -> "10000"
 * - 10000.500 -> "10000.5"
 * - 10000.123 -> "10000.123"
 * - 10000 -> "10000"
 * 
 * @param value - The number or string to format
 * @returns Formatted string without trailing zeros
 */
export function formatNumber(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '';
  }
  
  // Convert to number
  const num = typeof value === 'string' ? parseFloat(value) : value;
  
  // Check if it's a valid number
  if (isNaN(num)) {
    return String(value);
  }
  
  // Convert to string and remove trailing zeros
  const str = num.toString();
  
  // If no decimal point, return as is
  if (!str.includes('.')) {
    return str;
  }
  
  // Remove trailing zeros after decimal point
  return str.replace(/\.?0+$/, '');
}

/**
 * Format currency in Iraqi Dinar
 * 
 * @param value - The amount to format
 * @returns Formatted currency string
 */
export function formatCurrency(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '0 د.ع';
  }
  
  const num = typeof value === 'string' ? parseFloat(value) : value;
  
  if (isNaN(num)) {
    return '0 د.ع';
  }
  
  return new Intl.NumberFormat('ar-IQ', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num) + ' د.ع';
}

/**
 * Format number with thousand separators (Arabic format)
 * 
 * Examples:
 * - 1000 -> "1,000"
 * - 10000 -> "10,000"
 * - 100000 -> "100,000"
 * - 1000000 -> "1,000,000"
 * 
 * @param value - The number to format
 * @returns Formatted string with thousand separators
 */
export function formatNumberWithSeparators(value: number | string | null | undefined): string {
  const formatted = formatNumber(value);
  
  if (!formatted) {
    return '';
  }
  
  // Split into integer and decimal parts
  const parts = formatted.split('.');
  
  // Add thousand separators to integer part
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  
  return parts.join('.');
}
