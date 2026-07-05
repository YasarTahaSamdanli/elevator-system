import * as React from "react";
import { Navigate, useLocation } from "react-router-dom";
import { apiLogin, apiLogout, apiMe, type AuthUser } from "@/api/resources";
import { clearToken, getToken, setToken, UNAUTHORIZED_EVENT } from "@/lib/api";
import { Skeleton } from "@/components/ui/skeleton";

type AuthStatus = "loading" | "authenticated" | "guest";

interface AuthContextValue {
  status: AuthStatus;
  user: AuthUser | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = React.createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = React.useState<AuthStatus>(getToken() ? "loading" : "guest");
  const [user, setUser] = React.useState<AuthUser | null>(null);

  React.useEffect(() => {
    let cancelled = false;

    if (getToken()) {
      apiMe()
        .then((me) => {
          if (cancelled) return;
          setUser(me);
          setStatus("authenticated");
        })
        .catch(() => {
          if (cancelled) return;
          clearToken();
          setUser(null);
          setStatus("guest");
        });
    }

    const onUnauthorized = () => {
      setUser(null);
      setStatus("guest");
    };
    window.addEventListener(UNAUTHORIZED_EVENT, onUnauthorized);

    return () => {
      cancelled = true;
      window.removeEventListener(UNAUTHORIZED_EVENT, onUnauthorized);
    };
  }, []);

  const login = React.useCallback(async (email: string, password: string) => {
    const token = await apiLogin(email, password);
    setToken(token);
    const me = await apiMe();
    setUser(me);
    setStatus("authenticated");
  }, []);

  const logout = React.useCallback(async () => {
    try {
      await apiLogout();
    } catch {
      /* token might already be invalid — local logout still proceeds */
    }
    clearToken();
    setUser(null);
    setStatus("guest");
  }, []);

  const value = React.useMemo(
    () => ({ status, user, login, logout }),
    [status, user, login, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = React.useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}

export function RequireAuth({ children }: { children: React.ReactNode }) {
  const { status } = useAuth();
  const location = useLocation();

  if (status === "loading") {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <div className="w-full max-w-md space-y-3 px-6">
          <Skeleton className="h-8 w-1/2" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-2/3" />
        </div>
      </div>
    );
  }

  if (status === "guest") {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return <>{children}</>;
}
