import { useState, useEffect, useRef } from "react";
import { helpApi } from "@/api/help";
import type { HelpArticle } from "@/api/help";

interface HelpDrawerProps {
  pageKey: string;
  isOpen: boolean;
  onClose: () => void;
  contextData?: Record<string, any>;
}

export default function HelpDrawer({ pageKey, isOpen, onClose, contextData }: HelpDrawerProps) {
  const [articles, setArticles] = useState<HelpArticle[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");
  const [allHelp, setAllHelp] = useState<HelpArticle[]>([]);
  const drawerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isOpen) return;
    setLoading(true);
    Promise.all([
      helpApi.getByPageKey(pageKey),
      helpApi.list(),
    ]).then(([pageArticles, all]) => {
      setArticles(pageArticles);
      setAllHelp(all);
    }).finally(() => setLoading(false));
  }, [isOpen, pageKey]);

  useEffect(() => {
    if (!isOpen) return;
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleEsc);
    return () => document.removeEventListener("keydown", handleEsc);
  }, [isOpen, onClose]);

  const filteredArticles = search
    ? allHelp.filter(
        (a) =>
          a.title_ar.includes(search) ||
          a.content_ar.includes(search) ||
          (a.title_en?.includes(search) ?? false)
      )
    : articles;

  if (!isOpen) return null;

  return (
    <div
      style={{
        position: "fixed",
        inset: 0,
        zIndex: 1000,
        display: "flex",
        justifyContent: "flex-end",
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      {/* Backdrop */}
      <div style={{ position: "absolute", inset: 0, background: "rgba(0,0,0,0.4)" }} />

      {/* Drawer */}
      <div
        ref={drawerRef}
        style={{
          position: "relative",
          width: "420px",
          maxWidth: "90vw",
          height: "100%",
          background: "var(--color-background-primary)",
          borderRight: "0.5px solid var(--color-border-tertiary)",
          display: "flex",
          flexDirection: "column",
          boxShadow: "-4px 0 20px rgba(0,0,0,0.15)",
          animation: "slideInRight 0.25s ease-out",
        }}
      >
        {/* Header */}
        <div
          style={{
            padding: "16px 18px",
            borderBottom: "0.5px solid var(--color-border-tertiary)",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
          }}
        >
          <div style={{ fontSize: "15px", fontWeight: 600, color: "var(--color-text-primary)" }}>
            ❓ المساعدة
          </div>
          <button
            onClick={onClose}
            style={{
              background: "none",
              border: "none",
              cursor: "pointer",
              fontSize: "18px",
              color: "var(--color-text-secondary)",
              padding: "4px",
            }}
          >
            ×
          </button>
        </div>

        {/* Search */}
        <div style={{ padding: "12px 18px", borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="ابحث عن موضوع..."
            style={{
              width: "100%",
              padding: "8px 12px",
              fontSize: "13px",
              border: "0.5px solid var(--color-border-secondary)",
              borderRadius: "6px",
              background: "var(--color-background-secondary)",
              color: "var(--color-text-primary)",
              fontFamily: "inherit",
            }}
          />
        </div>

        {/* Content */}
        <div style={{ flex: 1, overflowY: "auto", padding: "16px 18px" }}>
          {loading ? (
            <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)" }}>
              جارٍ التحميل...
            </div>
          ) : filteredArticles.length === 0 ? (
            <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
              {search ? "لا توجد نتائج" : "لا توجد تعليمات لهذه الصفحة حالياً"}
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
              {filteredArticles.map((article) => (
                <div
                  key={article.id}
                  style={{
                    padding: "14px",
                    background: "var(--color-background-secondary)",
                    border: "0.5px solid var(--color-border-tertiary)",
                    borderRadius: "var(--border-radius-md)",
                  }}
                >
                  <div style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "8px" }}>
                    {article.title_ar}
                  </div>
                  <div
                    style={{
                      fontSize: "13px",
                      color: "var(--color-text-secondary)",
                      lineHeight: 1.7,
                      whiteSpace: "pre-line",
                    }}
                  >
                    {article.content_ar}
                  </div>

                  {/* Dynamic examples */}
                  {article.examples && article.examples.length > 0 && contextData && (
                    <div style={{ marginTop: "12px", padding: "10px", background: "var(--color-background-info)", borderRadius: "6px" }}>
                      <div style={{ fontSize: "12px", fontWeight: 500, color: "var(--color-text-info)", marginBottom: "6px" }}>
                        أمثلة من نظامك:
                      </div>
                      {article.examples.map((ex: any, idx: number) => {
                        const value = contextData[ex.key];
                        return (
                          <div key={idx} style={{ fontSize: "12px", color: "var(--color-text-primary)", marginBottom: "2px" }}>
                            • {ex.label}: <strong>{value ?? ex.fallback ?? "—"}</strong>
                          </div>
                        );
                      })}
                    </div>
                  )}

                  {/* Links */}
                  {article.links && article.links.length > 0 && (
                    <div style={{ marginTop: "8px", display: "flex", gap: "6px", flexWrap: "wrap" }}>
                      {article.links.map((link: any, idx: number) => (
                        <a
                          key={idx}
                          href={link.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          style={{
                            fontSize: "11px",
                            color: "var(--color-text-info)",
                            textDecoration: "none",
                            padding: "2px 8px",
                            background: "var(--color-background-info)",
                            borderRadius: "4px",
                          }}
                        >
                          {link.label}
                        </a>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        <div
          style={{
            padding: "12px 18px",
            borderTop: "0.5px solid var(--color-border-tertiary)",
            fontSize: "11px",
            color: "var(--color-text-tertiary)",
            textAlign: "center",
          }}
        >
          مركز المساعدة · جميع الحقوق محفوظة
        </div>
      </div>
    </div>
  );
}
