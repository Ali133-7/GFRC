import { useMemo } from 'react';
import { useSettings } from './useSettings';

export interface PrintSettings {
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
  hide_zero_or_empty: boolean;
  [key: string]: string | boolean | undefined;
}

export function usePrintSettings(): {
  settings: PrintSettings;
  logo: string | null;
  isLoading: boolean;
} {
  const { data: rawSettings, isLoading } = useSettings();

  const settings = useMemo<PrintSettings>(() => {
    const defaults: PrintSettings = {
      company_name: 'الدائرة المالية',
      company_name_en: 'Financial Department',
      company_address: '',
      company_phone: '',
      footer_text: 'هذا الوصل صادر من النظام الرسمي',
      thank_you_text: 'شكراً لثقتكم',
      receipt_title: 'وصل قبض',
      show_qr: true,
      show_stamp: true,
      show_signature: true,
      hide_zero_or_empty: false,
    };

    if (!rawSettings) return defaults;

    const map: Record<string, any> = { ...defaults };
    for (const s of rawSettings) {
      if (s.group !== 'print') continue;
      const key = s.key.toLowerCase().replace(/^print_/, '');
      if (s.type === 'boolean') {
        map[key] = s.value === '1' || s.value === 'true';
      } else {
        map[key] = s.value ?? '';
      }
    }

    return map as PrintSettings;
  }, [rawSettings]);

  const logo = useMemo(() => {
    if (!rawSettings) return null;
    const logoSetting = rawSettings.find(s => s.key === 'system_logo' || s.key === 'SYSTEM_LOGO_URL');
    return logoSetting?.value ?? null;
  }, [rawSettings]);

  return { settings, logo, isLoading };
}
