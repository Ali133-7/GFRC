import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import client from "@/api/client";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import type { OfficialFeeCategory } from "@/types/transactionTemplate";

export default function OfficialFeeFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const isEdit = Boolean(id && id !== "new");

  const [categoryId, setCategoryId] = useState("");
  const [feeCode, setFeeCode] = useState("");
  const [nameAr, setNameAr] = useState("");
  const [nameEn, setNameEn] = useState("");
  const [amount, setAmount] = useState("");
  const [effectiveFrom, setEffectiveFrom] = useState("");
  const [effectiveTo, setEffectiveTo] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [newCategoryName, setNewCategoryName] = useState("");
  const [showNewCategory, setShowNewCategory] = useState(false);
  const [editingCategoryId, setEditingCategoryId] = useState<string | null>(null);
  const [editingCategoryName, setEditingCategoryName] = useState("");

  const { data: categories, isLoading: loadingCategories } = useQuery({
    queryKey: ["fee-categories"],
    queryFn: async () => {
      const r = await client.get("/official-fees/categories");
      const d = r.data?.data ?? r.data;
      return (Array.isArray(d) ? d : []) as OfficialFeeCategory[];
    },
  });

  const { data: existing, isLoading } = useQuery({
    queryKey: ["official-fee", id],
    queryFn: async () => {
      const r = await client.get(`/official-fees/${id}`);
      return (r.data?.data ?? r.data) as { id: string; category_id: string; name_ar: string; name_en: string | null; amount: string; effective_from: string | null; effective_to: string | null; is_active: boolean };
    },
    enabled: isEdit,
  });

  useEffect(() => {
    if (existing) {
      setCategoryId(existing.category_id);
      setFeeCode((existing as any).fee_code ?? "");
      setNameAr(existing.name_ar);
      setNameEn(existing.name_en ?? "");
      setAmount(existing.amount);
      setEffectiveFrom(existing.effective_from ?? "");
      setEffectiveTo(existing.effective_to ?? "");
      setIsActive(existing.is_active);
    }
  }, [existing]);

  const saveMut = useMutation({
    mutationFn: async () => {
      const payload = {
        category_id: categoryId,
        fee_code: feeCode || null,
        name_ar: nameAr,
        name_en: nameEn || null,
        amount: Number(amount),
        effective_from: effectiveFrom || null,
        effective_to: effectiveTo || null,
        is_active: isActive,
      };
      if (isEdit) {
        await client.put(`/official-fees/${id}`, payload);
      } else {
        await client.post("/official-fees", payload);
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["official-fees"] });
      navigate("/official-fees");
    },
  });

  const createCategoryMut = useMutation({
    mutationFn: async () => {
      const r = await client.post("/official-fees/categories", {
        name_ar: newCategoryName,
        code: newCategoryName.replace(/\s+/g, '_').toUpperCase() + '_' + Date.now(),
      });
      return (r.data?.data ?? r.data) as OfficialFeeCategory;
    },
    onSuccess: (cat) => {
      qc.invalidateQueries({ queryKey: ["fee-categories"] });
      setCategoryId(cat.id);
      setShowNewCategory(false);
      setNewCategoryName("");
    },
  });

  const updateCategoryMut = useMutation({
    mutationFn: async ({ cid, name }: { cid: string; name: string }) => {
      await client.put(`/official-fees/categories/${cid}`, { name_ar: name });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["fee-categories"] });
      setEditingCategoryId(null);
      setEditingCategoryName("");
    },
  });

  const deleteCategoryMut = useMutation({
    mutationFn: async (cid: string) => {
      await client.delete(`/official-fees/categories/${cid}`);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["fee-categories"] });
      if (editingCategoryId === categoryId) setCategoryId("");
    },
  });

  const inputStyle: React.CSSProperties = { width: "100%", padding: "8px 10px", fontSize: "13px", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", fontFamily: "inherit" };
  const labelStyle: React.CSSProperties = { fontSize: "12px", fontWeight: 500, color: "var(--color-text-secondary)", marginBottom: "4px", display: "block" };

  if (isEdit && isLoading) return <div style={{ padding: 40 }}><LoadingSpinner /></div>;

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader title={isEdit ? "تعديل رسم" : "رسم جديد"} />
      <div style={{ maxWidth: 600, display: "flex", flexDirection: "column", gap: 14 }}>
        <div>
          <label style={labelStyle}>التصنيف *</label>
          <div style={{ display: "flex", gap: 8 }}>
            <select value={categoryId} onChange={(e) => setCategoryId(e.target.value)} style={{ ...inputStyle, flex: 1 }}>
              <option value="">اختر التصنيف...</option>
              {(categories ?? []).map((c) => (
                <option key={c.id} value={c.id}>{c.name_ar}</option>
              ))}
            </select>
            <button onClick={() => setShowNewCategory(!showNewCategory)} style={{ padding: "8px 12px", borderRadius: "6px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit", whiteSpace: "nowrap" }}>
              + تصنيف
            </button>
          </div>

          {/* New Category */}
          {showNewCategory && (
            <div style={{ display: "flex", gap: 8, marginTop: 8 }}>
              <input type="text" placeholder="اسم التصنيف الجديد" value={newCategoryName} onChange={(e) => setNewCategoryName(e.target.value)} style={{ ...inputStyle, flex: 1 }} />
              <button onClick={() => createCategoryMut.mutate()} disabled={!newCategoryName || createCategoryMut.isPending} style={{ padding: "8px 16px", borderRadius: "6px", border: "none", background: "var(--color-background-success)", color: "var(--color-text-success)", cursor: "pointer", fontFamily: "inherit" }}>
                {createCategoryMut.isPending ? "..." : "إضافة"}
              </button>
            </div>
          )}

          {/* Category Manager */}
          <div style={{ marginTop: 10, border: "0.5px solid var(--color-border-tertiary)", borderRadius: "8px", padding: "10px", background: "var(--color-background-secondary)" }}>
            <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-secondary)", marginBottom: "8px" }}>إدارة التصنيفات</div>
            {loadingCategories ? <LoadingSpinner /> : (
              <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
                {(categories ?? []).map((c) => (
                  <div key={c.id} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "4px 6px", borderRadius: "4px", background: c.id === categoryId ? "var(--color-background-info)" : "transparent" }}>
                    {editingCategoryId === c.id ? (
                      <>
                        <input type="text" value={editingCategoryName} onChange={(e) => setEditingCategoryName(e.target.value)} style={{ ...inputStyle, flex: 1, fontSize: "12px" }} />
                        <div style={{ display: "flex", gap: 4 }}>
                          <button onClick={() => updateCategoryMut.mutate({ cid: c.id, name: editingCategoryName })} disabled={!editingCategoryName} style={{ fontSize: "11px", padding: "2px 8px", borderRadius: "4px", border: "none", background: "var(--color-background-success)", color: "var(--color-text-success)", cursor: "pointer" }}>حفظ</button>
                          <button onClick={() => setEditingCategoryId(null)} style={{ fontSize: "11px", padding: "2px 8px", borderRadius: "4px", border: "0.5px solid var(--color-border-secondary)", background: "none", cursor: "pointer" }}>إلغاء</button>
                        </div>
                      </>
                    ) : (
                      <>
                        <span style={{ fontSize: "12px", color: c.id === categoryId ? "var(--color-text-info)" : "var(--color-text-primary)", fontWeight: c.id === categoryId ? 600 : 400 }}>{c.name_ar}</span>
                        <div style={{ display: "flex", gap: 4 }}>
                          <button onClick={() => { setEditingCategoryId(c.id); setEditingCategoryName(c.name_ar); }} style={{ fontSize: "11px", padding: "2px 8px", borderRadius: "4px", border: "0.5px solid var(--color-border-info)", background: "none", color: "var(--color-text-info)", cursor: "pointer" }}>تعديل</button>
                          <button onClick={() => { if (confirm(`حذف التصنيف "${c.name_ar}"؟`)) deleteCategoryMut.mutate(c.id); }} style={{ fontSize: "11px", padding: "2px 8px", borderRadius: "4px", border: "0.5px solid var(--color-border-danger)", background: "none", color: "var(--color-text-danger)", cursor: "pointer" }}>حذف</button>
                        </div>
                      </>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <div>
            <label style={labelStyle}>الاسم (عربي) *</label>
            <input type="text" value={nameAr} onChange={(e) => setNameAr(e.target.value)} style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>الاسم (إنجليزي)</label>
            <input type="text" value={nameEn} onChange={(e) => setNameEn(e.target.value)} style={inputStyle} />
          </div>
        </div>

        <div>
          <label style={labelStyle}>رمز الرسم (كود) *</label>
          <input
            type="text"
            value={feeCode}
            onChange={(e) => setFeeCode(e.target.value.toUpperCase().replace(/\s+/g, '_'))}
            placeholder="مثال: REG_STD_001"
            style={inputStyle}
          />
          <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)", marginTop: "4px" }}>
            كود فريد يُستخدم لربط الرسم بالقواعد والحقول في سير العمل
          </div>
        </div>

        <div>
          <label style={labelStyle}>المبلغ *</label>
          <input type="number" value={amount} onChange={(e) => setAmount(e.target.value)} style={inputStyle} />
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <div>
            <label style={labelStyle}>تاريخ النفاذ</label>
            <input type="date" value={effectiveFrom} onChange={(e) => setEffectiveFrom(e.target.value)} style={inputStyle} />
          </div>
          <div>
            <label style={labelStyle}>تاريخ الإلغاء</label>
            <input type="date" value={effectiveTo} onChange={(e) => setEffectiveTo(e.target.value)} style={inputStyle} />
          </div>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
          <span style={{ fontSize: "13px" }}>مفعّل</span>
        </div>

        <div style={{ display: "flex", gap: 12, marginTop: 8 }}>
          <button onClick={() => saveMut.mutate()} disabled={saveMut.isPending || !nameAr || !categoryId || !feeCode || amount === ""} style={{ padding: "10px 24px", fontSize: "13px", fontWeight: 500, borderRadius: "6px", border: "none", background: "var(--color-background-info)", color: "var(--color-text-info)", cursor: "pointer", fontFamily: "inherit", opacity: saveMut.isPending ? 0.6 : 1 }}>
            {saveMut.isPending ? "جاري الحفظ..." : "حفظ"}
          </button>
          <button onClick={() => navigate("/official-fees")} style={{ padding: "10px 24px", fontSize: "13px", fontWeight: 500, borderRadius: "6px", border: "0.5px solid var(--color-border-secondary)", background: "none", color: "var(--color-text-secondary)", cursor: "pointer", fontFamily: "inherit" }}>
            إلغاء
          </button>
        </div>
      </div>
    </div>
  );
}
