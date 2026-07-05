/**
 * Thin client for the backend API contract (App\Support\ApiResponse):
 * success  → { success: true, data, message, meta }
 * error    → { success: false, message, error: { code, details } }
 * Paginated lists carry meta.pagination = { page, per_page, total, total_pages }.
 */

const API_BASE: string = import.meta.env.VITE_API_URL ?? "/api/v1";
const TOKEN_KEY = "asansor-token";

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface Envelope<T> {
  data: T;
  message: string;
  meta?: { pagination?: PaginationMeta };
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly code: string,
    public readonly status: number,
    public readonly details: Record<string, string[]> = {}
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function getToken(): string | null {
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  window.localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  window.localStorage.removeItem(TOKEN_KEY);
}

/** Fired when the API answers 401 so the auth provider can reset state. */
export const UNAUTHORIZED_EVENT = "asansor:unauthorized";

export interface ListParams {
  page?: number;
  perPage?: number;
  search?: string;
  sort?: string;
  filter?: Record<string, string>;
}

export function listQueryString(params: ListParams): string {
  const query = new URLSearchParams();
  if (params.page) query.set("page", String(params.page));
  if (params.perPage) query.set("per_page", String(params.perPage));
  if (params.search?.trim()) query.set("search", params.search.trim());
  if (params.sort) query.set("sort", params.sort);
  for (const [field, value] of Object.entries(params.filter ?? {})) {
    if (value !== "") query.set(`filter[${field}]`, value);
  }
  const s = query.toString();
  return s ? `?${s}` : "";
}

export async function api<T>(
  path: string,
  options: { method?: string; body?: unknown } = {}
): Promise<Envelope<T>> {
  const token = getToken();

  let response: Response;
  try {
    response = await fetch(`${API_BASE}${path}`, {
      method: options.method ?? "GET",
      headers: {
        Accept: "application/json",
        ...(options.body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });
  } catch {
    throw new ApiError("Sunucuya ulaşılamıyor.", "NETWORK_ERROR", 0);
  }

  let payload: unknown = null;
  try {
    payload = await response.json();
  } catch {
    /* non-JSON body falls through to the generic error below */
  }

  const body = payload as {
    success?: boolean;
    data?: T;
    message?: string;
    meta?: { pagination?: PaginationMeta };
    error?: { code?: string; details?: Record<string, string[]> };
  } | null;

  if (!response.ok || body?.success !== true) {
    if (response.status === 401) {
      clearToken();
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
    }

    throw new ApiError(
      body?.message ?? "Beklenmeyen bir hata oluştu.",
      body?.error?.code ?? "UNKNOWN_ERROR",
      response.status,
      body?.error?.details ?? {}
    );
  }

  return { data: body.data as T, message: body.message ?? "", meta: body.meta };
}
