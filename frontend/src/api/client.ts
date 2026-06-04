/// <reference types="vite/client" />
import axios, { AxiosInstance, AxiosResponse, InternalAxiosRequestConfig, AxiosError } from "axios";

const client: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? "http://localhost:8000/api/v1",
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  timeout: 30000,
});

function getAuthToken(): string | null {
  try {
    const raw = localStorage.getItem("gfrc-auth");
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed?.state?.token ?? null;
  } catch {
    return null;
  }
}

client.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getAuthToken();
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

client.interceptors.response.use(
  (response: AxiosResponse) => {
    const data = response.data;
    if (data && typeof data === "object" && "success" in data && data.success === true && "data" in data) {
      response.data = data.data;
    }
    return response;
  },
  (error: AxiosError) => {
    const message = (error.response?.data as Record<string, unknown> | undefined)?.message as string | undefined;
    const status = error.response?.status;

    if (status === 401) {
      localStorage.removeItem("gfrc-auth");
      window.location.href = "/login";
      return Promise.reject(error);
    }

    if (status === 403) {
      console.warn("غير مصرح:", message);
    }

    return Promise.reject({
      response: error.response,
      request: error.request,
      message: error.message,
      arabicMessage: message ?? getArabicErrorMessage(status),
    });
  }
);

function getArabicErrorMessage(status?: number): string {
  switch (status) {
    case 400: return "طلب غير صحيح";
    case 401: return "انتهت جلستك، يرجى إعادة تسجيل الدخول";
    case 403: return "غير مصرح بهذه العملية";
    case 404: return "البيانات المطلوبة غير موجودة";
    case 422: return "تحقق من صحة البيانات المُدخلة";
    case 429: return "تم تجاوز الحد المسموح، حاول بعد قليل";
    case 500: return "خطأ في الخادم، حاول مجدداً";
    default:  return "حدث خطأ غير متوقع";
  }
}

export default client;
