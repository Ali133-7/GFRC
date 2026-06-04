import { useQuery } from "@tanstack/react-query";
import { feeApi } from "@/api/fees";

export const useOfficialFees = () =>
  useQuery({
    queryKey: ["official-fees", "active"],
    queryFn: () => feeApi.listActive(),
  });
