import client from "@/api/client";

export interface HelpArticle {
  id: string;
  page_key: string;
  category: string | null;
  title_ar: string;
  title_en: string | null;
  content_ar: string;
  content_en: string | null;
  media: any[] | null;
  links: any[] | null;
  examples: any[] | null;
  sort_order: number;
  is_active: boolean;
  is_system: boolean;
  created_at: string;
  updated_at: string;
}

export const helpApi = {
  list: (params?: { page_key?: string; category?: string; search?: string }) =>
    client.get("/help", { params }).then((r) => (r.data as HelpArticle[]) ?? []),

  getByPageKey: (pageKey: string) =>
    client.get(`/help/${pageKey}`).then((r) => (r.data as HelpArticle[]) ?? []),

  create: (payload: Partial<HelpArticle>) =>
    client.post("/help", payload).then((r) => (r.data as HelpArticle) ?? null),

  update: (id: string, payload: Partial<HelpArticle>) =>
    client.put(`/help/${id}`, payload).then((r) => (r.data as HelpArticle) ?? null),

  remove: (id: string) => client.delete(`/help/${id}`).then((r) => r.data),

  reorder: (articles: Array<{ id: string; sort_order: number }>) =>
    client.patch("/help/reorder", { articles }).then((r) => r.data ?? []),

  seed: () => client.post("/help/seed").then((r) => r.data),
};
