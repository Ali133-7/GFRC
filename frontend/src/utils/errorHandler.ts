// Standardized error handling utility
export function handleError(error: unknown, context: string): string {
  if (error instanceof Error) {
    console.error(`[${context}]`, error.message);
    return error.message;
  }
  
  if (typeof error === 'string') {
    console.error(`[${context}]`, error);
    return error;
  }
  
  const message = `خطأ في ${context}`;
  console.error(`[${context}]`, error);
  return message;
}

export function logError(error: unknown, context: string): void {
  const message = handleError(error, context);
  console.error(`Error in ${context}:`, message);
}
