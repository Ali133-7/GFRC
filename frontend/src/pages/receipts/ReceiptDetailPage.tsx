import React, { useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import client from "@/api/client";
import { ReceiptActions } from "@/components/receipt/ReceiptActions";
import { ReceiptStatusBadge } from "@/components/receipt/ReceiptStatusBadge";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { PageHeader } from "@/components/layout/PageHeader";
import { formatCurrency } from "@/utils/formatCurrency";
import { formatDateTime } from "@/utils/formatDate";
import { Receipt, ReceiptRevision } from "@/types/receipt";

function isZeroOrEmpty(item: Receipt["items"][0]): boolean {
  const amt = item.amount;
  const txt = item.text_value;
  const amtZero = amt === null || amt === "" || amt === "0" || amt === "0.000" || parseFloat(amt ?? "0") === 0;
  const txtEmpty = txt === null || txt === undefined || txt.trim() === "";
  return amtZero && txtEmpty;
}

export default function ReceiptDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [revisionsOpen, setRevisionsOpen] = useState(false);

  const { data: receipt, isLoading, error } = useQuery({
    queryKey: ["receipt", id],
    queryFn: async () => {
      const res = await client.get(`/receipts/${id}`);
      return (res.data?.data ?? res.data) as Receipt;
    },
    enabled: !!id,
  });

  const { data: settingsData } = useQuery({
    queryKey: ["settings-public"],
    queryFn: async () => {
      const res = await client.get("/settings/public").catch(() => ({ data: {} }));
      return (res.data ?? {}) as Record<string, unknown>;
    },
    staleTime: 5 * 60 * 1000,
  });

  const { data: qrSvg } = useQuery({
    queryKey: ["receipt-qr", id],
    queryFn: async () => {
      const res = await client.get(`/receipts/${id}/qr`, { responseType: "text" });
      return typeof res.data === "string" ? res.data : (res.data?.data ?? "");
    },
    enabled: !!id && !!receipt && receipt.status !== "draft",
  });

  const { data: revisions } = useQuery({
    queryKey: ["receipt-revisions", id],
    queryFn: async () => {
      const res = await client.get(`/receipts/${id}/revisions`);
      const d = res.data?.data ?? res.data;
      return (Array.isArray(d) ? d : []) as ReceiptRevision[];
    },
    enabled: revisionsOpen && !!id,
  });

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ["receipt", id] });
    queryClient.invalidateQueries({ queryKey: ["receipt-qr", id] });
    queryClient.invalidateQueries({ queryKey: ["receipt-revisions", id] });
    queryClient.invalidateQueries({ queryKey: ["receipts"] });
  };

  if (isLoading) return (
    <div style={{ display: "flex", justifyContent: "center", padding: "80px" }}><LoadingSpinner /></div>
  );

  if (error || !receipt) return (
    <div style={{ padding: "40px", color: "#dc2626", fontSize: "15px", textAlign: "center" }}>
      تعذّر تحميل الوصل
    </div>
  );

  const total = parseFloat(receipt.total_amount);

  const hideZeroEmpty = (() => {
    const val = settingsData?.["HIDE_ZERO_OR_EMPTY"] ?? (settingsData as any)?.data?.["HIDE_ZERO_OR_EMPTY"];
    return val === true || val === "true" || val === 1 || val === "1";
  })();

  const card = {
    background: "#fff",
    borderRadius: "12px",
    boxShadow: "0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02)",
    border: "1px solid #f1f5f9",
    overflow: "hidden",
  };

  const muted = { color: "#94a3b8", fontSize: "12px" };
  const strong = { color: "#0f172a", fontSize: "13px", fontWeight: 600 };

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif", background: "#f8fafc", minHeight: "100vh" }}>
      <PageHeader title={`وصل رقم: ${receipt.receipt_number}`} />

      <div style={{ maxWidth: "1100px", margin: "0 auto" }}>
        {/* Top Row: ID + Status + QR */}
        <div style={{ ...card, padding: "24px", marginTop: "20px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div>
            <div style={{ fontSize: "11px", color: "#94a3b8", marginBottom: "4px" }}>رقم الوصل</div>
            <div style={{ fontSize: "28px", fontWeight: 800, color: "#0f172a", fontFamily: "'Courier New', monospace", letterSpacing: "1px" }}>
              {receipt.receipt_number}
            </div>
            <div style={{ marginTop: "8px", display: "flex", alignItems: "center", gap: "8px" }}>
              <ReceiptStatusBadge status={receipt.status} />
              {receipt.version > 1 && (
                <span style={{ fontSize: "11px", color: "#64748b", background: "#f1f5f9", padding: "2px 8px", borderRadius: "4px" }}>
                  نسخة {receipt.version}
                </span>
              )}
            </div>
          </div>
          {qrSvg ? (
            <div style={{ width: "96px", height: "96px", border: "1px solid #e2e8f0", borderRadius: "10px", padding: "6px", background: "#fff", display: "flex", alignItems: "center", justifyContent: "center" }}
              dangerouslySetInnerHTML={{ __html: qrSvg }}
            />
          ) : (
            <div style={{ width: "96px", height: "96px", border: "1px dashed #e2e8f0", borderRadius: "10px", display: "flex", alignItems: "center", justifyContent: "center", color: "#cbd5e1", fontSize: "10px" }}>
              QR
            </div>
          )}
        </div>

        {/* Meta Grid */}
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "12px", marginTop: "16px" }}>
          {[
            { label: "السجل", value: receipt.register?.name_ar ?? "—", icon: "📁" },
            { label: "أمين الصندوق", value: receipt.created_by?.name ?? "—", icon: "👤" },
            { label: "تاريخ الإصدار", value: formatDateTime(receipt.created_at), icon: "📅" },
            ...(receipt.printed_at ? [{ label: "تاريخ الطباعة", value: formatDateTime(receipt.printed_at), icon: "🖨️" }] : []),
            ...(receipt.cancelled_at ? [{ label: "تاريخ الإلغاء", value: formatDateTime(receipt.cancelled_at), icon: "❌" }] : []),
          ].map((item) => (
            <div key={item.label} style={{ ...card, padding: "16px" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "6px", marginBottom: "6px" }}>
                <span style={{ fontSize: "14px" }}>{item.icon}</span>
                <span style={{ fontSize: "11px", color: "#94a3b8", fontWeight: 500 }}>{item.label}</span>
              </div>
              <div style={{ fontSize: "14px", fontWeight: 700, color: "#0f172a" }}>{item.value}</div>
            </div>
          ))}
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 280px", gap: "16px", marginTop: "16px" }}>
          {/* Left: Items + Revisions */}
          <div>
            {/* Items Card */}
            <div style={{ ...card, padding: "20px" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "16px" }}>
                <div style={{ fontSize: "14px", fontWeight: 700, color: "#0f172a" }}>تفاصيل المبالغ</div>
                <div style={{ fontSize: "18px", fontWeight: 800, color: "#0f172a", fontFamily: "'Courier New', monospace" }}>
                  {formatCurrency(total)}
                </div>
              </div>

              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
                <thead>
                  <tr style={{ borderBottom: "2px solid #f1f5f9" }}>
                    <th style={{ padding: "10px 0", textAlign: "right", fontWeight: 600, color: "#64748b", fontSize: "11px" }}>البيان</th>
                    <th style={{ padding: "10px 0", textAlign: "left", fontWeight: 600, color: "#64748b", fontSize: "11px" }}>المبلغ (د.ع)</th>
                  </tr>
                </thead>
                <tbody>
                  {receipt.items.filter((item) => !hideZeroEmpty || !isZeroOrEmpty(item)).map((item, idx, arr) => (
                    <tr key={item.id} style={{ borderBottom: idx === arr.length - 1 ? "none" : "1px solid #f8fafc" }}>
                      <td style={{ padding: "10px 0", color: "#334155" }}>
                        {item.label_ar_snapshot || item.field_name_snapshot}
                      </td>
                      <td style={{ padding: "10px 0", textAlign: "left", fontFamily: "'Courier New', monospace", fontWeight: 600, color: "#0f172a" }}>
                        {item.amount !== null ? formatCurrency(parseFloat(item.amount ?? "0")) : item.text_value ?? "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {receipt.notes && (
                <div style={{ marginTop: "14px", padding: "12px", background: "#fafafa", borderRadius: "8px", fontSize: "12px", color: "#64748b", borderRight: "3px solid #e2e8f0" }}>
                  <strong style={{ color: "#334155" }}>ملاحظات:</strong> {receipt.notes}
                </div>
              )}
              {receipt.cancel_reason && (
                <div style={{ marginTop: "10px", padding: "12px", background: "#fef2f2", borderRadius: "8px", fontSize: "12px", color: "#991b1b", borderRight: "3px solid #fecaca" }}>
                  <strong>سبب الإلغاء:</strong> {receipt.cancel_reason}
                </div>
              )}
            </div>

            {/* Revisions */}
            <div style={{ ...card, marginTop: "16px" }}>
              <button
                onClick={() => setRevisionsOpen(!revisionsOpen)}
                style={{ width: "100%", padding: "16px 20px", display: "flex", justifyContent: "space-between", alignItems: "center", background: "none", border: "none", cursor: "pointer", fontFamily: "inherit", fontSize: "13px", fontWeight: 600, color: "#0f172a", textAlign: "right" }}
              >
                <span>سجل المراجعات</span>
                <span style={{ fontSize: "11px", color: "#94a3b8" }}>{revisionsOpen ? "▲ إخفاء" : "▼ عرض"}</span>
              </button>
              {revisionsOpen && (
                <div style={{ borderTop: "1px solid #f1f5f9", padding: "16px 20px" }}>
                  {!revisions ? (
                    <div style={{ textAlign: "center", padding: "20px" }}><LoadingSpinner /></div>
                  ) : revisions.length === 0 ? (
                    <div style={{ fontSize: "12px", color: "#94a3b8", textAlign: "center", padding: "16px" }}>لا توجد مراجعات</div>
                  ) : (
                    revisions.map((rev) => {
                      const oldItems = (rev.old_snapshot?.items as any[]) ?? [];
                      const newItems = (rev.new_snapshot?.items as any[]) ?? [];
                      const oldValues = (rev.old_snapshot?.field_values as Record<string, unknown>) ?? {};
                      const newValues = (rev.new_snapshot?.field_values as Record<string, unknown>) ?? {};

                      const allFieldIds = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])];
                      const changedFields = allFieldIds.filter(id => oldValues[id] !== newValues[id]);

                      const itemMap = (items: any[]) => {
                        const map = new Map<string, { label: string; amount: string }>();
                        items.forEach((item: any) => {
                          const key = item.field_id ?? item.id;
                          const existing = map.get(key);
                          if (existing) {
                            existing.amount = (parseFloat(existing.amount) + parseFloat(item.amount ?? "0")).toFixed(3);
                          } else {
                            map.set(key, { label: item.label_ar_snapshot ?? item.field_name_snapshot ?? key, amount: item.amount ?? "0" });
                          }
                        });
                        return map;
                      };

                      const oldItemMap = itemMap(oldItems);
                      const newItemMap = itemMap(newItems);
                      const allItemKeys = [...new Set([...oldItemMap.keys(), ...newItemMap.keys()])];
                      const changedItems = allItemKeys.filter(k => {
                        const old = oldItemMap.get(k);
                        const nw = newItemMap.get(k);
                        return old?.amount !== nw?.amount || old?.label !== nw?.label;
                      });

                      return (
                        <div key={rev.id} style={{ marginBottom: "16px", padding: "14px", background: "#fafafa", borderRadius: "8px", fontSize: "12px" }}>
                          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px" }}>
                            <span style={{ fontWeight: 600, color: "#0f172a" }}>نسخة {rev.version} — {rev.reviser?.name ?? "—"}</span>
                            <span style={{ color: "#94a3b8" }}>{formatDateTime(rev.created_at)}</span>
                          </div>
                          <div style={{ color: "#64748b", marginBottom: "12px", padding: "8px", background: "#fff", borderRadius: "6px", border: "1px solid #e2e8f0" }}>
                            <strong>السبب:</strong> {rev.reason}
                          </div>

                          {changedItems.length > 0 && (
                            <div style={{ marginBottom: "12px" }}>
                              <div style={{ fontWeight: 600, color: "#0f172a", marginBottom: "6px", fontSize: "11px" }}>تغييرات المبالغ</div>
                              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "11px" }}>
                                <thead>
                                  <tr style={{ background: "#f1f5f9" }}>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>الحقل</th>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>القيمة القديمة</th>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>القيمة الجديدة</th>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>الفرق</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  {changedItems.map(key => {
                                    const old = oldItemMap.get(key);
                                    const nw = newItemMap.get(key);
                                    const oldAmt = parseFloat(old?.amount ?? "0");
                                    const newAmt = parseFloat(nw?.amount ?? "0");
                                    const diff = newAmt - oldAmt;
                                    return (
                                      <tr key={key}>
                                        <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", fontWeight: 500 }}>{old?.label ?? nw?.label ?? key}</td>
                                        <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", color: "#991b1b" }}>{oldAmt.toFixed(3)}</td>
                                        <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", color: "#166534" }}>{newAmt.toFixed(3)}</td>
                                        <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", color: diff > 0 ? "#166534" : diff < 0 ? "#991b1b" : "#64748b" }}>
                                          {diff > 0 ? "+" : ""}{diff.toFixed(3)}
                                        </td>
                                      </tr>
                                    );
                                  })}
                                </tbody>
                              </table>
                            </div>
                          )}

                          {changedFields.length > 0 && (
                            <div style={{ marginBottom: "12px" }}>
                              <div style={{ fontWeight: 600, color: "#0f172a", marginBottom: "6px", fontSize: "11px" }}>تغييرات الحقول</div>
                              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "11px" }}>
                                <thead>
                                  <tr style={{ background: "#f1f5f9" }}>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>الحقل</th>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>القيمة القديمة</th>
                                    <th style={{ padding: "6px 8px", textAlign: "right", border: "1px solid #e2e8f0", color: "#64748b" }}>القيمة الجديدة</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  {changedFields.map(id => (
                                    <tr key={id}>
                                      <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", fontWeight: 500, fontFamily: "monospace", fontSize: "10px" }}>{id.slice(0, 8)}…</td>
                                      <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", color: "#991b1b" }}>{String(oldValues[id] ?? "—")}</td>
                                      <td style={{ padding: "6px 8px", border: "1px solid #e2e8f0", color: "#166534" }}>{String(newValues[id] ?? "—")}</td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            </div>
                          )}

                          {changedItems.length === 0 && changedFields.length === 0 && (
                            <div style={{ fontSize: "11px", color: "#94a3b8" }}>لا توجد تغييرات واضحة في البيانات</div>
                          )}
                        </div>
                      );
                    })
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Right: Actions */}
          <div>
            <div style={{ ...card, padding: "20px", position: "sticky", top: "24px" }}>
              <div style={{ fontSize: "13px", fontWeight: 700, color: "#0f172a", marginBottom: "14px" }}>الإجراءات</div>
              <ReceiptActions receipt={receipt} onRefresh={handleRefresh} />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
