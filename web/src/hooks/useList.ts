import * as React from "react";
import type { ListResult } from "@/api/resources";
import { ApiError, type ListParams } from "@/lib/api";

/** Debounce a fast-changing value (e.g. search input) for server queries. */
export function useDebounced<T>(value: T, delayMs = 300): T {
  const [debounced, setDebounced] = React.useState(value);

  React.useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(value), delayMs);
    return () => window.clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

interface UseListState<T> {
  items: T[];
  pagination: ListResult<T>["pagination"] | null;
  isLoading: boolean;
  error: ApiError | null;
}

/**
 * Fetches a server-driven list whenever the query params change. Stale
 * responses (from out-of-order resolution) are dropped.
 */
export function useList<T>(
  fetcher: (params: ListParams) => Promise<ListResult<T>>,
  params: ListParams,
  options: { enabled?: boolean } = {}
): UseListState<T> & { reload: () => void } {
  const enabled = options.enabled ?? true;
  const [state, setState] = React.useState<UseListState<T>>({
    items: [],
    pagination: null,
    isLoading: enabled,
    error: null,
  });
  const [reloadKey, setReloadKey] = React.useState(0);
  const requestId = React.useRef(0);
  const fetcherRef = React.useRef(fetcher);

  const paramsKey = JSON.stringify(params);

  React.useEffect(() => {
    fetcherRef.current = fetcher;
  }, [fetcher]);

  React.useEffect(() => {
    if (!enabled) {
      setState((prev) => ({ ...prev, isLoading: false, error: null }));
      return;
    }

    const id = ++requestId.current;
    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    fetcherRef.current(JSON.parse(paramsKey) as ListParams)
      .then((result) => {
        if (requestId.current !== id) return;
        setState({
          items: result.items,
          pagination: result.pagination,
          isLoading: false,
          error: null,
        });
      })
      .catch((error: unknown) => {
        if (requestId.current !== id) return;
        setState((prev) => ({
          ...prev,
          isLoading: false,
          error:
            error instanceof ApiError
              ? error
              : new ApiError("Beklenmeyen bir hata oluştu.", "UNKNOWN_ERROR", 0),
        }));
      });
  }, [paramsKey, reloadKey, enabled]);

  const reload = React.useCallback(() => setReloadKey((k) => k + 1), []);

  return { ...state, reload };
}
