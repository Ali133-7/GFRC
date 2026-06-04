import QRCode from 'qrcode';

const VERIFY_BASE_URL = import.meta.env.VITE_VERIFY_URL || `${window.location.origin}/verify`;

export async function generateReceiptQr(receiptId: string, hash: string): Promise<string> {
  const url = `${VERIFY_BASE_URL}?id=${encodeURIComponent(receiptId)}&hash=${encodeURIComponent(hash)}`;
  return QRCode.toDataURL(url, {
    width: 128,
    margin: 2,
    color: {
      dark: '#000000',
      light: '#ffffff',
    },
  });
}

export function getVerificationUrl(receiptId: string, hash: string): string {
  return `${VERIFY_BASE_URL}?id=${encodeURIComponent(receiptId)}&hash=${encodeURIComponent(hash)}`;
}
