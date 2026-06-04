import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import client from "@/api/client";
import { Receipt } from "@/types/receipt";
import { formatDateTime } from "@/utils/formatDate";
import { formatCurrency } from "@/utils/formatCurrency";
import { amountToArabicWords } from "@/utils/amountToArabicWords";

function isZeroOrEmpty(item: Receipt["items"][0]): boolean {
  const amt = item.amount;
  const txt = item.text_value;
  const amtZero = amt === null || amt === "" || amt === "0" || amt === "0.000" || parseFloat(amt ?? "0") === 0;
  const txtEmpty = txt === null || txt === undefined || txt.trim() === "";
  return amtZero && txtEmpty;
}

export default function ReceiptPrintPage() {
  const { id } = useParams<{ id: string }>();
  const [receipt, setReceipt] = useState<Receipt | null>(null);
  const [qrSvg, setQrSvg] = useState<string>("");
  const [hideZeroEmpty, setHideZeroEmpty] = useState(false);
  const [logoUrl, setLogoUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string>("");

  useEffect(() => {
    if (!id) return;
    const load = async () => {
      try {
        const [receiptRes, qrRes, settingsRes] = await Promise.all([
          client.get(`/receipts/${id}`),
          client.get(`/receipts/${id}/qr`, { responseType: "text" }).catch(() => ({ data: "" })),
          client.get("/settings/public").catch(() => ({ data: {} })),
        ]);
        setReceipt(receiptRes.data as Receipt);

        if (typeof qrRes.data === "string") {
          setQrSvg(qrRes.data);
        } else if ((qrRes.data as any)?.data) {
          setQrSvg((qrRes.data as any).data);
        }

        const settingsObj = (settingsRes.data ?? {}) as Record<string, unknown>;
        const hideVal = settingsObj["HIDE_ZERO_OR_EMPTY"] ?? (settingsObj as any).data?.["HIDE_ZERO_OR_EMPTY"];
        setHideZeroEmpty(hideVal === true || hideVal === "true" || hideVal === 1 || hideVal === "1");

        const logoVal = settingsObj["system_logo"] ?? (settingsObj as any).data?.["system_logo"];
        if (typeof logoVal === "string" && logoVal) {
          setLogoUrl(logoVal);
        }
      } catch (err: any) {
        console.error("Print page load error:", err);
        const msg = err?.response?.data?.message || err?.message || String(err);
        setError(`تعذّر تحميل بيانات الوصل: ${msg}`);
      } finally {
        setLoading(false);
        setTimeout(() => window.print(), 800);
      }
    };
    load();
  }, [id]);

  if (loading) {
    return (
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
        <p style={{ color: "#64748b" }}>جاري تحميل الوصل...</p>
      </div>
    );
  }

  if (error || !receipt) {
    return (
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
        <p style={{ color: "#dc2626" }}>{error || "الوصل غير موجود"}</p>
      </div>
    );
  }

  const totalAmount = parseFloat(receipt.total_amount);
  const verifyCode = receipt.qr_payload ? receipt.qr_payload.slice(-8).toUpperCase() : "--------";

  const statusInfo: Record<string, { label: string; color: string }> = {
    draft:     { label: "مسودة", color: "#64748b" },
    pending:   { label: "معلق", color: "#d97706" },
    issued:    { label: "مرحّل", color: "#059669" },
    printed:   { label: "مطبوع", color: "#2563eb" },
    cancelled: { label: "ملغى", color: "#dc2626" },
  };

  const s = statusInfo[receipt.status] || { label: receipt.status, color: "#64748b" };

  return (
    <>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Noto Sans Arabic', 'Segoe UI', sans-serif; direction: rtl; background: #f8fafc; }
        .no-print { display: block; }
        @page { size: A4; margin: 12mm 14mm; }
        @media print {
          .no-print { display: none !important; }
          body { background: #fff; }
          .receipt-page { box-shadow: none !important; margin: 0 !important; width: 100% !important; border-radius: 0 !important; }
        }
        .receipt-page {
          width: 210mm;
          margin: 24px auto;
          background: #fff;
          padding: 14mm;
          border-radius: 12px;
          box-shadow: 0 4px 40px rgba(0,0,0,0.06);
          position: relative;
          overflow: hidden;
        }
        .receipt-page::before {
          content: '';
          position: absolute;
          top: 0; left: 0; right: 0;
          height: 4px;
          background: linear-gradient(90deg, #0f172a 0%, #3b82f6 50%, #0f172a 100%);
        }
        .top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8mm; }
        .brand { text-align: right; }
        .brand-title { font-size: 14px; font-weight: 700; color: #0f172a; letter-spacing: 0.5px; }
        .brand-sub { font-size: 10px; color: #94a3b8; margin-top: 2px; }
        .qr-wrap { width: 72px; height: 72px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff; }
        .qr-wrap svg { width: 100%; height: 100%; display: block; }
        .receipt-id-box { text-align: center; margin-bottom: 8mm; padding-bottom: 6mm; border-bottom: 1px solid #f1f5f9; }
        .receipt-id-label { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }
        .receipt-id-num { font-size: 26px; font-weight: 800; color: #0f172a; font-family: 'Courier New', monospace; letter-spacing: 1px; }
        .meta-row { display: flex; justify-content: center; gap: 6mm; margin-bottom: 8mm; flex-wrap: wrap; }
        .meta-chip { display: flex; flex-direction: column; align-items: center; padding: 3mm 5mm; border: 1px solid #f1f5f9; border-radius: 8px; min-width: 28mm; }
        .meta-chip-key { font-size: 9px; color: #94a3b8; margin-bottom: 2px; }
        .meta-chip-val { font-size: 11px; font-weight: 600; color: #0f172a; }
        .status-pill { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 10px; border-radius: 999px; background: ${s.color}15; color: ${s.color}; border: 1px solid ${s.color}30; }
        .section-title { font-size: 11px; font-weight: 700; color: #0f172a; margin-bottom: 3mm; display: flex; align-items: center; gap: 6px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #f1f5f9; }
        table.items { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 5mm; }
        table.items thead th { background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 600; padding: 2.5mm 3mm; text-align: right; border-bottom: 2px solid #e2e8f0; }
        table.items tbody td { font-size: 11px; padding: 2.2mm 3mm; border-bottom: 1px solid #f1f5f9; text-align: right; color: #334155; }
        table.items tbody tr:hover td { background: #f8fafc; }
        table.items tbody tr:last-child td { border-bottom: none; }
        table.items .total-cell { font-weight: 700; font-size: 13px; color: #0f172a; background: #f8fafc; border-top: 2px solid #e2e8f0; border-bottom: 2px solid #e2e8f0; }
        .amount-words-box { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px dashed #cbd5e1; border-radius: 8px; padding: 4mm 5mm; margin-bottom: 6mm; text-align: center; }
        .amount-words-text { font-size: 12px; color: #475569; font-weight: 500; }
        .amount-words-text span { font-weight: 700; color: #0f172a; }
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5mm; margin-top: 8mm; padding-top: 6mm; border-top: 1px solid #f1f5f9; }
        .bottom-block { text-align: center; }
        .bottom-block-label { font-size: 9px; color: #94a3b8; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 1px; }
        .bottom-block-value { font-size: 10px; color: #475569; font-weight: 500; }
        .sig-line-print { width: 45mm; height: 1px; background: #cbd5e1; margin: 8mm auto 3mm; }
        .stamp-circle { width: 30mm; height: 30mm; border: 1.5px dashed #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 9px; margin: 0 auto; }
        .verify-footer { text-align: center; margin-top: 8mm; padding-top: 4mm; border-top: 1px solid #f1f5f9; }
        .verify-footer code { font-family: 'Courier New', monospace; font-size: 10px; color: #94a3b8; letter-spacing: 1.5px; background: #f8fafc; padding: 2px 8px; border-radius: 4px; }
        .print-btn { position: fixed; bottom: 24px; left: 24px; background: #0f172a; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-family: 'Noto Sans Arabic', sans-serif; font-size: 13px; font-weight: 500; z-index: 999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.15s; }
        .print-btn:hover { transform: translateY(-2px); }
      `}</style>

      <div className="no-print" style={{ background: "#f1f5f9", padding: "10px", textAlign: "center", fontSize: "12px", color: "#64748b" }}>
        معاينة الطباعة — سيتم فتح نافذة الطباعة تلقائياً
      </div>

      <div className="receipt-page">
        {/* Top: Brand + QR */}
        <div className="top-row">
          <div className="brand">
            {logoUrl && (
              <img src={logoUrl} alt="الشعار" style={{ height: "48px", objectFit: "contain", marginBottom: "6px", display: "block" }} />
            )}
            <div className="brand-title">نظام إدارة الإيصالات المالية</div>
            <div className="brand-sub">GFRC — Government Financial Receipts</div>
          </div>
          {qrSvg ? (
            <div className="qr-wrap" dangerouslySetInnerHTML={{ __html: qrSvg }} />
          ) : (
            <div className="qr-wrap" style={{ fontSize: "9px", color: "#cbd5e1" }}>QR</div>
          )}
        </div>

        {/* Receipt Number */}
        <div className="receipt-id-box">
          <div className="receipt-id-label">رقم الوصل الإلكتروني</div>
          <div className="receipt-id-num">{receipt.receipt_number}</div>
          <div style={{ marginTop: "3px" }}>
            <span className="status-pill">{s.label}</span>
          </div>
        </div>

        {/* Meta Chips */}
        <div className="meta-row">
          <div className="meta-chip">
            <div className="meta-chip-key">السجل</div>
            <div className="meta-chip-val">{receipt.register?.name_ar ?? "—"}</div>
          </div>
          <div className="meta-chip">
            <div className="meta-chip-key">تاريخ الإصدار</div>
            <div className="meta-chip-val">{formatDateTime(receipt.created_at)}</div>
          </div>
          <div className="meta-chip">
            <div className="meta-chip-key">أمين الصندوق</div>
            <div className="meta-chip-val">{receipt.created_by?.name ?? "—"}</div>
          </div>
          <div className="meta-chip">
            <div className="meta-chip-key">الإصدار</div>
            <div className="meta-chip-val">نسخة {receipt.version}</div>
          </div>
        </div>

        {/* Items Table */}
        <div className="section-title">تفاصيل المبالغ</div>
        <table className="items">
          <thead>
            <tr>
              <th style={{ width: "55%" }}>البيان</th>
              <th style={{ width: "25%", textAlign: "center" }}>القيمة</th>
              <th style={{ width: "20%", textAlign: "left" }}>المبلغ (د.ع)</th>
            </tr>
          </thead>
          <tbody>
            {receipt.items.filter((item) => !hideZeroEmpty || !isZeroOrEmpty(item)).map((item) => (
              <tr key={item.id}>
                <td>{item.label_ar_snapshot || item.field_name_snapshot}</td>
                <td style={{ textAlign: "center", color: "#64748b" }}>
                  {item.text_value ?? "—"}
                </td>
                <td style={{ textAlign: "left", fontFamily: "'Courier New', monospace", fontWeight: 600 }}>
                  {item.amount !== null ? formatCurrency(parseFloat(item.amount ?? "0")) : "—"}
                </td>
              </tr>
            ))}
            <tr>
              <td className="total-cell" colSpan={2} style={{ textAlign: "right" }}>المجموع الكلي</td>
              <td className="total-cell" style={{ textAlign: "left", fontFamily: "'Courier New', monospace" }}>
                {formatCurrency(totalAmount)}
              </td>
            </tr>
          </tbody>
        </table>

        {/* Amount in Words */}
        <div className="amount-words-box">
          <div className="amount-words-text">
            المبلغ كتابةً: <span>{amountToArabicWords(totalAmount)}</span> دينار عراقي فقط لا غير
          </div>
        </div>

        {/* Notes */}
        {receipt.notes && (
          <div style={{ fontSize: "10px", color: "#64748b", marginBottom: "5mm", padding: "3mm 4mm", background: "#fafafa", borderRadius: "6px", borderRight: "3px solid #e2e8f0" }}>
            <strong style={{ color: "#334155" }}>ملاحظات:</strong> {receipt.notes}
          </div>
        )}

        {/* Bottom: Signature / Stamp / Verify */}
        <div className="bottom-grid">
          <div className="bottom-block">
            <div className="bottom-block-label">رمز التحقق</div>
            <div className="bottom-block-value">
              <code style={{ fontFamily: "'Courier New', monospace", fontSize: "11px", color: "#0f172a", background: "#f1f5f9", padding: "2px 8px", borderRadius: "4px" }}>{verifyCode}</code>
            </div>
          </div>
          <div className="bottom-block">
            <div className="bottom-block-label">ختم الدائرة</div>
            <div className="stamp-circle">ختم</div>
          </div>
          <div className="bottom-block">
            <div className="bottom-block-label">توقيع أمين الصندوق</div>
            <div className="sig-line-print" />
            <div className="bottom-block-value">{receipt.created_by?.name ?? ""}</div>
          </div>
        </div>

        {/* Footer verify */}
        <div className="verify-footer">
          <div style={{ fontSize: "9px", color: "#94a3b8", marginBottom: "4px" }}>
            للتحقق من صحة هذا الوصل، امسح رمز QR أو ادخل رمز التحقق على
          </div>
          <code>http://localhost:5173/verify</code>
          <div style={{ fontSize: "8px", color: "#cbd5e1", marginTop: "4px", letterSpacing: "0.5px" }}>
            تم إنشاؤه بواسطة نظام GFRC — {formatDateTime(receipt.created_at)}
          </div>
        </div>
      </div>

      <button className="print-btn no-print" onClick={() => window.print()}>
        🖨️ طباعة الوصل
      </button>
    </>
  );
}
