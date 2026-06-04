import type { Receipt, ReceiptItem } from '@/types/receipt';

export interface TemplateProps {
  receipt: Receipt;
  settings: {
    company_name: string;
    company_name_en: string;
    company_address: string;
    company_phone: string;
    footer_text: string;
    thank_you_text: string;
    receipt_title: string;
    show_qr: boolean;
    show_stamp: boolean;
    show_signature: boolean;
    hide_zero_or_empty?: boolean;
    [key: string]: string | boolean | undefined;
  };
  logo: string | null;
  qrSvg: string;
  activeElements?: Record<string, boolean>;
}

export { type ReceiptItem };
