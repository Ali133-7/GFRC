const LOGO_KEY = 'gfrc-logo';

export function getStoredLogo(): string | null {
  return localStorage.getItem(LOGO_KEY);
}

export function storeLogo(base64: string): void {
  localStorage.setItem(LOGO_KEY, base64);
}

export function removeStoredLogo(): void {
  localStorage.removeItem(LOGO_KEY);
}

export function fileToBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result as string);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}
