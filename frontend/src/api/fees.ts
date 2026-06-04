import client from "@/api/client";

export interface OfficialFee {
  id: string;
  fee_code: string;
  name_ar: string;
  name_en: string | null;
  amount: number;
}

export const feeApi = {
  listActive: () =>
    client.get("/fees/active").then((r) => (r.data as OfficialFee[]) ?? []),

  resolve: (code: string) =>
    client.get(`/fees/resolve/${encodeURIComponent(code)}`).then((r) => r.data),

  bulkResolve: (codes: string[]) =>
    client.post("/fees/bulk-resolve", { codes }).then((r) => r.data),
};
